<?php
/**
 * Analysis, backup progress and delete confirmation dialogs.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="ssb-analysis-overlay" class="ssb-analysis-overlay" role="dialog" aria-modal="true" aria-labelledby="ssb-analysis-title" aria-describedby="ssb-analysis-message">
    <div class="ssb-analysis__box">
        <h2 id="ssb-analysis-title" class="ssb-analysis__title">Bitte warten: Deine Installation wird untersucht</h2>
        <p id="ssb-analysis-message" class="ssb-analysis__message">Die Dateien deiner Installation werden gezählt und ihre Größe wird ermittelt.</p>
        <div id="ssb-analysis-progress" class="ssb-analysis__progress" role="progressbar" aria-label="Dateien werden untersucht">
            <span class="ssb-analysis__progress-bar"></span>
        </div>
        <p id="ssb-analysis-status" class="ssb-analysis__status" aria-live="polite">Analyse wird gestartet ...</p>
        <dl id="ssb-analysis-summary" class="ssb-analysis__summary" hidden>
            <dt>Zu speichernde Dateien</dt><dd id="ssb-analysis-files"></dd>
            <dt>Unkomprimierte Dateigröße</dt><dd id="ssb-analysis-bytes"></dd>
            <dt>ZIP-Größe ohne Komprimierung</dt><dd id="ssb-analysis-zip-bytes"></dd>
            <dt>Geschätzter Zeitbedarf</dt><dd id="ssb-analysis-time"></dd>
        </dl>
        <p id="ssb-analysis-hint" class="ssb-analysis__hint" hidden>Das ZIP64 wird ohne zusätzliche Komprimierung geschrieben. Größenangabe ohne Datenbank und ZIP-Verzeichnis; Zeitbedarf nur eine grobe Schätzung.</p>
        <p id="ssb-analysis-error" class="ssb-analysis__error" hidden></p>
        <button type="button" id="ssb-analysis-close" class="button button-primary ssb-analysis__close" hidden>Weiter</button>
    </div>
</div>

<div id="ssb-backup-overlay" class="ssb-overlay" role="status" aria-live="polite" aria-label="Backup wird erstellt">
    <div class="ssb-overlay__box">
        <span class="spinner is-active ssb-overlay__spinner" aria-hidden="true"></span>
        <h2 class="ssb-overlay__title">
            <span class="dashicons dashicons-warning ssb-overlay__warning" aria-hidden="true"></span>
            <span class="ssb-overlay__title-text">Backup wird jetzt erstellt</span>
            <button type="button" id="ssb-error-help-toggle" class="ssb-help-toggle" aria-expanded="false" aria-controls="ssb-error-help" title="Was bedeutet diese Meldung?">
                <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                <span class="screen-reader-text">Was bedeutet diese Meldung?</span>
            </button>
        </h2>
        <p class="ssb-overlay__notice">Das ZIP64 wird in kleinen Byte-Paketen geschrieben und anschließend vollständig auf CRC-Fehler geprüft. Große Installationen können länger dauern. Ohne geöffnetes Fenster pausiert der Auftrag; beim erneuten Öffnen dieser Seite wird er fortgesetzt. Unfertige Archive bleiben als .partial verborgen.</p>
        <div id="ssb-overlay-status" class="ssb-overlay__text">Bitte warten ...</div>
        <div id="ssb-error-help" class="ssb-help" hidden>
            <p><strong>Was bedeutet das?</strong> Ein Arbeitspaket konnte nicht abgeschlossen werden. Der letzte gespeicherte Checkpoint bleibt erhalten; unfertige Archive werden nicht freigegeben.</p>
            <p><strong>Was kannst du tun?</strong> Prüfe freien Speicherplatz, Dateiberechtigungen und das PHP-Fehlerprotokoll. Details stehen in wp-content/uploads/ssb-tmp/diagnostic-&lt;Auftrags-ID&gt;.jsonl. Nach Behebung den Dialog schließen und „Backup fortsetzen“ wählen.</p>
        </div>
        <button type="button" id="ssb-overlay-close" class="button ssb-overlay__close">Schließen</button>
        <button type="button" id="ssb-backup-discard" class="button" hidden>Unfertigen Auftrag verwerfen</button>
    </div>
</div>

<div id="ssb-delete-modal" class="ssb-delete-modal" role="dialog" aria-modal="true" aria-labelledby="ssb-delete-title">
    <div class="ssb-delete-modal__box">
        <span class="dashicons dashicons-warning ssb-delete-modal__icon" aria-hidden="true"></span>
        <h2 id="ssb-delete-title" class="ssb-delete-modal__title">Backup wirklich löschen?</h2>
        <p class="ssb-delete-modal__text">Diese Datei wird dauerhaft vom Server entfernt:</p>
        <code id="ssb-delete-name" class="ssb-delete-modal__file"></code>
        <div class="ssb-delete-modal__actions">
            <button type="button" id="ssb-delete-cancel" class="button">Abbrechen</button>
            <a id="ssb-delete-confirm" class="button button-primary ssb-delete-modal__confirm" href="#">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span> Löschen
            </a>
        </div>
    </div>
</div>
