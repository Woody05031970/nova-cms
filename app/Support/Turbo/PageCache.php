<?php

namespace App\Support\Turbo;

use App\Licensing\Features;
use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Modul „Turbo“: fertige Seiten für Besucher zwischenspeichern.
 *
 * Gespeichert wird nur, was für alle Besucher gleich ist: keine angemeldeten Benutzer, kein Warenkorb,
 * keine Formular-Meldungen, keine Adressen mit Parametern. Der Schutzschlüssel für Formulare (CSRF)
 * wird beim Ausliefern für jeden Besucher frisch eingesetzt. Jede Änderung an Seiten, Produkten,
 * Menü, Medien oder Einstellungen leert den Zwischenspeicher automatisch.
 */
class PageCache
{
    private const TOKEN = '%%NOVA_CSRF%%';

    private const SKIP = ['admin', 'admin/*', 'install', 'install/*', 'api/*', 'warenkorb', 'kasse', 'bestellung/*', 'newsletter/*', 'suche', 'robots.txt', 'sitemap.xml', 'llms.txt', 'up', 'kontakt/*', 'termine', 'termine/*', 'mitglieder', 'mitglieder/*', 'konto', 'konto/*', 'passwort-neu/*', 'passwort-neu', 'community', 'community/*'];

    public static function store(): Repository
    {
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }

    public static function enabled(): bool
    {
        return Setting::get('turbo_enabled', '1') === '1' && Features::on('turbo');
    }

    public static function minutes(): int
    {
        return max(5, min(1440, (int) Setting::get('turbo_minutes', '60')));
    }

    public static function cacheable(Request $request): bool
    {
        $session = $request->hasSession() ? $request->session() : null;

        return $request->isMethod('GET')
            && $request->getQueryString() === null
            && ! $request->is(...self::SKIP)
            && $request->user() === null
            && ! ($session && ($session->has('nova.cart') && $session->get('nova.cart') !== []))
            && ! ($session && ($session->has('errors') || $session->has('status') || $session->has('_old_input')))
            && self::enabled();
    }

    public static function key(Request $request): string
    {
        return 'turbo:'.self::version().':'.sha1($request->getSchemeAndHttpHost().'/'.$request->path());
    }

    public static function get(Request $request): ?string
    {
        try {
            $html = self::store()->get(self::key($request));
        } catch (Throwable) {
            return null;
        }

        return is_string($html) ? str_replace(self::TOKEN, csrf_token(), $html) : null;
    }

    public static function put(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }
        $html = (string) $response->getContent();
        if ($html === '' || str_contains($html, 'nova-form-errors')) {
            return;
        }
        try {
            self::store()->put(self::key($request), str_replace(csrf_token(), self::TOKEN, $html), now()->addMinutes(self::minutes()));
        } catch (Throwable) {
            // Ohne Zwischenspeicher geht es auch
        }
    }

    public static function version(): int
    {
        try {
            return (int) self::store()->rememberForever('turbo_version', fn () => 1);
        } catch (Throwable) {
            return 1;
        }
    }

    /** Leert den Zwischenspeicher, indem alle bisherigen Einträge ungültig werden */
    public static function flush(): void
    {
        try {
            self::store()->forever('turbo_version', self::version() + 1);
        } catch (Throwable) {
        }
    }
}
