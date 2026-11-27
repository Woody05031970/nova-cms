<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Setting;
use App\Shop\Money;
use App\Support\Booking\BookingCalendar;
use App\Support\Booking\Bookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Modul „Terminbuchung“ in der Verwaltung: Termine, Leistungen, Zeiten und Regeln */
class BookingAdminController extends Controller
{
    public function index(Request $request): View
    {
        $past = $request->boolean('vergangen');

        return view('admin.bookings', [
            'bookings' => Booking::query()
                ->when($past, fn ($q) => $q->where('starts_at', '<', now())->orderByDesc('starts_at'), fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'))
                ->limit(100)->get(),
            'past' => $past,
            'pending' => Booking::where('status', 'pending')->where('starts_at', '>=', now())->count(),
            'services' => Service::orderBy('sort')->orderBy('name')->get(),
            'hours' => BookingCalendar::hours(),
            'closed' => implode("\n", array_map(fn ($d) => date('d.m.Y', strtotime($d)), BookingCalendar::closed())),
            's' => fn ($k, $d = null) => Setting::get($k, $d),
        ]);
    }

    public function confirm(Booking $booking, Bookings $bookings): RedirectResponse
    {
        $bookings->confirm($booking);

        return back()->with('status', 'Termin bestätigt. '.$booking->name.' hat eine E-Mail bekommen.');
    }

    public function cancel(Booking $booking, Bookings $bookings): RedirectResponse
    {
        $bookings->cancel($booking);

        return back()->with('status', 'Termin abgesagt. '.$booking->name.' wurde per E-Mail informiert.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        Service::create($this->serviceData($request) + ['sort' => (int) Service::max('sort') + 1]);

        return redirect()->to(route('admin.bookings').'#leistungen')->with('status', 'Leistung angelegt.');
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->serviceData($request));

        return redirect()->to(route('admin.bookings').'#leistungen')->with('status', '„'.$service->name.'“ gespeichert.');
    }

    public function destroyService(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->to(route('admin.bookings').'#leistungen')->with('status', 'Leistung gelöscht. Bereits gebuchte Termine bleiben erhalten.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['array'],
            'hours.*.*.from' => ['nullable', 'date_format:H:i'],
            'hours.*.*.to' => ['nullable', 'date_format:H:i'],
            'closed' => ['nullable', 'string', 'max:5000'],
            'booking_confirm' => ['required', 'in:auto,manual'],
            'booking_lead_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'booking_days_ahead' => ['required', 'integer', 'min:1', 'max:365'],
            'booking_step' => ['required', 'in:10,15,20,30,45,60'],
            'booking_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'booking_email' => ['nullable', 'email', 'max:255'],
        ]);

        $hours = [];
        foreach (BookingCalendar::WEEKDAYS as $day => $name) {
            foreach ($data['hours'][$day] ?? [] as $range) {
                if (! empty($range['from']) && ! empty($range['to']) && $range['from'] < $range['to']) {
                    $hours[$day][] = [$range['from'], $range['to']];
                }
            }
        }
        $closed = collect(preg_split('/[\s,;]+/', (string) ($data['closed'] ?? '')))->filter()
            ->map(fn ($d) => ($t = \DateTime::createFromFormat('d.m.Y', $d) ?: \DateTime::createFromFormat('Y-m-d', $d)) ? $t->format('Y-m-d') : null)
            ->filter()->unique()->sort()->values()->all();

        Setting::putMany([
            'booking_hours' => json_encode($hours),
            'booking_closed' => json_encode($closed),
            'booking_confirm' => $data['booking_confirm'],
            'booking_lead_hours' => (string) $data['booking_lead_hours'],
            'booking_days_ahead' => (string) $data['booking_days_ahead'],
            'booking_step' => (string) $data['booking_step'],
            'booking_capacity' => (string) $data['booking_capacity'],
            'booking_email' => $data['booking_email'] ?? null,
            'booking_reminder' => $request->boolean('booking_reminder') ? '1' : '0',
        ]);

        return redirect()->to(route('admin.bookings').'#zeiten')->with('status', 'Zeiten und Regeln gespeichert.');
    }

    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration' => ['required', 'integer', 'min:5', 'max:600'],
            'buffer' => ['nullable', 'integer', 'min:0', 'max:240'],
            'price' => ['nullable', 'string', 'max:20'],
        ], ['name.required' => 'Bitte geben Sie der Leistung einen Namen.']);

        return [
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'duration' => $data['duration'],
            'buffer' => (int) ($data['buffer'] ?? 0), 'price_cents' => filled($data['price'] ?? null) ? Money::parse($data['price']) : null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
