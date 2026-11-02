<?php
/**
 * Page-scoped admin styles, scripts and AJAX configuration.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load assets only on the backup page or when its activation notice is shown.
 *
 * @return void
 */
function Ssbhf_Enqueue_admin_assets(): void
{
    $screen = get_current_screen();
    $show_activation_notice = $screen
        && $screen->id === 'plugins'
        && get_transient('ssbhf_activation_notice');
    if (!$screen || ($screen->id !== 'tools_page_ssb-simple-backup' && !$show_activation_notice)) {
        return;
    }

    $active_job_id = Ssbhf_Active_backup();
    $active_status = $active_job_id ? Ssbhf_Read_job_status($active_job_id) : null;

    wp_enqueue_style('ssbhf-admin', SSBHF_PLUGIN_URL . 'assets/css/admin.css', [], SSBHF_VERSION);
    wp_enqueue_script('ssbhf-admin', SSBHF_PLUGIN_URL . 'assets/js/admin.js', [], SSBHF_VERSION, true);
    wp_localize_script(
        'ssbhf-admin',
        'ssbhfAdmin',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ssbhf_ajax'),
            'activeJobId' => $active_job_id,
            'activeStatus' => $active_status,
        ]
    );
}
