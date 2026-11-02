<?php
/**
 * Admin menu registration and backup page data preparation.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the backup management page under Tools.
 *
 * @return void
 */
function Ssbhf_Register_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    add_management_page(
        'Suchis Simple Backup',
        'Suchis Simple Backup',
        'manage_options',
        'ssb-simple-backup',
        'Ssbhf_Render_page'
    );
}

/**
 * Prepare page data and render the admin view.
 *
 * @return void
 */
function Ssbhf_Render_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = isset($_GET['ssb_notice']) ? sanitize_text_field(wp_unslash($_GET['ssb_notice'])) : '';
    $file = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
    $message = isset($_GET['msg']) ? sanitize_text_field(wp_unslash($_GET['msg'])) : 'Backup fehlgeschlagen.';
    $checks = Ssbhf_Get_System_checks();
    $backup_ready = Ssbhf_System_Checks_Passed($checks);
    $files = Ssbhf_List_backups();
    $panel_id = 'ssbhf-page-checks';

    require SSBHF_PLUGIN_DIR . 'templates/admin-page.php';
}
