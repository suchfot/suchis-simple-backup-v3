<?php
/**
 * Chunked file scanning, manifests and size estimates.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the path to the persisted state of an analysis job.
 *
 * @param string $job_id Background job identifier.
 *
 * @return string
 */
function Ssbhf_Analysis_state_path(string $job_id): string
{
    $safe_id = preg_replace('/[^a-zA-Z0-9-]/', '', $job_id);
    return trailingslashit(SSBHF_TMP_DIR) . 'analysis-' . $safe_id . '.json';
}

/**
 * Create scan state and an empty file manifest for an analysis job.
 *
 * @param string $job_id Background job identifier.
 *
 * @return array|WP_Error
 */
function Ssbhf_Create_analysis_state(string $job_id)
{
    $root = realpath(ABSPATH);
    if ($root === false) {
        return new WP_Error('analysis_root_missing', 'Das WordPress-Verzeichnis konnte nicht gelesen werden.');
    }

    $excluded_dirs = [];
    foreach ([SSBHF_BACKUP_DIR, SSBHF_TMP_DIR] as $directory) {
        $real_directory = realpath($directory);
        if ($real_directory !== false) {
            $excluded_dirs[] = rtrim($real_directory, '/\\');
        }
    }

    $state = [
        'root'                 => rtrim($root, '/\\'),
        'excluded_dirs'        => $excluded_dirs,
        'stack'                => [['path' => rtrim($root, '/\\'), 'offset' => 0]],
        'manifest_path'        => trailingslashit(SSBHF_TMP_DIR) . 'manifest-' . preg_replace('/[^a-zA-Z0-9-]/', '', $job_id) . '.jsonl',
        'manifest_bytes'       => 0,
        'files'                => 0,
        'bytes'                => 0,
        'estimated_zip_bytes'  => 0,
        'scan_started_at'      => microtime(true),
    ];

    if (@file_put_contents($state['manifest_path'], '', LOCK_EX) === false
        || @file_put_contents(Ssbhf_Analysis_state_path($job_id), wp_json_encode($state), LOCK_EX) === false
    ) {
        return new WP_Error('analysis_state_write_failed', 'Der temporäre Status der Dateianalyse konnte nicht gespeichert werden.');
    }

    return $state;
}

/**
 * Process one bounded batch of directory entries for an analysis job.
 *
 * @param string $job_id Background job identifier.
 *
 * @return array|WP_Error
 */
function Ssbhf_Process_analysis_chunk(string $job_id, string $task = 'analysis')
{
    $state_path = Ssbhf_Analysis_state_path($job_id);
    $state = is_readable($state_path)
        ? json_decode((string) file_get_contents($state_path), true)
        : false;
    if (!is_array($state)) {
        return new WP_Error('analysis_state_missing', 'Der Status der Dateianalyse konnte nicht gelesen werden.');
    }

    $manifest = Ssbhf_Zip_handle($state['manifest_path'], $state['manifest_bytes'] ?? 0);
    if (!$manifest) {
        return new WP_Error('analysis_manifest_open_failed', 'Die temporäre Dateiliste konnte nicht geöffnet werden.');
    }

    $entries_processed = 0;
    $max_entries = 250;
    $deadline = microtime(true) + 2.0;
    $stack = $state['stack'];

    while ($stack && $entries_processed < $max_entries && microtime(true) < $deadline) {
        $directory = array_pop($stack);
        $handle = @opendir($directory['path']);
        if (!$handle) {
            fclose($manifest);
            return new WP_Error('analysis_directory', 'Verzeichnis ist nicht lesbar: ' . $directory['path']);
        }

        $entry_index = 0;
        while (($name = readdir($handle)) !== false) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $entry_index++;

            if ($entry_index <= (int) $directory['offset']) {
                continue;
            }

            $path = $directory['path'] . DIRECTORY_SEPARATOR . $name;
            $directory['offset'] = $entry_index;
            $entries_processed++;

            if (is_link($path)) {
                continue;
            }

            $excluded = false;
            foreach ($state['excluded_dirs'] as $excluded_dir) {
                if ($path === $excluded_dir || strpos($path, $excluded_dir . DIRECTORY_SEPARATOR) === 0) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            if (is_dir($path)) {
                $stack[] = $directory;
                $stack[] = ['path' => $path, 'offset' => 0];
                break;
            }

            if (is_file($path)) {
                $size = @filesize($path);
                if ($size !== false) {
                    $relative_path = ltrim(substr($path, strlen($state['root'])), '/\\');
                    $line = wp_json_encode(['path' => $path, 'relative_path' => $relative_path]);
                    if ($line !== false) {
                        Ssbhf_Write_all($manifest, $line . "\n");
                        $state['files']++;
                        $state['bytes'] += $size;
                        $state['estimated_zip_bytes'] += (int) round($size * Ssbhf_Estimated_compression_ratio($path));
                    }
                } else {
                    closedir($handle);
                    fclose($manifest);
                    return new WP_Error('analysis_stat', 'Dateigröße konnte nicht gelesen werden: ' . $path);
                }
            }

            if ($entries_processed >= $max_entries || microtime(true) >= $deadline) {
                $stack[] = $directory;
                break;
            }
        }

        closedir($handle);
    }

    $state['manifest_bytes'] = ftell($manifest);
    if (!fflush($manifest)) {
        fclose($manifest);
        return new WP_Error('analysis_manifest_flush', 'Dateiliste konnte nicht gespeichert werden.');
    }
    fclose($manifest);
    $state['stack'] = $stack;
    $done = empty($stack);
    $state['scan_complete'] = $done;
    $state['analysis_elapsed_seconds'] = max(0, microtime(true) - (float) ($state['scan_started_at'] ?? microtime(true)));
    Ssbhf_Atomic_json($state_path, $state);

    $status = [
        'status'              => $done && $task === 'analysis' ? 'done' : 'running',
        'task'                => $task,
        'step'                => $task === 'backup' && $done ? 'database' : 'analysis',
        'message'             => $done ? 'Dateianalyse abgeschlossen.' : 'Dateien werden untersucht ...',
        'files'               => $state['files'],
        'files_total'         => $done ? $state['files'] : 0,
        'bytes'               => $state['bytes'],
        'estimated_zip_bytes' => $state['estimated_zip_bytes'],
        'scan_complete'       => $done,
    ];
    if ($done) {
        $status['estimated_duration'] = Ssbhf_Estimated_duration_label($state['files'], $state['estimated_zip_bytes']);
    }
    Ssbhf_Write_job_status($job_id, $status);

    return $status;
}

/**
 * Estimate a rough backup duration from file count and estimated ZIP size.
 *
 * @param int $files     Number of files to add.
 * @param int $zip_bytes Estimated compressed file size.
 *
 * @return string
 */
function Ssbhf_Estimated_duration_label(int $files, int $zip_bytes): string
{
    $estimated_seconds = max(30, ($files / 50) + ($zip_bytes / (10 * 1024 * 1024)));
    $minimum_minutes = max(1, (int) floor($estimated_seconds * 0.7 / 60));
    $maximum_minutes = max($minimum_minutes + 1, (int) ceil($estimated_seconds * 1.7 / 60));

    return 'ca. ' . $minimum_minutes . ' bis ' . $maximum_minutes . ' Minuten';
}

/**
 * Estimate how much a file shrinks when added to a ZIP archive.
 *
 * @param string $path Absolute file path.
 *
 * @return float Estimated compressed-size ratio.
 */
function Ssbhf_Estimated_compression_ratio(string $path): float
{
    // STORE is intentional: no unbounded compression finalization in a request.
    return 1.0;
}
