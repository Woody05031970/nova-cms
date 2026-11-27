<?php

namespace App\Http\Middleware;

use App\Support\Backup\Backups;
use App\Support\Booking\Bookings;
use App\Support\Install\InstallState;
use App\Support\Planner\Tick;
use App\Support\Social\SocialPublisher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Höchstens einmal pro Minute: fällige Seiten veröffentlichen, danach fällige Beiträge senden. */
class RunDueTasks
{
    private bool $due = false;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && InstallState::isInstalled() && Cache::add('nova_tick', 1, 60)) {
            $this->due = true;
            try {
                Tick::publishDue(); // vor der Antwort, damit der Besucher die Seite schon sieht
            } catch (Throwable) {
                // Nie die Website blockieren
            }
        }

        return $next($request);
    }

    /** Beiträge an Facebook und Instagram nach dem Senden der Antwort */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->due) {
            try {
                app(SocialPublisher::class)->runDue();
                app(Bookings::class)->remind();
                app(Backups::class)->runDue();
            } catch (Throwable) {
            }
        }
    }
}
