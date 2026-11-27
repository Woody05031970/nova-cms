<?php

namespace App\Http\Middleware;

use App\Licensing\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Zeigt statt eines gesperrten Bereichs die Seite „In Ihrer Lizenz nicht enthalten“. */
class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (app(Features::class)->enabled($feature)) {
            return $next($request);
        }
        if (! $request->is('admin', 'admin/*')) {
            abort(404);
        }

        return response()->view('admin.feature-locked', ['feature' => $feature, 'info' => Features::ALL[$feature] ?? [$feature, '']], 403);
    }
}
