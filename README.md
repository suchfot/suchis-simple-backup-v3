# Suchis Simple Backup

Ein schlankes und benutzerfreundliches WordPress-Plugin für vollständige Website-Backups.

**Version:** 2.0.0
**Lizenz:** GPLv2 oder später
**Autor:** Christian Suchanek

---

## 📋 Inhaltsverzeichnis

- [Features](#features)
- [Installation](#installation)
- [Anforderungen](#anforderungen)
- [Verwendung](#verwendung)
- [Sicherheit](#sicherheit)
- [Häufig gestellte Fragen](#häufig-gestellte-fragen)
- [Fehlerbehandlung](#fehlerbehandlung)
- [Contributing](#contributing)
- [Lizenz](#lizenz)

---

## ✨ Features

- **Website-Backup:** Packt die Website-Dateien und, sofern der Datenbank-Dump gelingt, die WordPress-Datenbank in eine ZIP-Datei
- **ZIP-Kompression:** Automatische Komprimierung für einfacheren Download und Speicher
- **Lokale Speicherung:** Backups werden in `wp-content/backups/` gespeichert
- **Hintergrundverarbeitung:** Lange Backup-Aufträge werden im Hintergrund verarbeitet, während die Oberfläche den Fortschritt abfragt
- **Fortschritts- und Fehleranzeige:** Zeigt Datenbank- und Dateischritt sowie Diagnosemeldungen bei Fehlern
- **Laufzeitangabe:** Meldet nach erfolgreichem Abschluss die Dauer des Backup-Auftrags
- **Benutzerfreundliches Interface:** Backup-Verwaltung im WordPress-Dashboard mit Fortschrittsanzeige und Löschbestätigung
- **Download-Management:** Backups direkt herunterladen oder löschen
- **Intelligente Ausschlüsse:** Der Backup-Ordner wird automatisch vom Backup ausgeschlossen
- **Sicherheitsmaßnahmen:** .htaccess-Schutz und Nonce-Verifizierung
- **Admin-Only:** Nur Administratoren können Backups verwalten

---

## 🚀 Installation

### Manuelle Installation

1. Lade das Plugin-ZIP herunter: [Neueste Version herunterladen](https://github.com/suchfot/suchis-simple-backup-v3/releases/latest/download/suchis-simple-backup.zip)
2. Entpacke die ZIP-Datei
3. Lade den Ordner `suchis-simple-backup` in dein WordPress `wp-content/plugins/` Verzeichnis hoch
4. Aktiviere das Plugin im WordPress-Dashboard unter **Plugins**

### Installation über WordPress.org

1. Gehe zu **Plugins → Neu hinzufügen** in deinem WordPress-Dashboard
2. Suche nach "Suchis Simple Backup"
3. Klicke auf **Installieren** und dann **Aktivieren**

---

## 📋 Anforderungen

- **WordPress:** 6.0 oder höher
- **PHP:** 8.0 oder höher
- **PHP-Extensions:** `zip` (erforderlich)
- **Speicherplatz:** Mindestens so viel freier Platz wie deine gesamte Website groß ist
- **Schreibberechtigungen:** `wp-content/` Ordner muss schreibbar sein

### Überprüfung der Anforderungen

Gehe nach der Plugin-Aktivierung zu **Tools → Suchis Simple Backup**. Das Plugin überprüft automatisch, ob die zip-Extension verfügbar ist.

---

## 💻 Verwendung

### Zugriff auf das Plugin

1. Melde dich im WordPress-Dashboard als Administrator an
2. Navigiere zu **Tools → Suchis Simple Backup**

### Ein Backup erstellen

1. Klicke auf **Backup jetzt erstellen**.
2. Die Oberfläche zeigt den Fortschritt für Datenbank und Dateien an. Schließe die Seite währenddessen möglichst nicht.
3. Nach Abschluss wird die Backup-Datei in der Liste angezeigt und kann heruntergeladen werden.

Der Auftrag wird über WP-Cron im Hintergrund gestartet. Reagiert WP-Cron nicht, versucht das Plugin je nach Serverkonfiguration eine direkte Hintergrundverarbeitung. Der Status wird währenddessen regelmäßig aktualisiert. Ist WP-Cron oder eine dafür benötigte Loopback-Anfrage blockiert, zeigt das Plugin einen Fehler mit Hinweisen zur Diagnose an.

Der Datenbank-Dump ist best effort: Kann er nicht erstellt werden, kann das ZIP trotzdem mit den Website-Dateien erstellt werden. Prüfe deshalb die angezeigte Statusmeldung, wenn du ein vollständiges Datei- und Datenbank-Backup benötigst.

### Backups verwalten

#### Ansicht und Details

Im Plugin-Interface siehst du eine Tabelle mit allen Backups:

| Spalte | Beschreibung |
|--------|-------------|
| **Datei** | Name der Backup-Datei (Format: `wp-backup_files-db_hostname_YYYY-MM-DD_HHmmss.zip`) |
| **Größe** | Dateigröße in MB/GB |
| **Datum** | Erstellungsdatum und -uhrzeit |
| **Aktionen** | Download oder Löschen |

#### Backup herunterladen

1. Klicke in der Backup-Liste auf **Download**
2. Die ZIP-Datei wird auf deinen Computer heruntergeladen

Bei einem Fehler erscheint eine WordPress-Fehlermeldung statt eines ZIP-Anhangs.
Bei abgelaufenem Sicherheitslink die Verwaltungsseite neu laden. Weitere Details
stehen im PHP-Fehlerprotokoll unter `[Suchis Simple Backup]`. Die Fehleranzeige
wird vom Download nicht global abgeschaltet; Fehler der Dateioperationen werden
gezielt abgefangen und mit ihrer ursprünglichen PHP-Meldung protokolliert.

Eine PHP-8.5-spezifische Ursache des ursprünglich gemeldeten Fehlers ist bislang
nicht belegt; dafür wird die ursprüngliche HTML-Fehlermeldung oder das passende
Serverprotokoll benötigt.

#### Backup löschen

1. Klicke in der Backup-Liste auf **Löschen**
2. Bestätige die Löschung
3. Das Backup wird vom Server entfernt

---

## 🔒 Sicherheit

### Automatische Schutzmaßnahmen

Das Plugin implementiert mehrere Sicherheitsmaßnahmen automatisch:

#### .htaccess Schutz

```apache
# wp-content/backups/.htaccess
Deny from all
```

Verhindert direkten Browser-Zugriff auf den Backup-Ordner (Apache-Server).

#### Directory Listing Schutz

Eine leere `index.html` verhindert Directory Listing.

#### Nonce-Verifizierung

Alle Aktionen (Backup, Download, Löschen) sind durch WordPress-Nonces geschützt.

#### Admin-Only Access

Nur Benutzer mit `manage_options` Capability (normalerweise nur Administratoren) können auf das Plugin zugreifen.

#### SQL-Injection Prävention

Alle Datenbankwerte werden korrekt escaped:
- Tabellen- und Spalten-Namen: Backticks (`)
- Werte: Proper escaping basierend auf Datentyp

---

## ❓ Häufig gestellte Fragen

### F: Wie lange dauert ein Backup?

**A:** Das hängt von der Größe der Datenbank und der Dateien sowie den Ressourcen des Servers ab. Die Oberfläche zeigt den aktuellen Arbeitsschritt an; nach erfolgreichem Abschluss wird die tatsächliche Laufzeit angezeigt.

### F: Können Backups vom Browser heruntergeladen werden?

**A:** Nein, das Plugin schützt den Backup-Ordner durch .htaccess. Backups können nur über das Plugin-Interface heruntergeladen werden.

### F: Kann ich automatische Backups planen?

**A:** Nein. WP-Cron wird vom Plugin nur verwendet, um einen manuell gestarteten Backup-Auftrag im Hintergrund auszuführen. Eine wiederkehrende Backup-Planung bietet das Plugin nicht.

### F: Wo werden Backups gespeichert?

**A:** In `wp-content/backups/` auf dem Server. **WICHTIG:** Sichere diese Backups zusätzlich extern (Cloud, externe Festplatte)!

### F: Kann ich Backups auf Cloud-Speicher sichern?

**A:** Das Plugin speichert lokal. Für Cloud-Backups nutze:
- Manuelle Download + Upload zu Google Drive, Dropbox, etc.
- Zusätz-Plugins wie BackWPup oder UpdraftPlus
- FTP/SFTP-Sync des Backup-Ordners

### F: Was passiert bei Speicherplatzproblemen?

**A:** Das Backup kann fehlschlagen, wenn für ZIP-Datei oder temporären Datenbank-Dump nicht genügend Speicherplatz verfügbar ist. Lösche alte Backups oder schaffe freien Speicherplatz auf dem Server.

---

## 🐛 Fehlerbehandlung

### „ZipArchive fehlt"

**Fehler:**
```
ZipArchive fehlt. Bitte PHP-Extension zip aktivieren.
```

**Ursache:** Die PHP-Extension `zip` ist nicht aktiviert.

**Lösung:**
1. Kontaktiere deinen Hosting-Provider
2. Bitte um Aktivierung der `zip`-Extension
3. Falls nicht möglich, wechsle den Hoster

**Selbst überprüfen:**
```php
phpinfo();
// Suche nach "zip" in der Ausgabe
```

### „ZIP konnte nicht erstellt werden"

**Fehler:**
```
ZIP konnte nicht erstellt werden (Schreibrechte?)
```

**Ursache:** Keine Schreibberechtigungen für `wp-content/backups/`.

**Lösung:**
1. Via FTP: Ändere Dateirechte auf 755 oder 775
2. Via SSH:
```bash
chmod 755 wp-content/backups/
```

### Backup schlägt fehl oder der Fortschritt startet nicht

Wenn WP-Cron oder Loopback-Anfragen deaktiviert bzw. blockiert sind, kann WordPress den Hintergrundauftrag möglicherweise nicht starten. Prüfe unter **Werkzeuge → Website-Zustand**, ob Probleme mit geplanten Aufgaben oder Loopback-Anfragen gemeldet werden. Bei Bedarf kann dein Hosting-Support Serverfirewall und Sicherheitsplugins prüfen.

Bei einem Fehler zeigt der Fortschrittsdialog Status und Diagnosemeldungen an. Der Backup-Ordner und seine Dateien werden zusätzlich im Plugin unter **Werkzeuge → Suchis Simple Backup** verwaltet.

### „ZIP wurde nicht korrekt geschrieben"

**Fehler:**
```
ZIP wurde nicht korrekt geschrieben (Datei fehlt/zu klein).
```

**Ursache:** Backup-Prozess wurde unterbrochen oder ist zu klein.

**Lösung:**
1. Erhöhe PHP Timeout und Memory Limit in `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
set_time_limit(300); // 5 Minuten
```
2. Versuche es erneut
3. Bei großen Sites: Kontaktiere deinen Hosting-Provider

### Backup dauert sehr lange / wird unterbrochen

**Ursachen:**
- Website ist zu groß
- Server-Ressourcen begrenzt
- Server-Ressourcen begrenzt

**Lösungen:**
```php
// In wp-config.php; nur nach Rücksprache mit dem Hosting-Anbieter anpassen:
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

Das Plugin versucht, das PHP-Ausführungslimit für die Backup-Erstellung aufzuheben. Hosting-Limits können diese Einstellung jedoch übersteuern.

---

## 🤝 Contributing

Beiträge sind willkommen! Bitte beachte:

1. **Fork** das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Commit deine Änderungen (`git commit -m 'Add some AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

### Code-Standards

- PHP-Code: WordPress Coding Standards
- Dokumentation: PHPDoc für alle Funktionen
- Test: Funktionalität vor Pull Request testen

### Release erstellen

Nach dem Push der Änderungen auf den Standard-Branch erstellt ein Versions-Tag automatisch ein GitHub Release mit installierbarem Plugin-ZIP:

```bash
git tag v2.0.0
git push origin v2.0.0
```

---

## 📝 Lizenz

Dieses Plugin ist unter der **GPLv2 oder später** Lizenz lizenziert.

```
Suchis Simple Backup – Ein WordPress Backup Plugin
Copyright (C) 2024 Christian Suchanek

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

Vollständige Lizenz: [GPL 2.0](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 📞 Support und Kontakt

**Autor:** Christian Suchanek
**Website:** [www.derperformer.com](https://www.derperformer.com)
**E-Mail:** [christian.suchanek@gmail.com](mailto:christian.suchanek@gmail.com)
**GitHub:** [suchfot/suchis-simple-backup-v3](https://github.com/suchfot/suchis-simple-backup-v3)

---

---

## 📋 Changelog

### Version 2.0.0

- ✅ Backup-Aufträge werden im Hintergrund verarbeitet und der Fortschritt wird in der Admin-Oberfläche angezeigt
- ✅ Datenbank- und Dateischritt werden getrennt dargestellt
- ✅ Diagnoseprotokoll und konkretere Fehlermeldungen für fehlgeschlagene Backup-Aufträge
- ✅ Backup-Laufzeit wird nach erfolgreichem Abschluss angezeigt
- ✅ Backup-Verwaltung mit Download und bestätigtem Löschen überarbeitet

### Version 1.2.3

- ✅ Code-Refactoring für WordPress-Standards
- ✅ Verbesserte PHPDoc-Dokumentation
- ✅ Translatable Strings hinzugefügt
- ✅ Sicherheitsverbesserungen

### Version 1.2.0

- ✅ Erstrelease auf GitHub
- ✅ Benutzerfreundliche Admin-Interface
- ✅ Database Dump mit SQL Export

---

## ⚖️ Haftungsausschluss

Dieses Plugin wird "AS IS" bereitgestellt. Der Autor übernimmt keine Haftung für:
- Datenverlust
- Backup-Fehler
- Kompatibilitätsprobleme

**Wichtig:** Teste deine Backups regelmäßig und speichere sie an mehreren Orten!

---

**Zuletzt aktualisiert:** 30. Oktober 2026
