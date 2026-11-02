<?php
/**
 * System requirements and shared checklist rendering.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check the requirements needed to create a backup.
 *
 * @return array
 */
function Ssbhf_Get_System_checks(): array
{
    global $wpdb, $wp_version;

    Ssbhf_Ensure_dirs();

    $backup_writable = is_dir(SSBHF_BACKUP_DIR) && is_writable(SSBHF_BACKUP_DIR);
    $tmp_writable = is_dir(SSBHF_TMP_DIR) && is_writable(SSBHF_TMP_DIR);
    $zip_extension = class_exists('ZipArchive');
    $zip_works = false;

    if ($zip_extension && $tmp_writable) {
        $test_path = trailingslashit(SSBHF_TMP_DIR) . 'check-' . wp_generate_uuid4() . '.zip';
        try {
            $archive = new \ZipArchive();
            if (@$archive->open($test_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $added = @$archive->addFromString('check.txt', 'ok');
                $closed = @$archive->close();
                $zip_works = $added !== false && $closed && is_file($test_path) && filesize($test_path) > 0;
            }
        } catch (Throwable $error) {
            if (isset($archive) && $archive instanceof \ZipArchive) {
                @$archive->close();
            }
        }
        if (file_exists($test_path)) {
            @unlink($test_path);
        }
    }

    $database_works = isset($wpdb) && (string) $wpdb->get_var('SELECT 1') === '1';
    $wp_version = isset($wp_version) ? $wp_version : get_bloginfo('version');

    return [
        [
            'passed' => version_compare(PHP_VERSION, '7.4', '>='),
            'label'  => 'PHP ' . PHP_VERSION . ' (erforderlich: 7.4 oder höher)',
        ],
        [
            'passed' => version_compare($wp_version, '6.0', '>='),
            'label'  => 'WordPress ' . $wp_version . ' (erforderlich: 6.0 oder höher)',
        ],
        [
            'passed' => PHP_INT_SIZE >= 8,
            'label'  => '64-Bit-PHP für große ZIP64-Dateien',
        ],
        [
            'passed' => $zip_extension,
            'label'  => 'PHP-Erweiterung ZIP ist geladen',
        ],
        [
            'passed' => $zip_works,
            'label'  => 'ZIP-Datei kann erstellt und geschrieben werden',
        ],
        [
            'passed' => $database_works,
            'label'  => 'Verbindung zur WordPress-Datenbank funktioniert',
        ],
        [
            'passed' => $backup_writable,
            'label'  => 'Backup-Verzeichnis ist beschreibbar',
        ],
        [
            'passed' => $tmp_writable,
            'label'  => 'Temporäres Verzeichnis ist beschreibbar',
        ],
    ];
}

/**
 * Determine whether every required system check passed.
 *
 * @param array $checks System check results.
 *
 * @return bool
 */
function Ssbhf_System_Checks_Passed(array $checks): bool
{
    foreach ($checks as $check) {
        if (!$check['passed']) {
            return false;
        }
    }

    return true;
}

/**
 * Render the system checks as a checkbox list.
 *
 * @param array $checks System check results.
 *
 * @return void
 */
function Ssbhf_Render_System_Checklist(array $checks): void
{
    echo '<ul class="ssb-checklist">';
    foreach ($checks as $check) {
        $checked = $check['passed'] ? ' checked="checked"' : '';
        echo '<li><label><input type="checkbox" disabled="disabled"' . $checked . '> ' . esc_html($check['label']) . '</label></li>';
    }
    echo '</ul>';
}

/**
 * Render a checklist heading that can collapse when all checks pass.
 *
 * @param string $title         Heading text.
 * @param string $panel_id      ID of the checklist content panel.
 * @param bool   $checks_passed Whether every required check passed.
 *
 * @return void
 */
function Ssbhf_Render_Checklist_Heading(string $title, string $panel_id, bool $checks_passed): void
{
    echo '<h2 class="ssb-checks__heading">';
    if ($checks_passed) {
        echo '<button type="button" class="ssb-checks__toggle" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '">';
        echo '<span>' . esc_html($title) . '</span>';
        echo '<span class="dashicons dashicons-arrow-down-alt2 ssb-checks__toggle-icon" aria-hidden="true"></span>';
        echo '</button>';
    } else {
        echo '<span>' . esc_html($title) . '</span>';
    }
    echo '</h2>';
}
