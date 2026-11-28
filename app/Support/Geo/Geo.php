<?php

namespace App\Support\Geo;

use App\Licensing\Features;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Shop\ShopSettings;
use App\Support\Blocks\Builder;
use App\Support\Design\SiteDesign;
use App\Support\Seo\PageAnalyzer;
use App\Support\Seo\SiteSeo;
use Illuminate\Support\Str;

/**
 * Modul „KI-Sichtbarkeit“ (GEO, Generative Engine Optimization): Damit KI-Suchen wie ChatGPT,
 * Gemini, Perplexity oder die KI-Übersicht bei Google die Website verstehen und zitieren.
 *
 * - strukturierte Daten je Seite (WebPage, Brotkrumen, Organisation mit Logo und Profilen)
 * - /llms.txt: eine kurze, maschinenlesbare Übersicht der Website
 * - Prüfung je Seite mit verständlichen Tipps
 * - Steuerung, ob KI-Crawler die Inhalte lesen dürfen
 */
class Geo
{
    /** Bekannte Crawler von KI-Anbietern für robots.txt: Name => Anbieter */
    public const AI_BOTS = [
        'GPTBot' => 'OpenAI (ChatGPT)',
        'OAI-SearchBot' => 'OpenAI (ChatGPT-Suche)',
        'ClaudeBot' => 'Anthropic (Claude)',
        'Google-Extended' => 'Google (Gemini)',
        'PerplexityBot' => 'Perplexity',
        'Applebot-Extended' => 'Apple Intelligence',
        'CCBot' => 'Common Crawl',
        'meta-externalagent' => 'Meta AI',
    ];

    /** Profile in sozialen Netzwerken für „sameAs“: Einstellung => Name */
    public const PROFILES = [
        'geo_profile_facebook' => 'Facebook',
        'geo_profile_instagram' => 'Instagram',
        'geo_profile_linkedin' => 'LinkedIn',
        'geo_profile_youtube' => 'YouTube',
        'geo_profile_xing' => 'XING',
    ];

    public static function on(): bool
    {
        return Features::on('geo');
    }

    public static function llmsEnabled(): bool
    {
        return self::on() && SiteSeo::indexingAllowed() && Setting::get('geo_llms', '1') === '1';
    }

    /** Dürfen KI-Crawler die Website lesen? Ohne das Modul gilt: ja. */
    public static function aiBotsAllowed(): bool
    {
        return ! self::on() || Setting::get('geo_ai_bots', 'allow') !== 'block';
    }

    /** Zusatzzeilen für robots.txt */
    public static function robotsLines(): string
    {
        if (self::aiBotsAllowed()) {
            return '';
        }

        return collect(array_keys(self::AI_BOTS))->map(fn ($bot) => "User-agent: {$bot}\nDisallow: /\n")->implode("\n")."\n";
    }

    /** @return list<array<string, mixed>> */
    public function pageJsonLd(Page $page): array
    {
        $site = (string) Setting::get('site_name', config('app.name'));
        $org = $this->organization();
        $description = $page->meta_description ?: Str::limit($page->plainText(), 200);

        $out = [[
            '@context' => 'https://schema.org',
            '@type' => match ($page->purpose) {
                'contact' => 'ContactPage',
                'about' => 'AboutPage',
                default => 'WebPage',
            },
            '@id' => $page->url().'#seite',
            'url' => $page->url(),
            'name' => $page->meta_title ?: $page->title,
            'description' => $description ?: null,
            'inLanguage' => 'de-DE',
            'isPartOf' => ['@type' => 'WebSite', 'name' => $site, 'url' => url('/')],
            'datePublished' => $page->published_at?->toAtomString(),
            'dateModified' => $page->updated_at?->toAtomString(),
            'publisher' => $org ? ['@id' => url('/').'#organisation'] : null,
        ]];

        if (! $page->is_home) {
            $out[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => $page->url()],
                ],
            ];
        }
        if ($org) {
            $out[] = $org;
        }

        return array_map(fn ($d) => array_filter($d, fn ($v) => $v !== null && $v !== ''), $out);
    }

    /** Organisation mit Logo und Profilen. */
    public function organization(): ?array
    {
        $name = Setting::get('company_name', Setting::get('site_name'));
        if (! $name) {
            return null;
        }
        $logo = SiteDesign::values()['logo'] ?? null;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organisation',
            'name' => $name,
            'url' => url('/'),
            'logo' => $logo ? url($logo) : null,
            'description' => Setting::get('geo_summary') ?: Setting::get('tagline'),
            'email' => Setting::get('contact_email'),
            'telephone' => Setting::get('phone'),
            'sameAs' => array_values(array_filter(array_map(fn ($k) => self::profileUrl($k), array_keys(self::PROFILES)))) ?: null,
        ]);
    }

    public static function profileUrl(string $key): ?string
    {
        $v = trim((string) Setting::get($key));

        return preg_match('#^https://[^\s<>"]+$#', $v) ? $v : null;
    }

    /** Inhalt von /llms.txt nach dem Vorschlag auf llmstxt.org */
    public function llmsTxt(): string
    {
        $name = (string) Setting::get('site_name', config('app.name'));
        $summary = trim((string) (Setting::get('geo_summary') ?: Setting::get('tagline')));
        $pages = Page::published()->public()->orderByDesc('is_home')->orderBy('menu_order')->get();

        $out = "# {$name}\n\n";
        if ($summary !== '') {
            $out .= '> '.str_replace("\n", ' ', $summary)."\n\n";
        }
        $contact = array_filter([
            Setting::get('company_name') ? 'Anbieter: '.Setting::get('company_name') : null,
            trim(Setting::get('street').' '.Setting::get('zip_city')) ?: null,
            Setting::get('phone') ? 'Telefon: '.Setting::get('phone') : null,
            Setting::get('contact_email') ? 'E-Mail: '.Setting::get('contact_email') : null,
        ]);
        if ($contact) {
            $out .= implode("\n", $contact)."\n\n";
        }

        $main = $pages->whereNull('purpose');
        $legal = $pages->whereNotNull('purpose');
        $line = fn (Page $p) => '- ['.$p->title.']('.$p->url().')'
            .(($d = $p->meta_description ?: Str::limit($p->plainText(), 160)) ? ': '.str_replace("\n", ' ', $d) : '')."\n";

        if ($main->isNotEmpty()) {
            $out .= "## Seiten\n\n".$main->map($line)->implode('')."\n";
        }
        if (ShopSettings::enabled() && ($products = Product::active()->orderBy('name')->limit(50)->get())->isNotEmpty()) {
            $out .= "## Produkte\n\n".$products->map(fn ($p) => '- ['.$p->name.']('.$p->url().')'
                .($p->short_description ? ': '.str_replace("\n", ' ', $p->short_description) : '')."\n")->implode('')."\n";
        }
        if ($legal->isNotEmpty()) {
            $out .= "## Optional\n\n".$legal->map($line)->implode('')."\n";
        }

        return rtrim($out)."\n";
    }

    /**
     * Prüft eine Seite darauf, wie gut KI-Suchen sie verstehen und zitieren können.
     *
     * @return array{score: int, checks: list<array{key: string, ok: bool, label: string, tip: string}>}
     */
    public function check(Page $page): array
    {
        $analyzer = app(PageAnalyzer::class);
        $blocks = Builder::flatten($page->blocks ?? []);
        $text = $analyzer->text($blocks);
        $words = $analyzer->countWords($text);
        $html = collect($blocks)->where('type', 'text')->map(fn ($b) => (string) ($b['data']['html'] ?? ''))->implode("\n");
        $headings = preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, $hm) ?: 0;
        $questions = collect($hm[1] ?? [])->filter(fn ($h) => str_ends_with(trim(strip_tags($h)), '?'))->count();
        $hasFaq = collect($blocks)->contains(fn ($b) => ($b['type'] ?? null) === 'faq' && ! empty($b['data']['items']));
        $firstText = '';
        foreach ($blocks as $b) {
            $t = match ($b['type'] ?? null) {
                'text' => strip_tags((string) preg_replace('/<\/p>.*/s', '', (string) ($b['data']['html'] ?? ''))),
                'hero' => (string) ($b['data']['text'] ?? ''),
                default => '',
            };
            if (trim($t) !== '') {
                $firstText = trim($t);
                break;
            }
        }
        $firstWords = $analyzer->countWords($firstText);
        $facts = preg_match_all('/\d/u', $text);
        $legal = $page->purpose !== null;

        $checks = [];
        $add = function (string $key, bool $ok, string $label, string $tip) use (&$checks) {
            $checks[] = compact('key', 'ok', 'label', 'tip');
        };

        $add('summary', $page->meta_description !== null && mb_strlen($page->meta_description) >= 70, 'Kurze Zusammenfassung vorhanden',
            'Tragen Sie bei der Seite eine Beschreibung für Google ein. KI-Suchen nutzen sie als Zusammenfassung der Seite.');
        $add('answer-first', $firstWords >= 12 && $firstWords <= 80, 'Der erste Absatz beantwortet die Hauptfrage',
            $firstWords < 12
                ? 'Beginnen Sie mit zwei, drei Sätzen, die sagen, worum es geht und für wen. Genau diese Sätze zitieren KI-Suchen am häufigsten.'
                : 'Der erste Absatz ist sehr lang. Fassen Sie das Wichtigste vorn in höchstens drei Sätzen zusammen und gehen Sie danach ins Detail.');
        $add('questions', $hasFaq || $questions > 0, 'Fragen und Antworten',
            'Ergänzen Sie den Baustein „Häufige Fragen“ oder Zwischenüberschriften als Frage, z. B. „Was kostet …?“. Menschen fragen KI-Suchen in ganzen Sätzen.');
        $add('structure', $words < 300 || $headings >= 2, 'Klar gegliedert',
            'Teilen Sie längere Texte mit Zwischenüberschriften in Abschnitte. So findet die KI die passende Stelle für ihre Antwort.');
        $add('depth', $legal || $words >= 250, 'Genug Inhalt zum Zitieren',
            'Die Seite hat erst '.$words.' Wörter. Erklären Sie Ihr Angebot ausführlicher, mit Beispielen, Abläufen und Besonderheiten.');
        $add('facts', $legal || $facts >= 3, 'Konkrete Angaben',
            'Nennen Sie überprüfbare Fakten: Preise ab, Öffnungszeiten, Jahre Erfahrung, Lieferzeit, Einzugsgebiet. KI-Suchen bevorzugen genaue Angaben.');
        $add('fresh', $page->updated_at === null || $page->updated_at->gt(now()->subMonths(12)), 'Aktuell',
            'Die Seite wurde seit über einem Jahr nicht bearbeitet. Prüfen Sie, ob alles noch stimmt, und speichern Sie sie neu.');

        $ok = collect($checks)->where('ok', true)->count();

        return ['score' => (int) round($ok / count($checks) * 100), 'checks' => $checks];
    }

    /**
     * Angaben für die ganze Website.
     *
     * @return list<array{key: string, ok: bool, label: string, tip: string, link?: string}>
     */
    public function siteChecks(): array
    {
        $profiles = count(array_filter(array_map(fn ($k) => self::profileUrl($k), array_keys(self::PROFILES))));

        return [
            ['key' => 'indexing', 'ok' => SiteSeo::indexingAllowed(), 'label' => 'Website ist für Suchmaschinen freigegeben',
                'tip' => 'Unter SEO ist „Für Suchmaschinen sperren“ eingeschaltet. Dann finden auch KI-Suchen Ihre Website nicht.', 'link' => route('admin.seo')],
            ['key' => 'bots', 'ok' => self::aiBotsAllowed(), 'label' => 'KI-Crawler dürfen lesen',
                'tip' => 'Sie haben KI-Crawler ausgesperrt. Das schützt Ihre Texte, aber ChatGPT und Co. können Sie dann nicht empfehlen.'],
            ['key' => 'llms', 'ok' => self::llmsEnabled(), 'label' => 'Übersicht für KI (llms.txt) ist online',
                'tip' => 'Schalten Sie die llms.txt unten ein. Sie fasst Ihre Website für KI-Systeme zusammen.'],
            ['key' => 'summary', 'ok' => mb_strlen(trim((string) Setting::get('geo_summary'))) >= 60, 'label' => 'Kurzprofil Ihres Unternehmens',
                'tip' => 'Beschreiben Sie unten in zwei, drei Sätzen, wer Sie sind, was Sie anbieten und wo. Das ist Ihre Visitenkarte für KI-Suchen.'],
            ['key' => 'company', 'ok' => (bool) (Setting::get('company_name') && Setting::get('zip_city') && (Setting::get('phone') || Setting::get('contact_email'))), 'label' => 'Firmendaten vollständig',
                'tip' => 'Tragen Sie Firmenname, Ort und Telefon oder E-Mail in den Einstellungen ein. KI-Suchen beantworten damit Fragen wie „… in meiner Nähe“.', 'link' => route('admin.settings')],
            ['key' => 'profiles', 'ok' => $profiles > 0, 'label' => 'Profile in sozialen Netzwerken verknüpft',
                'tip' => 'Verknüpfen Sie unten Ihre Profile. So erkennt die KI, dass Website und Profile zu Ihnen gehören.'],
        ];
    }
}
