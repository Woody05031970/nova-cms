<?php

namespace App\Http\Middleware;

use App\Shop\ShopSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Der öffentliche Shop ist nur erreichbar, wenn er eingeschaltet ist. */
class ShopEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ShopSettings::enabled(), 404);

        return $next($request);
    }
}
