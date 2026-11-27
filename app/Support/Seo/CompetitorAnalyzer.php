<?php

namespace App\Support\Seo;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ruft eine fremde Website ab und ermittelt die Kennzahlen, die für Google zählen.
 */
class CompetitorAnalyzer
{
    private const MAX_BYTES = 3_000_000;

    private const MAX_REDIRECTS = 3;

    /**
     * @return array{url: string, https: bool, ms: int, title: string, description: string, h1: list<string>, headings: list<string>,
     *               words: int, images: int, images_missing_alt: int, internal_links: int, schema: bool, viewport: bool, keyword_hits: array<string, bool>}
     */
    public function analyze(string $url, ?string $keyword = null): array
    {
        [$finalUrl, $html, $ms] = $this->fetch($url);

        return $this->parse($html, $finalUrl, $ms, $keyword);
    }

    /** @return array{string, string, int} */
    private function fetch(string $url): array
    {
        $url = trim($url);
        if (str_contains($url, '://') && ! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Bitte geben Sie eine normale Webadresse an, z. B. https://www.beispiel.de');
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $start = microtime(true);
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $ip = $this->assertPublic($url);
            $p = parse_url($url);
            $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);

            try {
                // Die geprüfte IP wird festgehalten, damit niemand per DNS-Trick auf interne Adressen umlenkt.
                $response = Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$p['host'].':'.$port.':'.$ip]]])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NovaCMS-SEO/1.0)', 'Accept-Language' => 'de-DE,de;q=0.9'])
                    ->timeout(15)->get($url);
            } catch (ConnectionException) {
                throw new RuntimeException('Die Seite '.$url.' ist nicht erreichbar.');
            }

            if ($response->redirect() && $response->header('Location')) {
                $url = $this->absolute($response->header('Location'), $url);

                continue;
            }
            if ($response->failed()) {
                throw new RuntimeException('Die Seite antwortet mit Fehler '.$response->status().'.');
            }
            if (! str_contains(strtolower($response->header('Content-Type')), 'html')) {
                throw new RuntimeException('Unter dieser Adresse liegt keine Webseite.');
            }

            return [$url, substr($response->body(), 0, self::MAX_BYTES), (int) round((microtime(true) - $start) * 1000)];
        }

        throw new RuntimeException('Die Seite leitet zu oft weiter.');
    }

    /** Schutz: keine Abrufe ins eigene Netz (localhost, interne Adressen). */
    private function assertPublic(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            throw new RuntimeException('Bitte geben Sie eine normale Webadresse an, z. B. https://www.beispiel.de');
        }

        $ips = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : $this->resolve($host);
        if (! $ips) {
            throw new RuntimeException('Die Adresse '.$host.' wurde nicht gefunden.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Diese Adresse kann nicht geprüft werden.');
            }
        }

        return str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $v4 = gethostbynamel($host) ?: [];
        $v6 = collect(@dns_get_record($host, DNS_AAAA) ?: [])->pluck('ipv6')->filter()->all();

        return array_values(array_merge($v4, $v6));
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $root = $p['scheme'].'://'.$p['host'];

        return str_starts_with($location, '/') ? $root.$location : $root.'/'.ltrim($location, '/');
    }

    public function parse(string $html, string $url, int $ms = 0, ?string $keyword = null): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        $texts = fn (string $q) => array_values(array_filter(array_map(fn ($n) => trim(preg_replace('/\s+/u', ' ', $n->textContent)), iterator_to_array($xp->query($q))), fn ($t) => $t !== ''));
        $meta = fn (string $name) => trim((string) ($xp->query("//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='{$name}']/@content")->item(0)?->nodeValue ?? ''));

        foreach (iterator_to_array($xp->query('//script|//style|//noscript|//template')) as $n) {
            if ($n->nodeName === 'script' && strtolower($n->getAttribute('type')) === 'application/ld+json') {
                continue;
            }
            $n->parentNode?->removeChild($n);
        }

        $body = $xp->query('//body')->item(0);
        $bodyText = trim(preg_replace('/\s+/u', ' ', $body?->textContent ?? ''));
        $host = parse_url($url, PHP_URL_HOST);

        $images = iterator_to_array($xp->query('//img'));
        $links = 0;
        foreach (iterator_to_array($xp->query('//a[@href]')) as $a) {
            $href = $a->getAttribute('href');
            $h = parse_url($href, PHP_URL_HOST);
            if ((str_starts_with($href, '/') && ! str_starts_with($href, '//')) || $h === $host) {
                $links++;
            }
        }

        $title = $texts('//head/title')[0] ?? '';
        $description = $meta('description');
        $headings = $texts('//h2|//h3');
        $h1 = $texts('//h1');
        $analyzer = new PageAnalyzer;

        $hits = [];
        if ($keyword) {
            $hits = [
                'title' => $analyzer->contains($title, $keyword),
                'description' => $analyzer->contains($description, $keyword),
                'h1' => collect($h1)->contains(fn ($t) => $analyzer->contains($t, $keyword)),
                'headings' => collect($headings)->contains(fn ($t) => $analyzer->contains($t, $keyword)),
                'text' => $analyzer->contains($bodyText, $keyword),
            ];
        }

        return [
            'url' => $url,
            'https' => str_starts_with(strtolower($url), 'https://'),
            'ms' => $ms,
            'title' => $title,
            'description' => $description,
            'h1' => array_slice($h1, 0, 3),
            'headings' => array_slice($headings, 0, 25),
            'words' => $analyzer->countWords($bodyText),
            'images' => count($images),
            'images_missing_alt' => count(array_filter($images, fn ($i) => trim($i->getAttribute('alt')) === '')),
            'internal_links' => $links,
            'schema' => $xp->query("//script[@type='application/ld+json']")->length > 0,
            'viewport' => $meta('viewport') !== '',
            'keyword_hits' => $hits,
        ];
    }
}
