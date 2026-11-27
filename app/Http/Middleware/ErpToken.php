<?php

namespace App\Http\Middleware;

use App\Support\Erp\Erp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Schnittstelle der Warenwirtschaft: nur mit gültigem Zugangsschlüssel */
class ErpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Erp::on()) {
            return response()->json(['message' => 'Nicht gefunden.'], 404);
        }
        if (! Erp::checkToken($request->bearerToken())) {
            return response()->json(['message' => 'Zugangsschlüssel fehlt oder ist ungültig.'], 401);
        }

        return $next($request);
    }
}
