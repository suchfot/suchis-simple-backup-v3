<?php
/**
 * Backup storage, secure downloads and deletion.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Download a backup file.
 *
 * @return void
 */
function Ssbhf_Download_backup(): void
{
    if (!current_user_can('manage_options')) {
        Ssbhf_Download_error('Du bist nicht berechtigt, Backups herunterzuladen.', 'Permission denied.', 403);
    }
    if (!isset($_GET['_wpnonce']) || !is_string($_GET['_wpnonce'])) {
        Ssbhf_Download_error('Der Sicherheitslink fehlt. Bitte lade die Backup-Seite neu.', 'Missing or invalid nonce parameter.', 403);
    }
    if (!wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'ssbhf_download')) {
        Ssbhf_Download_error('Der Sicherheitslink ist ungültig oder abgelaufen. Bitte lade die Backup-Seite neu und versuche es erneut.', 'Nonce verification failed.', 403);
    }

    $name = isset($_GET['file']) && is_string($_GET['file']) ? wp_unslash($_GET['file']) : '';
    if (!$name || strpbrk($name, "/\\\0\r\n") !== false || substr($name, -4) !== '.zip') {
        Ssbhf_Download_error('Der angeforderte Backup-Dateiname ist ungültig.', 'Invalid filename.', 400);
    }

    $directory = realpath(SSBHF_BACKUP_DIR);
    $path = $directory !== false ? realpath($directory . DIRECTORY_SEPARATOR . $name) : false;
    if ($path === false || dirname($path) !== $directory || !is_file($path)) {
        Ssbhf_Download_error('Die Backup-Datei wurde im Backup-Verzeichnis nicht gefunden.', 'File missing or outside backup directory.', 404);
    }
    if (!is_readable($path)) {
        Ssbhf_Download_error('Die Backup-Datei ist nicht lesbar. Bitte prüfe die Dateiberechtigungen.', 'File is not readable.', 500);
    }

    $fh = Ssbhf_Download_io(static fn() => fopen($path, 'rb'));
    if ($fh === false) {
        Ssbhf_Download_error('Die Backup-Datei konnte nicht zum Lesen geöffnet werden.', 'fopen failed.', 500);
    }
    $stat = Ssbhf_Download_io(static fn() => fstat($fh));
    $chunk = Ssbhf_Download_io(static fn() => fread($fh, 1024 * 1024));
    if ($stat === false || $stat['size'] <= 0 || $chunk === false || $chunk === '') {
        fclose($fh);
        Ssbhf_Download_error('Die Backup-Datei ist leer oder konnte nicht gelesen werden.', 'File size or initial read failed.', 500);
    }

    Ssbhf_Download_io(static fn() => set_time_limit(0));
    Ssbhf_Download_io(static fn() => ini_set('zlib.output_compression', '0'));
    while (ob_get_level() > 0) {
        if (!Ssbhf_Download_io(static fn() => ob_end_clean())) {
            fclose($fh);
            Ssbhf_Download_error('Der Download konnte wegen eines aktiven Ausgabepuffers nicht gestartet werden.', 'Output buffer could not be cleared.', 500);
        }
    }
    if (headers_sent($output_file, $output_line)) {
        fclose($fh);
        Ssbhf_Download_error('Der Download konnte wegen einer vorzeitigen Serverausgabe nicht gestartet werden. Bitte prüfe das PHP-Fehlerprotokoll.', 'Headers already sent at ' . $output_file . ':' . $output_line, 500);
    }

    status_header(200);
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\'], '_', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Length: ' . $stat['size']);
    header('X-Content-Type-Options: nosniff');

    do {
        echo $chunk;
        flush();
        if (feof($fh) || connection_aborted()) {
            break;
        }
        $chunk = Ssbhf_Download_io(static fn() => fread($fh, 1024 * 1024));
        if ($chunk === false || ($chunk === '' && !feof($fh))) {
            error_log('[Suchis Simple Backup] Download interrupted: file read failed.');
            break;
        }
    } while ($chunk !== '');
    fclose($fh);
    exit;
}

function Ssbhf_Download_io(callable $operation)
{
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): void {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    try {
        return $operation();
    } catch (Throwable $error) {
        error_log('[Suchis Simple Backup] Download I/O: ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine());
        return false;
    } finally {
        restore_error_handler();
    }
}

function Ssbhf_Download_error(string $message, string $reason, int $status): void
{
    error_log('[Suchis Simple Backup] Download rejected: ' . $reason);
    if (!headers_sent()) {
        header_remove('Content-Disposition');
        header_remove('Content-Length');
        header('Content-Type: text/html; charset=UTF-8');
    }
    wp_die(esc_html($message), 'Backup-Download fehlgeschlagen', ['response' => $status, 'back_link' => true]);
}

/**
 * Delete a backup file.
 *
 * @return void
 */
function Ssbhf_Delete_backup(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', 403);
    }
    check_admin_referer('ssbhf_delete');

    $name = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
    $is_partial = substr($name, -12) === '.zip.partial';
    if (!$name || strpbrk($name, "/\\\0\r\n") !== false
        || (!$is_partial && substr($name, -4) !== '.zip')
    ) {
        wp_die('Invalid file', 400);
    }

    $lock = null;
    if ($is_partial) {
        $lock = Ssbhf_Backup_lock();
        if (!$lock) {
            wp_die('Ein Backup wird gerade verarbeitet.', 409);
        }
        $partial_names = array_column(Ssbhf_List_partial_backups(), 'name');
        if (!in_array($name, $partial_names, true)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            wp_die('Die unfertige Backup-Datei ist nicht verfügbar.', 404);
        }
    }

    $path = Ssbhf_Backup_path($name);
    if (is_file($path) && !is_link($path)) {
        @unlink($path);
    }
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    wp_safe_redirect(admin_url('tools.php?page=ssb-simple-backup&ssb_notice=deleted'));
    exit;
}

/**
 * Ensure backup and temporary directories exist with proper security.
 *
 * @return void
 */
function Ssbhf_Ensure_dirs(): void
{
    if (!is_dir(SSBHF_BACKUP_DIR)) {
        wp_mkdir_p(SSBHF_BACKUP_DIR);
    }
    if (!is_dir(SSBHF_TMP_DIR)) {
        wp_mkdir_p(SSBHF_TMP_DIR);
    }

    // Status, manifests, SQL and diagnostics contain sensitive site information.
    foreach ([SSBHF_BACKUP_DIR, SSBHF_TMP_DIR] as $directory) {
        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        $index = $directory . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
    }
}

/**
 * Get the full path to a backup file.
 *
 * @param string $filename The backup filename.
 *
 * @return string The full path to the backup file.
 */
function Ssbhf_Backup_path(string $filename): string
{
    Ssbhf_Ensure_dirs();
    return trailingslashit(SSBHF_BACKUP_DIR) . $filename;
}

/**
 * List all backup files in the backup directory.
 *
 * @return array An array of backup file information (name, size, mtime).
 */
function Ssbhf_List_backups(): array
{
    if (!is_dir(SSBHF_BACKUP_DIR)) {
        return [];
    }
    $items = glob(SSBHF_BACKUP_DIR . '/*.zip');
    if (!$items) {
        return [];
    }

    $out = [];
    foreach ($items as $path) {
        $out[] = ['name' => basename($path), 'size' => filesize($path), 'mtime' => filemtime($path)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

/**
 * List orphaned partial archives, excluding the archive owned by an active job.
 *
 * @return array An array of partial file information (name, size, mtime).
 */
function Ssbhf_List_partial_backups(): array
{
    if (!is_dir(SSBHF_BACKUP_DIR)) {
        return [];
    }
    $items = glob(SSBHF_BACKUP_DIR . '/*.zip.partial');
    if (!$items) {
        return [];
    }

    $active_path = '';
    $active_job_file = SSBHF_TMP_DIR . '/active-backup.json';
    $active_job_data = is_readable($active_job_file)
        ? json_decode((string) file_get_contents($active_job_file), true)
        : null;
    $active_job_id = is_array($active_job_data)
        ? ($active_job_data['job_id'] ?? '')
        : '';
    if (is_string($active_job_id) && $active_job_id !== '') {
        $state_path = Ssbhf_Analysis_state_path($active_job_id);
        $state = is_readable($state_path)
            ? json_decode((string) file_get_contents($state_path), true)
            : null;
        if (is_array($state) && !empty($state['zip_path'])) {
            $active_path = realpath($state['zip_path']) ?: '';
        }
    }

    $out = [];
    foreach ($items as $path) {
        if (is_link($path) || !is_file($path)) {
            continue;
        }
        $real_path = realpath($path);
        if ($real_path === false
            || ($active_path !== '' && $real_path === $active_path)
        ) {
            continue;
        }
        $size = @filesize($real_path);
        $mtime = @filemtime($real_path);
        if ($size === false || $mtime === false) {
            continue;
        }
        $out[] = [
            'name' => basename($real_path), 'size' => $size, 'mtime' => $mtime,
        ];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}
