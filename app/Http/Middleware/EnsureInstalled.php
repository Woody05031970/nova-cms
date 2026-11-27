<?php

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use App\Support\Update\AutoUpdate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leitet vor der Installation alle Aufrufe zum Installer und sperrt
 * den Installer, sobald Nova CMS eingerichtet ist. Nach einem Update
 * werden fehlende Datenbank-Änderungen einmalig eingespielt.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = InstallState::isInstalled();
        $isInstaller = $request->is('install', 'install/*');

        if (! $installed && ! $isInstaller) {
            return redirect()->route('install.welcome');
        }

        if ($installed && $isInstaller && ! $request->is('install/fertig')) {
            abort(404);
        }

        if ($installed && ! app()->runningUnitTests()) {
            AutoUpdate::run();
        }

        return $next($request);
    }
}
