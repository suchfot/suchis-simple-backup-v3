# Code-Struktur und große Backups (2.1.0)

`suchis-simple-backup.php` enthält Metadaten, Konstanten, Modulimporte und Hooks.
Der Aktivierungs-Hook bleibt an diese Hauptdatei gebunden.

## Module

| Datei unter `includes/` | Zuständigkeit |
| --- | --- |
| `admin-page.php` | Admin-Menü, Berechtigungen und Daten für die Ansicht |
| `admin-assets.php` | Seitenspezifisches CSS/JS und AJAX-/Wiederaufnahme-Konfiguration |
| `admin-notices.php` | Einmaliger Hinweis nach der Aktivierung |
| `system-checks.php` | Voraussetzungen und Checklisten, einschließlich 64-Bit-PHP |
| `ajax.php` | Authentifizierte Endpunkte: Analyse, Start/Fortsetzen, Paket, Verwerfen |
| `analysis.php` | Paketweiser Dateiscan, Dateimanifest und Schätzungen |
| `backup.php` | Auftragssperre, Checkpoints, Speicherplatzprüfung und Koordination |
| `zip-stream.php` | Fortsetzbarer ZIP64-STORE-Schreiber und inkrementelle CRC-Prüfung |
| `database.php` | Paketweiser SQL-Export und SQL-Escaping |
| `jobs.php` | Atomare JSON-Dateien, Jobstatus und Zeitformatierung |
| `files.php` | Verzeichnisse, Dateiliste, Download und Löschen |

`templates/admin-page.php` enthält die Verwaltungsansicht, `templates/dialogs.php`
die Dialoge. Dynamische Werte werden bei der Ausgabe escaped. CSS und JavaScript
liegen unter `assets/`. WordPress stellt dem JavaScript `ssbhfAdmin` bereit.

## ZIP64 statt wiederholtem ZIP-Neuaufbau

Die neue Engine schreibt ein einzelnes ZIP64 im **STORE-Verfahren, ohne zusätzliche
Komprimierung**. Das ist bewusst ein Kompromiss: Der Datenstrom kann mitten in einer
beliebig großen Datei fortgesetzt werden, ohne nicht serialisierbaren Deflate-Zustand
oder einen unbeschränkt langen `ZipArchive::close()`-Schritt. Dafür benötigt das
fertige ZIP ungefähr die unkomprimierte Quelldatenmenge plus SQL und ZIP-Verzeichnis.
Die Anzeige berücksichtigt diesen Unterschied; ZIP64-kompatible Entpacker verwenden.
64-Bit-PHP ist erforderlich.

- Pro Dateipaket höchstens 8 MiB Nutzdaten und 100 abgeschlossene Einträge;
  Lesen/Schreiben erfolgt in Blöcken von höchstens 1 MiB.
- Einzeldateien werden über mehrere AJAX-Aufrufe fortgesetzt. CRC32 wird aus den
  Teilprüfsummen kombiniert, statt alte Dateiteile erneut zu lesen.
- Das zentrale ZIP-Verzeichnis liegt vorübergehend in einer separaten Datei und
  wird zum Schluss paketweise angehängt. Das wachsende Archiv wird nicht pro Paket
  neu komprimiert oder vollständig umkopiert.
- SQL wird in Paketen mit bis zu fünf Abfragen à 100 Zeilen exportiert. Einzelne
  langsame Datenbankabfragen oder sehr große Zeilen können trotzdem Serverlimits
  erreichen; eine Datenbankabfrage selbst ist nicht unterbrechbar.

## Checkpoints, Wiederaufnahme und Sperren

Eine lokale `flock()`-Sperre serialisiert Start, Backup-Pakete und Verwerfen über alle
Browserfenster hinweg. Eine zweite Startanfrage erhält denselben aktiven Auftrag.
Die Dateien müssen auf einem Dateisystem mit zuverlässigem `flock()` und atomarem
`rename()` liegen; verteilte Installationen benötigen entsprechende Shared-Storage-
Garantien oder eine andere Sperrlösung.

Ein Checkpoint enthält unter anderem Manifestposition, laufende Quelldatei,
Dateioffset, CRC, ZIP-Schreibposition und Länge des zentralen Verzeichnisses.
Er wird nach erfolgreichem Flush per temporärer JSON-Datei und `rename()` ersetzt.
Nach einem abgebrochenen Request werden zusätzliche ZIP-/Verzeichnis-/SQL-/Manifest-
Bytes auf die zuletzt gespeicherten Grenzen zurückgeschnitten. Bereits bestätigte
Dateiinhalte werden dabei nicht erneut geschrieben.

`active-backup.json` verweist serverseitig auf den Auftrag. Beim erneuten Öffnen der
Plugin-Seite setzt das JavaScript einen laufenden Auftrag fort. Ohne offenes Fenster
pausiert die Verarbeitung; es gibt keinen unabhängigen Hintergrund-Worker. Bei einem
Fehler bleibt der Auftrag mit seinem letzten Checkpoint erhalten. Nach Behebung im
Dialog schließen und **Backup fortsetzen** wählen. Mit **Unfertigen Auftrag verwerfen**
lassen sich die temporären Daten explizit entfernen; fertige Backups bleiben erhalten.
Nach CRC-Fehlern ist Verwerfen und neu Erstellen sinnvoll, nicht bloß Wiederholen.
Alte Aufträge der vorherigen Engine werden nicht in ZIP64-Checkpoints migriert.

## Veröffentlichung und Prüfung

Das Archiv heißt bis zum Abschluss `<name>.zip.partial`. Es erscheint weder in der
normalen Backupliste noch kann es über den ZIP-Download-Endpunkt geladen werden.
Nach dem Schreiben des ZIP64-Verzeichnisses werden sämtliche gespeicherten Nutzdaten
paketweise erneut gelesen und ihre CRC32-Prüfsummen geprüft. Anschließend prüft
`ZipArchive::CHECKCONS` die Archivstruktur; erwartete und geprüfte Eintragsanzahl
müssen übereinstimmen. Erst dann wird im selben Backup-Verzeichnis nach `.zip`
umbenannt. Ein Crash zwischen Umbenennen und Statusspeicherung ist wiederaufnehmbar.

Alte `.zip`-Dateien werden weiterhin angezeigt: Ihre Vollständigkeit ist rückwirkend
nicht bekannt. Bereits mit einer älteren Version erzeugte Teilarchive nicht als
vollständige Sicherung behandeln; gegebenenfalls manuell entfernen.

## Speicherplatz und Diagnose

Vor dem Dateiexport wird der für STORE benötigte Platz grob vorgeprüft. Vor jedem
weiteren Paket werden freier Platz im Backup- und temporären Verzeichnis sowie
mindestens 32 MiB Reserve geprüft. Das ersetzt keine Speicherplatzreservierung:
Andere Prozesse, Quotas oder eine währenddessen wachsende Datenbank können trotzdem
zu Platzmangel führen. SQL liegt vorübergehend zusätzlich außerhalb des ZIP vor.

`wp-content/uploads/ssb-tmp/diagnostic-<Auftrags-ID>.jsonl` enthält Start/Ende jedes
Pakets, Phase, Quelldatei/Dateiposition, ZIP-Bytes, Paketlimit, Laufzeit, Speicherbedarf,
freien Platz und Fehlermeldungen einschließlich libzip-Codes bei Strukturfehlern.
Fehler werden außerdem ins PHP-Fehlerprotokoll geschrieben. Diagnosejournale bleiben
zur Fehlersuche erhalten und werden nicht automatisch gelöscht.

**Server absichern:** `wp-content/backups` und `wp-content/uploads/ssb-tmp` enthalten
sensible Daten. Das Plugin legt in beiden Ordnern `.htaccess` und `index.html` an.
Bei Nginx, IIS oder deaktivierter `.htaccess`-Auswertung muss der Serverbetreiber den
HTTP-Zugriff auf **beide vollständigen Verzeichnisse** separat sperren.

## Grenzen der Sicherung

Dies ist kein atomarer Dateisystem-/Datenbank-Snapshot. Dateigröße und Änderungszeit
einer laufenden Datei werden zwischen Paketen geprüft; währenddessen veränderte
Dateien führen zum Abbruch. Änderungen mit unveränderter Größe/Änderungszeit sind
damit nicht sicher erkennbar. Symlinks werden beim Scan ausgeschlossen. Dateien und
DB möglichst in einer ruhigen Phase sichern bzw. Schreibzugriffe unterbinden.
Ein erfolgreicher CRC-Test belegt Archivintegrität, nicht die fachliche Konsistenz
einer während des Backups veränderten Website.

## Lokale Prüfungen

```sh
php tests/smoke.php
SSBHF_LARGE_MIB=4097 php tests/smoke.php
find . -name '*.php' -print0 | xargs -0 -n 1 php -l
node --check assets/js/admin.js
```

Der Standalone-Test benötigt PHP mit ZIP-Erweiterung, aber kein WordPress. Standardmäßig
wird eine 19-MiB-Datei verwendet; die zweite Variante testet eine Datei über 4 GiB
und benötigt entsprechend viel Zeit und freien Speicher (mehrere temporäre Archive).
Die Fixture wird am Ende entfernt. Tests umfassen beide Startwege, Wiederaufnahme
mit simulierten unbestätigten ZIP-/Verzeichnisbytes, parallele Auftragssperren,
Reload-Konfiguration, unabhängigen SHA-256-Vergleich des großen ZIP-Eintrags,
CRC-Fehlerverweigerung, Verwerfen und Schutz bestehender fertiger Archive.

Zusätzlich Browserintegration über die gemeinsame Anleitung eine Ebene oberhalb
prüfen. Playground verwendet SQLite und belegt keine MySQL-/MariaDB-Kompatibilität.
Der Release-Workflow packt `includes/`, `templates/` und `assets/` mit ins Plugin-ZIP.
