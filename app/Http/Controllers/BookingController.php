<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Page;
use App\Models\Service;
use App\Support\Booking\BookingCalendar;
use App\Support\Booking\Bookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/**
 * Modul „Terminbuchung“ auf der Website: Leistung, Tag und Uhrzeit in drei Schritten wählen.
 * Funktioniert ohne JavaScript und ist komplett per Tastatur bedienbar.
 */
class BookingController extends Controller
{
    public function show(Request $request, BookingCalendar $calendar): View
    {
        $services = Service::active()->get();
        $service = $services->firstWhere('id', (int) $request->query('leistung')) ?? ($services->count() === 1 ? $services->first() : null);
        $day = null;
        if ($service && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('tag'))) {
            $day = Carbon::createFromFormat('Y-m-d', $request->query('tag'))->startOfDay();
        }

        return view('site.booking', [
            'services' => $services,
            'service' => $service,
            'days' => $service ? $calendar->days($service, 21) : collect(),
            'day' => $day,
            'slots' => $service && $day ? $calendar->slots($service, $day) : collect(),
            'privacy' => Page::published()->where('purpose', 'privacy')->first(),
        ]);
    }

    public function store(Request $request, Bookings $bookings): RedirectResponse
    {
        $data = $request->validate([
            'service' => ['required', 'integer'],
            'start' => ['required', 'date_format:Y-m-d H:i'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], [
            'start.required' => 'Bitte wählen Sie eine Uhrzeit.',
            'consent.accepted' => 'Bitte bestätigen Sie den Hinweis zum Datenschutz.',
        ]);
        if ($request->filled('website')) {
            abort(422); // Spam-Schutz
        }
        $service = Service::active()->findOrFail($data['service']);
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['start']);

        try {
            $booking = $bookings->book($service, $start, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['start' => $e->getMessage()]);
        }

        return redirect()->route('booking.status', $booking->token);
    }

    public function status(string $token): View
    {
        return view('site.booking-status', ['booking' => Booking::where('token', $token)->firstOrFail()]);
    }

    public function ics(string $token): Response
    {
        $b = Booking::where('token', $token)->where('status', '!=', 'cancelled')->firstOrFail();

        return response($b->ics(), 200, ['Content-Type' => 'text/calendar; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="termin.ics"']);
    }

    public function cancelForm(string $token): View
    {
        return view('site.booking-status', ['booking' => Booking::where('token', $token)->firstOrFail(), 'cancel' => true]);
    }

    public function cancel(string $token, Bookings $bookings): RedirectResponse
    {
        $b = Booking::where('token', $token)->firstOrFail();
        if ($b->status !== 'cancelled' && $b->starts_at->isFuture()) {
            $bookings->cancel($b, byCustomer: true);
        }

        return redirect()->route('booking.status', $token)->with('status', 'Ihr Termin wurde abgesagt.');
    }
}
