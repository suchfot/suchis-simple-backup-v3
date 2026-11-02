<?php
/**
 * Backup management view. Variables are prepared by Ssbhf_Render_page().
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ssb-page">
    <h1>Suchis Simple Backup</h1>
    <div class="ssb-checks">
        <?php Ssbhf_Render_Checklist_Heading('Systemprüfung', $panel_id, $backup_ready); ?>
        <div id="<?php echo esc_attr($panel_id); ?>" class="ssb-checks__content"<?php echo $backup_ready ? ' hidden' : ''; ?>>
            <?php Ssbhf_Render_System_Checklist($checks); ?>
            <p class="ssb-checks__hint">
                <?php echo $backup_ready
                    ? 'Alle Pflichtprüfungen bestanden. Das Backup kann gestartet werden.'
                    : 'Mindestens eine Pflichtprüfung ist fehlgeschlagen. Das Backup ist gesperrt, bis alle Voraussetzungen erfüllt sind.'; ?>
            </p>
            <p class="ssb-checks__hint">ZIP64 ohne zusätzliche Komprimierung: Auch große Einzeldateien werden in kleinen Byte-Paketen geschrieben. Benötigt 64-Bit-PHP und ungefähr die unkomprimierte Dateigröße plus Datenbank und Reserve als freien Speicher. Unfertige .partial-Dateien werden nicht als Backups angezeigt. Reload setzt den gespeicherten Auftrag fort; ohne geöffnetes Fenster pausiert er.</p>
        </div>
    </div>

    <?php if ($notice === 'created' && $file) : ?>
        <?php $download_url = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_download&file=' . rawurlencode($file)), 'ssbhf_download'); ?>
        <div class="notice notice-success">
            <p>Backup erstellt: <code><?php echo esc_html($file); ?></code> &nbsp;
                <a class="button button-small" href="<?php echo esc_url($download_url); ?>">Download</a>
            </p>
        </div>
    <?php elseif ($notice === 'failed') : ?>
        <div class="notice notice-error"><p><?php echo esc_html($message); ?></p></div>
    <?php elseif ($notice === 'deleted') : ?>
        <div class="notice notice-success"><p>Backup gelöscht.</p></div>
    <?php endif; ?>

    <div class="ssb-intro">
        <div>
            <h2>Website sichern</h2>
            <p>Erstellt ein geprüftes ZIP64-Backup aus Dateien und Datenbank (ohne Komprimierung).</p>
        </div>
        <div class="ssb-intro__actions">
            <button type="button" id="ssb-analysis-start" class="button">
                <span class="dashicons dashicons-search" aria-hidden="true"></span> Vorab-Backup prüfen
            </button>
            <button type="button" id="ssb-start-backup" class="button button-primary"<?php echo $backup_ready ? '' : ' disabled="disabled" aria-disabled="true"'; ?>>
                <span class="dashicons dashicons-database-export" aria-hidden="true"></span> Backup jetzt erstellen
            </button>
        </div>
    </div>
    <p class="ssb-info">Backups liegen in <code>wp-content/backups</code>. Dieser Ordner wird beim ZIP-Erstellen automatisch <strong>ausgeschlossen</strong>, damit sich Backups nicht selbst aufblasen.</p>

    <h2 class="ssb-section-title">Vorhandene Backups</h2>
    <?php if (!$files) : ?>
        <p class="ssb-empty"><em>Noch keine Backups vorhanden.</em></p>
    <?php else : ?>
        <table class="widefat striped ssb-table">
            <thead>
                <tr><th>Datei</th><th>Größe</th><th>Datum</th><th>Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($files as $backup) : ?>
                    <?php
                    $download_url = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_download&file=' . rawurlencode($backup['name'])), 'ssbhf_download');
                    $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ssbhf_delete&file=' . rawurlencode($backup['name'])), 'ssbhf_delete');
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($backup['name']); ?></code></td>
                        <td><?php echo esc_html(size_format($backup['size'])); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $backup['mtime'])); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($download_url); ?>">
                                <span class="dashicons dashicons-download" aria-hidden="true"></span> Download
                            </a>
                            <a class="button button-small ssb-delete-backup" href="<?php echo esc_url($delete_url); ?>" data-file="<?php echo esc_attr($backup['name']); ?>">
                                <span class="dashicons dashicons-trash" aria-hidden="true"></span> Löschen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="ssb-footer">Author: Christian Suchanek · <a href="mailto:christian.suchanek@gmail.com">christian.suchanek@gmail.com</a></p>
    <?php require SSBHF_PLUGIN_DIR . 'templates/dialogs.php'; ?>
</div>
