<?php

namespace App\Support\Seo;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Technische Angaben für Suchmaschinen: Sitemap, robots.txt, Open Graph, Firmendaten (schema.org).
 */
class SiteSeo
{
    public static function indexingAllowed(): bool
    {
        return Setting::get('seo_noindex') !== '1';
    }

    public function robots(): string
    {
        if (! self::indexingAllowed()) {
            return "User-agent: *\nDisallow: /\n";
        }

        return "User-agent: *\nDisallow: /admin\nDisallow: /install\n\nSitemap: ".url('sitemap.xml')."\n";
    }

    public function sitemap(): string
    {
        $pages = Page::published()->orderByDesc('is_home')->orderBy('menu_order')->get(['slug', 'is_home', 'updated_at', 'purpose']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as $page) {
            $xml .= '  <url><loc>'.e($page->url()).'</loc><lastmod>'.$page->updated_at?->toAtomString().'</lastmod>'
                .'<priority>'.($page->is_home ? '1.0' : ($page->purpose ? '0.2' : '0.8')).'</priority></url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /** Erstes Bild der Seite für Vorschauen in sozialen Netzwerken und Messengern. */
    public function shareImage(Page $page): ?string
    {
        foreach ($page->blocks ?? [] as $b) {
            $url = match ($b['type'] ?? null) {
                'hero' => $b['data']['image'] ?? null,
                'image' => $b['data']['url'] ?? null,
                'slider' => $b['data']['slides'][0]['url'] ?? null,
                default => null,
            };
            if ($url) {
                return url($url);
            }
        }

        return null;
    }

    /** Firmendaten als schema.org/LocalBusiness für die Startseite. */
    public function businessJsonLd(): ?string
    {
        $name = Setting::get('company_name', Setting::get('site_name'));
        if (! $name) {
            return null;
        }

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $name,
            'url' => url('/'),
            'description' => Setting::get('tagline'),
            'telephone' => Setting::get('phone'),
            'email' => Setting::get('contact_email'),
            'address' => Setting::get('street') || Setting::get('zip_city') ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => Setting::get('street'),
                'postalCode' => Str::before((string) Setting::get('zip_city'), ' ') ?: null,
                'addressLocality' => Str::contains((string) Setting::get('zip_city'), ' ') ? Str::after((string) Setting::get('zip_city'), ' ') : Setting::get('zip_city'),
                'addressCountry' => 'DE',
            ]) : null,
        ]);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
}
