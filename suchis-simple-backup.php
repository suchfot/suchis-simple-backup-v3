<?php
/**
 * Plugin Name: Suchis Simple Backup
 * Plugin URI: https://www.derperformer.com
 * Description: Einfaches Backup (Dateien + optional DB) als ZIP nach wp-content/backups inkl. Download-Links.
 * Version: 2.1.0
 * Author: Christian Suchanek
 * Author URI: https://www.derperformer.com/wordperessplugin/suchis-simple-backup-dein-wordpress-backup-plugin/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Hinweis: Dieses Plugin wurde mit Unterstützung von KI generiert.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Paths and asset version are defined here so moving modules does not change them.
define('SSBHF_PLUGIN_DIR', __DIR__ . '/');
define('SSBHF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSBHF_VERSION', '2.1.0');
define('SSBHF_BACKUP_DIR', WP_CONTENT_DIR . '/backups');
define('SSBHF_TMP_DIR', WP_CONTENT_DIR . '/uploads/ssb-tmp');

require_once SSBHF_PLUGIN_DIR . 'includes/zip-stream.php';
require_once SSBHF_PLUGIN_DIR . 'includes/files.php';
require_once SSBHF_PLUGIN_DIR . 'includes/jobs.php';
require_once SSBHF_PLUGIN_DIR . 'includes/database.php';
require_once SSBHF_PLUGIN_DIR . 'includes/analysis.php';
require_once SSBHF_PLUGIN_DIR . 'includes/backup.php';
require_once SSBHF_PLUGIN_DIR . 'includes/system-checks.php';
require_once SSBHF_PLUGIN_DIR . 'includes/ajax.php';
require_once SSBHF_PLUGIN_DIR . 'includes/admin-notices.php';
require_once SSBHF_PLUGIN_DIR . 'includes/admin-page.php';
require_once SSBHF_PLUGIN_DIR . 'includes/admin-assets.php';

add_action('admin_menu', 'Ssbhf_Register_admin_page');
add_action('admin_enqueue_scripts', 'Ssbhf_Enqueue_admin_assets');
add_action('admin_notices', 'Ssbhf_Show_Activation_notice');
add_action('admin_post_ssbhf_download', 'Ssbhf_Download_backup');
add_action('admin_post_ssbhf_delete', 'Ssbhf_Delete_backup');
add_action('wp_ajax_ssbhf_start', 'Ssbhf_Ajax_start');
add_action('wp_ajax_ssbhf_analyze', 'Ssbhf_Ajax_analyze');
add_action('wp_ajax_ssbhf_analyze_chunk', 'Ssbhf_Ajax_analyze_chunk');
add_action('wp_ajax_ssbhf_backup_chunk', 'Ssbhf_Ajax_backup_chunk');
add_action('wp_ajax_ssbhf_cancel', 'Ssbhf_Ajax_cancel');

// Activation must stay registered against the plugin entry file.
register_activation_hook(__FILE__, 'Ssbhf_Set_Activation_notice');
