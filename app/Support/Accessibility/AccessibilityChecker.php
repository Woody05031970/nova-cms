<?php

namespace App\Support\Accessibility;

use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Shop\ShopSettings;
use App\Support\Design\SiteDesign;

/**
 * Prüft die Website auf die häufigsten Barrieren nach WCAG 2.1 AA, wie sie das
 * Barrierefreiheitsstärkungsgesetz (BFSG) für Online-Shops verlangt.
 *
 * Was Nova CMS selbst ausgibt (Sprunglink, Tastaturbedienung, Menüs, Formulare,
 * Sprache, reduzierte Bewegung), ist bereits barrierefrei. Geprüft werden deshalb
 * vor allem die Inhalte, die Sie selbst pflegen.
 */
class AccessibilityChecker
{
    public const VAGUE_LINKS = ['hier', 'hier klicken', 'klicken sie hier', 'mehr', 'mehr lesen', 'weiter', 'link', 'click here', 'read more', '>>', '»'];

    /**
     * @return array{score: int, site: list<array<string, mixed>>, pages: list<array{page: Page, issues: list<array<string, string>>}>, counts: array<string, int>, builtin: list<string>}
     */
    public function run(): array
    {
        $site = $this->siteChecks();
        $pages = [];
        foreach (Page::published()->orderByDesc('is_home')->orderBy('title')->get() as $page) {
            $issues = $this->pageIssues($page->blocks ?? []);
            if ($issues) {
                $pages[] = ['page' => $page, 'issues' => $issues];
            }
        }

        $all = collect($site)->where('ok', false)->merge(collect($pages)->flatMap(fn ($p) => $p['issues']));
        $counts = ['error' => $all->where('level', 'error')->count(), 'warning' => $all->where('level', 'warning')->count(), 'info' => $all->where('level', 'info')->count()];
        $score = max(0, 100 - $counts['error'] * 12 - $counts['warning'] * 4 - $counts['info']);

        return ['score' => $score, 'site' => $site, 'pages' => $pages, 'counts' => $counts, 'builtin' => self::builtin()];
    }

    /** Was Nova CMS automatisch für Barrierefreiheit tut */
    public static function builtin(): array
    {
        return [
            'Link „Zum Inhalt springen“ und deutlich sichtbarer Tastatur-Fokus',
            'Menüs, Untermenüs und Handy-Menü vollständig per Tastatur und Screenreader bedienbar',
            'Formularfelder mit Beschriftung, Fehler werden am Feld vorgelesen',
            'Sprache der Seiten (Deutsch) ist für Vorlesesoftware hinterlegt',
            'Animationen und Hintergrundvideos stoppen bei „Bewegung reduzieren“',
            'Darstellung passt sich Smartphone, Tablet und Computer an, Zoom auf 200 % möglich',
            'Externe Videos laden erst nach Klick und haben einen Titel',
        ];
    }

    /** @return list<array{key: string, ok: bool, level: string, label: string, tip: string, fix?: string}> */
    public function siteChecks(): array
    {
        $design = SiteDesign::values();
        $brand = $design['brand'];
        $buttonContrast = SiteDesign::contrast($brand, '#ffffff');
        $linkContrast = SiteDesign::contrast(SiteDesign::palette($brand)[700], '#ffffff');
        $statement = Page::published()->where('purpose', 'accessibility')->exists();
        $mediaMissing = Media::where('kind', 'image')->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->count();

        $checks = [
            ['key' => 'statement', 'ok' => $statement, 'level' => 'error', 'label' => 'Erklärung zur Barrierefreiheit ist veröffentlicht',
                'tip' => 'Online-Shops müssen nach dem BFSG eine Erklärung zur Barrierefreiheit bereitstellen. Nova CMS legt sie mit einem Klick an und verlinkt sie in der Fußzeile.', 'fix' => 'statement'],
            ['key' => 'button-contrast', 'ok' => $buttonContrast >= 4.5, 'level' => 'error', 'label' => 'Knöpfe gut lesbar (Kontrast '.number_format($buttonContrast, 1, ',', '').' : 1)',
                'tip' => 'Weiße Schrift auf Ihrer Hauptfarbe braucht mindestens 4,5 : 1. Wählen Sie unter Design eine dunklere Hauptfarbe.', 'fix' => 'design'],
            ['key' => 'link-contrast', 'ok' => $linkContrast >= 4.5, 'level' => 'warning', 'label' => 'Links und Hervorhebungen gut lesbar (Kontrast '.number_format($linkContrast, 1, ',', '').' : 1)',
                'tip' => 'Farbige Texte auf weißem Grund brauchen mindestens 4,5 : 1. Wählen Sie unter Design eine kräftigere Hauptfarbe.', 'fix' => 'design'],
            ['key' => 'media-alt', 'ok' => $mediaMissing === 0, 'level' => 'warning', 'label' => $mediaMissing ? $mediaMissing.' Bilder in der Mediathek ohne Beschreibung' : 'Alle Bilder in der Mediathek haben eine Beschreibung',
                'tip' => 'Beschreibungen (Alternativtexte) werden blinden Menschen vorgelesen. Der KI-Assistent schlägt sie auf Wunsch vor.', 'fix' => 'media'],
        ];

        if (ShopSettings::enabled()) {
            $products = Product::active()->get();
            $noText = $products->filter(fn (Product $p) => trim(strip_tags((string) $p->description)) === '' && trim((string) $p->short_description) === '')->count();
            $checks[] = ['key' => 'products', 'ok' => $noText === 0, 'level' => 'warning', 'label' => $noText ? $noText.' Produkte ohne Beschreibungstext' : 'Alle Produkte haben einen Beschreibungstext',
                'tip' => 'Wer das Produktbild nicht sehen kann, braucht die wichtigsten Eigenschaften als Text (Größe, Material, Farbe, Inhalt).', 'fix' => 'products'];
            $checks[] = ['key' => 'checkout', 'ok' => true, 'level' => 'info', 'label' => 'Warenkorb und Kasse sind barrierefrei aufgebaut',
                'tip' => 'Beschriftete Felder, automatische Ausfüllhilfe, Fehler am Feld und klare Schaltflächen sind eingebaut.'];
        }

        return $checks;
    }

    /**
     * Prüft die Bausteine einer Seite.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{level: string, label: string, tip: string}>
     */
    public function pageIssues(array $blocks): array
    {
        $issues = [];
        $add = function (string $level, string $label, string $tip) use (&$issues) {
            $issues[] = ['level' => $level, 'label' => $label, 'tip' => $tip];
        };
        $missingAlt = 0;
        $vague = [];
        $lastLevel = 1; // Die Seite hat immer eine Hauptüberschrift (h1)
        $skipped = [];

        foreach ($blocks as $b) {
            $d = $b['data'] ?? [];
            switch ($b['type'] ?? '') {
                case 'image':
                    if (! empty($d['url']) && trim((string) ($d['alt'] ?? '')) === '') {
                        $missingAlt++;
                    }
                    break;
                case 'slider':
                    foreach ($d['slides'] ?? [] as $s) {
                        $missingAlt += trim((string) ($s['alt'] ?? '')) === '' ? 1 : 0;
                    }
                    break;
                case 'video':
                    if (! empty($d['file'])) {
                        $add('warning', 'Video ohne Untertitel oder Textfassung', 'Gehörlose Menschen brauchen Untertitel oder eine kurze Zusammenfassung als Text unter dem Video.');
                    }
                    break;
                case 'hero':
                case 'button':
                case 'cta':
                    foreach (['button_label', 'label', 'button2_label'] as $k) {
                        if (! empty($d[$k]) && $this->isVague((string) $d[$k])) {
                            $vague[] = $d[$k];
                        }
                    }
                    break;
                case 'text':
                case 'html':
                    $html = (string) ($d['html'] ?? '');
                    preg_match_all('/<h([2-6])\b/i', $html, $m);
                    foreach ($m[1] as $level) {
                        $level = (int) $level;
                        if ($level > $lastLevel + 1) {
                            $skipped[] = 'H'.$lastLevel.' → H'.$level;
                        }
                        $lastLevel = $level;
                    }
                    preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
                    foreach ($imgs[0] as $img) {
                        if (! preg_match('/\balt\s*=\s*"[^"]+"/i', $img)) {
                            $missingAlt++;
                        }
                    }
                    preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $html, $links);
                    foreach ($links[1] as $inner) {
                        $text = trim(html_entity_decode(strip_tags($inner)));
                        if ($text === '' && ! preg_match('/alt\s*=\s*"[^"]+"/i', $inner)) {
                            $add('error', 'Link ohne Text', 'Ein Link hat keinen lesbaren Text. Screenreader lesen dann nur „Link“ vor.');
                        } elseif ($this->isVague($text)) {
                            $vague[] = $text;
                        }
                    }
                    if (preg_match('/<table\b/i', $html) && ! preg_match('/<th\b/i', $html)) {
                        $add('warning', 'Tabelle ohne Kopfzeile', 'Markieren Sie die erste Zeile im Editor als Kopfzeile, damit Vorlesesoftware die Spalten zuordnen kann.');
                    }
                    if (preg_match('/href="[^"]+\.pdf"/i', $html)) {
                        $add('info', 'Verlinktes PDF', 'PDF-Dokumente müssen ebenfalls barrierefrei sein (mit Überschriften und Textebene). Alternativ den Inhalt als Seite anbieten.');
                    }
                    if (preg_match('/<(p|li)\b[^>]*>\s*(?:<[^>]+>)*[A-ZÄÖÜ0-9\s!?.,-]{40,}(?:<[^>]+>)*\s*<\/(p|li)>/u', $html)) {
                        $add('info', 'Längerer Text in Großbuchstaben', 'Text in Großbuchstaben ist schwerer zu lesen und wird teils buchstabiert vorgelesen.');
                    }
                    break;
            }
        }

        if ($missingAlt) {
            $add('error', $missingAlt === 1 ? 'Ein Bild ohne Beschreibung' : $missingAlt.' Bilder ohne Beschreibung', 'Ergänzen Sie im Baustein eine kurze Beschreibung (Alternativtext), was auf dem Bild zu sehen ist. Rein schmückende Bilder: Hintergrund im Titelbild verwenden.');
        }
        if ($skipped) {
            $add('warning', 'Überschriften-Ebene übersprungen ('.implode(', ', array_unique($skipped)).')', 'Überschriften sollten der Reihe nach folgen (Überschrift 2, dann 3). Wählen Sie im Editor das passende Absatzformat.');
        }
        if ($vague) {
            $add('warning', 'Unklare Linktexte: „'.implode('“, „', array_slice(array_unique($vague), 0, 3)).'“', 'Linktexte sollen auch ohne Umgebung verständlich sein, z. B. „Preisliste ansehen“ statt „hier“.');
        }

        return $issues;
    }

    private function isVague(string $text): bool
    {
        return in_array(mb_strtolower(trim($text, " \t\n\r\0\x0B.:!›→")), self::VAGUE_LINKS, true);
    }
}
