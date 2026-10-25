<?php
/**
 * Plugin Name: Suchis Simple Backup
 * Plugin URI: https://www.derperformer.com
 * Description: Einfaches Backup (Dateien + optional DB) als ZIP nach wp-content/backups inkl. Download-Links.
 * Version: 2.0.0
 * Author: Christian Suchanek
 * Author URI: https://www.derperformer.com/wordperessplugin/suchis-simple-backup-dein-wordpress-backup-plugin/
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Hinweis: Dieses Plugin wurde mit Unterstützung von KI generiert.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSBHF_BACKUP_DIR', WP_CONTENT_DIR . '/backups');
define('SSBHF_TMP_DIR', WP_CONTENT_DIR . '/uploads/ssb-tmp');

add_action(
    'admin_menu', function () {
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
);

add_action('admin_post_ssbhf_run', 'Ssbhf_Run_backup');
add_action('admin_post_ssbhf_download', 'Ssbhf_Download_backup');
add_action('admin_post_ssbhf_delete', 'Ssbhf_Delete_backup');
add_action('wp_ajax_ssbhf_start', 'Ssbhf_Ajax_start');
add_action('wp_ajax_ssbhf_status', 'Ssbhf_Ajax_status');
add_action('ssbhf_process_backup_job', 'Ssbhf_Process_backup_job');
add_action('admin_head', 'Ssbhf_Admin_styles');

/**
 * Add lightweight, page-scoped styling without loading a separate stylesheet.
 *
 * @return void
 */
function Ssbhf_Admin_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'tools_page_ssb-simple-backup') {
        return;
    }

    echo '<style>
        .ssb-page { max-width: 1180px; }
        .ssb-intro { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin: 18px 0 24px; padding: 22px 24px; border: 1px solid #dcdcde; border-left: 4px solid #2271b1; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .04); }
        .ssb-intro h2 { margin: 0 0 6px; font-size: 20px; font-weight: 600; }
        .ssb-intro p { margin: 0; color: #50575e; }
        .ssb-intro .button { white-space: nowrap; }
        .ssb-info { margin: 0 0 22px; color: #50575e; }
        .ssb-info code, .ssb-table code { padding: 3px 6px; background: #f0f0f1; color: #2c3338; }
        .ssb-section-title { margin: 28px 0 12px; font-size: 23px; font-weight: 600; }
        .ssb-table { border: 1px solid #c3c4c7; box-shadow: 0 1px 2px rgba(0, 0, 0, .04); }
        .ssb-table th { font-weight: 600; }
        .ssb-table td, .ssb-table th { padding: 12px 16px; }
        .ssb-table td:first-child { width: 48%; }
        .ssb-table .button { margin: 1px 4px 1px 0; }
        .ssb-empty { padding: 18px 20px; border: 1px dashed #c3c4c7; color: #646970; background: #fff; }
        .ssb-footer { margin-top: 18px; color: #646970; font-size: 12px; }
        .ssb-overlay { display: none; position: fixed; z-index: 100000; inset: 0; align-items: center; justify-content: center; background: rgba(255, 255, 255, .82); }
        .ssb-overlay.is-visible { display: flex; }
        .ssb-overlay__box { width: min(420px, calc(100% - 40px)); padding: 30px; text-align: center; border: 1px solid #c3c4c7; background: #fff; box-shadow: 0 8px 30px rgba(0, 0, 0, .15); }
        .ssb-overlay__spinner { float: none; width: 28px; height: 28px; margin: 0 auto 16px; font-size: 28px; }
        .ssb-overlay__title { display: flex; align-items: center; justify-content: center; gap: 8px; margin: 0 0 8px; font-size: 20px; }
        .ssb-overlay__notice { margin: 0 0 14px; color: #646970; font-size: 13px; line-height: 1.5; }
        .ssb-overlay__warning { display: none; color: #d63638; }
        .ssb-help-toggle { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; padding: 0; border: 1px solid #8c8f94; border-radius: 50%; color: #50575e; background: #fff; cursor: pointer; }
        .ssb-help-toggle:hover, .ssb-help-toggle:focus { border-color: #2271b1; color: #2271b1; }
        .ssb-help { margin: 14px 0 0; padding: 12px 14px; text-align: left; border-left: 3px solid #72aee6; background: #f0f6fc; color: #50575e; font-size: 13px; line-height: 1.5; }
        .ssb-help p { margin: 0 0 8px; }
        .ssb-help p:last-child { margin-bottom: 0; }
        .ssb-overlay__text { margin: 0; color: #50575e; }
        .ssb-progress { margin-top: 20px; text-align: left; }
        .ssb-progress__row { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border-top: 1px solid #f0f0f1; color: #646970; line-height: 1.45; }
        .ssb-progress__row.is-active { color: #1d2327; font-weight: 600; }
        .ssb-progress__row.is-done { color: #2271b1; }
        .ssb-progress__icon { flex: 0 0 20px; margin-top: 1px; color: #8c8f94; }
        .ssb-progress__row.is-active .ssb-progress__icon { color: #2271b1; }
        .ssb-progress__row.is-done .ssb-progress__icon { color: #00a32a; }
        .ssb-progress__label { flex: 1; }
        .ssb-terminal { min-height: 190px; max-height: 190px; box-sizing: border-box; margin-top: 16px; padding: 12px 14px; overflow-y: auto; text-align: left; background: #1d2327; color: #dcdcde; font: 12px/1.6 Consolas, Monaco, monospace; }
        .ssb-terminal__line { display: block; }
        .ssb-terminal__line::before { content: "> "; color: #72aee6; }
        .ssb-terminal__line.is-error { color: #f86368; }
        .ssb-overlay__close { display: none; margin: 0; }
        .ssb-overlay.has-error .ssb-overlay__close { display: block; margin: 28px auto 0; }
        .ssb-overlay.has-error .ssb-overlay__spinner { display: none; }
        .ssb-overlay.has-error .ssb-overlay__title { color: #b32d2e; }
        .ssb-overlay.has-error .ssb-overlay__notice { display: none; }
        .ssb-overlay.has-error .ssb-overlay__warning { display: inline-block; }
        .ssb-overlay:not(.has-error) .ssb-help-toggle { display: none; }
        .ssb-delete-modal { display: none; position: fixed; z-index: 100001; inset: 0; align-items: center; justify-content: center; background: rgba(29, 35, 39, .62); }
        .ssb-delete-modal.is-visible { display: flex; }
        .ssb-delete-modal__box { width: min(440px, calc(100% - 40px)); padding: 28px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 8px 30px rgba(0, 0, 0, .25); }
        .ssb-delete-modal__icon { color: #d63638; font-size: 28px; }
        .ssb-delete-modal__title { margin: 10px 0 8px; font-size: 20px; }
        .ssb-delete-modal__text { margin: 0; color: #50575e; }
        .ssb-delete-modal__file { display: block; margin-top: 10px; overflow-wrap: anywhere; }
        .ssb-delete-modal__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
        .ssb-delete-modal__confirm { border-color: #b32d2e !important; background: #b32d2e !important; }
        .ssb-intro .dashicons, .ssb-table .dashicons { vertical-align: text-bottom; }
        @media screen and (max-width: 782px) {
            .ssb-intro { display: block; }
            .ssb-intro .button { margin-top: 16px; }
            .ssb-table { display: block; overflow-x: auto; white-space: nowrap; }
            .ssb-table td:first-child { width: auto; }
        }
    </style>';

    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('ssbhf_ajax');
    echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const button = document.getElementById("ssb-start-backup");
            const overlay = document.getElementById("ssb-backup-overlay");
            const status = document.getElementById("ssb-overlay-status");
            const titleText = overlay.querySelector(".ssb-overlay__title-text");
            const closeButton = document.getElementById("ssb-overlay-close");
            const helpToggle = document.getElementById("ssb-error-help-toggle");
            const help = document.getElementById("ssb-error-help");
            const deleteModal = document.getElementById("ssb-delete-modal");
            const deleteName = document.getElementById("ssb-delete-name");
            const deleteConfirm = document.getElementById("ssb-delete-confirm");
            const deleteCancel = document.getElementById("ssb-delete-cancel");
            if (!button || !overlay) return;

            const showOverlay = function (message) {
                status.textContent = message;
                overlay.classList.add("is-visible");
                button.classList.add("disabled");
                button.disabled = true;
                button.setAttribute("aria-disabled", "true");
            };

            const renderTerminal = function (container, data) {
                const terminal = document.createElement("div");
                terminal.className = "ssb-terminal";
                const log = Array.isArray(data.log) && data.log.length
                    ? data.log
                    : [{ message: "Backup-Job wird gestartet ...", error: false }];
                log.forEach(function (line) {
                    const lineElement = document.createElement("span");
                    lineElement.className = "ssb-terminal__line" + (line.error ? " is-error" : "");
                    lineElement.textContent = line.message;
                    terminal.appendChild(lineElement);
                });
                container.appendChild(terminal);
            };

            const renderProgress = function (data) {
                status.textContent = "";
                const progress = document.createElement("div");
                progress.className = "ssb-progress";
                const addRow = function (icon, label, state) {
                    const row = document.createElement("div");
                    row.className = "ssb-progress__row " + state;
                    const iconElement = document.createElement("span");
                    iconElement.className = "dashicons " + icon + " ssb-progress__icon";
                    iconElement.setAttribute("aria-hidden", "true");
                    const labelElement = document.createElement("span");
                    labelElement.className = "ssb-progress__label";
                    labelElement.textContent = label;
                    row.appendChild(iconElement);
                    row.appendChild(labelElement);
                    progress.appendChild(row);
                };

                if (data.step === "files") {
                    addRow("dashicons-yes-alt", data.database_message, "is-done");
                    addRow("dashicons-open-folder", "Schritt 2 von 2: Dateien werden gepackt ...", "is-active");
                } else {
                    addRow("dashicons-database", "Schritt 1 von 2: Datenbank wird gesichert ...", "is-active");
                    addRow("dashicons-open-folder", "Schritt 2 von 2: Dateien werden danach gepackt", "");
                }
                status.appendChild(progress);
                renderTerminal(status, data);
            };

            const renderFailure = function (data) {
                overlay.classList.add("has-error");
                titleText.textContent = "Backup konnte nicht erstellt werden";
                status.textContent = data.message || "Unbekannter Fehler beim Erstellen des Backups.";
                renderTerminal(status, data);
                closeButton.style.display = "inline-block";
            };

            const request = function (action, data) {
                const form = new FormData();
                form.append("action", action);
                form.append("nonce", ' . wp_json_encode($nonce) . ');
                Object.keys(data || {}).forEach(function (key) { form.append(key, data[key]); });
                return fetch(' . wp_json_encode($ajax_url) . ', { method: "POST", credentials: "same-origin", body: form }).then(function (response) {
                    return response.json();
                });
            };

            const poll = function (jobId) {
                request("ssbhf_status", { job_id: jobId }).then(function (response) {
                    if (!response.success) throw new Error(response.data || "Status konnte nicht gelesen werden.");
                    const data = response.data;
                    if (data.status === "done") {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (data.status === "failed") {
                        renderFailure(data);
                        return;
                    }
                    renderProgress(data);
                    window.setTimeout(function () { poll(jobId); }, 2000);
                }).catch(function (error) {
                    renderFailure({ message: error.message });
                    button.classList.remove("disabled");
                    button.disabled = false;
                    button.removeAttribute("aria-disabled");
                });
            };

            button.addEventListener("click", function () {
                showOverlay("Backup wird jetzt gestartet ...");
                request("ssbhf_start", {}).then(function (response) {
                    if (!response.success) throw new Error(response.data || "Backup konnte nicht gestartet werden.");
                    renderProgress(response.data);
                    poll(response.data.job_id);
                }).catch(function (error) {
                    renderFailure({ message: error.message });
                    button.classList.remove("disabled");
                    button.disabled = false;
                    button.removeAttribute("aria-disabled");
                });
            });

            closeButton.addEventListener("click", function () {
                overlay.classList.remove("is-visible", "has-error");
                closeButton.style.display = "none";
                titleText.textContent = "Backup wird jetzt erstellt";
                help.hidden = true;
                helpToggle.setAttribute("aria-expanded", "false");
                button.classList.remove("disabled");
                button.disabled = false;
                button.removeAttribute("aria-disabled");
            });

            helpToggle.addEventListener("click", function () {
                help.hidden = !help.hidden;
                helpToggle.setAttribute("aria-expanded", help.hidden ? "false" : "true");
            });

            document.querySelectorAll(".ssb-delete-backup").forEach(function (deleteLink) {
                deleteLink.addEventListener("click", function (event) {
                    event.preventDefault();
                    deleteName.textContent = deleteLink.getAttribute("data-file") || "dieses Backup";
                    deleteConfirm.href = deleteLink.href;
                    deleteModal.classList.add("is-visible");
                    deleteConfirm.focus();
                });
            });

            const closeDeleteModal = function () {
                deleteModal.classList.remove("is-visible");
            };

            deleteCancel.addEventListener("click", closeDeleteModal);
            deleteModal.addEventListener("click", function (event) {
                if (event.target === deleteModal) closeDeleteModal();
            });
            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape" && deleteModal.classList.contains("is-visible")) closeDeleteModal();
            });
        });
    </script>';
}

/**
 * Render the backup management page in the WordPress admin.
 *
 * @return void
 */
function Ssbhf_Render_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = isset($_GET['ssb_notice']) ? sanitize_text_field(wp_unslash($_GET['ssb_notice'])) : '';
    $file   = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';

    echo '<div class="wrap ssb-page">';
    echo '<h1>Suchis Simple Backup</h1>';

    if (!class_exists('\ZipArchive')) {
        echo '<div class="notice notice-error"><p><strong>ZipArchive fehlt.</strong> Bitte PHP-Extension <code>zip</code> aktivieren.</p></div>';
        echo '</div>';
        return;
    }

    if ($notice === 'created' && $file) {
        $dl = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_download&file=' . rawurlencode($file)), 'ssbhf_download');
        echo '<div class="notice notice-success"><p>Backup erstellt: <code>' . esc_html($file) . '</code> &nbsp; <a class="button button-small" href="' . esc_url($dl) . '">Download</a></p></div>';
    } elseif ($notice === 'failed') {
        $msg = isset($_GET['msg']) ? sanitize_text_field(wp_unslash($_GET['msg'])) : 'Backup fehlgeschlagen.';
        echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
    } elseif ($notice === 'deleted') {
        echo '<div class="notice notice-success"><p>Backup gelöscht.</p></div>';
    }

    echo '<div class="ssb-intro">';
    echo '<div><h2>Website sichern</h2><p>Erstellt ein vollständiges Backup aus Dateien und Datenbank.</p></div>';
    echo '<button type="button" id="ssb-start-backup" class="button button-primary"><span class="dashicons dashicons-database-export" aria-hidden="true"></span> Backup jetzt erstellen</button>';
    echo '</div>';
    echo '<p class="ssb-info">Backups liegen in <code>wp-content/backups</code>. Dieser Ordner wird beim ZIP-Erstellen automatisch <strong>ausgeschlossen</strong>, damit sich Backups nicht selbst aufblasen.</p>';

    echo '<h2 class="ssb-section-title">Vorhandene Backups</h2>';

    $files = Ssbhf_List_backups();
    if (!$files) {
        echo '<p class="ssb-empty"><em>Noch keine Backups vorhanden.</em></p>';
    } else {
        echo '<table class="widefat striped ssb-table">';
        echo '<thead><tr><th>Datei</th><th>Größe</th><th>Datum</th><th>Aktionen</th></tr></thead><tbody>';
        foreach ($files as $f) {
            $dl = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_download&file=' . rawurlencode($f['name'])), 'ssbhf_download');
            $del = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_delete&file=' . rawurlencode($f['name'])), 'ssbhf_delete');
            echo '<tr>';
            echo '<td><code>' . esc_html($f['name']) . '</code></td>';
            echo '<td>' . esc_html(size_format($f['size'])) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $f['mtime'])) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($dl) . '"><span class="dashicons dashicons-download" aria-hidden="true"></span> Download</a> ';
            echo '<a class="button button-small ssb-delete-backup" href="' . esc_url($del) . '" data-file="' . esc_attr($f['name']) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span> Löschen</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p class="ssb-footer">Author: Christian Suchanek · <a href="mailto:christian.suchanek@gmail.com">christian.suchanek@gmail.com</a></p>';
    echo '<div id="ssb-backup-overlay" class="ssb-overlay" role="status" aria-live="polite" aria-label="Backup wird erstellt">';
    echo '<div class="ssb-overlay__box"><span class="spinner is-active ssb-overlay__spinner" aria-hidden="true"></span><h2 class="ssb-overlay__title"><span class="dashicons dashicons-warning ssb-overlay__warning" aria-hidden="true"></span><span class="ssb-overlay__title-text">Backup wird jetzt erstellt</span><button type="button" id="ssb-error-help-toggle" class="ssb-help-toggle" aria-expanded="false" aria-controls="ssb-error-help" title="Was bedeutet diese Meldung?"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="screen-reader-text">Was bedeutet diese Meldung?</span></button></h2><p class="ssb-overlay__notice">Bitte schließen Sie dieses Fenster nicht. Je nach Größe der Datenbank, Dateien und Medien kann das Backup mehrere Minuten oder länger dauern.</p><div id="ssb-overlay-status" class="ssb-overlay__text">Bitte warten ...</div><div id="ssb-error-help" class="ssb-help" hidden><p><strong>Was bedeutet das?</strong> WordPress hat den Backupauftrag angenommen, konnte ihn aber nicht im Hintergrund starten. Dafür sind normalerweise WP-Cron oder interne Loopback-Anfragen zuständig.</p><p><strong>Was kannst du tun?</strong> Prüfe zuerst unter <strong>Werkzeuge &rarr; Website-Zustand</strong>, ob WordPress Fehler bei Loopback-Anfragen oder geplanten Aufgaben meldet. Informiere danach die WordPress-Administration, deine betreuende Agentur oder den Hosting-Support.</p><p>Bitte nenne dabei diese Fehlermeldung und frage konkret nach <strong>WP-Cron</strong>, <strong>Loopback-Anfragen</strong>, Serverfirewall und Sicherheitsplugins. Es ist nicht automatisch ein Speicherplatzproblem.</p></div><button type="button" id="ssb-overlay-close" class="button ssb-overlay__close">Schließen</button></div></div>';
    echo '<div id="ssb-delete-modal" class="ssb-delete-modal" role="dialog" aria-modal="true" aria-labelledby="ssb-delete-title">';
    echo '<div class="ssb-delete-modal__box"><span class="dashicons dashicons-warning ssb-delete-modal__icon" aria-hidden="true"></span><h2 id="ssb-delete-title" class="ssb-delete-modal__title">Backup wirklich löschen?</h2><p class="ssb-delete-modal__text">Diese Datei wird dauerhaft vom Server entfernt:</p><code id="ssb-delete-name" class="ssb-delete-modal__file"></code><div class="ssb-delete-modal__actions"><button type="button" id="ssb-delete-cancel" class="button">Abbrechen</button><a id="ssb-delete-confirm" class="button button-primary ssb-delete-modal__confirm" href="#"><span class="dashicons dashicons-trash" aria-hidden="true"></span> Löschen</a></div></div></div>';
    echo '</div>';
}

/**
 * Start a backup job without keeping the browser request open.
 *
 * @return void
 */
function Ssbhf_Ajax_start(): void
{
    Ssbhf_Check_ajax_access();

    if (!class_exists('\ZipArchive')) {
        wp_send_json_error('ZipArchive fehlt. Bitte PHP-Extension zip aktivieren.', 400);
    }

    Ssbhf_Ensure_dirs();
    $job_id = wp_generate_uuid4();
    Ssbhf_Write_job_status(
        $job_id, [
        'status'  => 'queued',
        'message' => 'Backup wird im Hintergrund gestartet ...',
        'created_at' => time(),
        ]
    );

    $scheduled = wp_schedule_single_event(time() + 1, 'ssbhf_process_backup_job', [$job_id], true);
    if (is_wp_error($scheduled)) {
        if (function_exists('fastcgi_finish_request')) {
            $fallback_status = [
                'status'  => 'running',
                'step'    => 'database',
                'message' => 'WP-Cron ist nicht verfügbar. Backup wird direkt im Hintergrund verarbeitet ...',
            ];
            Ssbhf_Write_job_status($job_id, $fallback_status);
            Ssbhf_Send_ajax_response(
                [
                'job_id'  => $job_id,
                'message' => $fallback_status['message'],
                'step'    => 'database',
                ]
            );
            Ssbhf_Process_backup_job($job_id);
            exit;
        }
        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'failed',
            'message' => 'Der Backup-Hintergrundjob konnte nicht geplant werden: ' . $scheduled->get_error_message(),
            ]
        );
    } elseif (spawn_cron(time()) === false) {
        if (function_exists('fastcgi_finish_request')) {
            $fallback_status = [
                'status'  => 'running',
                'step'    => 'database',
                'message' => 'WP-Cron ist blockiert. Backup wird direkt im Hintergrund verarbeitet ...',
            ];
            Ssbhf_Write_job_status($job_id, $fallback_status);
            Ssbhf_Send_ajax_response(
                [
                'job_id'  => $job_id,
                'message' => $fallback_status['message'],
                'step'    => 'database',
                ]
            );
            Ssbhf_Process_backup_job($job_id);
            exit;
        }
        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'failed',
            'message' => 'WordPress konnte den Hintergrundjob nicht starten. Bitte WP-Cron und Loopback-Anfragen prüfen.',
            ]
        );
    }

    wp_send_json_success(
        [
        'job_id'  => $job_id,
        'message' => 'Backup wird jetzt erstellt. Das kann je nach Websitegröße einige Minuten dauern.',
        ]
    );
}

/**
 * Return the current state of a background backup job.
 *
 * @return void
 */
function Ssbhf_Ajax_status(): void
{
    Ssbhf_Check_ajax_access();

    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
    if (!is_array($status)) {
        wp_send_json_error('Der Status des Backups konnte nicht gelesen werden.', 404);
    }

    if ($status['status'] === 'queued' && !empty($status['created_at']) && time() - (int) $status['created_at'] > 15) {
        if (function_exists('fastcgi_finish_request')) {
            $status['status'] = 'running';
            $status['step'] = 'database';
            $status['message'] = 'WP-Cron hat nicht reagiert. Backup wird direkt im Hintergrund verarbeitet ...';
            Ssbhf_Write_job_status($job_id, $status);
            Ssbhf_Send_ajax_response($status);
            Ssbhf_Process_backup_job($job_id);
            exit;
        }
        $status['status'] = 'failed';
        $status['message'] = 'Der Backup-Hintergrundjob wurde nicht gestartet. Auf dieser Installation sind WP-Cron oder Loopback-Anfragen vermutlich deaktiviert bzw. blockiert.';
        $status['log'] = isset($status['log']) && is_array($status['log']) ? $status['log'] : [];
        $status['log'][] = ['message' => 'Fehler: WP-Cron hat den Job nach 15 Sekunden nicht ausgeführt.', 'error' => true];
        Ssbhf_Write_job_status($job_id, $status);
    }

    if ($status['status'] === 'done' && !empty($status['file'])) {
        $status['redirect'] = admin_url(
            'tools.php?page=ssb-simple-backup&ssb_notice=created&file=' . rawurlencode($status['file'])
        );
    }

    wp_send_json_success($status);
}

/**
 * Finish the AJAX response before running the PHP fallback job.
 *
 * @param array $data Response data.
 *
 * @return void
 */
function Ssbhf_Send_ajax_response(array $data): void
{
    nocache_headers();
    header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    echo wp_json_encode(['success' => true, 'data' => $data]);
    fastcgi_finish_request();
}

/**
 * Process a queued backup from WP-Cron.
 *
 * @param string $job_id Background job identifier.
 *
 * @return void
 */
function Ssbhf_Process_backup_job(string $job_id): void
{
    Ssbhf_Write_job_status(
        $job_id, [
        'status'  => 'running',
        'message' => 'Dateien und Datenbank werden gesichert ...',
        ]
    );

    try {
        $started_at = microtime(true);
        $result = Ssbhf_Create_backup($job_id);
        if (is_wp_error($result)) {
            Ssbhf_Write_job_status(
                $job_id, [
                'status'  => 'failed',
                'message' => $result->get_error_message(),
                ]
            );
            return;
        }

        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'done',
            'message' => 'Backup wurde erfolgreich erstellt. Gesamtdauer: ' . Ssbhf_Format_duration(microtime(true) - $started_at) . '.',
            'file'    => $result,
            ]
        );
    } catch (Throwable $error) {
        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'failed',
            'message' => 'Backup fehlgeschlagen: ' . $error->getMessage(),
            ]
        );
    }
}

/**
 * Check permissions and the AJAX nonce.
 *
 * @return void
 */
function Ssbhf_Check_ajax_access(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Forbidden', 403);
    }
    check_ajax_referer('ssbhf_ajax', 'nonce');
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
    @file_put_contents(Ssbhf_Job_status_path($job_id), wp_json_encode($data), LOCK_EX);
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

/**
 * Run the backup process, creating a ZIP file with site files and database.
 *
 * @return void
 */
function Ssbhf_Run_backup(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', 403);
    }
    check_admin_referer('ssbhf_run');

    $result = Ssbhf_Create_backup();
    if (is_wp_error($result)) {
        wp_safe_redirect(admin_url('tools.php?page=ssb-simple-backup&ssb_notice=failed&msg=' . rawurlencode($result->get_error_message())));
        exit;
    }

    wp_safe_redirect(admin_url('tools.php?page=ssb-simple-backup&ssb_notice=created&file=' . rawurlencode($result)));
    exit;
}

/**
 * Create a backup ZIP and return its filename.
 *
 * @param string|null $job_id Background job identifier, if applicable.
 *
 * @return string|WP_Error
 */
function Ssbhf_Create_backup(?string $job_id = null)
{
    // Backups großer Sites brauchen mehr als das PHP-Default-Zeitlimit (30s)
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (function_exists('ignore_user_abort')) {
        ignore_user_abort(true);
    }

    Ssbhf_Ensure_dirs();

    if ($job_id) {
        $free_space = @disk_free_space(SSBHF_BACKUP_DIR);
        $free_space_message = $free_space !== false ? size_format($free_space) : 'unbekannt';
        Ssbhf_Add_job_log(
            $job_id,
            'ZIP-Ziel geprüft: ' . (is_writable(SSBHF_BACKUP_DIR) ? 'beschreibbar' : 'nicht beschreibbar') . ', freier Speicher: ' . $free_space_message . '.'
        );
    }

    $host = parse_url(home_url(), PHP_URL_HOST);
    $host = $host ? preg_replace('~[^a-zA-Z0-9\._-]~', '-', $host) : 'site';
    $filename = 'wp-backup_files-db_' . $host . '_' . gmdate('Y-m-d_His') . '.zip';

    $zip_path = Ssbhf_Backup_path($filename);
    $tmp_sql  = trailingslashit(SSBHF_TMP_DIR) . 'database_' . $host . '_' . gmdate('Ymd_His') . '.sql';

    $db_started_at = microtime(true);

    if ($job_id) {
        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'running',
            'step'    => 'database',
            'message' => 'Schritt 1 von 2: Die Datenbank wird gesichert ...',
            ]
        );
    }

    // 1) DB dump to tmp (best-effort)
    $db_ok = Ssbhf_Dump_db($tmp_sql);

    if ($job_id) {
        $db_size = $db_ok && file_exists($tmp_sql) ? filesize($tmp_sql) : 0;
        $db_message = $db_ok
            ? 'Schritt 1 abgeschlossen: Datenbank-Dump mit ' . size_format($db_size) . ' in ' . Ssbhf_Format_duration(microtime(true) - $db_started_at) . ' erstellt.'
            : 'Schritt 1 abgeschlossen: Die Datenbank konnte nicht gesichert werden.';
        Ssbhf_Write_job_status(
            $job_id, [
            'status'  => 'running',
            'step'    => 'files',
            'database_message' => $db_message,
            'message' => $db_message . ' Als Nächstes: Schritt 2 von 2, die Dateien werden jetzt gepackt ...',
            ]
        );
        Ssbhf_Add_job_log($job_id, $db_message, !$db_ok);
    }

    // 2) Create ZIP
    if ($job_id) {
        Ssbhf_Add_job_log($job_id, 'ZIP-Datei wird angelegt: ' . $filename . '.');
    }
    $zip = new \ZipArchive();
    $open = $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    if ($open !== true) {
        if ($job_id) {
            Ssbhf_Add_job_log($job_id, 'Fehler: ZIP konnte nicht geöffnet werden. Rückgabecode: ' . (string) $open . '.', true);
        }
        @unlink($tmp_sql);
        return new WP_Error('zip_open_failed', 'ZIP konnte nicht geöffnet werden. Rückgabecode: ' . (string) $open . '.');
    }

    if ($db_ok && file_exists($tmp_sql)) {
        $zip->addFile($tmp_sql, 'database.sql');
    }

    $root = rtrim(realpath(ABSPATH), '/\\');
    $backup_dir = rtrim(realpath(SSBHF_BACKUP_DIR), '/\\');

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $fi) {
        if (!$fi->isFile()) {
            continue;
        }

        $path = $fi->getRealPath();
        if (!$path) {
            continue;
        }

        // Exclude backup dir to prevent self-bloat
        if ($backup_dir && str_starts_with($path, $backup_dir . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $rel = ltrim(str_replace($root, '', $path), '/\\');
        if ($rel === '') {
            continue;
        }

        $zip->addFile($path, $rel);
    }

    $close_ok = $zip->close();
    $zip_status = method_exists($zip, 'getStatusString') ? $zip->getStatusString() : 'unbekannt';
    @unlink($tmp_sql);

    $zip_size = file_exists($zip_path) ? filesize($zip_path) : false;
    if ($job_id) {
        $zip_size_message = $zip_size !== false ? size_format($zip_size) : 'Datei fehlt';
        Ssbhf_Add_job_log(
            $job_id,
            'ZIP abgeschlossen: Status „' . $zip_status . '“, Größe: ' . $zip_size_message . '.'
        );
    }

    if (!$close_ok) {
        if ($job_id) {
            Ssbhf_Add_job_log($job_id, 'Fehler: ZIP konnte nicht finalisiert werden.', true);
        }
        return new WP_Error('zip_close_failed', 'ZIP konnte nicht finalisiert werden. ZIP-Status: ' . $zip_status . '.');
    }

    // Sanity
    if (!file_exists($zip_path) || $zip_size === false || $zip_size < 100) {
        if ($job_id) {
            Ssbhf_Add_job_log($job_id, 'Fehler: Die ZIP-Datei fehlt oder ist kleiner als 100 Byte.', true);
        }
        return new WP_Error(
            'zip_invalid',
            'ZIP wurde nicht korrekt geschrieben. Größe: ' . ($zip_size !== false ? size_format($zip_size) : 'Datei fehlt') . '. ZIP-Status: ' . $zip_status . '.'
        );
    }

    return $filename;
}

/**
 * Dump the WordPress database to a SQL file.
 *
 * @param string $tmp_sql Path to the temporary SQL file.
 *
 * @return bool True if the dump was successful, false otherwise.
 */
function Ssbhf_Dump_db(string $tmp_sql): bool
{
    global $wpdb;

    $fh = @fopen($tmp_sql, 'wb');
    if (!$fh) {
        return false;
    }

    fwrite($fh, "-- Suchis Simple Backup DB Dump\n");
    fwrite($fh, "-- Site: " . home_url() . "\n");
    fwrite($fh, "-- Date: " . gmdate('c') . " (UTC)\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\n");
    fwrite($fh, "SET time_zone = '+00:00';\n");
    fwrite($fh, "SET foreign_key_checks = 0;\n\n");

    $tables = $wpdb->get_col('SHOW TABLES');
    if (!is_array($tables) || empty($tables)) {
        fwrite($fh, "\n-- No tables found.\nSET foreign_key_checks = 1;\n");
        fclose($fh);
        return true;
    }

    foreach ($tables as $table) {
        $tq = Ssbhf_bt($table);
        fwrite($fh, "\n-- Table: {$tq}\n");
        fwrite($fh, "DROP TABLE IF EXISTS {$tq};\n");

        $create = $wpdb->get_row("SHOW CREATE TABLE {$tq}", ARRAY_N);
        if (!is_array($create) || empty($create[1])) {
            fwrite($fh, "-- WARNING: SHOW CREATE TABLE failed for {$tq}\n");
            continue;
        }
        fwrite($fh, $create[1] . ";\n\n");

        // Stream rows in smaller chunks
        $offset = 0;
        $limit = 500;

        while (true) {
            $sql = $wpdb->prepare("SELECT * FROM {$tq} LIMIT %d OFFSET %d", $limit, $offset);
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || empty($rows)) {
                break;
            }

            $cols = array_keys($rows[0]);
            $col_list = implode(',', array_map('Ssbhf_bt', $cols));

            fwrite($fh, "INSERT INTO {$tq} ({$col_list}) VALUES\n");
            $n = count($rows);
            for ($i = 0; $i < $n; $i++) {
                $r = $rows[$i];
                $vals = [];
                foreach ($cols as $c) {
                    $vals[] = Ssbhf_Sql_value($wpdb, $r[$c] ?? null);
                }
                $line = '(' . implode(',', $vals) . ')';
                $line .= ($i < $n - 1) ? ",\n" : ";\n";
                fwrite($fh, $line);
            }

            $offset += $n;
        }
    }

    fwrite($fh, "\nSET foreign_key_checks = 1;\n");
    fclose($fh);
    return true;
}

/**
 * Escape a database identifier (table or column name) with backticks.
 *
 * @param string $name The identifier to escape.
 *
 * @return string The escaped identifier.
 */
function Ssbhf_bt(string $name): string
{
    $name = str_replace('`', '``', $name);
    return '`' . $name . '`';
}

/**
 * Convert a value to its SQL representation.
 *
 * @param object $wpdb  The WordPress database object.
 * @param mixed  $value The value to convert.
 *
 * @return string The SQL-escaped value.
 */
function Ssbhf_Sql_value($wpdb, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $s = (string)$value;

    if (isset($wpdb->dbh) && $wpdb->dbh instanceof mysqli) {
        $s = mysqli_real_escape_string($wpdb->dbh, $s);
    } else {
        $s = str_replace(["\\", "\0", "\n", "\r", "\x1a", "'", "\""], ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"'], $s);
    }
    return "'" . $s . "'";
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
    if (!$name || strpbrk($name, "/\\\0\r\n") !== false || !str_ends_with($name, '.zip')) {
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
    if (!$name || !str_ends_with($name, '.zip')) {
        wp_die('Invalid file', 400);
    }

    $path = Ssbhf_Backup_path($name);
    if (file_exists($path)) {
        @unlink($path);
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

    $htaccess = SSBHF_BACKUP_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
    }
    $index = SSBHF_BACKUP_DIR . '/index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, "");
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
