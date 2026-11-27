<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Wartungsmodus: Besucher sehen einen Hinweis (503), angemeldete Administratoren die Website. */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallState::isInstalled() || Setting::get('maintenance_mode') !== '1'
            || $request->is('admin', 'admin/*', 'login', 'logout', 'install', 'install/*', 'api/*', 'up', 'newsletter/abmelden/*')
            || $request->user()?->isAdmin()) {
            return $next($request);
        }

        return response()->view('site.maintenance', [
            'message' => Setting::get('maintenance_message') ?: 'Wir bauen gerade um und sind in Kürze wieder für Sie da.',
        ], 503)->header('Retry-After', '3600');
    }
}
