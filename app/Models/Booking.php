<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gebuchter Termin (Modul Terminbuchung) */
class Booking extends Model
{
    public const STATUS = ['pending' => 'Angefragt', 'confirmed' => 'Bestätigt', 'cancelled' => 'Abgesagt'];

    protected $fillable = ['service_id', 'service_name', 'starts_at', 'ends_at', 'name', 'email', 'phone', 'note', 'status', 'token', 'reminded_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function when(): string
    {
        return $this->starts_at->locale('de')->isoFormat('dddd, D. MMMM YYYY').', '.$this->starts_at->format('H:i').' Uhr';
    }

    /** Kalendereintrag zum Herunterladen (iCalendar) */
    public function ics(): string
    {
        $esc = fn (string $s) => addcslashes(str_replace(["\r", "\n"], ' ', $s), ',;\\');
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $site = (string) Setting::get('site_name', config('app.name'));

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Nova CMS//Termin//DE', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:'.$this->token.'@'.parse_url(url('/'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()), 'DTSTART:'.$fmt($this->starts_at), 'DTEND:'.$fmt($this->ends_at),
            'SUMMARY:'.$esc($this->service_name.' – '.$site),
            'LOCATION:'.$esc(trim(Setting::get('street').' '.Setting::get('zip_city'))),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }
}
