<?php

namespace App\Support\Starter;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Blocks\BlockSanitizer;
use App\Support\Pages\PageTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Startinhalt „Verkaufswebsite für Nova CMS“: eine fertige Website mit Startseite,
 * Funktionen, Modulen, Preisen, Vergleich, Fragen, Testversion und Kontakt, dazu
 * Shop-Produkte für Lizenzen und Module. Alles lässt sich danach im Editor ändern.
 *
 * Preise und kostenpflichtige Module sind Vorschläge: Module, die es noch nicht gibt,
 * werden als ausgeblendete Produkte angelegt und auf der Website als „Bald verfügbar“ gezeigt.
 */
class NovaSalesSite
{
    public const SETTING = 'starter_nova_sales';

    /** Seiten in Menü-Reihenfolge: Schlüssel => [Titel, gewünschte Adresse] */
    private const PAGES = [
        'start' => ['Nova CMS', 'start'],
        'funktionen' => ['Funktionen', 'funktionen'],
        'module' => ['Module', 'module'],
        'vergleich' => ['Vergleich', 'vergleich'],
        'preise' => ['Preise', 'preise'],
        'fragen' => ['Häufige Fragen', 'fragen'],
        'testen' => ['Kostenlos testen', 'testen'],
        'kontakt' => ['Kontakt', 'kontakt'],
    ];

    /** Lizenzpakete: Adresse => Angaben */
    public const LICENSES = [
        'nova-cms-start' => ['Nova CMS Start', 14900, 1, 'Für eine Website, z. B. Ihr Unternehmen oder Ihren Verein.'],
        'nova-cms-business' => ['Nova CMS Business', 34900, 3, 'Für bis zu drei Websites, z. B. Firma, Shop und Kampagnenseite.'],
        'nova-cms-agentur' => ['Nova CMS Agentur', 89900, 15, 'Für Webdesigner und Agenturen mit bis zu 15 Kunden-Websites.'],
    ];

    /** Vorgeschlagene kostenpflichtige Module: Adresse => [Name, Preis in Cent, Bereich, Beschreibung] */
    public const PAID_MODULES = [
        'modul-terminbuchung' => ['Terminbuchung', 7900, 'Service', 'Kunden buchen freie Termine direkt auf Ihrer Website, mit Bestätigung und Erinnerung per E-Mail.'],
        'modul-newsletter' => ['Newsletter', 6900, 'Marketing', 'Anmeldung mit Double-Opt-in, Vorlagen im Nova-Design und Versand über Ihren eigenen E-Mail-Server.'],
        'modul-mehrsprachigkeit' => ['Mehrsprachigkeit', 9900, 'Inhalte', 'Jede Seite in mehreren Sprachen, mit Sprachumschalter und KI-Übersetzungsvorschlag.'],
        'modul-gutscheine' => ['Gutscheine und Rabatte', 4900, 'Shop', 'Rabattcodes, Geschenkgutscheine und zeitlich begrenzte Aktionen für Ihren Shop.'],
        'modul-mitgliederbereich' => ['Mitgliederbereich', 8900, 'Inhalte', 'Geschützte Seiten und Downloads für angemeldete Kunden oder Vereinsmitglieder.'],
        'modul-warenwirtschaft' => ['Warenwirtschaft-Anbindung', 14900, 'Shop', 'Bestände und Bestellungen mit Ihrer Warenwirtschaft abgleichen, z. B. per CSV-Schnittstelle.'],
    ];

    public static function installed(): bool
    {
        return (bool) Setting::get(self::SETTING);
    }

    /** @return array{pages: int, products: int} */
    public function install(): array
    {
        return DB::transaction(function () {
            $products = $this->products();
            $pages = $this->pages();
            $this->menu($pages);
            $this->legalPages();

            Setting::putMany([
                self::SETTING => now()->toIso8601String(),
                'shop_enabled' => '1',
                'shop_shipping_cents' => '0',
                'shop_delivery_time' => 'Sofort per E-Mail nach Zahlungseingang',
                'tagline' => Setting::get('tagline') ?: 'Das CMS, das man nicht erklären muss.',
            ]);

            return ['pages' => count($pages), 'products' => $products];
        });
    }

    private function products(): int
    {
        $count = 0;
        $taxRate = 19;
        foreach (self::LICENSES as $slug => [$name, $cents, $domains, $text]) {
            $count += $this->product($slug, [
                'name' => $name, 'sku' => strtoupper(str_replace('nova-cms-', 'NOVA-', $slug)), 'price_cents' => $cents, 'tax_rate' => $taxRate,
                'short_description' => $text,
                'description' => '<p>'.e($text).'</p><ul><li>Alle Kernfunktionen: Seiten, Menü, Medien, Shop, SEO, Sicherheit und KI-Assistent</li>'
                    .'<li>Gültig für '.$domains.' '.($domains === 1 ? 'Website' : 'Websites').' (Test- und Entwicklungsumgebungen zählen nicht mit)</li>'
                    .'<li>12 Monate Updates inklusive, danach läuft Ihre Version unbegrenzt weiter</li>'
                    .'<li>Lizenzschlüssel kommt nach Zahlungseingang automatisch per E-Mail</li></ul>',
                'is_active' => true,
                'license_spec' => ['core' => true, 'modules' => [], 'max_domains' => $domains, 'update_months' => 12],
            ]);
        }
        foreach (self::PAID_MODULES as $slug => [$name, $cents, $area, $text]) {
            $count += $this->product($slug, [
                'name' => 'Modul: '.$name, 'sku' => strtoupper(Str::limit(str_replace('modul-', 'MOD-', $slug), 20, '')), 'price_cents' => $cents, 'tax_rate' => $taxRate,
                'short_description' => $text,
                'description' => '<p>'.e($text).'</p><p>Einmal kaufen, für alle Websites Ihrer Nova-Lizenz nutzen. Installation im Backoffice unter Plugins › Marktplatz.</p>',
                // Erst einschalten, wenn das Modul im Marktplatz bereitsteht
                'is_active' => false,
                'license_spec' => ['core' => false, 'modules' => [str_replace('modul-', '', $slug)], 'max_domains' => 1, 'update_months' => 12],
            ]);
        }

        return $count;
    }

    private function product(string $slug, array $data): int
    {
        if (Product::where('slug', $slug)->exists()) {
            return 0;
        }
        Product::create($data + ['slug' => $slug, 'stock' => null, 'images' => [], 'sort' => 0]);

        return 1;
    }

    /** @return array<string, Page> */
    private function pages(): array
    {
        // Erst alle Seiten anlegen, damit die Links ihre tatsächlichen Adressen kennen
        $pages = [];
        foreach (self::PAGES as $key => [$title, $slug]) {
            $pages[$key] = Page::create([
                'title' => $title,
                'slug' => Page::uniqueSlug($slug),
                'status' => 'published',
                'published_at' => now(),
                'blocks' => [],
                'is_home' => false,
                'show_in_menu' => $key !== 'start',
            ]);
        }

        Page::where('is_home', true)->update(['is_home' => false]);
        $pages['start']->update(['is_home' => true]);

        $url = fn (string $key) => $key === 'start' ? '/' : '/'.$pages[$key]->slug;
        $sanitizer = app(BlockSanitizer::class);
        foreach ($pages as $key => $page) {
            [$blocks, $meta] = $this->content($key, $url);
            $page->update(['blocks' => $sanitizer->clean($blocks), 'meta_title' => $meta[0], 'meta_description' => $meta[1]]);
        }

        return $pages;
    }

    /** Funktionen › Module und Vergleich als Unterpunkte */
    private function menu(array $pages): void
    {
        $parent = MenuItem::where('page_id', $pages['funktionen']->id)->first();
        $pos = 0;
        foreach (['module', 'vergleich'] as $child) {
            MenuItem::where('page_id', $pages[$child]->id)->update(['parent_id' => $parent?->id, 'position' => $pos++]);
        }
        $pos = (int) MenuItem::whereNull('parent_id')->where('id', '!=', $parent?->id)->min('position');
        foreach (['funktionen', 'preise', 'fragen', 'testen', 'kontakt'] as $key) {
            MenuItem::where('page_id', $pages[$key]->id)->update(['position' => $pos++]);
        }
    }

    /** Rechtstexte aus den Vorlagen, sofern es sie noch nicht gibt */
    private function legalPages(): void
    {
        foreach (['impressum', 'datenschutz', 'agb', 'widerruf'] as $tpl) {
            $data = PageTemplates::make($tpl);
            if (Page::where('purpose', $data['purpose'])->exists()) {
                continue;
            }
            Page::create([
                'title' => $data['title'], 'slug' => Page::uniqueSlug($data['title']), 'status' => 'published', 'published_at' => now(),
                'blocks' => $data['blocks'], 'purpose' => $data['purpose'], 'show_in_menu' => false,
            ]);
        }
    }

    // --------------------------------------------------------------------------------
    // Inhalte
    // --------------------------------------------------------------------------------

    private static function id(): string
    {
        return Str::lower(Str::random(10));
    }

    private static function block(string $type, array $data): array
    {
        return ['id' => self::id(), 'type' => $type, 'data' => $data];
    }

    /** @return array{0: list<array>, 1: array{0: string, 1: string}} */
    private function content(string $key, callable $url): array
    {
        return match ($key) {
            'start' => [[
                self::block('hero', [
                    'variant' => 'tech',
                    'eyebrow' => 'Nova CMS · Version '.config('nova.version'),
                    'title' => 'Die Website-Software, die man nicht erklären muss.',
                    'text' => 'Seiten, Online-Shop, KI-Assistent, SEO und Sicherheit in einer klaren Oberfläche. In wenigen Minuten auf Ihrem eigenen Webspace installiert und ohne Vorkenntnisse bedienbar.',
                    'button_label' => '30 Tage kostenlos testen', 'button_url' => $url('testen'),
                    'button2_label' => 'Preise ansehen', 'button2_url' => $url('preise'),
                ]),
                self::block('features', ['style' => 'stats', 'items' => [
                    ['title' => '1 Oberfläche', 'text' => 'für Seiten, Menü, Medien, Shop, SEO und KI'],
                    ['title' => '4 Schritte', 'text' => 'vom Hochladen bis zur fertigen Website'],
                    ['title' => '0 Tracker', 'text' => 'und keine externen Schriften ab Werk'],
                    ['title' => '30 Tage', 'text' => 'kostenlos testen, ohne Kreditkarte'],
                ]]),
                self::block('features', [
                    'eyebrow' => 'Was Nova anders macht', 'title' => 'Alles, was Sie brauchen. Nichts, was Sie suchen müssen.',
                    'intro' => 'Viele Website-Systeme können fast alles, wenn man weiß, wo es versteckt ist. Nova geht den umgekehrten Weg: Jede Funktion liegt dort, wo man sie erwartet.',
                    'style' => 'grid', 'items' => [
                        ['icon' => 'bolt', 'title' => 'Bedienung wie eine App', 'text' => 'Klare Bereiche statt verschachtelter Menüs. Mit Strg K springen Sie direkt zu jeder Seite, jedem Produkt oder jeder Bestellung.'],
                        ['icon' => 'eye', 'title' => 'Live-Vorschau beim Schreiben', 'text' => 'Neben dem Editor sehen Sie sofort, wie die Seite aussieht, am Computer und auf dem Handy. Noch bevor Sie speichern.'],
                        ['icon' => 'sparkles', 'title' => 'KI mit freier Anbieterwahl', 'text' => 'Texte verbessern, Beschreibungen für Google und Bildtexte vorschlagen lassen. Mit Ihrem eigenen Schlüssel, z. B. für Claude, ChatGPT oder Gemini.'],
                        ['icon' => 'cart', 'title' => 'Shop für den deutschen Markt', 'text' => 'Grundpreise, Steuerhinweise, Vorlagen für AGB, Widerruf und Versand. Zahlung per PayPal, Stripe, Vorkasse oder Rechnung.'],
                        ['icon' => 'shield', 'title' => 'Sicherheit eingebaut', 'text' => 'Firewall, Anmeldesperre, Zwei-Faktor-Anmeldung und eine Prüfung der Programmdateien. Ohne Zusatz-Plugins.'],
                        ['icon' => 'lock', 'title' => 'Datenschutzfreundlich ab Werk', 'text' => 'Keine externen Schriften, Videos laden erst nach einem Klick, und Ihre Daten liegen auf Ihrem eigenen Webspace.'],
                    ],
                ]),
                self::block('features', [
                    'eyebrow' => 'So einfach geht es', 'title' => 'In vier Schritten zur eigenen Website', 'style' => 'steps', 'items' => [
                        ['title' => 'Hochladen', 'text' => 'Installationspaket entpacken und per FTP auf Ihren Webspace laden, z. B. bei IONOS oder Strato.'],
                        ['title' => 'Installieren', 'text' => 'Der Assistent prüft Ihren Server, richtet die Datenbank ein und legt Ihr Konto an.'],
                        ['title' => 'Gestalten', 'text' => 'Seitenvorlagen wählen, Texte und Bilder einsetzen, Menü per Maus sortieren.'],
                        ['title' => 'Veröffentlichen', 'text' => 'Ein Klick, und Ihre Seite ist online. Mit Sitemap für Google und Hinweisen zur Optimierung.'],
                    ],
                ]),
                self::block('modules', [
                    'eyebrow' => 'Modular', 'title' => 'Der Kern ist komplett. Module machen ihn persönlich.',
                    'intro' => 'Alles für eine professionelle Website ist von Anfang an dabei. Zusätzliche Module installieren Sie mit einem Klick aus dem Marktplatz im Backoffice.',
                    'items' => (fn ($all) => [$all[0], $all[2], $all[4], $all[8], $all[9], $all[10]])($this->moduleItems()),
                ]),
                self::block('cta', ['style' => 'light', 'title' => 'Alle Module im Überblick', 'text' => 'Sehen Sie, was im Kern enthalten ist, was kostenlos dazukommt und welche Erweiterungen es zu kaufen gibt.', 'button_label' => 'Zu den Modulen', 'button_url' => $url('module')]),
                self::block('faq', ['eyebrow' => 'Gut zu wissen', 'title' => 'Häufige Fragen', 'items' => array_slice($this->faqItems($url), 0, 5)]),
                self::block('cta', [
                    'style' => 'dark', 'title' => 'Testen Sie Nova 30 Tage kostenlos.',
                    'text' => 'Ohne Kreditkarte und ohne Abo. Ihre Website bleibt auch nach der Testphase online.',
                    'button_label' => 'Jetzt testen', 'button_url' => $url('testen'), 'button2_label' => 'Frage stellen', 'button2_url' => $url('kontakt'),
                ]),
            ], ['Nova CMS: das CMS, das man nicht erklären muss', 'Seiten, Online-Shop, KI-Assistent, SEO und Sicherheit in einer Oberfläche. Auf dem eigenen Webspace installiert, ohne Vorkenntnisse bedienbar. 30 Tage kostenlos testen.']],

            'funktionen' => [[
                self::block('hero', [
                    'variant' => 'tech', 'eyebrow' => 'Funktionen',
                    'title' => 'Ein System. Alles an Bord.',
                    'text' => 'Von der ersten Seite bis zum Online-Shop: Nova bringt die Werkzeuge mit, für die man anderswo viele einzelne Erweiterungen zusammensuchen muss.',
                    'button_label' => 'Kostenlos testen', 'button_url' => $url('testen'), 'button2_label' => 'Zum Vergleich', 'button2_url' => $url('vergleich'),
                ]),
                self::block('features', ['eyebrow' => 'Inhalte', 'title' => 'Seiten gestalten wie im Baukasten', 'style' => 'grid', 'items' => [
                    ['icon' => 'layers', 'title' => '14 Bausteine', 'text' => 'Text, Titelbild, Bild, Slider, Video, Vorteile, Preise, Module, Vergleich, Fragen, Aufruf, Kontaktformular, Knopf und HTML.'],
                    ['icon' => 'pages', 'title' => 'Texteditor mit HTML-Ansicht', 'text' => 'Überschriften, Listen, Tabellen, Bilder und Ausrichtung per Klick. Wer möchte, schaltet auf den HTML-Quelltext um.'],
                    ['icon' => 'eye', 'title' => 'Live-Vorschau', 'text' => 'Änderungen erscheinen sofort in der Vorschau, wahlweise als Computer- oder Handy-Ansicht.'],
                    ['icon' => 'menu', 'title' => 'Menü per Maus', 'text' => 'Haupt- und Unterpunkte verschieben und einrücken. Die Website zeigt Untermenüs automatisch als Aufklappmenü.'],
                    ['icon' => 'image', 'title' => 'Mediathek', 'text' => 'Große Fotos werden automatisch verkleinert. Bildbeschreibungen kann die KI vorschlagen.'],
                    ['icon' => 'clock', 'title' => 'Versionen und Papierkorb', 'text' => 'Ältere Fassungen einer Seite wiederherstellen und gelöschte Seiten zurückholen.'],
                ]]),
                self::block('features', ['eyebrow' => 'Wachstum', 'title' => 'Gefunden werden und verkaufen', 'style' => 'grid', 'items' => [
                    ['icon' => 'search', 'title' => 'SEO-Analyse je Seite', 'text' => 'Fokus-Suchbegriff festlegen und konkrete Hinweise bekommen, was auf der Seite noch fehlt.'],
                    ['icon' => 'chart', 'title' => 'Wettbewerber-Check', 'text' => 'Vergleichen Sie Ihre Seite mit einer Seite der Konkurrenz und sehen Sie, wo Sie aufholen können.'],
                    ['icon' => 'globe', 'title' => 'Sitemap und Google-Vorschau', 'text' => 'Sitemap und robots.txt entstehen automatisch. Im Editor sehen Sie, wie die Seite bei Google erscheint.'],
                    ['icon' => 'cart', 'title' => 'Online-Shop', 'text' => 'Produkte mit Grundpreis, Lagerbestand und Lieferzeit. Bestellungen verwalten, Bestätigungs- und Versand-E-Mails.'],
                    ['icon' => 'key', 'title' => 'Zahlungsarten', 'text' => 'PayPal, Stripe, Vorkasse und Rechnung. Zugangsdaten werden verschlüsselt gespeichert.'],
                    ['icon' => 'sparkles', 'title' => 'KI-Assistent', 'text' => 'Texte kürzen, verbessern oder freundlicher formulieren und Beschreibungen für Google vorschlagen lassen.'],
                ]]),
                self::block('features', ['eyebrow' => 'Sicherheit und Betrieb', 'title' => 'Ruhig schlafen inklusive', 'style' => 'grid', 'items' => [
                    ['icon' => 'shield', 'title' => 'Firewall', 'text' => 'Erkennt typische Angriffe und sperrt auffällige Adressen automatisch.'],
                    ['icon' => 'lock', 'title' => 'Zwei-Faktor-Anmeldung', 'text' => 'Zusätzlicher Code aus einer Authenticator-App. Dazu eine Sperre nach mehreren falschen Passwörtern.'],
                    ['icon' => 'check', 'title' => 'Sicherheits-Check', 'text' => 'Prüft Einstellungen, Uploads und Programmdateien und erklärt verständlich, was zu tun ist.'],
                    ['icon' => 'rocket', 'title' => 'Einfache Updates', 'text' => 'Neue Version hochladen, Website aufrufen, fertig. Datenbank-Änderungen spielt Nova selbst ein.'],
                    ['icon' => 'puzzle', 'title' => 'Plugins und Marktplatz', 'text' => 'Erweiterungen im Backoffice hochladen oder aus dem Marktplatz installieren, wie man es kennt.'],
                    ['icon' => 'globe', 'title' => 'Läuft auf normalem Webspace', 'text' => 'PHP 8.3 und eine MySQL- oder SQLite-Datenbank genügen. Kein eigener Server nötig.'],
                ]]),
                self::block('cta', ['style' => 'dark', 'title' => 'Überzeugen Sie sich selbst.', 'text' => '30 Tage alle Funktionen kostenlos nutzen.', 'button_label' => 'Kostenlos testen', 'button_url' => $url('testen'), 'button2_label' => 'Preise ansehen', 'button2_url' => $url('preise')]),
            ], ['Funktionen von Nova CMS', 'Block-Editor mit Live-Vorschau, Menü per Maus, Mediathek, Online-Shop, SEO-Analyse, KI-Assistent und Sicherheitscenter in einem System.']],

            'module' => [[
                self::block('hero', [
                    'variant' => 'tech', 'eyebrow' => 'Module',
                    'title' => 'Klein anfangen. Mitwachsen.',
                    'text' => 'Der Kern von Nova enthält alles für eine professionelle Website mit Shop. Kostenlose und kostenpflichtige Module erweitern ihn gezielt um das, was Sie wirklich brauchen.',
                    'button_label' => 'Preise ansehen', 'button_url' => $url('preise'),
                ]),
                self::block('modules', [
                    'eyebrow' => 'Übersicht', 'title' => 'Was ist enthalten, was kommt dazu?',
                    'intro' => 'Filtern Sie nach „Im Kern“, „Kostenlos“ oder „Kostenpflichtig“. Kaufbare Module können Sie direkt auswählen und gemeinsam in den Warenkorb legen.',
                    'builder' => true, 'items' => $this->moduleItems(),
                ]),
                self::block('features', ['eyebrow' => 'So funktioniert es', 'title' => 'Module installieren in drei Klicks', 'style' => 'steps', 'items' => [
                    ['title' => 'Auswählen', 'text' => 'Kostenlose Module stehen sofort im Marktplatz bereit. Kostenpflichtige kaufen Sie hier im Shop.'],
                    ['title' => 'Freischalten', 'text' => 'Nach dem Kauf erhalten Sie den Schlüssel per E-Mail und tragen ihn unter „Lizenz“ ein.'],
                    ['title' => 'Installieren', 'text' => 'Unter Plugins › Marktplatz auf „Installieren“ klicken. Fertig, ohne FTP und ohne Code.'],
                ]]),
                self::block('cta', ['style' => 'light', 'title' => 'Sie vermissen ein Modul?', 'text' => 'Schreiben Sie uns, was Ihnen fehlt. Die meistgewünschten Module bauen wir zuerst.', 'button_label' => 'Wunsch mitteilen', 'button_url' => $url('kontakt')]),
            ], ['Module: kostenlos und kostenpflichtig', 'Übersicht aller Funktionen im Kern von Nova CMS sowie kostenloser und kostenpflichtiger Module für Shop, Marketing und Inhalte.']],

            'vergleich' => [[
                self::block('compare', [
                    'eyebrow' => 'Vergleich', 'title' => 'Nova und klassische Website-Systeme',
                    'intro' => 'Klassische Open-Source-Systeme sind mächtig, setzen aber für viele Aufgaben zusätzliche Erweiterungen voraus. Nova bringt diese Funktionen von Haus aus mit.',
                    'ours' => 'Nova CMS', 'theirs' => 'Klassisches CMS (Grundinstallation)',
                    'rows' => [
                        ['label' => 'Einrichtung über einen Assistenten mit Serverprüfung', 'ours' => 'yes', 'theirs' => 'je nach System'],
                        ['label' => 'Seiten-Editor mit Live-Vorschau für Computer und Handy', 'ours' => 'yes', 'theirs' => 'je nach System'],
                        ['label' => 'Online-Shop mit deutschen Rechtstext-Vorlagen', 'ours' => 'yes', 'theirs' => 'meist per Erweiterung'],
                        ['label' => 'SEO-Analyse mit Fokus-Suchbegriff je Seite', 'ours' => 'yes', 'theirs' => 'meist per Erweiterung'],
                        ['label' => 'KI-Assistent mit freier Anbieterwahl', 'ours' => 'yes', 'theirs' => 'meist per Erweiterung'],
                        ['label' => 'Firewall und Prüfung der Programmdateien', 'ours' => 'yes', 'theirs' => 'meist per Erweiterung'],
                        ['label' => 'Zwei-Faktor-Anmeldung', 'ours' => 'yes', 'theirs' => 'teils eingebaut, teils per Erweiterung'],
                        ['label' => 'Schnellsuche über alle Inhalte (Strg K)', 'ours' => 'yes', 'theirs' => 'je nach System'],
                        ['label' => 'Ohne externe Schriften und Tracker ab Werk', 'ours' => 'yes', 'theirs' => 'abhängig von Theme und Erweiterungen'],
                        ['label' => 'Läuft auf üblichem Webspace mit PHP', 'ours' => 'yes', 'theirs' => 'yes'],
                        ['label' => 'Riesige Auswahl an Themes und Erweiterungen', 'ours' => 'Marktplatz im Aufbau', 'theirs' => 'yes'],
                    ],
                    'note' => 'Verglichen wird der Funktionsumfang einer Grundinstallation ohne Erweiterungen. Stand: '.now()->format('m/Y').'. Funktionen anderer Systeme ändern sich mit jeder Version; Angaben ohne Gewähr.',
                ]),
                self::block('features', ['eyebrow' => 'Fair gesagt', 'title' => 'Für wen ist Nova die richtige Wahl?', 'style' => 'grid', 'items' => [
                    ['icon' => 'users', 'title' => 'Selbstständige und kleine Firmen', 'text' => 'Die ihre Website selbst pflegen möchten, ohne sich in Technik einzuarbeiten.'],
                    ['icon' => 'heart', 'title' => 'Vereine und Initiativen', 'text' => 'Bei denen viele Hände mithelfen und die Bedienung sofort verständlich sein muss.'],
                    ['icon' => 'code', 'title' => 'Webdesigner und Agenturen', 'text' => 'Die ihren Kunden ein System übergeben wollen, das ohne Schulung funktioniert.'],
                ]]),
                self::block('cta', ['style' => 'dark', 'title' => 'Machen Sie den Praxistest.', 'text' => '30 Tage kostenlos, auf Ihrem eigenen Webspace.', 'button_label' => 'Kostenlos testen', 'button_url' => $url('testen')]),
            ], ['Nova im Vergleich zu klassischen CMS', 'Was Nova CMS von Haus aus mitbringt: Shop, SEO-Analyse, KI-Assistent, Firewall und Live-Vorschau ohne zusätzliche Erweiterungen.']],

            'preise' => [[
                self::block('pricing', [
                    'eyebrow' => 'Preise', 'title' => 'Einmal kaufen. Dauerhaft nutzen.',
                    'intro' => 'Kein Abo: Sie kaufen eine Lizenz und nutzen Ihre Version unbegrenzt. 12 Monate Updates sind inklusive.',
                    'plans' => [
                        ['name' => 'Start', 'product' => 'nova-cms-start', 'period' => 'einmalig', 'text' => 'Für eine Website', 'features' => ['1 Website', 'Alle Kernfunktionen inklusive Shop', 'KI-Assistent mit eigenem Schlüssel', '12 Monate Updates', 'Support per E-Mail'], 'button_label' => 'In den Warenkorb', 'button_url' => $url('kontakt'), 'price' => '149 €'],
                        ['name' => 'Business', 'product' => 'nova-cms-business', 'period' => 'einmalig', 'text' => 'Für Firma, Shop und mehr', 'features' => ['Bis zu 3 Websites', 'Alle Kernfunktionen inklusive Shop', 'KI-Assistent mit eigenem Schlüssel', '12 Monate Updates', 'Bevorzugter Support'], 'button_label' => 'In den Warenkorb', 'button_url' => $url('kontakt'), 'price' => '349 €', 'highlight' => true],
                        ['name' => 'Agentur', 'product' => 'nova-cms-agentur', 'period' => 'einmalig', 'text' => 'Für Webdesigner und Agenturen', 'features' => ['Bis zu 15 Kunden-Websites', 'Alle Kernfunktionen inklusive Shop', 'Test- und Entwicklungsumgebungen frei', '12 Monate Updates', 'Bevorzugter Support'], 'button_label' => 'In den Warenkorb', 'button_url' => $url('kontakt'), 'price' => '899 €'],
                    ],
                    'note' => 'Alle Lizenzen 30 Tage kostenlos testen. Test- und Entwicklungsumgebungen zählen nicht als Website.',
                ]),
                self::block('features', ['style' => 'grid', 'eyebrow' => 'In jeder Lizenz', 'title' => 'Ohne Kleingedrucktes', 'items' => [
                    ['icon' => 'globe', 'title' => 'Website bleibt immer online', 'text' => 'Auch nach Ende der Testphase oder der Update-Laufzeit ist Ihre Website weiter erreichbar.'],
                    ['icon' => 'rocket', 'title' => 'Updates verlängerbar', 'text' => 'Nach 12 Monaten läuft Ihre Version weiter. Neue Versionen erhalten Sie mit einer Verlängerung.'],
                    ['icon' => 'key', 'title' => 'Schlüssel sofort per E-Mail', 'text' => 'Nach Zahlungseingang kommt der Lizenzschlüssel automatisch. Eintragen, fertig.'],
                ]]),
                self::block('faq', ['title' => 'Fragen zu Lizenz und Preisen', 'items' => array_values(array_filter($this->faqItems($url), fn ($i) => ! empty($i['pricing'])))]),
                self::block('cta', ['style' => 'light', 'title' => 'Unsicher, welches Paket passt?', 'text' => 'Wir beraten Sie gern, kostenlos und unverbindlich.', 'button_label' => 'Beratung anfragen', 'button_url' => $url('kontakt')]),
            ], ['Preise: einmal kaufen, dauerhaft nutzen', 'Nova CMS Start, Business und Agentur: einmalige Lizenz statt Abo, 12 Monate Updates inklusive, 30 Tage kostenlos testen.']],

            'fragen' => [[
                self::block('faq', ['eyebrow' => 'Hilfe', 'title' => 'Häufig gestellte Fragen', 'intro' => 'Ihre Frage ist nicht dabei? Schreiben Sie uns, wir antworten in der Regel innerhalb eines Werktags.', 'items' => $this->faqItems($url)]),
                self::block('cta', ['style' => 'dark', 'title' => 'Noch Fragen?', 'text' => 'Wir helfen Ihnen gern weiter.', 'button_label' => 'Kontakt aufnehmen', 'button_url' => $url('kontakt'), 'button2_label' => 'Kostenlos testen', 'button2_url' => $url('testen')]),
            ], ['Häufige Fragen zu Nova CMS', 'Antworten zu Installation, Hosting, KI, Shop, Lizenz, Updates und Datenschutz bei Nova CMS.']],

            'testen' => [[
                self::block('hero', [
                    'variant' => 'tech', 'eyebrow' => '30 Tage kostenlos',
                    'title' => 'Testen Sie Nova auf Ihrem eigenen Webspace.',
                    'text' => 'Alle Funktionen, 30 Tage lang, ohne Kreditkarte. Danach entscheiden Sie in Ruhe. Ihre Website bleibt in jedem Fall online.',
                    'button_label' => 'Testversion anfordern', 'button_url' => '#kontaktformular',
                ]),
                self::block('features', ['eyebrow' => 'Ablauf', 'title' => 'So starten Sie', 'style' => 'steps', 'items' => [
                    ['title' => 'Anfordern', 'text' => 'Formular ausfüllen. Sie erhalten den Download-Link für das Installationspaket per E-Mail.'],
                    ['title' => 'Hochladen', 'text' => 'ZIP-Datei entpacken und den Inhalt per FTP auf Ihren Webspace laden.'],
                    ['title' => 'Domain ausrichten', 'text' => 'Im Kundenbereich Ihres Hosters die Domain auf den Ordner „public“ zeigen lassen.'],
                    ['title' => 'Assistent starten', 'text' => 'Domain im Browser öffnen und dem Installationsassistenten folgen.'],
                ]]),
                self::block('text', ['html' => '<h2>Was Ihr Webspace braucht</h2><ul><li>PHP 8.3 oder neuer</li><li>Eine MySQL-Datenbank (oder SQLite, das bei vielen Hostern schon dabei ist)</li><li>Die PHP-Erweiterungen, die übliche Hoster mitbringen. Der Assistent prüft das für Sie.</li></ul><p>Passende Pakete gibt es z. B. bei IONOS, Strato, all-inkl.com oder netcup. Sie sind unsicher? Schreiben Sie uns Ihren Hoster und Tarif, wir prüfen das gern.</p>']),
                self::block('contact', ['title' => 'Testversion anfordern', 'intro' => 'Wir schicken Ihnen den Download-Link und eine kurze Anleitung.', 'button_label' => 'Testversion anfordern',
                    'success' => 'Vielen Dank! Wir senden Ihnen den Download-Link in Kürze per E-Mail.', 'subjects' => ['Testversion anfordern', 'Frage vor dem Test']]),
            ], ['Nova CMS 30 Tage kostenlos testen', 'Testversion von Nova CMS anfordern, auf dem eigenen Webspace installieren und 30 Tage alle Funktionen nutzen, ohne Kreditkarte.']],

            'kontakt' => [[
                self::block('contact', [
                    'eyebrow' => 'Kontakt', 'title' => 'Wie können wir helfen?',
                    'intro' => 'Fragen zu Funktionen, Lizenzen oder Modulen? Schreiben Sie uns. Wir antworten in der Regel innerhalb eines Werktags.',
                    'button_label' => 'Nachricht senden',
                    'subjects' => ['Allgemeine Frage', 'Beratung zu Lizenzen', 'Agentur- und Partneranfrage', 'Modulwunsch', 'Support'],
                ]),
                self::block('text', ['html' => $this->contactText()]),
            ], ['Kontakt', 'Fragen zu Nova CMS, Lizenzen oder Modulen? Schreiben Sie uns.']],
        };
    }

    /** @return list<array<string, mixed>> */
    private function moduleItems(): array
    {
        $core = [
            ['Seiten und Block-Editor', 'Inhalte', '14 Bausteine, Texteditor mit HTML-Ansicht, Live-Vorschau, Versionen und Papierkorb.'],
            ['Menü-Verwaltung', 'Inhalte', 'Haupt- und Unterpunkte per Maus sortieren, eigene Links und Shop-Link.'],
            ['Online-Shop', 'Shop', 'Produkte, Warenkorb, Kasse, Bestellungen, PayPal, Stripe, Vorkasse und Rechnung.'],
            ['SEO-Center', 'SEO', 'Analyse je Seite, Fokus-Suchbegriff, Wettbewerber-Check, Sitemap und Google-Vorschau.'],
            ['KI-Assistent', 'KI', 'Texte verbessern, Beschreibungen und Bildtexte vorschlagen. Freie Wahl des KI-Anbieters.'],
            ['Sicherheitscenter', 'Sicherheit', 'Firewall, Zwei-Faktor-Anmeldung, Anmeldesperre und Prüfung der Programmdateien.'],
            ['Mediathek', 'Inhalte', 'Bilder und Videos hochladen, automatisch verkleinern und wiederverwenden.'],
            ['Kontaktformular', 'Inhalte', 'Anfragen mit Spam-Schutz, gesammelt in der Verwaltung und per E-Mail.'],
        ];
        $items = array_map(fn ($c) => ['name' => $c[0], 'category' => $c[1], 'text' => $c[2], 'kind' => 'core'], $core);
        $items[] = ['name' => 'Öffnungszeiten', 'category' => 'Inhalte', 'text' => 'Öffnungszeiten an einer Stelle pflegen. Das Dashboard erinnert Sie, wenn sie fehlen.', 'kind' => 'free'];
        foreach (self::PAID_MODULES as $slug => [$name, $cents, $area, $text]) {
            $items[] = ['name' => $name, 'category' => $area, 'text' => $text, 'kind' => 'paid', 'product' => $slug,
                'price' => number_format($cents / 100, 2, ',', '.').' €', 'soon' => true];
        }

        return $items;
    }

    /** @return list<array{q: string, a: string, pricing?: bool}> */
    private function faqItems(callable $url): array
    {
        return [
            ['q' => 'Brauche ich Programmierkenntnisse?', 'a' => 'Nein. Installation, Gestaltung und Pflege laufen komplett über den Browser. Wer möchte, kann im Texteditor auf die HTML-Ansicht umschalten, muss es aber nie.'],
            ['q' => 'Welchen Webspace brauche ich?', 'a' => 'Einen üblichen Webspace mit PHP 8.3 und einer MySQL- oder SQLite-Datenbank, wie ihn z. B. IONOS oder Strato anbieten. Der Installationsassistent prüft vorab, ob alles passt, und erklärt verständlich, was gegebenenfalls fehlt.'],
            ['q' => 'Was passiert nach den 30 Testtagen?', 'a' => 'Ihre Website bleibt online und für Besucher erreichbar. Nur die Verwaltung wird gesperrt, bis Sie einen Lizenzschlüssel eintragen. Alle Inhalte bleiben erhalten.', 'pricing' => true],
            ['q' => 'Ist das ein Abo?', 'a' => 'Nein. Sie kaufen eine Lizenz einmalig und nutzen Ihre Version unbegrenzt. 12 Monate Updates sind inklusive, danach können Sie die Updates auf Wunsch verlängern.', 'pricing' => true],
            ['q' => 'Wie funktioniert die KI, und was kostet sie?', 'a' => 'Sie wählen Ihren KI-Anbieter selbst, z. B. Anthropic (Claude), OpenAI (ChatGPT) oder Google (Gemini), und hinterlegen Ihren eigenen Schlüssel. Die Kosten rechnet der Anbieter direkt mit Ihnen ab, meist wenige Cent pro Text. Ohne Schlüssel funktioniert Nova genauso, nur ohne KI-Vorschläge.'],
            ['q' => 'Ist Nova datenschutzfreundlich?', 'a' => 'Nova lädt ab Werk keine externen Schriften und keine Tracker. Videos von YouTube oder Vimeo werden erst nach einem Klick geladen. Die Website und alle Daten liegen auf Ihrem eigenen Webspace. Vorlagen für Impressum und Datenschutzerklärung sind dabei, ersetzen aber keine Rechtsberatung.'],
            ['q' => 'Kann ich einen Online-Shop betreiben?', 'a' => 'Ja, der Shop ist im Kern enthalten: Produkte mit Grundpreis und Lagerbestand, Warenkorb, Kasse, Bestellverwaltung, Zahlung per PayPal, Stripe, Vorkasse oder Rechnung sowie Vorlagen für AGB, Widerruf und Versand.'],
            ['q' => 'Für wie viele Websites gilt eine Lizenz?', 'a' => 'Start gilt für eine Website, Business für drei und Agentur für fünfzehn. Test- und Entwicklungsumgebungen wie localhost oder test.ihre-domain.de zählen nicht mit. Beim Umzug geben Sie die alte Website einfach frei.', 'pricing' => true],
            ['q' => 'Was ist der Unterschied zwischen kostenlosen und kostenpflichtigen Modulen?', 'a' => 'Kostenlose Module installieren Sie direkt aus dem Marktplatz im Backoffice. Kostenpflichtige Module kaufen Sie im Shop. Nach dem Kauf erhalten Sie einen Schlüssel, danach erscheint das Modul im Marktplatz zum Installieren.', 'pricing' => true],
            ['q' => 'Wie funktionieren Updates?', 'a' => 'Sie laden die neue Version über die alte hoch und rufen die Website einmal auf. Notwendige Änderungen an der Datenbank spielt Nova automatisch ein. Ihre Inhalte, Bilder und Einstellungen bleiben unberührt.'],
            ['q' => 'Kann ich von einem anderen System umziehen?', 'a' => 'Texte und Bilder übernehmen Sie per Kopieren und Hochladen. Bei größeren Websites unterstützen wir Sie gern, sprechen Sie uns an.'],
            ['q' => 'Welche Zahlungsarten gibt es beim Kauf?', 'a' => 'Die verfügbaren Zahlungsarten sehen Sie in der Kasse. Den Lizenzschlüssel erhalten Sie nach Zahlungseingang automatisch per E-Mail.', 'pricing' => true],
        ];
    }

    private function contactText(): string
    {
        $s = fn (string $k) => e((string) Setting::get($k, ''));
        $lines = array_filter([
            '<strong>'.($s('company_name') ?: $s('site_name')).'</strong>',
            $s('street'), $s('zip_city'),
            Setting::get('phone') ? 'Telefon: '.$s('phone') : null,
            Setting::get('contact_email') ? 'E-Mail: <a href="mailto:'.$s('contact_email').'">'.$s('contact_email').'</a>' : null,
        ]);

        return '<h2>So erreichen Sie uns direkt</h2><p>'.implode('<br>', $lines).'</p>'
            .(count($lines) < 3 ? '<p><em>[Adresse und Telefon ergänzen Sie unter Einstellungen › Firmenprofil]</em></p>' : '');
    }
}
