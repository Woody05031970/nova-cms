<?php

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use App\Support\Turbo\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Modul „Turbo“: liefert zwischengespeicherte Seiten an Besucher in Millisekunden aus. */
class Turbo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallState::isInstalled() || ! PageCache::cacheable($request)) {
            return $next($request);
        }

        if (($html = PageCache::get($request)) !== null) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Nova-Cache' => 'HIT']);
        }

        $response = $next($request);
        PageCache::put($request, $response);
        $response->headers->set('X-Nova-Cache', 'MISS');

        return $response;
    }
}
