<?php
/**
 * Plugin Name: Suchis Simple Backup
 * Plugin URI: https://www.derperformer.com
 * Description: Einfaches Backup (Dateien + optional DB) als ZIP nach wp-content/backups inkl. Download-Links.
 * Version: 2.0.0
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

add_action('admin_post_ssbhf_download', 'Ssbhf_Download_backup');
add_action('admin_post_ssbhf_delete', 'Ssbhf_Delete_backup');
add_action('wp_ajax_ssbhf_start', 'Ssbhf_Ajax_start');
add_action('wp_ajax_ssbhf_analyze', 'Ssbhf_Ajax_analyze');
add_action('wp_ajax_ssbhf_analyze_chunk', 'Ssbhf_Ajax_analyze_chunk');
add_action('wp_ajax_ssbhf_backup_chunk', 'Ssbhf_Ajax_backup_chunk');
add_action('admin_head', 'Ssbhf_Admin_styles');
add_action('admin_notices', 'Ssbhf_Show_Activation_notice');
register_activation_hook(__FILE__, 'Ssbhf_Set_Activation_notice');

/**
 * Add lightweight, page-scoped styling without loading a separate stylesheet.
 *
 * @return void
 */
function Ssbhf_Admin_styles(): void
{
    $screen = get_current_screen();
    $show_activation_notice = $screen
        && $screen->id === 'plugins'
        && get_transient('ssbhf_activation_notice');
    if (!$screen || ($screen->id !== 'tools_page_ssb-simple-backup' && !$show_activation_notice)) {
        return;
    }

    echo '<style>
        .ssb-page { max-width: 1180px; }
        .ssb-intro { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin: 18px 0 24px; padding: 22px 24px; border: 1px solid #dcdcde; border-left: 4px solid #2271b1; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .04); }
        .ssb-intro h2 { margin: 0 0 6px; font-size: 20px; font-weight: 600; }
        .ssb-intro p { margin: 0; color: #50575e; }
        .ssb-intro .button { white-space: nowrap; }
        .ssb-intro__actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ssb-info { margin: 0 0 22px; color: #50575e; }
        .ssb-info code, .ssb-table code { padding: 3px 6px; background: #f0f0f1; color: #2c3338; }
        .ssb-checks { margin: 0 0 22px; padding: 16px 20px; border: 1px solid #c3c4c7; background: #fff; }
        .ssb-checks__heading { margin: 0 0 12px; font-size: 16px; }
        .ssb-checks__toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0; border: 0; color: inherit; background: transparent; font: inherit; font-weight: 600; text-align: left; cursor: pointer; }
        .ssb-checks__toggle:focus-visible { outline: 2px solid #2271b1; outline-offset: 3px; }
        .ssb-checks__toggle-icon { flex: 0 0 auto; margin-left: 12px; color: #646970; transition: transform .15s ease; }
        .ssb-checks__toggle[aria-expanded="true"] .ssb-checks__toggle-icon { transform: rotate(180deg); }
        .ssb-checks__content[hidden] { display: none; }
        .ssb-checks-notice .ssb-checks__heading { margin: 4px 0 8px; font-size: 14px; }
        .ssb-checks-notice .ssb-checklist { margin-bottom: 12px; }
        .ssb-checklist { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px 18px; margin: 0; padding: 0; list-style: none; }
        .ssb-checklist label { display: flex; align-items: flex-start; gap: 8px; }
        .ssb-checklist input { flex: 0 0 auto; margin: 2px 0 0; }
        .ssb-checks__hint { margin: 12px 0 0; color: #646970; }
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
        .ssb-analysis-overlay { display: none; position: fixed; z-index: 100002; inset: 0; align-items: center; justify-content: center; padding: 24px; background: rgba(29, 35, 39, .58); }
        .ssb-analysis-overlay.is-visible { display: flex; }
        .ssb-analysis__box { width: min(520px, 100%); padding: 28px; border: 1px solid #c3c4c7; background: #fff; box-shadow: 0 8px 30px rgba(0, 0, 0, .2); }
        .ssb-analysis__title { margin: 0 0 10px; font-size: 20px; line-height: 1.35; }
        .ssb-analysis__message { margin: 0; color: #50575e; }
        .ssb-analysis__progress { height: 10px; margin: 22px 0 12px; overflow: hidden; background: #dcdcde; }
        .ssb-analysis__progress-bar { display: block; width: 38%; height: 100%; background: #2271b1; animation: ssb-analysis-progress 1.4s ease-in-out infinite alternate; }
        .ssb-analysis__status { min-height: 20px; margin: 0; color: #646970; font-size: 13px; }
        .ssb-analysis__summary { display: grid; grid-template-columns: 1fr auto; gap: 8px 18px; margin: 22px 0 0; }
        #ssb-analysis-overlay [hidden] { display: none !important; }
        .ssb-analysis__summary dt { color: #50575e; }
        .ssb-analysis__summary dd { margin: 0; font-weight: 600; text-align: right; }
        .ssb-analysis__hint { margin: 18px 0 0; color: #646970; font-size: 12px; line-height: 1.5; }
        .ssb-analysis__error { margin: 18px 0 0; color: #b32d2e; }
        .ssb-analysis__close { margin-top: 22px; }
        @keyframes ssb-analysis-progress { from { transform: translateX(-110%); } to { transform: translateX(270%); } }
        @media (prefers-reduced-motion: reduce) { .ssb-analysis__progress-bar { width: 100%; animation: none; } }
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
            const analysisStart = document.getElementById("ssb-analysis-start");
            const overlay = document.getElementById("ssb-backup-overlay");
            const status = document.getElementById("ssb-overlay-status");
            const analysisOverlay = document.getElementById("ssb-analysis-overlay");
            const analysisTitle = document.getElementById("ssb-analysis-title");
            const analysisMessage = document.getElementById("ssb-analysis-message");
            const analysisStatus = document.getElementById("ssb-analysis-status");
            const analysisProgress = document.getElementById("ssb-analysis-progress");
            const analysisSummary = document.getElementById("ssb-analysis-summary");
            const analysisFiles = document.getElementById("ssb-analysis-files");
            const analysisBytes = document.getElementById("ssb-analysis-bytes");
            const analysisZipBytes = document.getElementById("ssb-analysis-zip-bytes");
            const analysisHint = document.getElementById("ssb-analysis-hint");
            const analysisError = document.getElementById("ssb-analysis-error");
            const analysisClose = document.getElementById("ssb-analysis-close");
            let analysisJobId = null;

            document.querySelectorAll(".ssb-checks__toggle").forEach(function (toggle) {
                const panel = document.getElementById(toggle.getAttribute("aria-controls"));
                if (!panel) return;
                toggle.addEventListener("click", function () {
                    const expanded = toggle.getAttribute("aria-expanded") === "true";
                    toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
                    panel.hidden = expanded;
                });
            });

            if (!button || !overlay) return;

            const titleText = overlay.querySelector(".ssb-overlay__title-text");
            const closeButton = document.getElementById("ssb-overlay-close");
            const helpToggle = document.getElementById("ssb-error-help-toggle");
            const help = document.getElementById("ssb-error-help");
            const deleteModal = document.getElementById("ssb-delete-modal");
            const deleteName = document.getElementById("ssb-delete-name");
            const deleteConfirm = document.getElementById("ssb-delete-confirm");
            const deleteCancel = document.getElementById("ssb-delete-cancel");

            const showOverlay = function (message) {
                status.textContent = message;
                overlay.classList.add("is-visible");
                button.classList.add("disabled");
                button.disabled = true;
                button.setAttribute("aria-disabled", "true");
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
                    addRow("dashicons-open-folder", "Dateien gepackt: " + Number(data.processed_files || 0).toLocaleString("de-DE") + " von " + Number(data.files || 0).toLocaleString("de-DE"), "is-active");
                } else {
                    addRow("dashicons-database", "Schritt 1 von 2: Datenbank wird gesichert ...", "is-active");
                    addRow("dashicons-open-folder", "Schritt 2 von 2: Dateien werden danach gepackt", "");
                }
                status.appendChild(progress);
            };

            const renderFailure = function (data) {
                overlay.classList.add("has-error");
                titleText.textContent = "Backup konnte nicht erstellt werden";
                status.textContent = data.message || "Unbekannter Fehler beim Erstellen des Backups.";
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

            const formatBytes = function (bytes) {
                const units = ["Bytes", "KB", "MB", "GB", "TB"];
                let value = Number(bytes) || 0;
                let unit = 0;
                while (value >= 1024 && unit < units.length - 1) {
                    value /= 1024;
                    unit++;
                }
                return value.toLocaleString("de-DE", { maximumFractionDigits: 1 }) + " " + units[unit];
            };

            const finishAnalysis = function (data) {
                analysisTitle.textContent = "Deine Installation wurde untersucht";
                analysisMessage.textContent = "Die Analyse ist abgeschlossen.";
                analysisStatus.textContent = "";
                analysisProgress.hidden = true;
                analysisSummary.hidden = false;
                analysisFiles.textContent = Number(data.files || 0).toLocaleString("de-DE");
                analysisBytes.textContent = formatBytes(data.bytes);
                analysisZipBytes.textContent = "ca. " + formatBytes(data.estimated_zip_bytes);
                analysisHint.hidden = false;
                analysisClose.hidden = false;
                if (button.getAttribute("data-backup-ready") === "1") {
                    button.disabled = false;
                    button.classList.remove("disabled");
                    button.removeAttribute("aria-disabled");
                }
                analysisClose.focus();
            };

            const failAnalysis = function (message) {
                analysisTitle.textContent = "Dateianalyse nicht möglich";
                analysisMessage.textContent = "Die Installation konnte vor dem Backup nicht vollständig untersucht werden.";
                analysisStatus.textContent = "";
                analysisProgress.hidden = true;
                analysisError.textContent = message;
                analysisError.hidden = false;
                analysisClose.hidden = false;
                analysisClose.focus();
            };

            const pollAnalysis = function (jobId, failures) {
                request("ssbhf_analyze_chunk", { job_id: jobId }).then(function (response) {
                    if (!response.success) throw new Error(response.data || "Status konnte nicht gelesen werden.");
                    const data = response.data;
                    if (data.status === "done") {
                        finishAnalysis(data);
                        return;
                    }
                    if (data.status === "failed") {
                        failAnalysis(data.message || "Unbekannter Fehler bei der Dateianalyse.");
                        return;
                    }
                    analysisStatus.textContent = Number(data.files || 0).toLocaleString("de-DE")
                        + " Dateien geprüft, " + formatBytes(data.bytes || 0) + " erfasst ...";
                    window.setTimeout(function () { pollAnalysis(jobId, 0); }, 1000);
                }).catch(function (error) {
                    const nextFailures = failures + 1;
                    analysisStatus.textContent = "Statusabfrage unterbrochen: " + error.message + ". Die Analyse läuft möglicherweise weiter; erneuter Versuch ...";
                    window.setTimeout(function () {
                        pollAnalysis(jobId, nextFailures);
                    }, Math.min(15000, 2000 * nextFailures));
                });
            };

            if (analysisOverlay) {
                analysisClose.addEventListener("click", function () {
                    analysisOverlay.classList.remove("is-visible");
                    analysisStart.disabled = false;
                    analysisStart.focus();
                });
                analysisStart.addEventListener("click", function () {
                    analysisJobId = null;
                    analysisStart.disabled = true;
                    button.disabled = true;
                    button.setAttribute("aria-disabled", "true");
                    analysisTitle.textContent = "Bitte warten: Deine Installation wird untersucht";
                    analysisMessage.textContent = "Die Dateien deiner Installation werden gezählt und ihre Größe wird ermittelt.";
                    analysisStatus.textContent = "Analyse wird gestartet ...";
                    analysisProgress.hidden = false;
                    analysisSummary.hidden = true;
                    analysisHint.hidden = true;
                    analysisError.hidden = true;
                    analysisError.textContent = "";
                    analysisClose.hidden = true;
                    analysisOverlay.classList.add("is-visible");
                    request("ssbhf_analyze", {}).then(function (response) {
                        if (!response.success) throw new Error(response.data || "Dateianalyse konnte nicht gestartet werden.");
                        analysisJobId = response.data.job_id;
                        pollAnalysis(response.data.job_id, 0);
                    }).catch(function (error) {
                        failAnalysis(error.message);
                    });
                });
            }

            let statusPollFailures = 0;
            const poll = function (jobId) {
                request("ssbhf_backup_chunk", { job_id: jobId }).then(function (response) {
                    if (!response.success) throw new Error(response.data || "Status konnte nicht gelesen werden.");
                    statusPollFailures = 0;
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
                    window.setTimeout(function () { poll(jobId); }, 300);
                }).catch(function (error) {
                    statusPollFailures++;
                    status.textContent = "Die Statusabfrage ist vorübergehend unterbrochen. Das Backup kann im Hintergrund weiterlaufen; wir versuchen es in Kürze erneut. (" + error.message + ")";
                    window.setTimeout(function () { poll(jobId); }, Math.min(15000, 2000 * statusPollFailures));
                });
            };

            button.addEventListener("click", function () {
                if (!analysisJobId) return;
                showOverlay("Backup wird jetzt gestartet ...");
                request("ssbhf_start", { analysis_job_id: analysisJobId }).then(function (response) {
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
 * Queue a one-time notice after plugin activation.
 *
 * @return void
 */
function Ssbhf_Set_Activation_notice(): void
{
    set_transient('ssbhf_activation_notice', 1, 5 * MINUTE_IN_SECONDS);
}

/**
 * Show the requirement checklist after plugin activation.
 *
 * @return void
 */
function Ssbhf_Show_Activation_notice(): void
{
    if (!current_user_can('manage_options') || !get_transient('ssbhf_activation_notice')) {
        return;
    }

    delete_transient('ssbhf_activation_notice');
    $checks = Ssbhf_Get_System_checks();
    $ready = Ssbhf_System_Checks_Passed($checks);
    $panel_id = 'ssbhf-activation-checks';

    echo '<div class="notice ' . ($ready ? 'notice-success' : 'notice-error') . ' ssb-checks-notice">';
    Ssbhf_Render_Checklist_Heading(
        $ready ? 'Suchis Simple Backup ist einsatzbereit.' : 'Suchis Simple Backup benötigt Aufmerksamkeit.',
        $panel_id,
        $ready
    );
    echo '<div id="' . esc_attr($panel_id) . '" class="ssb-checks__content"' . ($ready ? ' hidden' : '') . '>';
    Ssbhf_Render_System_Checklist($checks);
    echo '<p>Weitere Details findest du unter <strong>Werkzeuge &rarr; Suchis Simple Backup</strong>.</p>';
    echo '</div></div>';
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
    $checks = Ssbhf_Get_System_checks();
    $backup_ready = Ssbhf_System_Checks_Passed($checks);

    echo '<div class="wrap ssb-page">';
    echo '<h1>Suchis Simple Backup</h1>';
    $panel_id = 'ssbhf-page-checks';
    echo '<div class="ssb-checks">';
    Ssbhf_Render_Checklist_Heading('Systemprüfung', $panel_id, $backup_ready);
    echo '<div id="' . esc_attr($panel_id) . '" class="ssb-checks__content"' . ($backup_ready ? ' hidden' : '') . '>';
    Ssbhf_Render_System_Checklist($checks);
    echo $backup_ready
        ? '<p class="ssb-checks__hint">Alle Pflichtprüfungen bestanden. Das Backup kann gestartet werden.</p>'
        : '<p class="ssb-checks__hint">Mindestens eine Pflichtprüfung ist fehlgeschlagen. Das Backup ist gesperrt, bis alle Voraussetzungen erfüllt sind.</p>';
    echo '<p class="ssb-checks__hint">Die Dateien werden in kleinen Paketen geprüft und zum ZIP hinzugefügt. WP-Cron ist dafür nicht erforderlich. Die ZIP-Größe ist eine Schätzung ohne Datenbank.</p>';
    echo '</div></div>';

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
    echo '<div class="ssb-intro__actions">';
    echo '<button type="button" id="ssb-analysis-start" class="button"><span class="dashicons dashicons-search" aria-hidden="true"></span> Vorab-Backup prüfen</button>';
    $backup_ready_attribute = $backup_ready ? '1' : '0';
    echo '<button type="button" id="ssb-start-backup" class="button button-primary" data-backup-ready="' . esc_attr($backup_ready_attribute) . '" disabled="disabled" aria-disabled="true"><span class="dashicons dashicons-database-export" aria-hidden="true"></span> Backup jetzt erstellen</button>';
    echo '</div>';
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
    echo '<div id="ssb-analysis-overlay" class="ssb-analysis-overlay" role="dialog" aria-modal="true" aria-labelledby="ssb-analysis-title" aria-describedby="ssb-analysis-message">';
    echo '<div class="ssb-analysis__box"><h2 id="ssb-analysis-title" class="ssb-analysis__title">Bitte warten: Deine Installation wird untersucht</h2><p id="ssb-analysis-message" class="ssb-analysis__message">Die Dateien deiner Installation werden gezählt und ihre Größe wird ermittelt.</p><div id="ssb-analysis-progress" class="ssb-analysis__progress" role="progressbar" aria-label="Dateien werden untersucht"><span class="ssb-analysis__progress-bar"></span></div><p id="ssb-analysis-status" class="ssb-analysis__status" aria-live="polite">Analyse wird gestartet ...</p><dl id="ssb-analysis-summary" class="ssb-analysis__summary" hidden><dt>Zu speichernde Dateien</dt><dd id="ssb-analysis-files"></dd><dt>Unkomprimierte Dateigröße</dt><dd id="ssb-analysis-bytes"></dd><dt>Geschätzte ZIP-Größe</dt><dd id="ssb-analysis-zip-bytes"></dd></dl><p id="ssb-analysis-hint" class="ssb-analysis__hint" hidden>Die ZIP-Größe ist eine Schätzung anhand der Dateitypen. Die Datenbank ist nicht eingerechnet; die tatsächliche Archivgröße kann abweichen.</p><p id="ssb-analysis-error" class="ssb-analysis__error" hidden></p><button type="button" id="ssb-analysis-close" class="button button-primary ssb-analysis__close" hidden>Weiter</button></div></div>';
    echo '<div id="ssb-backup-overlay" class="ssb-overlay" role="status" aria-live="polite" aria-label="Backup wird erstellt">';
    echo '<div class="ssb-overlay__box"><span class="spinner is-active ssb-overlay__spinner" aria-hidden="true"></span><h2 class="ssb-overlay__title"><span class="dashicons dashicons-warning ssb-overlay__warning" aria-hidden="true"></span><span class="ssb-overlay__title-text">Backup wird jetzt erstellt</span><button type="button" id="ssb-error-help-toggle" class="ssb-help-toggle" aria-expanded="false" aria-controls="ssb-error-help" title="Was bedeutet diese Meldung?"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="screen-reader-text">Was bedeutet diese Meldung?</span></button></h2><p class="ssb-overlay__notice">Bitte schließen Sie dieses Fenster nicht. Große Installationen können mehrere Minuten oder länger dauern. Das ZIP wird in kleinen Dateipaketen erstellt; kurze Statusunterbrechungen bedeuten nicht automatisch einen Abbruch.</p><div id="ssb-overlay-status" class="ssb-overlay__text">Bitte warten ...</div><div id="ssb-error-help" class="ssb-help" hidden><p><strong>Was bedeutet das?</strong> Ein Dateipaket konnte nicht gelesen oder zum ZIP hinzugefügt werden.</p><p><strong>Was kannst du tun?</strong> Notiere die Fehlermeldung aus dem Protokoll und prüfe Dateiberechtigungen, verfügbaren Speicherplatz sowie das PHP-Fehlerprotokoll.</p></div><button type="button" id="ssb-overlay-close" class="button ssb-overlay__close">Schließen</button></div></div>';
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

    if (!Ssbhf_System_Checks_Passed(Ssbhf_Get_System_checks())) {
        wp_send_json_error('Die Systemprüfung ist nicht vollständig. Bitte prüfe die Voraussetzungen auf der Plugin-Seite.', 400);
    }

    $job_id = isset($_POST['analysis_job_id'])
        ? sanitize_text_field(wp_unslash($_POST['analysis_job_id']))
        : '';
    $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
    $state_path = $job_id ? Ssbhf_Analysis_state_path($job_id) : '';
    $state = $state_path && is_readable($state_path)
        ? json_decode((string) file_get_contents($state_path), true)
        : false;

    if (!is_array($status) || $status['status'] !== 'done' || $status['task'] !== 'analysis'
        || !is_array($state) || empty($state['manifest_path']) || !is_readable($state['manifest_path'])
    ) {
        wp_send_json_error('Die Dateianalyse fehlt oder ist noch nicht abgeschlossen. Bitte lade die Seite neu.', 400);
    }

    $host = parse_url(home_url(), PHP_URL_HOST);
    $host = $host ? preg_replace('~[^a-zA-Z0-9\._-]~', '-', $host) : 'site';
    $filename = 'wp-backup_files-db_' . $host . '_' . gmdate('Y-m-d_His') . '.zip';
    $state['task'] = 'backup';
    $state['manifest_offset'] = 0;
    $state['processed_files'] = 0;
    $state['zip_path'] = Ssbhf_Backup_path($filename);
    $state['filename'] = $filename;
    $state['tmp_sql'] = trailingslashit(SSBHF_TMP_DIR) . 'database_' . $host . '_' . gmdate('Ymd_His') . '.sql';
    $state['zip_initialized'] = false;
    $state['started_at'] = microtime(true);

    if (@file_put_contents($state_path, wp_json_encode($state), LOCK_EX) === false) {
        wp_send_json_error('Der Fortschritt des Backups konnte nicht gespeichert werden.', 500);
    }

    Ssbhf_Write_job_status(
        $job_id,
        [
            'status'          => 'running',
            'task'            => 'backup',
            'step'            => 'database',
            'message'         => 'Datenbank wird vorbereitet ...',
            'files'           => $state['files'],
            'processed_files' => 0,
            'bytes'           => $state['bytes'],
            'estimated_zip_bytes' => $state['estimated_zip_bytes'],
        ]
    );

    wp_send_json_success(['job_id' => $job_id, 'step' => 'database']);
}

/**
 * Process one bounded ZIP packet requested by the browser.
 *
 * @return void
 */
function Ssbhf_Ajax_backup_chunk(): void
{
    Ssbhf_Check_ajax_access();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
    if (!is_array($status) || empty($status['task']) || $status['task'] !== 'backup') {
        wp_send_json_error('Der Backupauftrag wurde nicht gefunden.', 404);
    }
    if ($status['status'] === 'done' || $status['status'] === 'failed') {
        if ($status['status'] === 'done' && !empty($status['file'])) {
            $status['redirect'] = admin_url(
                'tools.php?page=ssb-simple-backup&ssb_notice=created&file=' . rawurlencode($status['file'])
            );
        }
        wp_send_json_success($status);
    }

    try {
        $result = Ssbhf_Process_backup_chunk($job_id);
    } catch (Throwable $error) {
        $result = new WP_Error('backup_chunk_failed', $error->getMessage());
    }

    if (is_wp_error($result)) {
        $status = [
            'status'  => 'failed',
            'task'    => 'backup',
            'message' => $result->get_error_message(),
        ];
        Ssbhf_Write_job_status($job_id, $status);
    } else {
        $status = $result;
    }

    if ($status['status'] === 'done' && !empty($status['file'])) {
        $status['redirect'] = admin_url(
            'tools.php?page=ssb-simple-backup&ssb_notice=created&file=' . rawurlencode($status['file'])
        );
    }

    wp_send_json_success($status);
}

/**
 * Initialize the archive once, then append one bounded batch from the manifest.
 *
 * @param string $job_id Background job identifier.
 *
 * @return array|WP_Error
 */
function Ssbhf_Process_backup_chunk(string $job_id)
{
    $state_path = Ssbhf_Analysis_state_path($job_id);
    $state = is_readable($state_path)
        ? json_decode((string) file_get_contents($state_path), true)
        : false;
    if (!is_array($state) || empty($state['manifest_path']) || !is_readable($state['manifest_path'])) {
        return new WP_Error('backup_manifest_missing', 'Die temporäre Dateiliste fehlt. Bitte starte das Backup erneut.');
    }

    $status = Ssbhf_Read_job_status($job_id);
    $database_message = is_array($status) && !empty($status['database_message'])
        ? $status['database_message']
        : 'Datenbank wird vorbereitet ...';

    if (empty($state['zip_initialized'])) {
        $db_ok = Ssbhf_Dump_db($state['tmp_sql']);
        $zip = new \ZipArchive();
        $open = $zip->open($state['zip_path'], \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($open !== true) {
            @unlink($state['tmp_sql']);
            return new WP_Error('zip_open_failed', 'ZIP konnte nicht angelegt werden. Rückgabecode: ' . (string) $open . '.');
        }

        if ($db_ok && file_exists($state['tmp_sql']) && !$zip->addFile($state['tmp_sql'], 'database.sql')) {
            $zip->close();
            @unlink($state['tmp_sql']);
            return new WP_Error('zip_database_failed', 'Der Datenbank-Dump konnte nicht zum ZIP hinzugefügt werden.');
        }
        if (!$zip->close()) {
            @unlink($state['tmp_sql']);
            return new WP_Error('zip_close_failed', 'Das ZIP konnte nach dem Datenbank-Dump nicht gespeichert werden.');
        }
        @unlink($state['tmp_sql']);

        $database_message = $db_ok
            ? 'Datenbank-Dump wurde erstellt und zum ZIP hinzugefügt.'
            : 'Die Datenbank konnte nicht gesichert werden; die Dateisicherung wird fortgesetzt.';
        $state['zip_initialized'] = true;
        Ssbhf_Add_job_log($job_id, $database_message, !$db_ok);
    }

    $manifest = @fopen($state['manifest_path'], 'rb');
    if (!$manifest || fseek($manifest, (int) $state['manifest_offset']) !== 0) {
        if (is_resource($manifest)) {
            fclose($manifest);
        }
        return new WP_Error('backup_manifest_read_failed', 'Die temporäre Dateiliste konnte nicht gelesen werden.');
    }

    $zip = new \ZipArchive();
    $open = $zip->open($state['zip_path'], \ZipArchive::CREATE);
    if ($open !== true) {
        fclose($manifest);
        return new WP_Error('zip_reopen_failed', 'Das ZIP konnte für das nächste Dateipaket nicht geöffnet werden.');
    }

    $files_in_chunk = 0;
    $entries_in_chunk = 0;
    $deadline = microtime(true) + 2.0;
    while ($entries_in_chunk < 100 && microtime(true) < $deadline) {
        $line = fgets($manifest);
        if ($line === false) {
            break;
        }
        $entries_in_chunk++;
        $state['manifest_offset'] += strlen($line);
        $entry = json_decode($line, true);
        if (!is_array($entry) || empty($entry['path']) || empty($entry['relative_path']) || !is_file($entry['path'])) {
            continue;
        }
        if (!$zip->addFile($entry['path'], $entry['relative_path'])) {
            $zip->close();
            fclose($manifest);
            return new WP_Error('zip_add_file_failed', 'Eine Datei konnte nicht zum ZIP hinzugefügt werden: ' . basename($entry['path']));
        }
        $files_in_chunk++;
        $state['processed_files']++;
    }

    $manifest_complete = $state['manifest_offset'] >= (int) filesize($state['manifest_path']);
    fclose($manifest);
    if (!$zip->close()) {
        return new WP_Error('zip_chunk_close_failed', 'Das ZIP konnte nach einem Dateipaket nicht gespeichert werden.');
    }

    if ($manifest_complete) {
        $zip_size = is_file($state['zip_path']) ? filesize($state['zip_path']) : false;
        if ($zip_size === false || $zip_size < 100) {
            return new WP_Error('zip_invalid', 'Das fertige ZIP fehlt oder ist ungültig.');
        }

        $duration = microtime(true) - (float) $state['started_at'];
        $done = [
            'status'          => 'done',
            'task'            => 'backup',
            'message'         => 'Backup wurde erstellt. Größe: ' . size_format($zip_size) . '. Gesamtdauer: ' . Ssbhf_Format_duration($duration) . '.',
            'file'            => $state['filename'],
            'files'           => $state['files'],
            'processed_files' => $state['processed_files'],
            'database_message' => $database_message,
        ];
        Ssbhf_Write_job_status($job_id, $done);
        @unlink($state['manifest_path']);
        @unlink($state_path);
        return $done;
    }

    if (@file_put_contents($state_path, wp_json_encode($state), LOCK_EX) === false) {
        return new WP_Error('backup_state_write_failed', 'Der Fortschritt des ZIP-Auftrags konnte nicht gespeichert werden.');
    }
    $progress = [
        'status'           => 'running',
        'task'             => 'backup',
        'step'             => 'files',
        'message'          => 'Dateien werden paketweise zum ZIP hinzugefügt ...',
        'database_message' => $database_message,
        'files'            => $state['files'],
        'processed_files'  => $state['processed_files'],
    ];
    Ssbhf_Write_job_status($job_id, $progress);
    return $progress;
}

/**
 * Start an asynchronous scan of files that would be included in the backup.
 *
 * @return void
 */
function Ssbhf_Ajax_analyze(): void
{
    Ssbhf_Check_ajax_access();
    Ssbhf_Ensure_dirs();

    $job_id = wp_generate_uuid4();
    $state = Ssbhf_Create_analysis_state($job_id);
    if (is_wp_error($state)) {
        wp_send_json_error($state->get_error_message(), 500);
    }

    Ssbhf_Write_job_status(
        $job_id,
        [
            'status'              => 'queued',
            'task'                => 'analysis',
            'message'             => 'Dateien werden zur Größenabschätzung untersucht ...',
            'files'               => 0,
            'bytes'               => 0,
            'estimated_zip_bytes' => 0,
            'created_at'          => time(),
        ]
    );

    wp_send_json_success(['job_id' => $job_id, 'task' => 'analysis']);
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
        'files'                => 0,
        'bytes'                => 0,
        'estimated_zip_bytes'  => 0,
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
function Ssbhf_Process_analysis_chunk(string $job_id)
{
    $state_path = Ssbhf_Analysis_state_path($job_id);
    $state = is_readable($state_path)
        ? json_decode((string) file_get_contents($state_path), true)
        : false;
    if (!is_array($state)) {
        return new WP_Error('analysis_state_missing', 'Der Status der Dateianalyse konnte nicht gelesen werden.');
    }

    $manifest = @fopen($state['manifest_path'], 'ab');
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
            continue;
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
                        fwrite($manifest, $line . "\n");
                        $state['files']++;
                        $state['bytes'] += $size;
                        $state['estimated_zip_bytes'] += (int) round($size * Ssbhf_Estimated_compression_ratio($path));
                    }
                }
            }

            if ($entries_processed >= $max_entries || microtime(true) >= $deadline) {
                $stack[] = $directory;
                break;
            }
        }

        closedir($handle);
    }

    fclose($manifest);
    $state['stack'] = $stack;
    if (@file_put_contents($state_path, wp_json_encode($state), LOCK_EX) === false) {
        return new WP_Error('analysis_state_write_failed', 'Der Fortschritt der Dateianalyse konnte nicht gespeichert werden.');
    }

    $done = empty($stack);
    $status = [
        'status'              => $done ? 'done' : 'running',
        'task'                => 'analysis',
        'step'                => 'analysis',
        'message'             => $done ? 'Dateianalyse abgeschlossen.' : 'Dateien werden untersucht ...',
        'files'               => $state['files'],
        'bytes'               => $state['bytes'],
        'estimated_zip_bytes' => $state['estimated_zip_bytes'],
    ];
    Ssbhf_Write_job_status($job_id, $status);

    return $status;
}

/**
 * Process one analysis packet requested by the browser.
 *
 * @return void
 */
function Ssbhf_Ajax_analyze_chunk(): void
{
    Ssbhf_Check_ajax_access();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $status = $job_id ? Ssbhf_Read_job_status($job_id) : false;
    if (!is_array($status) || empty($status['task']) || $status['task'] !== 'analysis') {
        wp_send_json_error('Die Dateianalyse wurde nicht gefunden.', 404);
    }
    if ($status['status'] === 'done' || $status['status'] === 'failed') {
        wp_send_json_success($status);
    }

    $result = Ssbhf_Process_analysis_chunk($job_id);
    if (is_wp_error($result)) {
        $result = [
            'status'  => 'failed',
            'task'    => 'analysis',
            'message' => $result->get_error_message(),
        ];
        Ssbhf_Write_job_status($job_id, $result);
    }

    wp_send_json_success($result);
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
 * Estimate how much a file shrinks when added to a ZIP archive.
 *
 * @param string $path Absolute file path.
 *
 * @return float Estimated compressed-size ratio.
 */
function Ssbhf_Estimated_compression_ratio(string $path): float
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $already_compressed = ['7z', 'avif', 'br', 'docx', 'gz', 'jpeg', 'jpg', 'm4a', 'm4v', 'mov', 'mp3', 'mp4', 'ogg', 'pdf', 'png', 'webm', 'webp', 'woff', 'woff2', 'xlsx', 'zip'];
    $text_files = ['css', 'csv', 'html', 'js', 'json', 'md', 'php', 'sql', 'svg', 'txt', 'xml'];

    if (in_array($extension, $already_compressed, true)) {
        return 0.97;
    }
    if (in_array($extension, $text_files, true)) {
        return 0.35;
    }

    return 0.65;
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
    if (!$name || substr($name, -4) !== '.zip') {
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
