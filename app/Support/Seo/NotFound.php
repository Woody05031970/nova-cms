<?php

namespace App\Support\Seo;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\Install\InstallState;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Behandelt Aufrufe nicht vorhandener Adressen auf der öffentlichen Website. */
class NotFound
{
    /** Gibt null zurück, wenn Laravel die Standard-Fehlerseite zeigen soll (Verwaltung, API, vor der Installation). */
    public static function handle(Request $request): ?Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->expectsJson()
            || $request->is('admin', 'admin/*', 'api/*', 'install', 'install/*', 'build/*', 'uploads/*')
            || ! InstallState::isInstalled()) {
            return null;
        }

        try {
            $path = Redirect::normalize($request->getPathInfo());
            if ($redirect = Redirect::where('from_path', $path)->first()) {
                $redirect->increment('hits', 1, ['last_hit_at' => now()]);
                $target = str_starts_with($redirect->to_url, '/') ? url($redirect->to_url) : $redirect->to_url;

                return redirect()->to($target.($request->getQueryString() ? '?'.$request->getQueryString() : ''), $redirect->status);
            }
            // Typische Angriffs- und Bot-Adressen nicht protokollieren
            if (! preg_match('#\.(php|asp|aspx|env|git|sql|bak|zip|ini|cgi)$|wp-|xmlrpc|/\.|cgi-bin#i', $path)) {
                NotFoundLog::record($path, $request->headers->get('referer'));
            }

            return response()->view('site.not-found', ['siteName' => Setting::get('site_name', config('app.name'))], 404);
        } catch (Throwable) {
            return null;
        }
    }
}
