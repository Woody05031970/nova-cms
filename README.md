# Nova CMS

Modulares, KI-gestütztes Content-Management-System für Laien. Selbst gehostet auf normalem Webspace (z. B. IONOS, Strato), erweiterbar über Plugins.

**Stand:**

- Schritt 1 (Grundgerüst): Web-Installer, Anmeldung, Dashboard mit Website-Gesundheit, Firmenprofil, Plugin-Verwaltung.
- Schritt 2 (Seiten): Seiten aus Vorlagen anlegen (Startseite, Über uns, Kontakt, Impressum, Datenschutz), Block-Editor mit Text (WYSIWYG), Titelbild, Bild, Slider, Video (YouTube/Vimeo erst nach Klick), Knopf und HTML; Entwürfe, Versionen, Papierkorb, Google-Vorschau, öffentliche Website mit Menü und Footer.
- Schritt 3 (KI): freie Wahl des KI-Anbieters (Claude über das offizielle Anthropic-PHP-SDK, ChatGPT, Gemini, Mistral, eigener Ollama-Server oder jeder OpenAI-kompatible Dienst), API-Schlüssel verschlüsselt gespeichert, Verbindungstest, Website-Assistent (schreibt die ersten Seiten als Entwurf), KI im Text-Editor (verbessern, kürzen, ausführlicher, Tonfall, Rechtschreibung, Englisch) und Google-Beschreibung per KI.
- Schritt 4 (Medien): Mediathek mit Suche und Filtern, Hochladen mehrerer Dateien per Drag & Drop, große Fotos werden automatisch auf 2400 Pixel verkleinert (EXIF-Drehung berücksichtigt), WebP-Vorschaubilder, Bildbeschreibungen per KI (Bilderkennung bei Claude, ChatGPT, Gemini), Anzeige „Verwendet auf“ mit Löschschutz, Auswahl aus der Mediathek im Editor (auch mehrere Bilder für Slider). Dateien, die schon in `public/uploads` liegen (z. B. per FTP), werden beim Öffnen der Mediathek übernommen.
- Schritt 5 (SEO): SEO-Check je Seite mit Punktzahl und Tipps in einfacher Sprache (Fokus-Suchbegriff, Titel, Beschreibung, Textmenge, Überschriften, Bildbeschreibungen, interne Links), Live-Prüfung im Editor, KI-Vorschläge für Suchbegriffe, SEO-Übersicht aller Seiten, automatische `sitemap.xml` und `robots.txt`, Open-Graph-Angaben, Firmendaten als schema.org/LocalBusiness, Sperre für Suchmaschinen während des Aufbaus, Wettbewerber-Vergleich mit KI-Empfehlungen (Abruf fremder Seiten nur an öffentliche Adressen, auch nach Weiterleitungen).
- Schritt 6 (Sicherheit): Zwei-Faktor-Anmeldung mit Authenticator-App (QR-Code) und 8 Notfall-Codes, Anmeldesperre nach 5 Fehlversuchen für 15 Minuten, Seite „Mein Konto“ (Name, E-Mail, Passwort), Angriffsschutz (Firewall gegen SQL-Injection, eingeschleuste Skripte, Pfad-Tricks und Schwachstellen-Scanner, automatische Sperre nach 10 Angriffen), IP-Adressen von Hand sperren, Sicherheits-Header, Protokoll (30 Tage, danach automatisch gelöscht), Sicherheits-Check mit Punktzahl (Fehlermodus, HTTPS, PHP-Version, .env-Rechte, Zwei-Faktor aller Admins, Programme im Upload-Ordner, Schadcode-Muster in Plugins und Uploads, veränderte Programmdateien per Fingerabdruck). Der Upload-Ordner bekommt automatisch eine `.htaccess`, die PHP-Dateien sperrt. Als Cronjob beim Hoster: `php artisan nova:security-scan` (Rückgabewert 1 bei Problemen); nach einem Update `--trust`.

Plugins können über den Filter `ai.providers` weitere KI-Anbieter ergänzen (`App\Ai\ProviderCatalog`).

Hochgeladene Bilder und Videos liegen in `public/uploads/` (funktioniert ohne Symlinks auf jedem Webspace).

## Installation auf einem Webspace

1. Alle Dateien per FTP in einen Ordner auf dem Webspace hochladen (inklusive `vendor` und `public/build`).
2. Im Kundenbereich des Hosters das Zielverzeichnis der Domain auf den Unterordner `public` stellen. Geht das nicht, leitet die mitgelieferte `.htaccess` automatisch dorthin.
3. Die Domain im Browser aufrufen. Der Installations-Assistent führt durch vier Schritte: Systemprüfung, Datenbank (SQLite ohne Zugangsdaten oder MySQL), Website-Name und Admin-Konto, fertig.

Voraussetzungen: PHP 8.3 oder neuer mit den Erweiterungen pdo, pdo_sqlite oder pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, zip.

## Entwicklung

```sh
composer install
npm install && npm run build
php artisan serve   # dann http://localhost:8000 öffnen, der Installer startet
php artisan test
```

Zum erneuten Installieren `storage/app/installed.lock` löschen.

## Plugins

Plugins liegen in `plugins/<slug>/` und werden im Admin unter **Plugins** als ZIP hochgeladen, aktiviert, deaktiviert und entfernt, ähnlich wie bei WordPress. Ein vollständiges Beispiel steht in `plugin-examples/oeffnungszeiten/`.

Aufbau:

```
plugin.json              Name, Version, Namensraum, Provider, benötigte Nova-Version
src/                     PHP-Klassen (PSR-4 unter dem Namensraum)
resources/views/         Blade-Vorlagen (optional)
database/migrations/     Datenbank-Änderungen, laufen beim Aktivieren (optional)
```

`plugin.json`:

```json
{
    "slug": "oeffnungszeiten",
    "name": "Öffnungszeiten",
    "version": "1.0.0",
    "requires": "0.1.0",
    "namespace": "NovaPlugins\\Oeffnungszeiten",
    "provider": "NovaPlugins\\Oeffnungszeiten\\OeffnungszeitenServiceProvider"
}
```

Der Provider ist ein normaler Laravel-ServiceProvider. In `boot()` stehen die Erweiterungspunkte über `App\Plugins\Hooks` bereit:

| Methode | Wirkung |
| --- | --- |
| `addMenuItem($label, $url, $icon, $order)` | Menüpunkt in der Admin-Navigation |
| `addDashboardWidget($title, fn () => $html)` | Kachel auf dem Dashboard |
| `addHint($title, $help, fn () => $erledigt, $url, $bereich)` | Eintrag in "Das fehlt noch" und im Gesundheitswert |
| `addAction` / `doAction`, `addFilter` / `applyFilters` | Allgemeine Hooks wie bei WordPress |

Sicherheit: Archive werden auf Pfad-Tricks, Symlinks und Größe geprüft, Namensräume des Kerns sind gesperrt. Wirft ein Plugin beim Start einen Fehler, wird es automatisch deaktiviert und die Website läuft weiter. Signierte Pakete aus dem Marketplace folgen in einem späteren Schritt.

Hinweis: Plugins sollten die vorhandenen Tailwind-Klassen und die Komponenten-Klassen `nova-card`, `nova-btn`, `nova-input` usw. nutzen. Eigene Stile bringt ein Plugin als eigene CSS-Datei mit.
