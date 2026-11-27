@extends('site.layout')
@section('title', 'Termin buchen – '.$siteName)
@section('description', 'Buchen Sie Ihren Termin online: Leistung wählen, Tag und Uhrzeit aussuchen, fertig.')

@php
    $step = ! $service ? 1 : (! $day ? 2 : 3);
    $link = fn (array $q) => route('booking.show', $q).'#buchung';
@endphp

@section('content')
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-16 sm:px-6" id="buchung">
        <h1 class="text-4xl font-semibold tracking-tight">Termin buchen</h1>
        <ol class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Schritte">
            @foreach ([1 => 'Leistung', 2 => 'Tag', 3 => 'Uhrzeit und Angaben'] as $n => $l)
                <li @class(['flex items-center gap-2', 'font-semibold text-nova-700' => $n === $step, 'text-slate-500' => $n !== $step]) @if($n === $step) aria-current="step" @endif>
                    <span @class(['grid size-6 place-items-center rounded-full text-xs', 'bg-nova-600 text-white' => $n <= $step, 'bg-slate-200 text-slate-600' => $n > $step])>{{ $n }}</span>{{ $l }}
                </li>
            @endforeach
        </ol>

        {{-- Schritt 1: Leistung --}}
        <div class="mt-8">
            <h2 class="text-lg font-semibold">1. Was möchten Sie buchen?</h2>
            @if ($services->isEmpty())
                <p class="mt-3 text-slate-600">Zurzeit sind keine Termine buchbar.</p>
            @endif
            <ul class="mt-3 grid gap-3 sm:grid-cols-2" data-booking-services>
                @foreach ($services as $s)
                    <li>
                        <a href="{{ $link(['leistung' => $s->id]) }}" @class(['block h-full rounded-2xl border p-4 transition hover:border-nova-400', 'border-nova-600 bg-nova-50 ring-2 ring-nova-600' => $service?->id === $s->id, 'border-slate-200 bg-white' => $service?->id !== $s->id]) @if($service?->id === $s->id) aria-current="true" @endif>
                            <span class="block font-semibold">{{ $s->name }}</span>
                            <span class="mt-0.5 block text-sm text-slate-600">{{ $s->durationLabel() }}@if($s->priceLabel()) · {{ $s->priceLabel() }}@endif</span>
                            @if ($s->description)<span class="mt-1 block text-sm text-slate-600">{{ $s->description }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Schritt 2: Tag --}}
        @if ($service)
            <div class="mt-10">
                <h2 class="text-lg font-semibold">2. An welchem Tag?</h2>
                @if ($days->isEmpty())
                    <p class="mt-3 text-slate-600">In den nächsten Wochen ist leider nichts mehr frei. Bitte rufen Sie uns an oder schreiben Sie uns.</p>
                @else
                    <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4" data-booking-days>
                        @foreach ($days as $d)
                            <li><a href="{{ $link(['leistung' => $service->id, 'tag' => $d->toDateString()]) }}" @class(['block rounded-xl border px-3 py-2 text-center text-sm transition hover:border-nova-400', 'border-nova-600 bg-nova-600 text-white' => $day?->isSameDay($d), 'border-slate-200 bg-white' => ! $day?->isSameDay($d)]) @if($day?->isSameDay($d)) aria-current="date" @endif>
                                <span class="block font-semibold">{{ $d->locale('de')->isoFormat('dd') }}</span>{{ $d->format('d.m.') }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Schritt 3: Uhrzeit und Angaben --}}
        @if ($service && $day)
            <form method="POST" action="{{ route('booking.store') }}" class="mt-10 space-y-6" data-booking-form>
                @csrf
                <input type="hidden" name="service" value="{{ $service->id }}">
                <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <fieldset>
                    <legend class="text-lg font-semibold">3. Uhrzeit am {{ $day->locale('de')->isoFormat('dddd, D. MMMM') }}</legend>
                    @error('start')<p class="mt-2 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                    @if ($slots->isEmpty())
                        <p class="mt-3 text-slate-600">An diesem Tag ist leider nichts mehr frei. Bitte wählen Sie einen anderen Tag.</p>
                    @else
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                            @foreach ($slots as $t)
                                <label class="cursor-pointer">
                                    <input type="radio" name="start" value="{{ $t->format('Y-m-d H:i') }}" class="peer sr-only" required @checked(old('start') === $t->format('Y-m-d H:i'))>
                                    <span class="block rounded-xl border border-slate-200 bg-white px-2 py-2 text-center text-sm font-medium peer-checked:border-nova-600 peer-checked:bg-nova-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-nova-600 peer-focus-visible:ring-offset-2 hover:border-nova-400">{{ $t->format('H:i') }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
                @if ($slots->isNotEmpty())
                    <div class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2">
                        <div><label class="nova-label" for="b-name">Ihr Name</label><input class="nova-input" id="b-name" name="name" required maxlength="160" autocomplete="name" value="{{ old('name') }}"></div>
                        <div><label class="nova-label" for="b-email">E-Mail-Adresse</label><input class="nova-input" id="b-email" type="email" name="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}"></div>
                        <div><label class="nova-label" for="b-phone">Telefon <span class="font-normal text-slate-500">(optional)</span></label><input class="nova-input" id="b-phone" type="tel" name="phone" maxlength="60" autocomplete="tel" value="{{ old('phone') }}"></div>
                        <div class="sm:col-span-2"><label class="nova-label" for="b-note">Nachricht <span class="font-normal text-slate-500">(optional)</span></label><textarea class="nova-input" id="b-note" name="note" rows="3" maxlength="2000">{{ old('note') }}</textarea></div>
                        <label class="flex items-start gap-2 text-sm text-slate-600 sm:col-span-2">
                            <input type="checkbox" name="consent" value="1" required class="mt-1 accent-nova-600">
                            <span>Ich bin einverstanden, dass meine Angaben für den Termin gespeichert werden. @if ($privacy)Mehr dazu in der <a class="underline" href="{{ $privacy->url() }}">Datenschutzerklärung</a>.@endif</span>
                        </label>
                        <div class="sm:col-span-2">
                            <button class="inline-flex items-center gap-2 rounded-xl bg-nova-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-nova-700" data-booking-submit>{{ \App\Support\Booking\BookingCalendar::autoConfirm() ? 'Verbindlich buchen' : 'Termin anfragen' }} <x-icon name="arrow" class="size-4" /></button>
                        </div>
                    </div>
                @endif
            </form>
        @endif
    </section>
@endsection
