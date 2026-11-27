<?php

namespace App\Support\Seo;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Prüft eine Seite nach den wichtigsten Regeln für Google und erklärt jedes Ergebnis in einfachen Worten.
 */
class PageAnalyzer
{
    /**
     * @param  array{title?: ?string, slug?: ?string, meta_title?: ?string, meta_description?: ?string, focus_keyword?: ?string, blocks?: ?array, is_home?: bool, purpose?: ?string}  $page
     * @return array{score: int, words: int, checks: list<array{key: string, status: string, label: string, tip: string}>}
     */
    public function analyze(array $page): array
    {
        $blocks = $page['blocks'] ?? [];
        $text = $this->text($blocks);
        $words = $this->countWords($text);
        $headings = $this->headings($blocks);
        $title = trim((string) (($page['meta_title'] ?? null) ?: ($page['title'] ?? '')));
        $fullTitle = $title.(($page['is_home'] ?? false) && empty($page['meta_title']) ? '' : ' – '.Setting::get('site_name', ''));
        $description = trim((string) ($page['meta_description'] ?? ''));
        $keyword = trim((string) ($page['focus_keyword'] ?? ''));
        $legal = in_array($page['purpose'] ?? null, ['imprint', 'privacy', 'terms', 'withdrawal', 'shipping', 'accessibility'], true);

        $checks = [];
        $add = function (string $key, string $status, string $label, string $tip = '') use (&$checks) {
            $checks[] = compact('key', 'status', 'label', 'tip');
        };

        // Titel
        $len = mb_strlen($fullTitle);
        match (true) {
            $len < 30 => $add('title', 'warn', 'Titel für Google ist kurz ('.$len.' Zeichen)', 'Nutzen Sie 30 bis 60 Zeichen. Nennen Sie, was Sie anbieten und wo, z. B. „Bäckerei in Köln-Ehrenfeld“.'),
            $len > 60 => $add('title', 'warn', 'Titel für Google ist zu lang ('.$len.' Zeichen)', 'Google schneidet nach etwa 60 Zeichen ab. Kürzen Sie den „Titel für Google“.'),
            default => $add('title', 'good', 'Titel für Google hat eine gute Länge'),
        };

        // Beschreibung
        $dlen = mb_strlen($description);
        match (true) {
            $dlen === 0 => $add('description', 'bad', 'Beschreibung für Google fehlt', 'Ohne Beschreibung nimmt Google irgendeinen Text von der Seite. Schreiben Sie ein bis zwei Sätze, die neugierig machen.'),
            $dlen < 70 => $add('description', 'warn', 'Beschreibung für Google ist kurz ('.$dlen.' Zeichen)', 'Ideal sind 120 bis 160 Zeichen.'),
            $dlen > 160 => $add('description', 'warn', 'Beschreibung für Google ist zu lang ('.$dlen.' Zeichen)', 'Google zeigt nur etwa 160 Zeichen an.'),
            default => $add('description', 'good', 'Beschreibung für Google hat eine gute Länge'),
        };

        // Fokus-Suchbegriff
        if ($keyword === '') {
            $add('keyword', $legal ? 'good' : 'warn', $legal ? 'Kein Suchbegriff nötig (Pflichtseite)' : 'Kein Fokus-Suchbegriff festgelegt',
                $legal ? '' : 'Legen Sie fest, mit welchem Suchbegriff diese Seite gefunden werden soll, z. B. „Bäckerei Köln“. Dann prüft Nova CMS, ob er an den richtigen Stellen steht.');
        } else {
            $add('kw_title', $this->contains($title, $keyword) ? 'good' : 'bad', 'Suchbegriff im Titel für Google',
                $this->contains($title, $keyword) ? '' : 'Der Suchbegriff sollte im Titel vorkommen, am besten weit vorne.');
            $add('kw_description', $this->contains($description, $keyword) ? 'good' : 'warn', 'Suchbegriff in der Beschreibung',
                $this->contains($description, $keyword) ? '' : 'Google hebt den Suchbegriff in der Beschreibung fett hervor. Das lockt mehr Klicks an.');
            $opening = implode(' ', array_slice(preg_split('/\s+/u', $text) ?: [], 0, 100));
            $add('kw_intro', $this->contains($opening, $keyword) ? 'good' : 'warn', 'Suchbegriff am Anfang des Textes',
                $this->contains($opening, $keyword) ? '' : 'Erwähnen Sie den Suchbegriff in den ersten Sätzen.');
            $inHeading = collect($headings)->contains(fn ($h) => $this->contains($h, $keyword));
            $add('kw_heading', $inHeading ? 'good' : 'warn', 'Suchbegriff in einer Überschrift',
                $inHeading ? '' : 'Verwenden Sie den Suchbegriff in mindestens einer Zwischenüberschrift.');
            if (! ($page['is_home'] ?? false)) {
                $slugOk = $this->contains(str_replace('-', ' ', (string) ($page['slug'] ?? '')), Str::slug($keyword, ' ', 'de'))
                    || $this->contains(str_replace('-', ' ', (string) ($page['slug'] ?? '')), $keyword);
                $add('kw_slug', $slugOk ? 'good' : 'warn', 'Suchbegriff in der Adresse der Seite',
                    $slugOk ? '' : 'Eine Adresse wie /'.Str::slug($keyword, '-', 'de').' hilft Google und Besuchern.');
            }
            $count = $this->occurrences($text, $keyword);
            if ($words >= 100 && $count / max(1, $words) * 100 > 3) {
                $add('kw_density', 'warn', 'Suchbegriff kommt sehr oft vor ('.$count.'×)', 'Zu häufige Wiederholung wirkt auf Google wie Werbung. Nutzen Sie auch andere Wörter.');
            }
        }

        // Textlänge
        if (! $legal) {
            match (true) {
                $words < 150 => $add('length', 'bad', 'Sehr wenig Text ('.$words.' Wörter)', 'Google bevorzugt Seiten, die eine Frage gründlich beantworten. Ziel: mindestens 300 Wörter.'),
                $words < 300 => $add('length', 'warn', 'Etwas wenig Text ('.$words.' Wörter)', 'Mit 300 Wörtern oder mehr haben Sie bessere Chancen. Die KI im Editor kann Texte ausführlicher schreiben.'),
                default => $add('length', 'good', 'Genug Text ('.$words.' Wörter)'),
            };
        }

        if ($words > 200) {
            $add('headings', count($headings) > 0 ? 'good' : 'warn', 'Text ist mit Zwischenüberschriften gegliedert',
                count($headings) > 0 ? '' : 'Gliedern Sie längere Texte mit Überschriften. Das lesen Menschen und Google lieber.');
        }

        // Bilder
        [$images, $missingAlt] = $this->images($blocks);
        if ($images > 0) {
            $add('alt', $missingAlt === 0 ? 'good' : 'warn',
                $missingAlt === 0 ? 'Alle Bilder haben eine Beschreibung' : $missingAlt.' von '.$images.' Bildern ohne Beschreibung',
                $missingAlt === 0 ? '' : 'Bildbeschreibungen helfen bei der Google-Bildersuche und blinden Besuchern. In der Mediathek kann die KI sie schreiben.');
        } elseif (! $legal) {
            $add('images', 'warn', 'Keine Bilder auf der Seite', 'Ein passendes Foto macht die Seite lebendiger, und Besucher bleiben länger.');
        }

        // Interne Links
        if (! $legal) {
            $links = $this->internalLinks($blocks);
            $add('links', $links > 0 ? 'good' : 'warn', $links > 0 ? 'Verweist auf andere Seiten Ihrer Website' : 'Keine Links zu anderen Seiten',
                $links > 0 ? '' : 'Verlinken Sie passende Seiten, z. B. mit einem Knopf zum Kontakt. Das hilft Besuchern und Google.');
        }

        $points = ['good' => 1, 'warn' => 0.5, 'bad' => 0];
        $score = (int) round(collect($checks)->avg(fn ($c) => $points[$c['status']]) * 100);

        return ['score' => $score, 'words' => $words, 'checks' => $checks];
    }

    public function analyzePage(Page $page): array
    {
        return $this->analyze($page->only(['title', 'slug', 'meta_title', 'meta_description', 'focus_keyword', 'blocks', 'is_home', 'purpose']));
    }

    /** Alle Texte eines Bausteins mit Listen (Vorteile, Pakete, Fragen …), ohne technische Felder */
    public static function structuredText(array $d): string
    {
        $skip = ['icon', 'style', 'kind', 'product', 'button_url', 'button2_url', 'highlight', 'soon', 'builder', 'variant'];
        $out = [];
        array_walk_recursive($d, function ($v, $k) use (&$out, $skip) {
            if (is_string($v) && $v !== '' && ! in_array($k, $skip, true) && ! in_array($v, ['yes', 'no', 'partly'], true)) {
                $out[] = $v;
            }
        });

        return implode('. ', $out);
    }

    /** Lesbarer Text aller Blöcke, in der Reihenfolge auf der Seite. */
    public function text(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $b) {
            $d = $b['data'] ?? [];
            $parts[] = match ($b['type'] ?? null) {
                'text' => strip_tags(preg_replace('/<\/(p|h\d|li|blockquote)>|<br\s*\/?>/i', '$0 ', (string) ($d['html'] ?? ''))),
                'hero' => ($d['title'] ?? '').'. '.($d['text'] ?? ''),
                'image' => $d['caption'] ?? '',
                'slider' => collect($d['slides'] ?? [])->pluck('caption')->implode(' '),
                'button' => $d['label'] ?? '',
                'features', 'pricing', 'modules', 'compare', 'faq', 'cta', 'contact', 'newsletter' => self::structuredText($d),
                default => '',
            };
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(implode(' ', $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** @return list<string> */
    private function headings(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $i => $b) {
            if (($b['type'] ?? null) === 'text' && preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', (string) ($b['data']['html'] ?? ''), $m)) {
                array_push($out, ...array_map(fn ($h) => trim(strip_tags($h)), $m[1]));
            }
            if (($b['type'] ?? null) === 'hero' && ! empty($b['data']['title'])) {
                $out[] = (string) $b['data']['title'];
            }
        }

        return $out;
    }

    /** @return array{int, int} */
    private function images(array $blocks): array
    {
        $total = $missing = 0;
        foreach ($blocks as $b) {
            $d = $b['data'] ?? [];
            if (($b['type'] ?? null) === 'image' && ! empty($d['url'])) {
                $total++;
                $missing += trim((string) ($d['alt'] ?? '')) === '' ? 1 : 0;
            }
            if (($b['type'] ?? null) === 'slider') {
                foreach ($d['slides'] ?? [] as $s) {
                    $total++;
                    $missing += trim((string) ($s['alt'] ?? '')) === '' ? 1 : 0;
                }
            }
        }

        return [$total, $missing];
    }

    private function internalLinks(array $blocks): int
    {
        $n = 0;
        foreach ($blocks as $b) {
            $d = $b['data'] ?? [];
            if (($b['type'] ?? null) === 'text') {
                $n += preg_match_all('/href="\/(?!\/)/i', (string) ($d['html'] ?? ''));
            }
            foreach (['url', 'button_url'] as $k) {
                if (in_array($b['type'] ?? null, ['button', 'hero'], true) && str_starts_with((string) ($d[$k] ?? ''), '/') && ! str_starts_with((string) $d[$k], '//')) {
                    $n++;
                }
            }
        }

        return $n;
    }

    public function countWords(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
    }

    /** Kommt der Suchbegriff vor? Alle Wörter des Begriffs müssen enthalten sein. */
    public function contains(string $haystack, string $keyword): bool
    {
        $haystack = mb_strtolower($haystack);
        $keyword = mb_strtolower(trim($keyword));
        if ($keyword === '') {
            return false;
        }
        if (str_contains($haystack, $keyword)) {
            return true;
        }

        $words = preg_split('/\s+/u', $keyword) ?: [];

        return collect($words)->every(fn ($w) => mb_strlen($w) < 3 || str_contains($haystack, $w));
    }

    private function occurrences(string $text, string $keyword): int
    {
        return mb_substr_count(mb_strtolower($text), mb_strtolower(trim($keyword)));
    }
}
