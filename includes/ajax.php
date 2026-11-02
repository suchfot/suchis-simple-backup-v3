<?php
/** Authenticated, serialized job endpoints. */
if (!defined('ABSPATH')) {
    exit;
}

function Ssbhf_Ajax_start(): void
{
    Ssbhf_Check_ajax_access();
    $lock = Ssbhf_Backup_lock();
    if (!$lock) {
        wp_send_json_error('Ein Backup-Paket wird gerade verarbeitet. Bitte kurz warten und erneut versuchen.', 409);
    }
    try {
        $result = Ssbhf_Start_or_resume_backup();
    } catch (Throwable $error) {
        $result = new WP_Error('backup_start', $error->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 400);
    }
    wp_send_json_success($result);
}

function Ssbhf_Start_or_resume_backup()
{
    if (PHP_INT_SIZE < 8) {
        throw new RuntimeException('Große ZIP64-Backups benötigen 64-Bit-PHP.');
    }
    $active = Ssbhf_Active_backup();
    if ($active) {
        $state = json_decode((string) file_get_contents(Ssbhf_Analysis_state_path($active)), true);
        if (is_array($state) && ($state['engine'] ?? '') === 'zip64-store-v1') {
            $status = Ssbhf_Read_job_status($active);
            $status['status'] = 'running';
            Ssbhf_Write_job_status($active, $status);
            return ['job_id' => $active, 'step' => $state['phase'], 'resumed' => true];
        }
    }
    if (!Ssbhf_System_Checks_Passed(Ssbhf_Get_System_checks())) {
        throw new RuntimeException('Die Systemprüfung ist nicht vollständig. Bitte Voraussetzungen prüfen.');
    }
    $analysis_id = isset($_POST['analysis_job_id']) ? sanitize_text_field(wp_unslash($_POST['analysis_job_id'])) : '';
    $status = $analysis_id ? Ssbhf_Read_job_status($analysis_id) : false;
    $analysis_path = $analysis_id ? Ssbhf_Analysis_state_path($analysis_id) : '';
    $analysis = $analysis_path && is_readable($analysis_path) ? json_decode((string) file_get_contents($analysis_path), true) : null;
    $ready = is_array($status) && ($status['status'] ?? '') === 'done' && ($status['task'] ?? '') === 'analysis'
        && is_array($analysis) && !empty($analysis['scan_complete']) && is_readable($analysis['manifest_path']);
    // Never reuse an analysis ID as a backup ID. Concurrent tabs cannot mutate it.
    $job_id = wp_generate_uuid4();
    $state = Ssbhf_Create_analysis_state($job_id);
    if (is_wp_error($state)) {
        return $state;
    }
    if ($ready) {
        if (!copy($analysis['manifest_path'], $state['manifest_path'])) {
            throw new RuntimeException('Dateiliste konnte nicht übernommen werden.');
        }
        foreach (['files', 'bytes', 'estimated_zip_bytes', 'manifest_bytes', 'root', 'excluded_dirs'] as $key) {
            $state[$key] = $analysis[$key];
        }
        $state['stack'] = [];
    }
    $host = preg_replace('~[^a-zA-Z0-9._-]~', '-', parse_url(home_url(), PHP_URL_HOST) ?: 'site');
    $filename = 'wp-backup_files-db_' . $host . '_' . gmdate('Y-m-d_His') . '_' . substr($job_id, 0, 8) . '.zip';
    $state += [
        'task' => 'backup', 'engine' => 'zip64-store-v1', 'phase' => 'database',
        'scan_complete' => $ready, 'manifest_offset' => 0, 'processed_files' => 0,
        'zip_path' => Ssbhf_Backup_path($filename . '.partial'), 'filename' => $filename,
        'central_path' => SSBHF_TMP_DIR . '/central-' . $job_id . '.partial',
        'tmp_sql' => SSBHF_TMP_DIR . '/database-' . $job_id . '.sql',
        'zip_offset' => 0, 'central_size' => 0, 'entry_count' => 0, 'started_at' => microtime(true),
    ];
    Ssbhf_Save_backup_state($job_id, $state);
    $step = $ready ? 'database' : 'analysis';
    Ssbhf_Write_job_status($job_id, ['status' => 'running', 'task' => 'backup', 'step' => $step]);
    Ssbhf_Atomic_json(SSBHF_TMP_DIR . '/active-backup.json', ['job_id' => $job_id]);
    return ['job_id' => $job_id, 'step' => $step];
}

function Ssbhf_Ajax_backup_chunk(): void
{
    Ssbhf_Check_ajax_access();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $lock = Ssbhf_Backup_lock();
    if (!$lock) {
        wp_send_json_success(['status' => 'busy', 'message' => 'Ein anderes Fenster verarbeitet gerade ein Paket.']);
    }
    try {
        $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
        if (!is_array($status) || ($status['task'] ?? '') !== 'backup') {
            $status = new WP_Error('not_found', 'Backupauftrag nicht gefunden.');
        } elseif (!in_array($status['status'], ['done', 'failed', 'cancelled'], true)) {
            $result = Ssbhf_Process_backup_chunk($job_id);
            if (is_wp_error($result)) {
                $status = ['status' => 'failed', 'task' => 'backup', 'job_id' => $job_id, 'resumable' => true, 'message' => $result->get_error_message()];
                Ssbhf_Write_job_status($job_id, $status);
            } else {
                $status = $result;
            }
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if (is_wp_error($status)) {
        wp_send_json_error($status->get_error_message(), 404);
    }
    if ($status['status'] === 'done') {
        $status['redirect'] = admin_url('tools.php?page=ssb-simple-backup&ssb_notice=created&file=' . rawurlencode($status['file']));
    }
    wp_send_json_success($status);
}

function Ssbhf_Ajax_analyze(): void
{
    Ssbhf_Check_ajax_access();
    Ssbhf_Ensure_dirs();
    $job_id = wp_generate_uuid4();
    $state = Ssbhf_Create_analysis_state($job_id);
    if (is_wp_error($state)) {
        wp_send_json_error($state->get_error_message(), 500);
    }
    Ssbhf_Write_job_status($job_id, [
        'status' => 'queued', 'task' => 'analysis', 'files' => 0, 'bytes' => 0,
        'estimated_zip_bytes' => 0, 'created_at' => time(),
    ]);
    wp_send_json_success(['job_id' => $job_id, 'task' => 'analysis']);
}

function Ssbhf_Ajax_analyze_chunk(): void
{
    Ssbhf_Check_ajax_access();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
    if (!is_array($status) || ($status['task'] ?? '') !== 'analysis') {
        wp_send_json_error('Dateianalyse nicht gefunden.', 404);
    }
    // Separate lock: the same analysis can also be polled from multiple tabs.
    $lock = fopen(Ssbhf_Analysis_state_path($job_id) . '.lock', 'c+b');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        wp_send_json_success($status);
    }
    try {
        $status = Ssbhf_Read_job_status($job_id);
        if (!in_array($status['status'], ['done', 'failed'], true)) {
            $status = Ssbhf_Process_analysis_chunk($job_id);
            if (is_wp_error($status)) {
                $status = ['status' => 'failed', 'task' => 'analysis', 'message' => $status->get_error_message()];
                Ssbhf_Write_job_status($job_id, $status);
            }
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    wp_send_json_success($status);
}

/** Explicitly discard an unfinished job; never remove a published ZIP. */
function Ssbhf_Ajax_cancel(): void
{
    Ssbhf_Check_ajax_access();
    $lock = Ssbhf_Backup_lock();
    if (!$lock) {
        wp_send_json_error('Ein Paket läuft noch. Bitte kurz warten.', 409);
    }
    try {
        $id = Ssbhf_Active_backup();
        if ($id) {
            $path = Ssbhf_Analysis_state_path($id);
            $state = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
            if (is_array($state)) {
                foreach (['zip_path', 'central_path', 'tmp_sql', 'manifest_path'] as $key) {
                    $file = $state[$key] ?? '';
                    if ($key === 'zip_path' && substr($file, -8) !== '.partial') {
                        continue;
                    }
                    if ($file && is_file($file) && !unlink($file)) {
                        throw new RuntimeException('Temporäre Datei konnte nicht gelöscht werden.');
                    }
                }
            }
            Ssbhf_Write_job_status($id, ['status' => 'cancelled', 'task' => 'backup', 'message' => 'Auftrag verworfen.']);
            if (is_file($path)) {
                unlink($path);
            }
            unlink(SSBHF_TMP_DIR . '/active-backup.json');
        }
        $result = true;
    } catch (Throwable $error) {
        $result = $error->getMessage();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if ($result !== true) {
        wp_send_json_error($result, 500);
    }
    wp_send_json_success(['cancelled' => true]);
}

function Ssbhf_Check_ajax_access(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Forbidden', 403);
    }
    check_ajax_referer('ssbhf_ajax', 'nonce');
}
