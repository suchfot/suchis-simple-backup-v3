<?php
/** Checkpointed ZIP64 backup coordinator. */
if (!defined('ABSPATH')) {
    exit;
}

function Ssbhf_Backup_lock()
{
    Ssbhf_Ensure_dirs();
    $lock = fopen(SSBHF_TMP_DIR . '/backup.lock', 'c+b');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return false;
    }
    return $lock;
}

function Ssbhf_Active_backup(): string
{
    $path = SSBHF_TMP_DIR . '/active-backup.json';
    $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    $id = is_array($data) ? ($data['job_id'] ?? '') : '';
    $status = $id ? Ssbhf_Read_job_status($id) : false;
    return is_array($status) && ($status['status'] ?? '') !== 'done' ? $id : '';
}

function Ssbhf_Save_backup_state(string $job_id, array $state): void
{
    Ssbhf_Atomic_json(Ssbhf_Analysis_state_path($job_id), $state);
}

/** Persistent JSONL journal, not returned in full in every polling response. */
function Ssbhf_Backup_diagnostic(string $job_id, string $event, array $state, array $extra = []): void
{
    $safe = preg_replace('/[^a-zA-Z0-9-]/', '', $job_id);
    $context = [
        'time' => gmdate('c'), 'event' => $event, 'phase' => $state['phase'] ?? 'analysis',
        'file' => $state['entry']['name'] ?? ($state['verify_entry']['name'] ?? ''),
        'file_offset' => $state['entry']['offset'] ?? 0,
        'zip_bytes' => $state['zip_offset'] ?? 0,
        'free_bytes' => @disk_free_space(SSBHF_BACKUP_DIR),
        'tmp_free_bytes' => @disk_free_space(SSBHF_TMP_DIR),
        'memory_bytes' => memory_get_usage(true),
    ] + $extra;
    $written = @file_put_contents(SSBHF_TMP_DIR . '/diagnostic-' . $safe . '.jsonl', wp_json_encode($context) . "\n", FILE_APPEND | LOCK_EX);
    if ($written === false || $event === 'error') {
        error_log('[Suchis Simple Backup] ' . wp_json_encode($context));
    }
}

function Ssbhf_Backup_space(int $bytes): void
{
    $free = disk_free_space(SSBHF_BACKUP_DIR);
    $tmp_free = disk_free_space(SSBHF_TMP_DIR);
    $reserve = 32 * 1024 * 1024;
    if ($free === false || $tmp_free === false || $free < $bytes + $reserve || $tmp_free < $reserve) {
        throw new RuntimeException('Nicht genügend freier Speicherplatz (inklusive 32 MiB Reserve). Platz schaffen und Auftrag fortsetzen.');
    }
}

/** One bounded request. The caller must hold the site-wide backup lock. */
function Ssbhf_Process_backup_chunk(string $job_id)
{
    $started = microtime(true);
    $path = Ssbhf_Analysis_state_path($job_id);
    $state = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($state) || ($state['engine'] ?? '') !== 'zip64-store-v1') {
        return new WP_Error('backup_engine', 'Dieser Auftrag kann mit der neuen ZIP-Engine nicht fortgesetzt werden. Bitte einen neuen Auftrag starten.');
    }
    $zip = null;
    $central = null;
    $manifest = null;
    // Turn I/O warnings into logged packet failures, not malformed AJAX output.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        return false;
    });
    try {
        Ssbhf_Backup_diagnostic($job_id, 'packet_start', $state, ['packet_limit_bytes' => 8 * 1024 * 1024]);
        if (empty($state['scan_complete'])) {
            $scan = Ssbhf_Process_analysis_chunk($job_id, 'backup');
            if (is_wp_error($scan)) {
                throw new RuntimeException($scan->get_error_message());
            }
            return $scan;
        }
        Ssbhf_Backup_space(8 * 1024 * 1024);
        if ($state['phase'] === 'database' && !isset($state['db'])) {
            // STORE cannot shrink the sources. Fail early, before copying gigabytes.
            Ssbhf_Backup_space((int) $state['bytes'] + (int) $state['files'] * 1024);
        }
        if ($state['phase'] === 'database') {
            Ssbhf_Dump_db_chunk($state);
            if (!empty($state['db_complete'])) {
                Ssbhf_Backup_space((int) $state['bytes'] + (int) $state['db']['offset'] + (int) $state['files'] * 1024);
                $state['phase'] = 'files';
            }
        } elseif ($state['phase'] !== 'publish') {
            $zip = Ssbhf_Zip_handle($state['zip_path'], $state['zip_offset']);
            $central = Ssbhf_Zip_handle($state['central_path'], $state['central_size']);
            $budget = 8 * 1024 * 1024;
            if ($state['phase'] === 'files') {
                $manifest = fopen($state['manifest_path'], 'rb');
                if (!$manifest || fseek($manifest, $state['manifest_offset']) !== 0) {
                    throw new RuntimeException('Temporäre Dateiliste konnte nicht gelesen werden.');
                }
                $count = 0;
                while ($budget > 0 && $count < 100 && microtime(true) - $started < 2.0) {
                    if (empty($state['database_added'])) {
                        $entry = ['path' => $state['tmp_sql'], 'relative_path' => 'database.sql'];
                        $next_offset = $state['manifest_offset'];
                    } else {
                        $line = fgets($manifest);
                        if ($line === false) {
                            if (!feof($manifest)) {
                                throw new RuntimeException('Lesefehler in der Dateiliste.');
                            }
                            if ($state['processed_files'] !== $state['files']) {
                                throw new RuntimeException('Dateianzahl stimmt nicht mit der Analyse überein.');
                            }
                            $state['phase'] = 'directory';
                            break;
                        }
                        $entry = json_decode($line, true);
                        $next_offset = ftell($manifest);
                        if (!is_array($entry) || empty($entry['path']) || empty($entry['relative_path'])) {
                            throw new RuntimeException('Ungültiger Eintrag in der Dateiliste.');
                        }
                    }
                    $state['last_file'] = $entry['relative_path'];
                    // Log before opening the source, so fatal errors also leave context.
                    Ssbhf_Backup_diagnostic($job_id, 'file', $state, ['source' => $entry['relative_path']]);
                    if (!Ssbhf_Zip_entry($state, $entry, $zip, $central, $budget)) {
                        break;
                    }
                    if (empty($state['database_added'])) {
                        $state['database_added'] = true;
                    } else {
                        $state['manifest_offset'] = $next_offset;
                        $state['processed_files']++;
                    }
                    $count++;
                }
            } elseif ($state['phase'] === 'directory') {
                Ssbhf_Zip_finish($state, $zip, $central, $budget);
            } elseif ($state['phase'] === 'verify') {
                Ssbhf_Zip_verify($state, $zip, $central, $budget);
            }
            if (!fflush($zip) || !fflush($central)) {
                throw new RuntimeException('ZIP-Daten konnten nicht gespeichert werden.');
            }
        }
        // Commit before publishing. A crash after rename is recoverable as well.
        Ssbhf_Save_backup_state($job_id, $state);
        if ($state['phase'] === 'publish') {
            if (is_resource($zip)) {
                fclose($zip);
                $zip = null;
            }
            $final = Ssbhf_Backup_path($state['filename']);
            $candidate = is_file($state['zip_path']) ? $state['zip_path'] : $final;
            $archive = new ZipArchive();
            $code = $archive->open($candidate, ZipArchive::CHECKCONS);
            if ($code !== true) {
                throw new RuntimeException('ZIP-Strukturprüfung fehlgeschlagen; libzip-Code: ' . (string) $code);
            }
            $valid = $archive->numFiles === $state['files'] + 1 && $state['verified_entries'] === $state['files'] + 1;
            $archive->close();
            if (!$valid) {
                throw new RuntimeException('ZIP-Prüfung: Archiv ist unvollständig.');
            }
            if ($candidate !== $final && (file_exists($final) || !rename($state['zip_path'], $final))) {
                throw new RuntimeException('Geprüftes ZIP konnte nicht freigegeben werden.');
            }
            clearstatcache(true, $final);
            $status = [
                'status' => 'done', 'task' => 'backup', 'file' => $state['filename'],
                'files' => $state['files'], 'processed_files' => $state['processed_files'],
                'database_message' => 'Datenbank gesichert.',
                'message' => 'ZIP64 geprüft und freigegeben: ' . size_format(filesize($final)) . '. Dauer: ' . Ssbhf_Format_duration(microtime(true) - $state['started_at']),
            ];
            Ssbhf_Write_job_status($job_id, $status);
            Ssbhf_Backup_diagnostic($job_id, 'done', $state);
            // Cleanup only after a durable completed status. Keep diagnostics.
            foreach (['manifest_path', 'central_path', 'tmp_sql'] as $key) {
                if (is_file($state[$key])) {
                    @unlink($state[$key]);
                }
            }
            @unlink($path);
            @unlink(SSBHF_TMP_DIR . '/active-backup.json');
            return $status;
        }
        $status = [
            'status' => 'running', 'task' => 'backup', 'step' => $state['phase'],
            'files' => $state['files'], 'processed_files' => $state['processed_files'],
            'archive_bytes' => $state['zip_offset'], 'current_file' => $state['entry']['name'] ?? '',
            'current_file_bytes' => $state['entry']['offset'] ?? 0,
            'verified_entries' => $state['verified_entries'] ?? 0,
            'database_message' => 'Datenbank gesichert.', 'message' => 'ZIP64-Sicherung läuft (ohne Komprimierung).',
        ];
        Ssbhf_Write_job_status($job_id, $status);
        Ssbhf_Backup_diagnostic($job_id, 'packet_done', $state, ['elapsed_seconds' => microtime(true) - $started]);
        return $status;
    } catch (Throwable $error) {
        // Do not commit modified offsets: retry rolls back to the previous checkpoint.
        Ssbhf_Backup_diagnostic($job_id, 'error', $state, [
            'message' => $error->getMessage(), 'exception' => get_class($error),
            'elapsed_seconds' => microtime(true) - $started, 'last_file' => $state['last_file'] ?? '',
        ]);
        return new WP_Error('backup_packet_failed', $error->getMessage() . ' Der Auftrag ist pausiert; nach Behebung erneut starten, um fortzusetzen.');
    } finally {
        restore_error_handler();
        foreach ([$zip, $central, $manifest] as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
