<?php

namespace App\Http\Middleware;

use App\Licensing\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prüft die Lizenz in der Verwaltung. Die öffentliche Website bleibt immer erreichbar.
 */
class LicenseGate
{
    public function __construct(private readonly License $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Höchstens einmal am Tag beim Server nachfragen, erst nach der Antwort an den Browser
        app()->terminating(fn () => $this->license->refresh());

        if (! $this->license->adminUsable() && ! $request->routeIs('admin.license*', 'admin.account*')) {
            return redirect()->route('admin.license');
        }

        return $next($request);
    }
}
