<?php

namespace App\Support\Planner;

use App\Licensing\Features;
use App\Models\Page;
use App\Support\Backup\Backups;
use App\Support\Booking\Bookings;
use App\Support\Social\SocialPublisher;

/**
 * Zeitgesteuerte Aufgaben ohne Cronjob: Beim ersten Seitenaufruf nach dem geplanten Termin
 * wird eine Seite veröffentlicht und die dazugehörigen Beiträge gehen an Facebook und Instagram.
 * Wer einen Cronjob einrichten kann, ruft zusätzlich minütlich „php artisan schedule:run“ auf.
 */
class Tick
{
    /** Geplante Seiten veröffentlichen, deren Termin erreicht ist */
    public static function publishDue(): int
    {
        if (! Features::on('redaktionsplan')) {
            return 0;
        }
        $due = Page::where('status', 'scheduled')->where('published_at', '<=', now())->get();
        foreach ($due as $page) {
            $page->update(['status' => 'published']);
        }

        return $due->count();
    }

    public static function run(): array
    {
        return ['pages' => self::publishDue(), 'posts' => app(SocialPublisher::class)->runDue(), 'reminders' => app(Bookings::class)->remind(), 'backup' => app(Backups::class)->runDue() !== null];
    }
}
