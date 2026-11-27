<?php

namespace App\Support\Seo;

use DOMDocument;
use DOMXPath;
use Throwable;

/**
 * Erfasst den Aufbau einer fremden Website: Menü, wichtigste Unterseiten mit ihren
 * Kennzahlen, angebotene Funktionen (Shop, FAQ, Bewertungen …) und häufige Begriffe.
 * Es werden nur öffentlich sichtbare Seiten gelesen, so wie ein Besucher sie sieht.
 */
class CompetitorCrawler
{
    public const MAX_PAGES = 8;

    /** Funktionen, die erkannt werden: Schlüssel => Bezeichnung */
    public const FEATURES = [
        'shop' => 'Online-Shop',
        'faq' => 'Häufige Fragen',
        'blog' => 'Ratgeber / Blog / News',
        'contact_form' => 'Kontaktformular',
        'newsletter' => 'Newsletter',
        'whatsapp' => 'WhatsApp',
        'booking' => 'Online-Terminbuchung',
        'reviews' => 'Bewertungen / Kundenstimmen',
        'video' => 'Videos',
        'prices' => 'Preise sichtbar',
        'phone' => 'Telefonnummer sichtbar',
        'local' => 'Firmendaten für Google (LocalBusiness)',
        'accessibility' => 'Erklärung zur Barrierefreiheit',
        'social' => 'Social-Media-Profile',
    ];

    private const STOPWORDS = ['aber', 'alle', 'allem', 'allen', 'aller', 'alles', 'als', 'also', 'am', 'an', 'auch', 'auf', 'aus', 'bei', 'bin', 'bis', 'bitte', 'damit', 'dann', 'das', 'dass', 'dein', 'dem', 'den', 'der', 'des', 'die', 'dies', 'diese', 'diesem', 'diesen', 'dieser', 'doch', 'dort', 'durch', 'ein', 'eine', 'einem', 'einen', 'einer', 'eines', 'euch', 'für', 'gibt', 'haben', 'hat', 'hier', 'ich', 'ihr', 'ihre', 'ihrem', 'ihren', 'ihrer', 'im', 'in', 'ist', 'jetzt', 'kann', 'kein', 'können', 'mehr', 'mit', 'nach', 'nicht', 'noch', 'nur', 'oder', 'ohne', 'sich', 'sie', 'sind', 'so', 'über', 'um', 'und', 'uns', 'unser', 'unsere', 'unserem', 'unseren', 'unserer', 'vom', 'von', 'vor', 'war', 'was', 'weitere', 'wenn', 'werden', 'wie', 'wir', 'wird', 'zu', 'zum', 'zur', 'home', 'startseite', 'menü', 'menu', 'kontakt', 'impressum', 'datenschutz', 'datenschutzerklärung', 'cookie', 'cookies', 'agb', 'login', 'suche', 'mehr', 'erfahren', 'willkommen', 'seite', 'seiten', 'zurück', 'weiter', 'alle', 'neu', 'neue', 'jahre', 'jahren', 'immer', 'sowie', 'ganz', 'gerne', 'heute', 'hallo', 'herzlich', 'gmbh', 'www', 'https', 'http', 'the', 'and', 'for', 'your', 'with'];

    public function __construct(private readonly CompetitorAnalyzer $analyzer) {}

    /** @return array<string, mixed> */
    public function crawl(string $url): array
    {
        [$homeUrl, $html, $ms] = $this->analyzer->fetch($url);
        $host = (string) parse_url($homeUrl, PHP_URL_HOST);
        $home = $this->analyzer->parse($html, $homeUrl, $ms);
        $xp = $this->xpath($html);

        $menu = $this->menu($xp, $homeUrl, $host);
        $pages = [['label' => 'Startseite', 'path' => '/'] + $home];
        $htmlAll = [$html];
        foreach (array_slice(array_filter($menu, fn ($m) => $m['internal'] && $m['path'] !== '/'), 0, self::MAX_PAGES - 1) as $item) {
            try {
                [$u, $h, $t] = $this->analyzer->fetch($item['url']);
                if (parse_url($u, PHP_URL_HOST) !== $host) {
                    continue;
                }
                $pages[] = ['label' => $item['label'], 'path' => $item['path']] + $this->analyzer->parse($h, $u, $t);
                $htmlAll[] = $h;
            } catch (Throwable) {
                // Einzelne Unterseite nicht erreichbar: weiter mit den übrigen
            }
        }

        $all = implode("\n", $htmlAll);
        $schemaTypes = $this->schemaTypes($all);
        $name = $this->siteName($xp) ?: preg_replace('/^www\./', '', $host);

        return [
            'url' => $homeUrl,
            'host' => preg_replace('/^www\./', '', $host),
            'name' => $name,
            'ms' => $ms,
            'https' => $home['https'],
            'menu' => array_map(fn ($m) => ['label' => $m['label'], 'path' => $m['path'], 'children' => $m['children']], $menu),
            'pages' => array_map(fn ($p) => [
                'label' => $p['label'], 'path' => $p['path'], 'title' => $p['title'], 'description' => $p['description'],
                'h1' => $p['h1'][0] ?? '', 'headings' => array_slice($p['headings'], 0, 12), 'words' => $p['words'],
                'images' => $p['images'], 'images_missing_alt' => $p['images_missing_alt'], 'schema' => $p['schema'],
            ], $pages),
            'features' => $this->features($all, $menu, $schemaTypes),
            'schema_types' => $schemaTypes,
            'terms' => $this->terms($pages, $name.' '.$host),
            'avg_words' => (int) round(collect($pages)->avg('words')),
            'sitemap_pages' => $this->sitemapCount($homeUrl),
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function siteName(DOMXPath $xp): string
    {
        $og = trim((string) ($xp->query("//meta[@property='og:site_name']/@content")->item(0)?->nodeValue ?? ''));
        if ($og !== '') {
            return mb_substr($og, 0, 80);
        }
        $title = trim((string) ($xp->query('//title')->item(0)?->textContent ?? ''));
        $parts = preg_split('/\s+[|–—-]\s+/u', $title);

        return mb_substr(trim((string) end($parts)), 0, 80);
    }

    /**
     * Hauptmenü: Links in nav/header, mit Unterpunkten, wenn verschachtelte Listen vorhanden sind.
     *
     * @return list<array{label: string, url: string, path: string, internal: bool, children: list<string>}>
     */
    private function menu(DOMXPath $xp, string $base, string $host): array
    {
        $navs = iterator_to_array($xp->query('//header//nav | //nav[not(ancestor::footer)] | //*[@role="navigation"][not(ancestor::footer)]'));
        if (! $navs) {
            $navs = iterator_to_array($xp->query('//header'));
        }
        $items = [];
        $seen = [];
        foreach ($navs as $nav) {
            $tops = iterator_to_array($xp->query('./ul/li | ./div/ul/li | ./*/ul/li', $nav)) ?: [];
            if (! $tops) {
                foreach (iterator_to_array($xp->query('.//a[@href]', $nav)) as $a) {
                    $tops[] = $a;
                }
            }
            foreach ($tops as $node) {
                $a = $node->nodeName === 'a' ? $node : $xp->query('./a[@href] | ./*/a[@href]', $node)->item(0);
                if (! $a) {
                    continue;
                }
                $label = trim(preg_replace('/\s+/u', ' ', $a->textContent));
                $href = trim($a->getAttribute('href'));
                if ($label === '' || mb_strlen($label) > 40 || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
                    continue;
                }
                $abs = $this->absolute($href, $base);
                $path = (string) (parse_url($abs, PHP_URL_PATH) ?: '/');
                $key = mb_strtolower($label);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $children = [];
                if ($node->nodeName === 'li') {
                    foreach (iterator_to_array($xp->query('.//ul//a', $node)) as $c) {
                        $t = trim(preg_replace('/\s+/u', ' ', $c->textContent));
                        if ($t !== '' && mb_strlen($t) <= 50) {
                            $children[] = $t;
                        }
                    }
                }
                $items[] = ['label' => $label, 'url' => $abs, 'path' => $path, 'internal' => preg_replace('/^www\./', '', (string) parse_url($abs, PHP_URL_HOST)) === preg_replace('/^www\./', '', $host), 'children' => array_slice(array_values(array_unique($children)), 0, 12)];
                if (count($items) >= 14) {
                    break 2;
                }
            }
        }

        return $items;
    }

    private function absolute(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $root = $p['scheme'].'://'.$p['host'];
        if (str_starts_with($href, '//')) {
            return $p['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $root.$href;
        }
        $dir = rtrim(dirname($p['path'] ?? '/'), '/');

        return $root.$dir.'/'.$href;
    }

    /** @return list<string> */
    private function schemaTypes(string $html): array
    {
        preg_match_all('/"@type"\s*:\s*"([A-Za-z]+)"/', $html, $m);
        preg_match_all('/itemtype="https?:\/\/schema\.org\/([A-Za-z]+)"/i', $html, $m2);

        return array_values(array_unique(array_merge($m[1], $m2[1])));
    }

    /** @return array<string, bool> */
    private function features(string $html, array $menu, array $schema): array
    {
        $h = mb_strtolower($html);
        $labels = mb_strtolower(implode(' ', array_merge(array_column($menu, 'label'), ...array_column($menu, 'children'))));
        $has = fn (string $re) => (bool) preg_match($re, $h);

        return [
            'shop' => in_array('Product', $schema, true) || $has('#(warenkorb|in den warenkorb|add-to-cart|/cart|/shop|woocommerce|shopify)#'),
            'faq' => in_array('FAQPage', $schema, true) || str_contains($labels, 'faq') || str_contains($labels, 'fragen') || $has('#häufig(e|)\s+(gestellte\s+)?fragen#u'),
            'blog' => str_contains($labels, 'blog') || str_contains($labels, 'news') || str_contains($labels, 'ratgeber') || str_contains($labels, 'aktuelles') || str_contains($labels, 'magazin') || in_array('BlogPosting', $schema, true) || in_array('Article', $schema, true),
            'contact_form' => $has('#<form[^>]*>(?:(?!</form>).)*(type="email"|name="[^"]*mail)#s') && $has('#<textarea#'),
            'newsletter' => $has('#newsletter#'),
            'whatsapp' => $has('#(wa\.me/|api\.whatsapp\.com|whatsapp)#'),
            'booking' => $has('#(termin\s*(buchen|vereinbaren|online)|online-?buchung|calendly|doctolib|etermin|shore\.com|treatwell)#u'),
            'reviews' => in_array('AggregateRating', $schema, true) || in_array('Review', $schema, true) || $has('#(kundenstimmen|bewertungen|rezensionen|trustpilot|provenexpert|google-bewertung)#u'),
            'video' => $has('#(<video|youtube\.com/embed|youtube-nocookie|player\.vimeo)#'),
            'prices' => $has('#\d+[,.]\d{2}\s*(€|eur)|€\s*\d+#u'),
            'phone' => $has('#href="tel:#'),
            'local' => (bool) array_intersect($schema, ['LocalBusiness', 'Organization', 'Store', 'Restaurant', 'Bakery', 'MedicalBusiness', 'HomeAndConstructionBusiness', 'ProfessionalService']),
            'accessibility' => $has('#barrierefreiheit#u'),
            'social' => $has('#(instagram\.com/|facebook\.com/|linkedin\.com/|tiktok\.com/@|youtube\.com/(c/|channel/|@))#'),
        ];
    }

    /**
     * Häufige Begriffe aus Titeln und Überschriften (das, worauf der Wettbewerber optimiert).
     *
     * @return array<string, int>
     */
    private function terms(array $pages, string $brand = ''): array
    {
        // Der eigene Name des Mitbewerbers ist kein Suchbegriff, den man übernehmen sollte
        $brandWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($brand));
        $counts = [];
        foreach ($pages as $p) {
            $text = implode(' ', array_merge([$p['title'], $p['description']], $p['h1'], $p['headings']));
            foreach (preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($text)) as $w) {
                $w = trim($w, '-');
                if (mb_strlen($w) >= 4 && ! is_numeric($w) && ! in_array($w, self::STOPWORDS, true) && ! in_array($w, $brandWords, true)) {
                    $counts[$w] = ($counts[$w] ?? 0) + 1;
                }
            }
        }
        arsort($counts);

        return array_slice(array_filter($counts, fn ($c) => $c >= 2), 0, 25, true);
    }

    private function sitemapCount(string $home): ?int
    {
        $p = parse_url($home);
        try {
            [, $xml] = $this->fetchSitemap($p['scheme'].'://'.$p['host'].'/sitemap.xml');
        } catch (Throwable) {
            return null;
        }

        return substr_count(strtolower($xml), '<loc>') ?: null;
    }

    /** @return array{string, string} */
    protected function fetchSitemap(string $url): array
    {
        [$u, $body] = $this->analyzer->fetch($url, html: false);

        return [$u, $body];
    }
}
