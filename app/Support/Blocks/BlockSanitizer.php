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
    public const TYPES = ['text', 'hero', 'image', 'video', 'slider', 'button', 'features', 'pricing', 'modules', 'compare', 'faq', 'cta', 'contact', 'newsletter', 'html'];

    /** Symbole für Vorteils-Kacheln */
    public const ICONS = ['sparkles', 'bolt', 'shield', 'cart', 'search', 'pages', 'menu', 'image', 'globe', 'layers', 'rocket', 'heart', 'users', 'chart', 'lock', 'clock', 'check', 'code', 'puzzle', 'key', 'eye', 'mail'];

    private const MAX_BLOCKS = 200;

    private ?HTMLPurifier $purifier = null;

    /** @param array<int, mixed> $blocks  @return list<array{id: string, type: string, data: array<string, mixed>}> */
    public function clean(array $blocks, bool $allowHtml = true): array
    {
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
