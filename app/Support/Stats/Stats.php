<?php

namespace App\Support\Stats;

use App\Licensing\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul „Besucherstatistik“: zählt Seitenaufrufe ohne Cookies und ohne personenbezogene Daten.
 * Gespeichert werden nur Summen pro Tag: Adresse, Anzahl, davon Handy, Herkunfts-Domain und Suchbegriffe.
 * Keine IP-Adressen, keine Kennungen, deshalb ist kein Cookie-Banner nötig.
 */
class Stats
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse|facebookexternalhit|whatsapp|novaturbocheck/i';

    private const SKIP = ['admin', 'admin/*', 'install', 'install/*', 'api/*', 'build/*', 'uploads/*', 'robots.txt', 'sitemap.xml', 'llms.txt', 'warenkorb', 'kasse', 'bestellung/*', 'newsletter/*', 'up'];

    public static function shouldCount(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->is(...self::SKIP)
            && ! preg_match(self::BOTS, (string) $request->userAgent())
            && $request->header('Purpose') !== 'prefetch' && $request->header('Sec-Purpose') === null
            && ! $request->user()?->isAdmin()
            && Features::on('statistik');
    }

    public static function record(Request $request): void
    {
        $day = now()->toDateString();
        $path = '/'.ltrim(mb_substr($request->path(), 0, 290), '/');
        $mobile = (int) (bool) preg_match('/Mobi|Android|iPhone|iPad/i', (string) $request->userAgent());

        self::bump('stat_views', ['day' => $day, 'path' => $path], ['views' => 1, 'mobile' => $mobile]);

        $ref = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if ($ref && $ref !== $request->getHost()) {
            self::bump('stat_sources', ['day' => $day, 'kind' => 'referrer', 'value' => mb_substr(preg_replace('/^www\./', '', mb_strtolower($ref)), 0, 200)], ['hits' => 1]);
        }
        if ($request->is('suche') && ($q = trim(mb_strtolower((string) $request->query('q')))) !== '') {
            self::bump('stat_sources', ['day' => $day, 'kind' => 'search', 'value' => mb_substr($q, 0, 200)], ['hits' => 1]);
        }
    }

    /** Zähler erhöhen oder Zeile anlegen */
    private static function bump(string $table, array $key, array $inc): void
    {
        $updated = DB::table($table)->where($key)->update(collect($inc)->mapWithKeys(fn ($v, $col) => [$col => DB::raw("{$col} + ".(int) $v)])->all());
        if (! $updated) {
            DB::table($table)->insertOrIgnore($key + $inc);
        }
    }

    /** Auswertung für die letzten $days Tage */
    public static function report(int $days): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $views = fn () => DB::table('stat_views')->where('day', '>=', $from);
        $total = (int) $views()->sum('views');
        $mobile = (int) $views()->sum('mobile');
        $perDay = $views()->selectRaw('day, SUM(views) as v')->groupBy('day')->pluck('v', 'day');
        $series = collect(range($days - 1, 0))->map(function ($i) use ($perDay) {
            $d = now()->subDays($i);

            return ['day' => $d, 'views' => (int) ($perDay[$d->toDateString()] ?? 0)];
        });
        $previous = (int) DB::table('stat_views')->whereBetween('day', [now()->subDays(2 * $days - 1)->toDateString(), now()->subDays($days)->toDateString()])->sum('views');
        $top = fn (string $kind) => DB::table('stat_sources')->where('kind', $kind)->where('day', '>=', $from)
            ->selectRaw('value, SUM(hits) as hits')->groupBy('value')->orderByDesc('hits')->limit(10)->get();

        return [
            'total' => $total,
            'previous' => $previous,
            'mobile' => $total ? (int) round($mobile / $total * 100) : 0,
            'series' => $series,
            'max' => max(1, $series->max('views')),
            'pages' => $views()->selectRaw('path, SUM(views) as views')->groupBy('path')->orderByDesc('views')->limit(10)->get(),
            'referrers' => $top('referrer'),
            'searches' => $top('search'),
        ];
    }
}
