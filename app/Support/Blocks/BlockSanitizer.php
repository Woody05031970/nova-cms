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
    public const TYPES = ['text', 'hero', 'image', 'video', 'slider', 'button', 'html'];

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
        ];
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
            $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,a[href|target|rel],ul,ol,li,h2,h3,h4,blockquote,code,pre,hr');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetBlank', false);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('AutoFormat.RemoveEmpty', true);
            $this->purifier = new HTMLPurifier($config);
        }

        return trim($this->purifier->purify($html));
    }
}
