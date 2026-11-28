<?php

namespace App\Support\Blocks;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Str;

/**
 * Prüft und bereinigt die Inhaltsblöcke einer Seite, bevor sie gespeichert werden.
 * Unbekannte Blocktypen und gefährliche Inhalte (z. B. Skripte im Text) fallen weg.
 * Nur der HTML-Block bleibt unverändert; er ist Administratoren vorbehalten.
 */
class BlockSanitizer
{
    public const TYPES = ['section', 'text', 'hero', 'image', 'video', 'slider', 'button', 'features', 'pricing', 'modules', 'compare', 'faq', 'cta', 'contact', 'newsletter', 'booking', 'reviews', 'html'];

    /** Symbole für Vorteils-Kacheln */
    public const ICONS = ['sparkles', 'bolt', 'shield', 'cart', 'search', 'pages', 'menu', 'image', 'globe', 'layers', 'rocket', 'heart', 'users', 'chart', 'lock', 'clock', 'check', 'code', 'puzzle', 'key', 'eye', 'mail', 'star', 'location', 'truck', 'leaf', 'award', 'smile', 'wrench', 'camera', 'gift', 'thumb', 'phone', 'calendar', 'chat', 'user', 'home', 'store', 'tag', 'send'];

    private const MAX_BLOCKS = 200;

    private const MAX_WIDGETS = 400;

    private bool $allowHtml = true;

    private ?HTMLPurifier $purifier = null;

    /** @param array<int, mixed> $blocks  @return list<array{id: string, type: string, data: array<string, mixed>}> */
    public function clean(array $blocks, bool $allowHtml = true): array
    {
        $this->allowHtml = $allowHtml;
        $out = [];
        foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            if ($block['type'] === 'html' && ! $allowHtml) {
                continue;
            }
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $id = is_string($block['id'] ?? null) && preg_match('/^[a-z0-9]{1,24}$/i', $block['id']) ? $block['id'] : Str::lower(Str::random(10));

            $out[] = ['id' => $id, 'type' => $block['type'], 'data' => $this->{'clean'.ucfirst($block['type'])}($data)];
        }

        return $out;
    }

    /** Bereinigtes HTML für Texte außerhalb von Blöcken (z. B. Produktbeschreibungen) */
    public function html(string $html): string
    {
        return $this->purify($html);
    }

    /** Nur Bilder aus der eigenen Mediathek oder sichere Adressen */
    public function image(?string $url): ?string
    {
        return $this->mediaUrl($url);
    }

    /**
     * Bereich aus dem visuellen Editor: Spalten mit Elementen (Überschrift, Text, Bild …).
     * Inhalte liegen unter "data", Aussehen unter "style", damit Übersetzungen nur Texte anfassen.
     */
    private function cleanSection(array $d): array
    {
        $layout = in_array($d['layout'] ?? null, array_keys(Builder::LAYOUTS), true) ? $d['layout'] : '1';
        $count = count(Builder::LAYOUTS[$layout]);
        $s = is_array($d['style'] ?? null) ? $d['style'] : [];
        $cols = array_values(array_filter(is_array($d['cols'] ?? null) ? $d['cols'] : [], 'is_array'));
        // Überzählige Spalten nicht verlieren: ihre Elemente wandern in die letzte Spalte
        while (count($cols) > $count) {
            $extra = array_pop($cols);
            $cols[$count - 1]['widgets'] = array_merge(is_array($cols[$count - 1]['widgets'] ?? null) ? $cols[$count - 1]['widgets'] : [], is_array($extra['widgets'] ?? null) ? $extra['widgets'] : []);
        }
        while (count($cols) < $count) {
            $cols[] = [];
        }

        $outCols = [];
        $total = 0;
        foreach ($cols as $c) {
            $cs = is_array($c['style'] ?? null) ? $c['style'] : [];
            $widgets = [];
            foreach (is_array($c['widgets'] ?? null) ? array_values($c['widgets']) : [] as $w) {
                if ($total >= self::MAX_WIDGETS || ! is_array($w) || ! isset(Builder::WIDGETS[$w['type'] ?? ''])) {
                    continue;
                }
                if ($w['type'] === 'html' && ! $this->allowHtml) {
                    continue;
                }
                $widgets[] = $this->cleanWidget($w);
                $total++;
            }
            $outCols[] = [
                'id' => $this->id($c['id'] ?? null),
                'style' => [
                    'bg' => $this->color($cs['bg'] ?? null),
                    'pad' => $this->int($cs['pad'] ?? 0, 0, 80),
                    'radius' => $this->int($cs['radius'] ?? 0, 0, 40),
                    'shadow' => (bool) ($cs['shadow'] ?? false),
                    'border' => (bool) ($cs['border'] ?? false),
                    'valign' => $this->enum($cs['valign'] ?? null, ['top', 'center', 'bottom'], 'top'),
                    'align' => $this->enum($cs['align'] ?? null, ['', 'left', 'center', 'right'], ''),
                ],
                'widgets' => $widgets,
            ];
        }

        return [
            'layout' => $layout,
            'style' => [
                'width' => $this->enum($s['width'] ?? null, ['narrow', 'boxed', 'full'], 'boxed'),
                'height' => $this->enum($s['height'] ?? null, ['auto', 's', 'm', 'screen'], 'auto'),
                'valign' => $this->enum($s['valign'] ?? null, ['top', 'center', 'bottom'], 'top'),
                'gap' => $this->enum($s['gap'] ?? null, ['none', 's', 'm', 'l'], 'm'),
                'pt' => $this->int($s['pt'] ?? 64, 0, 240),
                'pb' => $this->int($s['pb'] ?? 64, 0, 240),
                'bg' => $this->enum($s['bg'] ?? null, ['none', 'color', 'gradient', 'image', '3d'], 'none'),
                'bg_3d' => $this->enum($s['bg_3d'] ?? null, array_keys(Builder::SCENES), 'wellen'),
                'bg_color' => $this->color($s['bg_color'] ?? null),
                'bg_color2' => $this->color($s['bg_color2'] ?? null),
                'bg_angle' => $this->int($s['bg_angle'] ?? 135, 0, 360),
                'bg_image' => $this->mediaUrl($s['bg_image'] ?? null),
                'bg_pos' => $this->enum($s['bg_pos'] ?? null, ['center', 'top', 'bottom'], 'center'),
                'bg_fixed' => (bool) ($s['bg_fixed'] ?? false),
                'overlay' => $this->color($s['overlay'] ?? null),
                'overlay_opacity' => $this->int($s['overlay_opacity'] ?? 50, 0, 90),
                'text' => $this->enum($s['text'] ?? null, ['auto', 'dark', 'light'], 'auto'),
                'card' => (bool) ($s['card'] ?? false),
                'anchor' => preg_match('/^[a-z0-9][a-z0-9\-]{0,39}$/', (string) ($s['anchor'] ?? '')) ? $s['anchor'] : '',
                'hide' => $this->hide($s['hide'] ?? null),
                'reverse' => (bool) ($s['reverse'] ?? false),
            ],
            'cols' => $outCols,
        ];
    }

    private function cleanWidget(array $w): array
    {
        $type = $w['type'];
        $d = is_array($w['data'] ?? null) ? $w['data'] : [];
        $s = is_array($w['style'] ?? null) ? $w['style'] : [];
        $common = [
            'align' => $this->enum($s['align'] ?? null, ['', 'left', 'center', 'right'], ''),
            'mb' => $this->int($s['mb'] ?? 20, 0, 160),
            'hide' => $this->hide($s['hide'] ?? null),
        ];
        [$data, $style] = match ($type) {
            'heading' => [
                ['text' => $this->str($d['text'] ?? '', 300), 'link' => $this->linkUrl($d['link'] ?? null)],
                ['tag' => $this->enum($s['tag'] ?? null, ['h1', 'h2', 'h3', 'h4', 'p'], 'h2'), 'size' => $this->enum($s['size'] ?? null, ['s', 'm', 'l', 'xl', 'xxl'], 'l'),
                    'weight' => $this->enum($s['weight'] ?? null, ['normal', 'semibold', 'bold'], 'semibold'), 'color' => $this->color($s['color'] ?? null), 'gradient' => (bool) ($s['gradient'] ?? false)],
            ],
            'text' => [
                ['html' => $this->purify((string) ($d['html'] ?? ''))],
                ['size' => $this->enum($s['size'] ?? null, ['s', 'm', 'l'], 'm'), 'color' => $this->color($s['color'] ?? null)],
            ],
            'image' => [
                ['url' => $this->mediaUrl($d['url'] ?? null), 'alt' => $this->str($d['alt'] ?? '', 200), 'caption' => $this->str($d['caption'] ?? '', 300), 'link' => $this->linkUrl($d['link'] ?? null)],
                ['width' => $this->int($s['width'] ?? 100, 10, 100), 'radius' => $this->enum($s['radius'] ?? null, ['none', 's', 'm', 'l', 'full'], 'm'),
                    'shadow' => (bool) ($s['shadow'] ?? false), 'ratio' => $this->enum($s['ratio'] ?? null, ['auto', '1:1', '4:3', '3:2', '16:9', '3:4'], 'auto')],
            ],
            'button' => [
                ['label' => $this->str($d['label'] ?? '', 60), 'url' => $this->linkUrl($d['url'] ?? null), 'label2' => $this->str($d['label2'] ?? '', 60), 'url2' => $this->linkUrl($d['url2'] ?? null)],
                ['variant' => $this->enum($s['variant'] ?? null, Builder::BUTTONS, 'primary'), 'variant2' => $this->enum($s['variant2'] ?? null, Builder::BUTTONS, 'outline'),
                    'size' => $this->enum($s['size'] ?? null, ['s', 'm', 'l'], 'm'), 'full' => (bool) ($s['full'] ?? false)],
            ],
            'icon' => [
                ['icon' => in_array($d['icon'] ?? null, self::ICONS, true) ? $d['icon'] : 'sparkles', 'title' => $this->str($d['title'] ?? '', 120), 'text' => $this->str($d['text'] ?? '', 600), 'link' => $this->linkUrl($d['link'] ?? null)],
                ['layout' => $this->enum($s['layout'] ?? null, ['top', 'left'], 'top'), 'shape' => $this->enum($s['shape'] ?? null, ['plain', 'circle', 'square'], 'circle'),
                    'color' => $this->color($s['color'] ?? null), 'size' => $this->enum($s['size'] ?? null, ['s', 'm', 'l'], 'm')],
            ],
            'list' => [
                ['items' => $this->lines($d['items'] ?? [], 30, 200)],
                ['icon' => $this->enum($s['icon'] ?? null, ['check', 'arrow', 'dot', 'star'], 'check'), 'color' => $this->color($s['color'] ?? null), 'size' => $this->enum($s['size'] ?? null, ['s', 'm', 'l'], 'm')],
            ],
            'counter' => [
                ['number' => $this->str($d['number'] ?? '', 20), 'prefix' => $this->str($d['prefix'] ?? '', 10), 'suffix' => $this->str($d['suffix'] ?? '', 10), 'label' => $this->str($d['label'] ?? '', 120)],
                ['color' => $this->color($s['color'] ?? null), 'size' => $this->enum($s['size'] ?? null, ['m', 'l', 'xl'], 'l')],
            ],
            'quote' => [
                ['text' => $this->str($d['text'] ?? '', 1000), 'name' => $this->str($d['name'] ?? '', 80), 'role' => $this->str($d['role'] ?? '', 120), 'image' => $this->mediaUrl($d['image'] ?? null)],
                ['variant' => $this->enum($s['variant'] ?? null, ['card', 'plain'], 'card'), 'stars' => (bool) ($s['stars'] ?? false)],
            ],
            'video' => [$this->cleanVideo($d), []],
            'gallery' => [
                ['items' => $this->items($d['items'] ?? null, 24, fn (array $i) => ['url' => $this->mediaUrl($i['url'] ?? null), 'alt' => $this->str($i['alt'] ?? '', 200)], fn ($i) => $i['url'] !== null)],
                ['cols' => $this->int($s['cols'] ?? 3, 2, 6), 'ratio' => $this->enum($s['ratio'] ?? null, ['1:1', '4:3', '3:2', '16:9', '3:4'], '1:1'),
                    'gap' => $this->enum($s['gap'] ?? null, ['none', 's', 'm'], 's'), 'radius' => $this->enum($s['radius'] ?? null, ['none', 's', 'm', 'l'], 's')],
            ],
            'spacer' => [[], ['height' => $this->int($s['height'] ?? 48, 0, 400)]],
            'divider' => [[], ['line' => $this->enum($s['line'] ?? null, ['solid', 'dashed', 'dotted'], 'solid'), 'width' => $this->int($s['width'] ?? 100, 10, 100),
                'weight' => $this->int($s['weight'] ?? 1, 1, 10), 'color' => $this->color($s['color'] ?? null)]],
            'html' => [$this->cleanHtml($d), []],
        };

        return ['id' => $this->id($w['id'] ?? null), 'type' => $type, 'data' => $data, 'style' => $common + $style];
    }

    private function id(mixed $v): string
    {
        return is_string($v) && preg_match('/^[a-z0-9]{1,24}$/i', $v) ? $v : Str::lower(Str::random(10));
    }

    private function enum(mixed $v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function int(mixed $v, int $min, int $max): int
    {
        return max($min, min($max, (int) $v));
    }

    /** Farbe: ein Name aus der Palette (folgt dem Design) oder #rrggbb */
    private function color(mixed $v): ?string
    {
        if (is_string($v) && (isset(Builder::COLORS[$v]) || preg_match('/^#[0-9a-f]{6}$/i', $v))) {
            return strtolower($v);
        }

        return null;
    }

    /** @return list<string> */
    private function hide(mixed $v): array
    {
        return array_values(array_intersect(['desktop', 'tablet', 'phone'], is_array($v) ? $v : []));
    }

    private function cleanText(array $d): array
    {
        return ['html' => $this->purify((string) ($d['html'] ?? ''))];
    }

    private function cleanHero(array $d): array
    {
        return [
            'title' => $this->str($d['title'] ?? '', 160),
            'text' => $this->str($d['text'] ?? '', 400),
            'image' => $this->mediaUrl($d['image'] ?? null),
            'button_label' => $this->str($d['button_label'] ?? '', 60),
            'button_url' => $this->linkUrl($d['button_url'] ?? null),
            'variant' => in_array($d['variant'] ?? null, ['tech', 'video'], true) ? $d['variant'] : 'image',
            'video' => $this->mediaUrl($d['video'] ?? null),
            'overlay' => in_array($d['overlay'] ?? null, ['dark', 'brand', 'light'], true) ? $d['overlay'] : 'dark',
            'opacity' => max(0, min(90, (int) ($d['opacity'] ?? 55))),
            'height' => in_array($d['height'] ?? null, ['normal', 'tall', 'full'], true) ? $d['height'] : 'tall',
            'align' => ($d['align'] ?? '') === 'center' ? 'center' : 'left',
            'eyebrow' => $this->str($d['eyebrow'] ?? '', 80),
            'button2_label' => $this->str($d['button2_label'] ?? '', 60),
            'button2_url' => $this->linkUrl($d['button2_url'] ?? null),
        ];
    }

    private function cleanFeatures(array $d): array
    {
        $items = $this->items($d['items'] ?? null, 24, fn (array $i) => [
            'icon' => in_array($i['icon'] ?? null, self::ICONS, true) ? $i['icon'] : 'sparkles',
            'title' => $this->str($i['title'] ?? '', 120),
            'text' => $this->str($i['text'] ?? '', 500),
        ], fn ($i) => $i['title'] !== '' || $i['text'] !== '');

        return [
            ...$this->heading($d),
            'style' => in_array($d['style'] ?? null, ['grid', 'steps', 'stats'], true) ? $d['style'] : 'grid',
            'items' => $items,
        ];
    }

    private function cleanPricing(array $d): array
    {
        $plans = $this->items($d['plans'] ?? null, 6, fn (array $p) => [
            'name' => $this->str($p['name'] ?? '', 60),
            'price' => $this->str($p['price'] ?? '', 30),
            'period' => $this->str($p['period'] ?? '', 60),
            'text' => $this->str($p['text'] ?? '', 300),
            'features' => $this->lines($p['features'] ?? '', 20, 120),
            'product' => $this->slug($p['product'] ?? null),
            'button_label' => $this->str($p['button_label'] ?? '', 60),
            'button_url' => $this->linkUrl($p['button_url'] ?? null),
            'highlight' => (bool) ($p['highlight'] ?? false),
        ], fn ($p) => $p['name'] !== '');

        return [...$this->heading($d), 'plans' => $plans, 'note' => $this->str($d['note'] ?? '', 400)];
    }

    private function cleanModules(array $d): array
    {
        $items = $this->items($d['items'] ?? null, 60, fn (array $i) => [
            'name' => $this->str($i['name'] ?? '', 80),
            'text' => $this->str($i['text'] ?? '', 400),
            'kind' => in_array($i['kind'] ?? null, ['core', 'free', 'paid'], true) ? $i['kind'] : 'core',
            'category' => $this->str($i['category'] ?? '', 40),
            'price' => $this->str($i['price'] ?? '', 30),
            'product' => $this->slug($i['product'] ?? null),
            'soon' => (bool) ($i['soon'] ?? false),
        ], fn ($i) => $i['name'] !== '');

        return [...$this->heading($d), 'items' => $items, 'builder' => (bool) ($d['builder'] ?? false)];
    }

    private function cleanCompare(array $d): array
    {
        $cell = fn ($v) => in_array($v, ['yes', 'no', 'partly'], true) ? $v : $this->str($v ?? '', 120);

        return [
            ...$this->heading($d),
            'ours' => $this->str($d['ours'] ?? 'Nova CMS', 60),
            'theirs' => $this->str($d['theirs'] ?? '', 60),
            'rows' => $this->items($d['rows'] ?? null, 40, fn (array $r) => [
                'label' => $this->str($r['label'] ?? '', 160),
                'ours' => $cell($r['ours'] ?? ''),
                'theirs' => $cell($r['theirs'] ?? ''),
            ], fn ($r) => $r['label'] !== ''),
            'note' => $this->str($d['note'] ?? '', 500),
        ];
    }

    private function cleanFaq(array $d): array
    {
        return [
            ...$this->heading($d),
            'items' => $this->items($d['items'] ?? null, 60, fn (array $i) => [
                'q' => $this->str($i['q'] ?? '', 200),
                'a' => mb_substr(trim(strip_tags((string) ($i['a'] ?? ''))), 0, 2000),
            ], fn ($i) => $i['q'] !== '' && $i['a'] !== ''),
        ];
    }

    private function cleanCta(array $d): array
    {
        return [
            'title' => $this->str($d['title'] ?? '', 160),
            'text' => $this->str($d['text'] ?? '', 400),
            'button_label' => $this->str($d['button_label'] ?? '', 60),
            'button_url' => $this->linkUrl($d['button_url'] ?? null),
            'button2_label' => $this->str($d['button2_label'] ?? '', 60),
            'button2_url' => $this->linkUrl($d['button2_url'] ?? null),
            'style' => ($d['style'] ?? '') === 'light' ? 'light' : 'dark',
        ];
    }

    private function cleanContact(array $d): array
    {
        return [
            ...$this->heading($d),
            'button_label' => $this->str($d['button_label'] ?? '', 60) ?: 'Nachricht senden',
            'success' => $this->str($d['success'] ?? '', 300),
            'subjects' => $this->lines($d['subjects'] ?? '', 10, 80),
        ];
    }

    private function cleanNewsletter(array $d): array
    {
        return [
            ...$this->heading($d),
            'button_label' => $this->str($d['button_label'] ?? '', 40) ?: 'Abonnieren',
            'ask_name' => (bool) ($d['ask_name'] ?? false),
            'style' => ($d['style'] ?? '') === 'light' ? 'light' : 'dark',
        ];
    }

    private function cleanReviews(array $d): array
    {
        return [
            ...$this->heading($d),
            'limit' => max(1, min(5, (int) ($d['limit'] ?? 3))),
            'show_button' => (bool) ($d['show_button'] ?? true),
        ];
    }

    private function cleanBooking(array $d): array
    {
        return [
            ...$this->heading($d),
            'button_label' => $this->str($d['button_label'] ?? '', 40) ?: 'Termin wählen',
            'service' => max(0, (int) ($d['service'] ?? 0)),
        ];
    }

    /** Überschrift und Einleitung, die viele Blöcke gemeinsam haben */
    private function heading(array $d): array
    {
        return ['eyebrow' => $this->str($d['eyebrow'] ?? '', 80), 'title' => $this->str($d['title'] ?? '', 160), 'intro' => $this->str($d['intro'] ?? '', 600)];
    }

    /**
     * @param  callable(array): array  $map
     * @param  callable(array): bool  $keep
     * @return list<array<string, mixed>>
     */
    private function items(mixed $list, int $max, callable $map, callable $keep): array
    {
        $out = [];
        foreach (array_slice(is_array($list) ? array_values($list) : [], 0, $max) as $item) {
            if (is_array($item) && $keep($clean = $map($item))) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** Mehrzeiliger Text als Liste, z. B. die Leistungen eines Pakets */
    private function lines(mixed $v, int $max, int $len): array
    {
        $lines = is_array($v) ? $v : preg_split('/\r\n|\r|\n/', (string) $v);

        return array_values(array_slice(array_filter(array_map(fn ($l) => $this->str($l, $len), $lines), fn ($l) => $l !== ''), 0, $max));
    }

    private function slug(mixed $v): ?string
    {
        $v = trim((string) $v);

        return preg_match('/^[a-z0-9][a-z0-9\-]{0,119}$/', $v) ? $v : null;
    }

    private function cleanImage(array $d): array
    {
        return [
            'url' => $this->mediaUrl($d['url'] ?? null),
            'alt' => $this->str($d['alt'] ?? '', 200),
            'caption' => $this->str($d['caption'] ?? '', 300),
            'size' => in_array($d['size'] ?? null, ['normal', 'wide', 'full'], true) ? $d['size'] : 'normal',
        ];
    }

    private function cleanVideo(array $d): array
    {
        $url = trim((string) ($d['url'] ?? ''));
        $parsed = VideoUrl::parse($url);

        return [
            'url' => $parsed ? $url : null,
            'provider' => $parsed['provider'] ?? null,
            'video_id' => $parsed['id'] ?? null,
            'file' => $parsed ? null : $this->mediaUrl($d['file'] ?? null),
            'poster' => $this->mediaUrl($d['poster'] ?? null),
            'caption' => $this->str($d['caption'] ?? '', 300),
        ];
    }

    private function cleanSlider(array $d): array
    {
        $slides = [];
        foreach (array_slice(is_array($d['slides'] ?? null) ? $d['slides'] : [], 0, 20) as $s) {
            if (! is_array($s) || ! ($url = $this->mediaUrl($s['url'] ?? null))) {
                continue;
            }
            $slides[] = ['url' => $url, 'alt' => $this->str($s['alt'] ?? '', 200), 'caption' => $this->str($s['caption'] ?? '', 200)];
        }

        return ['slides' => $slides, 'autoplay' => (bool) ($d['autoplay'] ?? true)];
    }

    private function cleanButton(array $d): array
    {
        return [
            'label' => $this->str($d['label'] ?? '', 60),
            'url' => $this->linkUrl($d['url'] ?? null),
            'style' => ($d['style'] ?? '') === 'secondary' ? 'secondary' : 'primary',
            'align' => in_array($d['align'] ?? null, ['left', 'center', 'right'], true) ? $d['align'] : 'left',
        ];
    }

    private function cleanHtml(array $d): array
    {
        return ['code' => mb_substr((string) ($d['code'] ?? ''), 0, 100_000)];
    }

    private function str(mixed $v, int $max): string
    {
        return mb_substr(trim(strip_tags((string) $v)), 0, $max);
    }

    /** Bilder und Videos: nur eigene Uploads oder https-Adressen. */
    private function mediaUrl(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^/uploads/[A-Za-z0-9/_\-.]+$#', $v) && ! str_contains($v, '..')) {
            return $v;
        }

        return preg_match('#^https://[^\s"<>]+$#i', $v) ? $v : null;
    }

    /** Links: eigene Seiten, http(s), E-Mail, Telefon, Sprungmarken. */
    private function linkUrl(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^(/(?!/)[^\s"<>]*|https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[+0-9 ()/\-]+|\#[A-Za-z0-9_\-]+)$#i', $v)) {
            return $v;
        }
        if (preg_match('#^[a-z0-9.\-]+\.[a-z]{2,}(/[^\s"<>]*)?$#i', $v)) {
            return 'https://'.$v; // "www.beispiel.de" -> https://www.beispiel.de
        }

        return null;
    }

    private function purify(string $html): string
    {
        if ($this->purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', storage_path('framework/cache'));
            $config->set('HTML.Allowed', 'p[style],br,strong,b,em,i,u,s,mark,sub,sup,a[href|target|rel],ul,ol,li,h2[style],h3[style],h4[style],blockquote,code,pre,hr,'
                .'img[src|alt|width|height],table,colgroup,col,thead,tbody,tr,th[colspan|rowspan|style],td[colspan|rowspan|style]');
            $config->set('CSS.AllowedProperties', ['text-align']);
            $config->set('HTML.DefinitionID', 'nova-text');
            $config->set('HTML.DefinitionRev', 2);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetBlank', false);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('AutoFormat.RemoveEmpty', true);
            if ($def = $config->maybeGetRawHTMLDefinition()) {
                $def->addElement('mark', 'Inline', 'Inline', 'Common'); // Textmarker
            }
            $this->purifier = new HTMLPurifier($config);
        }

        return trim($this->purifier->purify($html));
    }
}
