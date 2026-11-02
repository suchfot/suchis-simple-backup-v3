<?php
/**
 * Persisted job status, diagnostic logs and duration formatting.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Replace JSON atomically so a killed request cannot leave a partial checkpoint. */
function Ssbhf_Atomic_json(string $path, array $data): void
{
    $json = wp_json_encode($data);
    if ($json === false) {
        throw new RuntimeException('Fortschritt konnte nicht als JSON gespeichert werden.');
    }
    $temporary = $path . '.new-' . bin2hex(random_bytes(6));
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !rename($temporary, $path)) {
            throw new RuntimeException('Fortschritt konnte nicht atomar gespeichert werden.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

/**
 * Get the status path for a job.
 *
 * @param string $job_id Background job identifier.
 *
 * @return string
 */
function Ssbhf_Job_status_path(string $job_id): string
{
    $safe_id = preg_replace('/[^a-zA-Z0-9-]/', '', $job_id);
    return trailingslashit(SSBHF_TMP_DIR) . 'job-' . $safe_id . '.json';
}

/**
 * Write a background job status file.
 *
 * @param string $job_id Background job identifier.
 * @param array  $data   Status data.
 *
 * @return void
 */
function Ssbhf_Write_job_status(string $job_id, array $data): void
{
    Ssbhf_Ensure_dirs();
    $existing = Ssbhf_Read_job_status($job_id);
    if (is_array($existing) && isset($existing['log']) && !isset($data['log'])) {
        $data['log'] = $existing['log'];
    }
    Ssbhf_Atomic_json(Ssbhf_Job_status_path($job_id), $data);
}

/**
 * Append a diagnostic line to a background job.
 *
 * @param string $job_id  Background job identifier.
 * @param string $message Diagnostic message.
 * @param bool   $error   Whether the line describes an error.
 *
 * @return void
 */
function Ssbhf_Add_job_log(string $job_id, string $message, bool $error = false): void
{
    $status = Ssbhf_Read_job_status($job_id);
    if (!is_array($status)) {
        $status = ['status' => 'running'];
    }
    $status['log'] = isset($status['log']) && is_array($status['log']) ? $status['log'] : [];
    $status['log'][] = ['message' => $message, 'error' => $error];
    Ssbhf_Write_job_status($job_id, $status);
}

/**
 * Read a background job status file.
 *
 * @param string $job_id Background job identifier.
 *
 * @return array|false
 */
function Ssbhf_Read_job_status(string $job_id)
{
    $path = Ssbhf_Job_status_path($job_id);
    if (!is_readable($path)) {
        return false;
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : false;
}

/**
 * Format a duration for the admin status message.
 *
 * @param float $seconds Duration in seconds.
 *
 * @return string
 */
function Ssbhf_Format_duration(float $seconds): string
{
    if ($seconds < 60) {
        return number_format($seconds, 1, ',', '') . ' Sekunden';
    }

    return number_format(floor($seconds / 60), 0, ',', '.') . ' Minuten und ' . number_format(fmod($seconds, 60), 0, ',', '.') . ' Sekunden';
}
