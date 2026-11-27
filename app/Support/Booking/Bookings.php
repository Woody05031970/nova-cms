<?php

namespace App\Support\Booking;

use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Termine anlegen, bestätigen, absagen und erinnern, jeweils mit E-Mail. */
class Bookings
{
    public function __construct(private readonly BookingCalendar $calendar) {}

    /** @param array{name: string, email: string, phone?: ?string, note?: ?string} $customer */
    public function book(Service $service, Carbon $start, array $customer): Booking
    {
        // Sperre gegen doppelte Buchung derselben Zeit
        $booking = Cache::lock('nova-booking', 10)->block(5, function () use ($service, $start, $customer) {
            if (! $this->calendar->isFree($service, $start)) {
                throw new RuntimeException('Dieser Termin ist leider gerade vergeben worden. Bitte wählen Sie eine andere Zeit.');
            }

            return DB::transaction(fn () => Booking::create([
                'service_id' => $service->id, 'service_name' => $service->name,
                'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($service->duration),
                'name' => $customer['name'], 'email' => $customer['email'], 'phone' => $customer['phone'] ?? null, 'note' => $customer['note'] ?? null,
                'status' => BookingCalendar::autoConfirm() ? 'confirmed' : 'pending', 'token' => Str::random(40),
            ]));
        });

        $this->mail($booking, $booking->status === 'confirmed' ? 'confirmed' : 'requested');
        if ($to = Setting::get('booking_email', Setting::get('contact_email'))) {
            $this->send($to, $booking, 'owner');
        }

        return $booking;
    }

    public function confirm(Booking $booking): void
    {
        $booking->update(['status' => 'confirmed']);
        $this->mail($booking, 'confirmed');
    }

    public function cancel(Booking $booking, bool $byCustomer = false): void
    {
        $booking->update(['status' => 'cancelled']);
        if ($byCustomer) {
            if ($to = Setting::get('booking_email', Setting::get('contact_email'))) {
                $this->send($to, $booking, 'owner');
            }
        }
        $this->mail($booking, 'cancelled');
    }

    /** Erinnerung am Vortag */
    public function remind(): int
    {
        if (! BookingCalendar::on() || Setting::get('booking_reminder', '1') !== '1') {
            return 0;
        }
        $due = Booking::where('status', 'confirmed')->whereNull('reminded_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])->limit(20)->get();
        foreach ($due as $b) {
            $b->update(['reminded_at' => now()]);
            $this->mail($b, 'reminder');
        }

        return $due->count();
    }

    private function mail(Booking $b, string $kind): void
    {
        $this->send($b->email, $b, $kind);
    }

    private function send(string $to, Booking $b, string $kind): void
    {
        try {
            Mail::to($to)->send(new BookingMail($b, $kind));
        } catch (Throwable $e) {
            report($e); // Der Termin bleibt gespeichert
        }
    }
}
