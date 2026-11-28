<?php

namespace App\Http\Middleware;

use App\Support\Community\Community;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Community: Gäste lesen nur mit, wenn die Community öffentlich ist. Schreiben dürfen nur
 * freigeschaltete Mitglieder und Administratoren, gesperrte Profile nicht.
 */
class CommunityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $member = $user?->canUseCommunity() ?? false;
        $reading = $request->isMethod('GET') && ! $request->routeIs('community.edit', 'community.page.edit', 'community.messages*', 'community.notifications', 'community.topic.create');

        if (! $member && ! ($reading && Community::isPublic())) {
            if ($user && ! $member) {
                abort(403, 'Ihr Zugang zur Community ist gesperrt oder noch nicht freigeschaltet.');
            }
            $request->session()->put('members.intended', $request->isMethod('GET') ? $request->fullUrl() : route('community.home'));

            return redirect()->route('members.login')->with('status', 'Bitte melden Sie sich an, um in der '.Community::title().' mitzumachen.');
        }
        if ($member) {
            Community::touch($user);
        }

        return $next($request);
    }
}
