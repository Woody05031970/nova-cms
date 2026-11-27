<?php

namespace App\Http\Middleware;

use App\Support\Stats\Stats;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Modul „Besucherstatistik“: zählt nach dem Senden der Antwort, damit die Seite nicht langsamer wird. */
class CountVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (Stats::shouldCount($request, $response)) {
                Stats::record($request);
            }
        } catch (Throwable) {
            // Die Statistik darf die Website nie stören
        }
    }
}
