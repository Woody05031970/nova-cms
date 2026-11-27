# Nova CMS

Modulares, KI-gestütztes Content-Management-System für Laien. Selbst gehostet auf normalem Webspace (z. B. IONOS, Strato), erweiterbar über Plugins.

**Stand:**

- Schritt 1 (Grundgerüst): Web-Installer, Anmeldung, Dashboard mit Website-Gesundheit, Firmenprofil, Plugin-Verwaltung.
- Schritt 2 (Seiten): Seiten aus Vorlagen anlegen (Startseite, Über uns, Kontakt, Impressum, Datenschutz), Block-Editor mit Text (WYSIWYG), Titelbild, Bild, Slider, Video (YouTube/Vimeo erst nach Klick), Knopf und HTML; Entwürfe, Versionen, Papierkorb, Google-Vorschau, öffentliche Website mit Menü und Footer.
- Schritt 3 (KI): freie Wahl des KI-Anbieters (Claude über das offizielle Anthropic-PHP-SDK, ChatGPT, Gemini, Mistral, eigener Ollama-Server oder jeder OpenAI-kompatible Dienst), API-Schlüssel verschlüsselt gespeichert, Verbindungstest, Website-Assistent (schreibt die ersten Seiten als Entwurf), KI im Text-Editor (verbessern, kürzen, ausführlicher, Tonfall, Rechtschreibung, Englisch) und Google-Beschreibung per KI.
- Schritt 4 (Medien): Mediathek mit Suche und Filtern, Hochladen mehrerer Dateien per Drag & Drop, große Fotos werden automatisch auf 2400 Pixel verkleinert (EXIF-Drehung berücksichtigt), WebP-Vorschaubilder, Bildbeschreibungen per KI (Bilderkennung bei Claude, ChatGPT, Gemini), Anzeige „Verwendet auf“ mit Löschschutz, Auswahl aus der Mediathek im Editor (auch mehrere Bilder für Slider). Dateien, die schon in `public/uploads` liegen (z. B. per FTP), werden beim Öffnen der Mediathek übernommen.
- Schritt 5 (SEO): SEO-Check je Seite mit Punktzahl und Tipps in einfacher Sprache (Fokus-Suchbegriff, Titel, Beschreibung, Textmenge, Überschriften, Bildbeschreibungen, interne Links), Live-Prüfung im Editor, KI-Vorschläge für Suchbegriffe, SEO-Übersicht aller Seiten, automatische `sitemap.xml` und `robots.txt`, Open-Graph-Angaben, Firmendaten als schema.org/LocalBusiness, Sperre für Suchmaschinen während des Aufbaus, Wettbewerber-Vergleich mit KI-Empfehlungen (Abruf fremder Seiten nur an öffentliche Adressen, auch nach Weiterleitungen).
- Schritt 6 (Sicherheit): Zwei-Faktor-Anmeldung mit Authenticator-App (QR-Code) und 8 Notfall-Codes, Anmeldesperre nach 5 Fehlversuchen für 15 Minuten, Seite „Mein Konto“ (Name, E-Mail, Passwort), Angriffsschutz (Firewall gegen SQL-Injection, eingeschleuste Skripte, Pfad-Tricks und Schwachstellen-Scanner, automatische Sperre nach 10 Angriffen), IP-Adressen von Hand sperren, Sicherheits-Header, Protokoll (30 Tage, danach automatisch gelöscht), Sicherheits-Check mit Punktzahl (Fehlermodus, HTTPS, PHP-Version, .env-Rechte, Zwei-Faktor aller Admins, Programme im Upload-Ordner, Schadcode-Muster in Plugins und Uploads, veränderte Programmdateien per Fingerabdruck). Der Upload-Ordner bekommt automatisch eine `.htaccess`, die PHP-Dateien sperrt. Als Cronjob beim Hoster: `php artisan nova:security-scan` (Rückgabewert 1 bei Problemen); nach einem Update `--trust`.
- Schritt 7 (Shop): Produkte mit Bildern, Statt-Preis, Lagerbestand, Grundpreis nach PAngV, Lieferzeit und Produkttexten per KI; Warenkorb, Kasse nach deutschem Recht (Preise inkl. MwSt. mit Hinweis auf Versandkosten, Übersicht direkt vor dem Knopf „Zahlungspflichtig bestellen“, AGB-Bestätigung, Lieferländer), Steuersätze für DE/AT/CH/LU/LI, anteilige Steuer auf Versandkosten, Kleinunternehmer-Regelung, versandkostenfrei ab Betrag. Zahlungsarten Vorkasse, Rechnung, PayPal (Orders API v2) und Stripe Checkout (Kreditkarte, Apple Pay, Google Pay); Schlüssel verschlüsselt gespeichert, Zahlungen werden beim Anbieter gegengeprüft. Bestellbestätigung per E-Mail mit Widerrufsbelehrung und AGB, Benachrichtigung an den Händler, Versandmeldung mit Sendungsnummer, druckbare Rechnung, Storno mit Rückbuchung des Bestands. Vorlagen für AGB, Widerrufsbelehrung (gesetzliches Muster mit Formular) und Versand und Zahlung. E-Mail-Versand über das Postfach des Hosters (SMTP) unter Einstellungen, mit Test-E-Mail.

- Schritt 8 (Lizenzen und Marktplatz): Lizenzschlüssel `NOVA-XXXX-XXXX-XXXX-XXXX` mit Prüfsumme gegen Tippfehler, 30 Tage Testzeitraum, danach ist nur die Verwaltung gesperrt (die Website bleibt immer online). Der Lizenzserver unterschreibt jede Antwort mit Ed25519, Installationen prüfen mit dem öffentlichen Schlüssel; manipulierte oder auf eine andere Installation kopierte Lizenzdaten werden erkannt. Bestätigung höchstens einmal täglich, ohne Verbindung gilt eine Lizenz noch bis zu 21 Tage. Begrenzte Anzahl Websites je Lizenz (Testumgebungen wie `localhost`, `*.test`, `staging.*` zählen nicht), Freigabe beim Umzug. Modul-Marktplatz im Admin unter Plugins › Marktplatz: Katalog unterschrieben, jede Datei per SHA-256 geprüft, Ein-Klick-Installation und -Aktualisierung; kostenpflichtige Module (`"license": "paid"` in `plugin.json`) lassen sich nur mit passender Lizenz aktivieren. Lizenzserver-Verwaltung (Lizenzen ausgeben, sperren, Websites lösen, Module hochladen, Preis und Veröffentlichung). Verkauf über den eigenen Shop: beim Produkt „Lizenz beim Kauf“ einstellen, nach Zahlungseingang wird die Lizenz erzeugt und per E-Mail verschickt.
- Version 0.2 (Backoffice): neues Design mit dunkler Seitenleiste in Gruppen (Inhalte, Wachstum, System), Kopfleiste mit Pfad und **Schnellsuche (Strg+K)** über Bereiche, Seiten, Produkte, Bestellungen und Medien. **Menüverwaltung** unter Inhalte › Menü: Haupt- und Unterpunkte per Ziehen und Ablegen (oder mit Pfeiltasten ein- und ausrücken), eigene Links, Shop-Position, eigene Menütexte, ausblenden, neuer Tab, Vorschau der Navigation; auf der Website als Aufklappmenü, auf dem Handy eingerückt. **Text-Editor** mit Absatzformaten, Durchstreichen, Textmarker, Ausrichtung, Tabellen, Bildern aus der Mediathek, Trennlinie, Formatierung entfernen und **HTML-Quelltext-Ansicht**; Blöcke per Ziehen anordnen; **Live-Vorschau** neben dem Editor (Computer und Handy), zeigt Änderungen schon vor dem Speichern. Die Adresse neuer Seiten folgt dem Titel, bis die Seite online ist.
- Version 0.3 (Verkaufswebsite): neue Bausteine **Vorteile** (Kacheln, Schritte, Kennzahlen), **Preise** (Pakete, auf Wunsch mit Shop-Produkt), **Module** (Filter im Kern/kostenlos/kostenpflichtig, Baukasten mit Summe), **Vergleich**, **Häufige Fragen** (mit FAQ-Daten für Google), **Aufruf** und **Kontaktformular** (Spam-Schutz, Anfragen unter Inhalte › Anfragen und per E-Mail). Titelbild mit Variante „Technik“. Startpaket **Verkaufswebsite für Nova CMS** im Installer oder später unter Einstellungen › Startpaket: 8 Seiten, Menü, Rechtstext-Vorlagen sowie Shop-Produkte für Lizenzen (mit Lizenzangaben) und vorgeschlagene Module (ausgeblendet, bis sie im Marktplatz bereitstehen). Updates spielen Datenbank-Änderungen beim ersten Aufruf automatisch ein.
- Version 0.4 (Design und Marketing): Bereich **Design** mit sechs Vorlagen (Klar, Aurora, Studio, Natur, Ozean, Nacht), eigener Haupt- und Akzentfarbe, Schrift, Ecken, Kopf- und Fußzeile, Einblend-Effekten, Logo-Upload und Live-Vorschau. Titelbild mit **Hintergrundvideo** und durchscheinendem Farbschleier. **Newsletter** mit Double-Opt-in, Abonnentenliste, CSV-Export, Test-E-Mail und Versand in Schüben. **WhatsApp**-Knopf und Produktanfrage per wa.me-Link (ohne fremde Skripte). Vier neue Seitenvorlagen. Freischaltung nach Lizenz: Hersteller-Installation, Entwicklerversion und Testphase haben alles, gekaufte Lizenzen die enthaltenen Module (Start: Kern, Business: Design Pro und WhatsApp, Agentur: alles).
- Version 0.5 (Barrierefreiheit und SEO): Website, Shop und Verwaltung nach WCAG 2.1 AA (Sprunglink, sichtbarer Fokus, Tastatur- und Screenreader-Bedienung, Fehler am Feld, Kontrastprüfung). **Barrierefreiheits-Check** unter System mit Erklärung zur Barrierefreiheit nach BFSG per Klick. Neues **Menü für Handy und Tablet** mit aufklappbaren Unterpunkten. **Mitbewerber-Analyse** (Menü, Seiten, Funktionen, Begriffe von bis zu 5 Websites) mit **SEO-Helfer**, der Maßnahmen nach Wirkung sortiert. **Weiterleitungen** (automatisch beim Umbenennen) und Protokoll nicht gefundener Seiten, **Suche** auf der Website, **Wartungsmodus**.

Für Warenwirtschaft und eigene Zahlungsarten gibt es Hooks: Aktionen `shop.order.placed`, `shop.order.paid`, `shop.order.updated` und der Filter `shop.payment_methods` (Klassen, die `App\Shop\Payments\PaymentMethod` umsetzen).

Plugins können über den Filter `ai.providers` weitere KI-Anbieter ergänzen (`App\Ai\ProviderCatalog`).

Hochgeladene Bilder und Videos liegen in `public/uploads/` (funktioniert ohne Symlinks auf jedem Webspace).

## Installation auf einem Webspace

Wichtig: Hochgeladen wird das **Installationspaket** `nova-cms-<version>.zip`, nicht der Quellcode von GitHub. Nur im Paket ist der Ordner `vendor` mit den Programmbibliotheken enthalten. Das Paket baut `scripts/release.sh` oder auf GitHub der Workflow „Installationspaket“ (Actions › Run workflow, oder automatisch bei einem Tag `v…`).

1. Das ZIP auf dem eigenen Computer entpacken und den **Inhalt** des Ordners `nova-cms` per FTP in einen Ordner auf dem Webspace hochladen. Im FTP-Programm „versteckte Dateien anzeigen“ einschalten, damit auch `.htaccess` und `.env.example` übertragen werden (Nova CMS kommt zur Not auch ohne sie aus).
2. Im Kundenbereich des Hosters das Zielverzeichnis der Domain auf den Unterordner `public` stellen. Geht das nicht, leitet die mitgelieferte `.htaccess` automatisch dorthin.
3. PHP-Version beim Hoster auf 8.3 oder neuer stellen.
4. Die Domain im Browser aufrufen. Der Installations-Assistent führt durch vier Schritte: Systemprüfung, Datenbank (SQLite ohne Zugangsdaten oder MySQL), Website-Name und Admin-Konto, fertig.

Fehlt etwas (zu alte PHP-Version, kein `vendor`, Ordner ohne Schreibrechte, fehlende PHP-Erweiterungen), zeigt `public/preflight.php` vor dem Start eine Seite mit dem Grund und der Lösung statt „HTTP Error 500“. Fehlende Ordner unter `storage` und die `.htaccess` in `public` legt sie selbst an.

Voraussetzungen: PHP 8.3 oder neuer mit den Erweiterungen pdo, pdo_sqlite oder pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, zip, sodium.

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

Sicherheit: Archive werden auf Pfad-Tricks, Symlinks und Größe geprüft, Namensräume des Kerns sind gesperrt. Wirft ein Plugin beim Start einen Fehler, wird es automatisch deaktiviert und die Website läuft weiter. Pakete aus dem Marktplatz werden zusätzlich gegen die unterschriebene Prüfsumme geprüft.

Hinweis: Plugins sollten die vorhandenen Tailwind-Klassen und die Komponenten-Klassen `nova-card`, `nova-btn`, `nova-input` usw. nutzen. Eigene Stile bringt ein Plugin als eigene CSS-Datei mit.

## Lizenzserver einrichten (für den Hersteller)

Der Lizenzserver ist eine normale Nova-CMS-Installation mit eingeschaltetem Shop. Er braucht einen geheimen Schlüssel, die ausgelieferte Kaufversion nur den öffentlichen.

1. Schlüsselpaar erzeugen: `php artisan nova:license-keys`
2. Auf dem Lizenzserver in die `.env`: `NOVA_LICENSE_PRIVATE_KEY=…` und `NOVA_LICENSE_PUBLIC_KEY=…`. Danach erscheint im Admin der Menüpunkt **Lizenzserver**, und die Schnittstelle `https://<server>/api/nova/…` ist aktiv (`aktivieren`, `freigeben`, `marktplatz`, `marktplatz/<slug>`).
3. In die Kaufversion, die Kunden bekommen: Datei `nova-license.pub` im Hauptordner mit dem öffentlichen Schlüssel, und die Adresse des Servers in `config/nova.php` (`license.server`) oder per `NOVA_LICENSE_SERVER`.
4. Module als ZIP unter Lizenzserver › Module hochladen, Preis und Kategorie setzen, veröffentlichen.
5. Im Shop ein Produkt anlegen und unter „Lizenz beim Kauf“ Grundlizenz, Module, Anzahl Websites und Update-Zeitraum wählen.

Ohne öffentlichen Schlüssel läuft Nova CMS als Entwicklerversion ohne Lizenzprüfung. Weitere Einstellungen in `config/nova.php` unter `license`: `trial_days`, `lock_after_trial`, `offline_grace_days`. Achtung: Ein neues Schlüsselpaar macht alle bisherigen Bestätigungen ungültig; Kunden müssen dann einmal „Jetzt prüfen“ klicken.
