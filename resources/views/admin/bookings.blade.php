@extends('layouts.admin')
@section('title', 'Termine')
@section('width', 'max-w-6xl')

@php
    $badge = ['pending' => 'bg-amber-50 text-amber-900', 'confirmed' => 'bg-emerald-50 text-emerald-800', 'cancelled' => 'bg-slate-100 text-slate-600'];
    $euro = fn ($c) => $c === null ? '' : number_format($c / 100, 2, ',', '');
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Termine</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Kunden buchen freie Termine direkt auf Ihrer Website. Fügen Sie dazu auf einer Seite den Baustein „Terminbuchung“ ein oder verlinken Sie <a href="{{ route('booking.show') }}" class="font-mono text-sm text-nova-700 hover:underline" target="_blank" rel="noopener">{{ route('booking.show') }}</a>.</p>
        </div>
        @if ($pending)<p class="rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-900">{{ $pending }} {{ $pending === 1 ? 'Anfrage wartet' : 'Anfragen warten' }} auf Bestätigung</p>@endif
    </div>

    <section class="nova-card mt-8 p-0" aria-labelledby="b-list">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
            <h2 id="b-list" class="font-semibold">{{ $past ? 'Vergangene Termine' : 'Anstehende Termine' }}</h2>
            <a href="{{ route('admin.bookings', $past ? [] : ['vergangen' => 1]) }}" class="text-sm text-nova-700 hover:underline">{{ $past ? 'Anstehende zeigen' : 'Vergangene zeigen' }}</a>
        </div>
        @if ($bookings->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Keine Termine.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100" data-bookings>
                @foreach ($bookings as $b)
                    <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center">
                        <div class="w-40 shrink-0 text-sm"><p class="font-semibold">{{ $b->starts_at->locale('de')->isoFormat('dd, D. MMM') }}</p><p class="text-slate-600">{{ $b->starts_at->format('H:i') }} – {{ $b->ends_at->format('H:i') }} Uhr</p></div>
                        <div class="min-w-0 flex-1 text-sm">
                            <p><span class="font-medium">{{ $b->name }}</span> · {{ $b->service_name }} <span class="ml-1 rounded px-1.5 py-0.5 text-xs {{ $badge[$b->status] }}">{{ $b->statusLabel() }}</span></p>
                            <p class="text-slate-600"><a href="mailto:{{ $b->email }}" class="hover:underline">{{ $b->email }}</a>@if($b->phone) · <a href="tel:{{ $b->phone }}" class="hover:underline">{{ $b->phone }}</a>@endif</p>
                            @if ($b->note)<p class="mt-1 text-slate-600">„{{ $b->note }}“</p>@endif
                        </div>
                        @if ($b->status !== 'cancelled' && $b->starts_at->isFuture())
                            <div class="flex gap-2">
                                @if ($b->status === 'pending')<form method="POST" action="{{ route('admin.bookings.confirm', $b) }}">@csrf<button class="nova-btn py-2 text-sm" data-booking-confirm>Bestätigen</button></form>@endif
                                <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" onsubmit="return confirm('Termin absagen? Der Kunde bekommt eine E-Mail.')">@csrf<button class="nova-btn-secondary py-2 text-sm">Absagen</button></form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="nova-card mt-6" id="leistungen" aria-labelledby="b-services">
        <h2 id="b-services" class="font-semibold">Leistungen</h2>
        <p class="mt-1 text-sm text-slate-600">Was kann gebucht werden, und wie lange dauert es? Die Pause danach hält Zeit zum Aufräumen oder für den Weg frei.</p>
        <div class="mt-4 space-y-3">
            @foreach ($services as $sv)
                <details class="rounded-xl border border-slate-200" data-service="{{ $sv->id }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                        <span><span class="font-medium">{{ $sv->name }}</span> <span class="text-sm text-slate-500">· {{ $sv->durationLabel() }}@if($sv->priceLabel()) · {{ $sv->priceLabel() }}@endif @unless($sv->is_active) · ausgeblendet @endunless</span></span>
                        <x-icon name="chevron-down" class="size-4 text-slate-400" />
                    </summary>
                    <div class="border-t border-slate-100 p-4">
                        @include('admin.partials.service-form', ['sv' => $sv, 'action' => route('admin.bookings.services.update', $sv), 'method' => 'PUT'])
                        <form method="POST" action="{{ route('admin.bookings.services.destroy', $sv) }}" class="mt-2" onsubmit="return confirm('Leistung löschen?')">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Leistung löschen</button></form>
                    </div>
                </details>
            @endforeach
            <details class="rounded-xl border border-dashed border-slate-300" @if($services->isEmpty()) open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 font-medium text-nova-700">+ Neue Leistung</summary>
                <div class="border-t border-slate-100 p-4">@include('admin.partials.service-form', ['sv' => null, 'action' => route('admin.bookings.services.store'), 'method' => 'POST'])</div>
            </details>
        </div>
    </section>

    <section class="nova-card mt-6" id="zeiten" aria-labelledby="b-hours">
        <h2 id="b-hours" class="font-semibold">Zeiten und Regeln</h2>
        <form method="POST" action="{{ route('admin.bookings.settings') }}" class="mt-4 space-y-6">
            @csrf @method('PUT')
            <fieldset>
                <legend class="nova-label">Wann kann gebucht werden?</legend>
                <p class="text-sm text-slate-500">Zwei Zeiträume pro Tag, z. B. für die Mittagspause. Leere Felder bedeuten: geschlossen.</p>
                <div class="mt-3 space-y-2">
                    @foreach (\App\Support\Booking\BookingCalendar::WEEKDAYS as $day => $name)
                        <div class="grid grid-cols-[6.5rem_1fr] items-center gap-2 sm:grid-cols-[8rem_1fr_1fr]">
                            <span class="text-sm font-medium">{{ $name }}</span>
                            @foreach ([0, 1] as $i)
                                <div @class(['flex items-center gap-1.5', 'col-start-2 sm:col-start-auto' => $i === 1])>
                                    <label class="sr-only" for="h{{ $day }}{{ $i }}f">{{ $name }}, Zeitraum {{ $i + 1 }} von</label>
                                    <input type="time" id="h{{ $day }}{{ $i }}f" name="hours[{{ $day }}][{{ $i }}][from]" value="{{ $hours[$day][$i][0] ?? '' }}" class="nova-input py-1.5 text-sm">
                                    <span aria-hidden="true">–</span>
                                    <label class="sr-only" for="h{{ $day }}{{ $i }}t">{{ $name }}, Zeitraum {{ $i + 1 }} bis</label>
                                    <input type="time" id="h{{ $day }}{{ $i }}t" name="hours[{{ $day }}][{{ $i }}][to]" value="{{ $hours[$day][$i][1] ?? '' }}" class="nova-input py-1.5 text-sm">
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nova-label" for="closed">Geschlossene Tage</label>
                    <textarea class="nova-input" id="closed" name="closed" rows="4" placeholder="24.12.2026&#10;31.12.2026">{{ $closed }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Ein Datum pro Zeile, z. B. Urlaub oder Feiertage.</p>
                </div>
                <div class="space-y-4">
                    <fieldset>
                        <legend class="nova-label">Neue Buchungen</legend>
                        <label class="mt-1 flex items-start gap-2 text-sm"><input type="radio" name="booking_confirm" value="auto" class="mt-1 accent-nova-600" @checked($s('booking_confirm', 'auto') === 'auto')><span>Sofort bestätigen</span></label>
                        <label class="mt-1 flex items-start gap-2 text-sm"><input type="radio" name="booking_confirm" value="manual" class="mt-1 accent-nova-600" @checked($s('booking_confirm', 'auto') === 'manual')><span>Erst von mir bestätigen lassen</span></label>
                    </fieldset>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="booking_reminder" value="1" class="mt-1 accent-nova-600" @checked($s('booking_reminder', '1') === '1')><span>Kunden am Vortag per E-Mail erinnern</span></label>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div><label class="nova-label" for="lead">Vorlauf in Stunden</label><input class="nova-input" type="number" id="lead" name="booking_lead_hours" min="0" max="720" value="{{ $s('booking_lead_hours', '12') }}"></div>
                <div><label class="nova-label" for="ahead">Buchbar für Tage</label><input class="nova-input" type="number" id="ahead" name="booking_days_ahead" min="1" max="365" value="{{ $s('booking_days_ahead', '60') }}"></div>
                <div><label class="nova-label" for="step">Termine alle</label><select class="nova-input" id="step" name="booking_step">@foreach ([10, 15, 20, 30, 45, 60] as $m)<option value="{{ $m }}" @selected((int) $s('booking_step', '30') === $m)>{{ $m }} Minuten</option>@endforeach</select></div>
                <div><label class="nova-label" for="cap">Gleichzeitige Termine</label><input class="nova-input" type="number" id="cap" name="booking_capacity" min="1" max="50" value="{{ $s('booking_capacity', '1') }}"></div>
                <div><label class="nova-label" for="bmail">Benachrichtigung an</label><input class="nova-input" type="email" id="bmail" name="booking_email" value="{{ $s('booking_email') }}" placeholder="{{ $s('contact_email') }}"></div>
            </div>
            <button class="nova-btn w-full sm:w-auto" data-booking-settings>Speichern</button>
        </form>
    </section>
@endsection
