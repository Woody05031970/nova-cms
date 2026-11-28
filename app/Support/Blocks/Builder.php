<?php

namespace App\Support\Blocks;

use App\Licensing\Features;
use App\Support\Design\SiteDesign;

/**
 * Der visuelle Editor: Bereiche („section“) mit Spalten und Elementen.
 *
 * Hier stehen die erlaubten Werte und die Umrechnung der Einstellungen in Klassen und CSS.
 * Dieselbe Darstellung baut resources/js/builder/render.js für die Leinwand im Editor nach;
 * Änderungen bitte an beiden Stellen vornehmen.
 */
class Builder
{
    /** Spaltenaufteilungen (Anteile je Spalte) */
    public const LAYOUTS = [
        '1' => [1], '1-1' => [1, 1], '1-1-1' => [1, 1, 1], '1-1-1-1' => [1, 1, 1, 1],
        '2-1' => [2, 1], '1-2' => [1, 2], '1-2-1' => [1, 2, 1],
    ];

    public const WIDGETS = [
        'heading' => 'Überschrift', 'text' => 'Text', 'image' => 'Bild', 'button' => 'Knopf', 'icon' => 'Symbol mit Text',
        'list' => 'Liste', 'counter' => 'Kennzahl', 'quote' => 'Zitat', 'video' => 'Video', 'gallery' => 'Galerie',
        'spacer' => 'Abstand', 'divider' => 'Trennlinie', 'html' => 'HTML',
    ];

    public const BUTTONS = ['primary', 'accent', 'outline', 'light', 'dark'];

    /** Farben, die dem Design folgen. Eigene Farben sind zusätzlich als #rrggbb erlaubt. */
    public const COLORS = [
        'brand' => ['Hauptfarbe', 'var(--color-nova-600)'],
        'brand-dark' => ['Hauptfarbe dunkel', 'var(--color-nova-900)'],
        'brand-light' => ['Hauptfarbe hell', 'var(--color-nova-50)'],
        'accent' => ['Akzentfarbe', 'var(--color-fuchsia-600)'],
        'dark' => ['Fast schwarz', '#0f172a'],
        'gray' => ['Grau', '#64748b'],
        'light' => ['Hellgrau', '#f1f5f9'],
        'white' => ['Weiß', '#ffffff'],
    ];

    public static function color(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return self::COLORS[$v][1] ?? (preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : null);
    }

    /** Ist die Farbe dunkel, sodass darauf helle Schrift gehört? */
    public static function isDark(?string $v): bool
    {
        if ($v === null) {
            return false;
        }
        $design = SiteDesign::values();
        $hex = match ($v) {
            'brand' => $design['brand'], 'accent' => $design['accent'],
            'brand-dark', 'dark' => '#0f172a', 'gray' => '#64748b',
            'brand-light', 'light', 'white' => '#ffffff',
            default => $v,
        };

        return preg_match('/^#[0-9a-f]{6}$/i', $hex) && ! SiteDesign::isLight($hex);
    }

    /** 3D-Hintergründe (Modul „3D-Effekte“, resources/js/three/nova3d.js) */
    public const SCENES = ['wellen' => 'Wellen aus Licht', 'netz' => 'Verbundenes Netz', 'kugel' => 'Leuchtende Kugel', 'formen' => 'Schwebende Formen'];

    /** Klassen und Inline-CSS eines Bereichs @return array{class: string, style: string, overlay: ?string, inner: string} */
    public static function section(array $d): array
    {
        $s = $d['style'] ?? [];
        $css = ['--nb-pt:'.(int) ($s['pt'] ?? 64).'px', '--nb-pb:'.(int) ($s['pb'] ?? 64).'px'];
        $bgCss = [];
        $dark = false;
        switch ($s['bg'] ?? 'none') {
            case 'color':
                if ($c = self::color($s['bg_color'] ?? null)) {
                    $bgCss[] = 'background-color:'.$c;
                    $dark = self::isDark($s['bg_color']);
                }
                break;
            case 'gradient':
                $a = self::color($s['bg_color'] ?? null) ?? self::COLORS['brand'][1];
                $b = self::color($s['bg_color2'] ?? null) ?? self::COLORS['accent'][1];
                $bgCss[] = 'background-image:linear-gradient('.(int) ($s['bg_angle'] ?? 135).'deg,'.$a.','.$b.')';
                $dark = self::isDark($s['bg_color'] ?? 'brand');
                break;
            case '3d':
                // Grundfarbe mit leichtem Verlauf; ohne Modul oder ohne WebGL bleibt genau diese Fläche stehen
                $a = self::color($s['bg_color'] ?? null) ?? self::COLORS['dark'][1];
                $bgCss[] = 'background-color:'.$a;
                $bgCss[] = 'background-image:radial-gradient(120% 90% at 80% 10%,color-mix(in srgb,'.(self::color($s['bg_color2'] ?? null) ?? self::COLORS['accent'][1]).' 22%,transparent),transparent 60%)';
                $dark = self::isDark($s['bg_color'] ?? 'dark');
                break;
            case 'image':
                if (! empty($s['bg_image'])) {
                    $bgCss[] = "background-image:url('".e($s['bg_image'])."')";
                    $bgCss[] = 'background-position:'.($s['bg_pos'] ?? 'center');
                    $dark = ! empty($s['overlay']) ? self::isDark($s['overlay']) && ($s['overlay_opacity'] ?? 50) >= 30 : true;
                }
                break;
        }
        $text = $s['text'] ?? 'auto';
        $dark = $text === 'light' || ($text === 'auto' && $dark);
        $card = ! empty($s['card']) && ($s['width'] ?? 'boxed') !== 'full';
        $overlay = ($s['bg'] ?? '') === 'image' && ! empty($s['bg_image']) && ! empty($s['overlay']) && ($s['overlay_opacity'] ?? 0) > 0
            ? 'background-color:'.self::color($s['overlay']).';opacity:'.round(($s['overlay_opacity']) / 100, 2) : null;

        $class = ['nb-section', 'nb-w-'.($s['width'] ?? 'boxed'), 'nb-gap-'.($s['gap'] ?? 'm'), 'nb-va-'.($s['valign'] ?? 'top')];
        if (($s['height'] ?? 'auto') !== 'auto') {
            $class[] = 'nb-h-'.$s['height'];
        }
        $class[] = $dark ? 'nb-dark' : 'nb-light';
        if ($card) {
            $class[] = 'nb-card';
        }
        if (! empty($s['bg_fixed']) && ($s['bg'] ?? '') === 'image') {
            $class[] = 'nb-fixed';
        }
        if (($s['bg'] ?? 'none') === 'image') {
            $class[] = 'nb-bg-image';
        }
        if (! empty($s['reverse'])) {
            $class[] = 'nb-reverse';
        }
        $three = null;
        if (($s['bg'] ?? 'none') === '3d') {
            $class[] = 'nb-3d';
            if (Features::on('effekte-3d')) {
                $three = ['scene' => isset(self::SCENES[$s['bg_3d'] ?? '']) ? $s['bg_3d'] : 'wellen',
                    'c1' => self::color($s['bg_color'] ?? null) ?? self::COLORS['dark'][1], 'c2' => self::color($s['bg_color2'] ?? null) ?? self::COLORS['accent'][1]];
            }
        }
        foreach ($s['hide'] ?? [] as $h) {
            $class[] = 'nb-hide-'.$h;
        }

        return [
            'class' => implode(' ', $class),
            'style' => implode(';', $card ? $css : array_merge($css, $bgCss)),
            'inner' => $card ? implode(';', $bgCss) : '',
            'overlay' => $overlay,
            'three' => $three,
            'card' => $card,
        ];
    }

    /** Beginnt die Seite mit einem Bereich, der schon eine H1 hat? Dann entfällt der Seitentitel darüber. */
    public static function startsWithH1(array $blocks): bool
    {
        $first = $blocks[0] ?? null;
        if (($first['type'] ?? null) !== 'section') {
            return false;
        }
        foreach ($first['data']['cols'] ?? [] as $col) {
            foreach ($col['widgets'] ?? [] as $w) {
                if (($w['type'] ?? null) === 'heading' && ($w['style']['tag'] ?? null) === 'h1') {
                    return true;
                }
            }
        }

        return false;
    }

    public static function gridColumns(string $layout): string
    {
        return implode(' ', array_map(fn ($f) => 'minmax(0,'.$f.'fr)', self::LAYOUTS[$layout] ?? [1]));
    }

    /** @return array{class: string, style: string} */
    public static function column(array $c): array
    {
        $s = $c['style'] ?? [];
        $class = ['nb-col', 'nb-cva-'.($s['valign'] ?? 'top')];
        if (! empty($s['align'])) {
            $class[] = 'nb-al-'.$s['align'];
        }
        if (! empty($s['shadow'])) {
            $class[] = 'nb-shadow';
        }
        if (! empty($s['border'])) {
            $class[] = 'nb-border';
        }
        $css = [];
        if ($bg = self::color($s['bg'] ?? null)) {
            $css[] = 'background-color:'.$bg;
            $class[] = self::isDark($s['bg']) ? 'nb-dark' : 'nb-light';
        }
        if (! empty($s['pad'])) {
            $css[] = 'padding:'.(int) $s['pad'].'px';
        }
        if (! empty($s['radius'])) {
            $css[] = 'border-radius:'.(int) $s['radius'].'px';
        }

        return ['class' => implode(' ', $class), 'style' => implode(';', $css)];
    }

    /** Gemeinsame Klassen und Abstand eines Elements */
    public static function widget(array $w): array
    {
        $s = $w['style'] ?? [];
        $class = ['nb-w', 'nb-w-'.$w['type']];
        if (! empty($s['align'])) {
            $class[] = 'nb-al-'.$s['align'];
        }
        foreach ($s['hide'] ?? [] as $h) {
            $class[] = 'nb-hide-'.$h;
        }

        return ['class' => implode(' ', $class), 'style' => '--nb-mb:'.(int) ($s['mb'] ?? 20).'px'];
    }

    public static function ratio(string $r): string
    {
        return $r === 'auto' ? '' : str_replace(':', ' / ', $r);
    }

    /**
     * Wandelt Bereiche in einfache Blöcke um, damit SEO-Check, Suche, Vorschaubild und
     * KI-Sichtbarkeit die Inhalte des visuellen Editors genauso lesen wie alte Seiten.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function flatten(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $b) {
            if (! is_array($b) || ($b['type'] ?? null) !== 'section') {
                $out[] = $b;

                continue;
            }
            foreach ($b['data']['cols'] ?? [] as $col) {
                foreach ($col['widgets'] ?? [] as $w) {
                    $d = $w['data'] ?? [];
                    $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
                    $item = match ($w['type'] ?? null) {
                        'heading' => ($d['text'] ?? '') === '' ? null : ['type' => 'text', 'data' => ['html' => (($tag = $w['style']['tag'] ?? 'h2') === 'p' || $tag === 'h1' ? '<p>'.$e($d['text']).'</p>' : '<'.$tag.'>'.$e($d['text']).'</'.$tag.'>')]],
                        'text' => ['type' => 'text', 'data' => ['html' => (string) ($d['html'] ?? '')]],
                        'image' => empty($d['url']) ? null : ['type' => 'image', 'data' => ['url' => $d['url'], 'alt' => $d['alt'] ?? '', 'caption' => $d['caption'] ?? '']],
                        'button' => ['type' => 'button', 'data' => ['label' => trim(($d['label'] ?? '').' '.($d['label2'] ?? '')), 'url' => $d['url'] ?? null, 'url2' => $d['url2'] ?? null]],
                        'icon' => ['type' => 'text', 'data' => ['html' => '<h3>'.$e($d['title'] ?? '').'</h3><p>'.$e($d['text'] ?? '').'</p>']],
                        'list' => ['type' => 'text', 'data' => ['html' => '<ul>'.collect($d['items'] ?? [])->map(fn ($i) => '<li>'.$e($i).'</li>')->implode('').'</ul>']],
                        'counter' => ['type' => 'text', 'data' => ['html' => '<p>'.$e(($d['prefix'] ?? '').($d['number'] ?? '').($d['suffix'] ?? '').' '.($d['label'] ?? '')).'</p>']],
                        'quote' => ['type' => 'text', 'data' => ['html' => '<blockquote><p>'.$e($d['text'] ?? '').'</p></blockquote><p>'.$e(trim(($d['name'] ?? '').' '.($d['role'] ?? ''))).'</p>']],
                        'video' => ['type' => 'video', 'data' => $d],
                        'gallery' => ['type' => 'slider', 'data' => ['slides' => array_map(fn ($i) => ['url' => $i['url'], 'alt' => $i['alt'] ?? '', 'caption' => ''], $d['items'] ?? [])]],
                        default => null,
                    };
                    if ($item) {
                        $out[] = ['id' => $w['id'] ?? '', ...$item];
                    }
                }
            }
        }

        return $out;
    }
}
