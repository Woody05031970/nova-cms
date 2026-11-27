<?php

namespace App\Support\Design;

use App\Licensing\Features;
use App\Models\Setting;

/**
 * Das Aussehen der öffentlichen Website: Vorlage, Farben, Schrift, Ecken, Logo und Effekte.
 * Die Werte werden als CSS-Variablen ausgegeben, dadurch wirken sie auf alle Bausteine.
 */
class SiteDesign
{
    /**
     * Vorlagen. „free“ steht in jeder Lizenz zur Verfügung, die übrigen gehören zu Design Pro.
     *
     * @var array<string, array<string, mixed>>
     */
    public const THEMES = [
        'klar' => ['label' => 'Klar', 'description' => 'Hell, ruhig und aufgeräumt. Passt fast immer.', 'brand' => '#5a37f5', 'accent' => '#d946ef', 'font' => 'system', 'radius' => 'soft', 'header' => 'light', 'footer' => 'light', 'bg' => '#ffffff', 'free' => true],
        'aurora' => ['label' => 'Aurora', 'description' => 'Technisch und modern, mit Glas-Effekt und Farbverlauf.', 'brand' => '#6d4fff', 'accent' => '#ec4899', 'font' => 'system', 'radius' => 'round', 'header' => 'glass', 'footer' => 'dark', 'bg' => '#fbfaff'],
        'studio' => ['label' => 'Studio', 'description' => 'Elegant wie ein Magazin: Serifenschrift, klare Kanten.', 'brand' => '#111827', 'accent' => '#c2410c', 'font' => 'serif', 'radius' => 'sharp', 'header' => 'light', 'footer' => 'dark', 'bg' => '#ffffff'],
        'natur' => ['label' => 'Natur', 'description' => 'Warm und freundlich, ideal für Handwerk, Hofladen und Praxis.', 'brand' => '#15803d', 'accent' => '#ca8a04', 'font' => 'rounded', 'radius' => 'round', 'header' => 'light', 'footer' => 'light', 'bg' => '#faf7f2'],
        'ozean' => ['label' => 'Ozean', 'description' => 'Frisch und vertrauenswürdig in Blau, mit großzügigen Rundungen.', 'brand' => '#0369a1', 'accent' => '#06b6d4', 'font' => 'system', 'radius' => 'round', 'header' => 'glass', 'footer' => 'dark', 'bg' => '#f8fbff'],
        'nacht' => ['label' => 'Nacht', 'description' => 'Dunkle Kopf- und Fußzeile, kräftige Akzente. Wirkt edel.', 'brand' => '#8b5cf6', 'accent' => '#22d3ee', 'font' => 'system', 'radius' => 'soft', 'header' => 'dark', 'footer' => 'dark', 'bg' => '#ffffff'],
    ];

    public const FONTS = [
        'system' => ['Modern', "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"],
        'serif' => ['Klassisch', "ui-serif, 'Iowan Old Style', 'Palatino Linotype', Palatino, Georgia, 'Times New Roman', serif"],
        'rounded' => ['Freundlich', "ui-rounded, 'SF Pro Rounded', 'Nunito', 'Varela Round', 'Segoe UI', system-ui, sans-serif"],
        'mono' => ['Technisch', "ui-monospace, 'SF Mono', 'Cascadia Code', 'Roboto Mono', Menlo, Consolas, monospace"],
    ];

    public const RADII = [
        'sharp' => ['Kantig', ['lg' => '0.25rem', 'xl' => '0.25rem', '2xl' => '0.375rem', '3xl' => '0.5rem']],
        'soft' => ['Weich', ['lg' => '0.5rem', 'xl' => '0.75rem', '2xl' => '1rem', '3xl' => '1.5rem']],
        'round' => ['Rund', ['lg' => '0.75rem', 'xl' => '1rem', '2xl' => '1.5rem', '3xl' => '2rem']],
    ];

    public const HEADERS = ['light' => 'Hell', 'glass' => 'Glas (durchscheinend)', 'dark' => 'Dunkel'];

    public const ANIMATIONS = ['none' => 'Keine', 'fade' => 'Sanft einblenden', 'rise' => 'Von unten hereingleiten', 'zoom' => 'Leicht heranzoomen'];

    /** Einstellungen, die gespeichert werden (Schlüssel in der Tabelle settings) */
    public const KEYS = ['theme', 'brand', 'accent', 'font', 'heading_font', 'radius', 'header', 'footer', 'animation', 'logo', 'logo_height', 'show_name'];

    private static ?array $preview = null;

    /** Ungespeicherte Werte aus der Vorschau im Design-Bereich */
    public static function preview(?array $values): void
    {
        self::$preview = $values;
    }

    /** @return array<string, mixed> */
    public static function values(): array
    {
        $pro = Features::on('design-pro');
        $stored = [];
        foreach (self::KEYS as $k) {
            $stored[$k] = Setting::get('design_'.$k);
        }
        $v = array_merge($stored, array_filter(self::$preview ?? [], fn ($x) => $x !== null));

        $themeKey = isset(self::THEMES[$v['theme'] ?? '']) ? $v['theme'] : 'klar';
        if (! $pro && empty(self::THEMES[$themeKey]['free'])) {
            $themeKey = 'klar';
        }
        $t = self::THEMES[$themeKey];
        $color = fn ($c, $fallback) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $fallback;

        return [
            'theme' => $themeKey,
            'brand' => $color($v['brand'] ?? null, $t['brand']),
            'accent' => $pro ? $color($v['accent'] ?? null, $t['accent']) : $t['accent'],
            'font' => $pro && isset(self::FONTS[$v['font'] ?? '']) ? $v['font'] : $t['font'],
            'heading_font' => $pro && isset(self::FONTS[$v['heading_font'] ?? '']) ? $v['heading_font'] : null,
            'radius' => isset(self::RADII[$v['radius'] ?? '']) ? $v['radius'] : $t['radius'],
            'header' => isset(self::HEADERS[$v['header'] ?? '']) ? $v['header'] : $t['header'],
            'footer' => in_array($v['footer'] ?? null, ['light', 'dark'], true) ? $v['footer'] : $t['footer'],
            'animation' => $pro && isset(self::ANIMATIONS[$v['animation'] ?? '']) ? $v['animation'] : ($pro ? 'rise' : 'none'),
            'logo' => is_string($v['logo'] ?? null) && preg_match('#^/uploads/[A-Za-z0-9/_\-.]+$#', $v['logo']) ? $v['logo'] : null,
            'logo_height' => max(20, min(96, (int) ($v['logo_height'] ?? 36))),
            'show_name' => ($v['show_name'] ?? '1') !== '0',
            'bg' => $t['bg'],
            'pro' => $pro,
        ];
    }

    /** CSS-Variablen für die Website */
    public static function css(?array $v = null): string
    {
        $v ??= self::values();
        $vars = [];
        foreach (self::palette($v['brand']) as $step => $hex) {
            $vars['--color-nova-'.$step] = $hex;
        }
        // Die Akzentfarbe ersetzt das Pink der Farbverläufe
        foreach (self::palette($v['accent']) as $step => $hex) {
            $vars['--color-fuchsia-'.$step] = $hex;
        }
        foreach (self::RADII[$v['radius']][1] as $size => $value) {
            $vars['--radius-'.$size] = $value;
        }
        $vars['--font-sans'] = self::FONTS[$v['font']][1];
        $vars['--font-heading'] = self::FONTS[$v['heading_font'] ?? $v['font']][1];
        $vars['--site-bg'] = $v['bg'];

        return ':root{'.collect($vars)->map(fn ($val, $k) => $k.':'.$val)->implode(';').'}';
    }

    /**
     * Farbabstufungen 50 bis 900 aus einer Grundfarbe (entspricht etwa Stufe 600).
     *
     * @return array<int, string>
     */
    public static function palette(string $hex): array
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $mix = fn (array $with, float $t) => sprintf('#%02x%02x%02x',
            (int) round($r + ($with[0] - $r) * $t), (int) round($g + ($with[1] - $g) * $t), (int) round($b + ($with[2] - $b) * $t));
        $white = [255, 255, 255];
        $black = [8, 6, 20];

        return [
            50 => $mix($white, 0.93), 100 => $mix($white, 0.86), 200 => $mix($white, 0.72), 300 => $mix($white, 0.52),
            400 => $mix($white, 0.3), 500 => $mix($white, 0.14), 600 => $hex, 700 => $mix($black, 0.16),
            800 => $mix($black, 0.32), 900 => $mix($black, 0.5),
        ];
    }

    /** Ist eine Farbe so hell, dass darauf dunkle Schrift nötig ist? */
    public static function isLight(string $hex): bool
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 170;
    }
}
