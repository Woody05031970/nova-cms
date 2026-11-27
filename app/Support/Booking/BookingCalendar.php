<?php

namespace App\Support\Booking;

use App\Licensing\Features;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Modul „Terminbuchung“: Öffnungszeiten für Termine, freie Zeiten und Regeln.
 *
 * Einstellungen:
 * - booking_hours: {"1": [["09:00","12:00"],["13:00","17:00"]], …} (1 = Montag … 7 = Sonntag)
 * - booking_closed: ["2026-12-24", …] geschlossene Tage
 * - booking_confirm: auto | manual
 * - booking_lead_hours, booking_days_ahead, booking_step, booking_capacity
 */
class BookingCalendar
{
    public const DEFAULT_HOURS = ['1' => [['09:00', '17:00']], '2' => [['09:00', '17:00']], '3' => [['09:00', '17:00']], '4' => [['09:00', '17:00']], '5' => [['09:00', '15:00']]];

    public const WEEKDAYS = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '7' => 'Sonntag'];

    public static function on(): bool
    {
        return Features::on('terminbuchung');
    }

    /** @return array<string, list<array{0: string, 1: string}>> */
    public static function hours(): array
    {
        $h = json_decode((string) Setting::get('booking_hours'), true);

        return is_array($h) ? $h : self::DEFAULT_HOURS;
    }

    /** @return list<string> */
    public static function closed(): array
    {
        $c = json_decode((string) Setting::get('booking_closed'), true);

        return is_array($c) ? array_values($c) : [];
    }

    public static function int(string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int) Setting::get($key, (string) $default)));
    }

    public static function autoConfirm(): bool
    {
        return Setting::get('booking_confirm', 'auto') === 'auto';
    }

    /** Tage mit mindestens einer freien Zeit, ab heute */
    public function days(Service $service, int $limit = 60): Collection
    {
        $days = collect();
        $ahead = self::int('booking_days_ahead', 60, 1, 365);
        for ($i = 0; $i < $ahead && $days->count() < $limit; $i++) {
            $day = now()->startOfDay()->addDays($i);
            if ($this->slots($service, $day)->isNotEmpty()) {
                $days->push($day);
            }
        }

        return $days;
    }

    /** Freie Anfangszeiten an einem Tag */
    public function slots(Service $service, Carbon $day): Collection
    {
        $day = $day->copy()->startOfDay();
        if (in_array($day->toDateString(), self::closed(), true) || $day->gt(now()->addDays(self::int('booking_days_ahead', 60, 1, 365)))) {
            return collect();
        }
        $earliest = now()->addHours(self::int('booking_lead_hours', 12, 0, 720));
        $step = self::int('booking_step', 30, 5, 240);
        $capacity = self::int('booking_capacity', 1, 1, 50);
        $taken = Booking::where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$day->copy()->subDay(), $day->copy()->endOfDay()])
            ->with('service:id,buffer')->get();

        $slots = collect();
        foreach (self::hours()[(string) $day->isoWeekday()] ?? [] as [$from, $to]) {
            $start = $day->copy()->setTimeFromTimeString($from);
            $end = $day->copy()->setTimeFromTimeString($to);
            for ($t = $start->copy(); $t->copy()->addMinutes($service->duration)->lte($end); $t->addMinutes($step)) {
                if ($t->lt($earliest)) {
                    continue;
                }
                $until = $t->copy()->addMinutes($service->duration + $service->buffer);
                $overlap = $taken->filter(fn (Booking $b) => $b->starts_at->lt($until)
                    && $b->ends_at->copy()->addMinutes($b->service?->buffer ?? 0)->gt($t))->count();
                if ($overlap < $capacity) {
                    $slots->push($t->copy());
                }
            }
        }

        return $slots;
    }

    public function isFree(Service $service, Carbon $start): bool
    {
        return $this->slots($service, $start)->contains(fn (Carbon $s) => $s->equalTo($start));
    }
}
