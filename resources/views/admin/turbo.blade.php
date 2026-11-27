@extends('layouts.admin')
@section('title', 'Turbo')
@section('width', 'max-w-4xl')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Turbo</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Turbo speichert fertige Seiten zwischen und liefert sie an Besucher sofort aus, ohne sie jedes Mal neu zu berechnen. Schnelle Seiten halten Besucher und zählen bei Google für das Ranking (Core Web Vitals).</p>

    <section class="nova-card mt-8" aria-labelledby="turbo-settings">
        <h2 id="turbo-settings" class="font-semibold">Zwischenspeicher</h2>
        <form method="POST" action="{{ route('admin.turbo.update') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <label class="flex items-start gap-3">
                <input type="checkbox" name="turbo_enabled" value="1" class="mt-1 accent-nova-600" @checked($enabled) data-turbo-enabled>
                <span><span class="font-medium">Turbo einschalten</span><span class="block text-sm text-slate-500">Gilt nur für Besucher. Wenn Sie angemeldet sind, sehen Sie immer den aktuellen Stand. Warenkorb, Kasse, Suche und Formulare bleiben immer live.</span></span>
            </label>
            <div class="max-w-xs">
                <label class="nova-label" for="turbo_minutes">Seiten neu berechnen spätestens nach</label>
                <select class="nova-input" id="turbo_minutes" name="turbo_minutes">
                    @foreach ([15 => '15 Minuten', 60 => '1 Stunde', 360 => '6 Stunden', 1440 => '24 Stunden'] as $m => $l)<option value="{{ $m }}" @selected($minutes === $m)>{{ $l }}</option>@endforeach
                </select>
                <p class="mt-1 text-sm text-slate-500">Änderungen, die Sie speichern, sind trotzdem sofort online.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="nova-btn">Speichern</button>
                <button name="measure" value="1" class="nova-btn-secondary" data-turbo-measure>Ladezeit messen</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.turbo.clear') }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm text-slate-600 hover:underline" data-turbo-clear>Zwischenspeicher jetzt leeren</button></form>

        @if ($measure)
            <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm" role="status">
                <p class="font-medium">Startseite als Besucher geladen:</p>
                <ul class="mt-1 space-y-0.5 text-slate-700">
                    @foreach ($measure as $i => $m)<li>{{ $i + 1 }}. Aufruf: <strong class="tabular-nums">{{ $m['ms'] }} ms</strong> {{ $m['cache'] === 'HIT' ? '(aus dem Zwischenspeicher)' : '(neu berechnet)' }}</li>@endforeach
                </ul>
            </div>
        @elseif (session()->has('turbo_measure'))
            <p class="mt-4 text-sm text-amber-800" role="status">Die Messung hat nicht geklappt. Manche Webspaces erlauben keine Aufrufe der eigenen Website.</p>
        @endif
    </section>

    <section class="nova-card mt-6" aria-labelledby="turbo-tips">
        <h2 id="turbo-tips" class="font-semibold">Weitere Tipps für schnelle Seiten</h2>
        @if ($bigImages->isEmpty())
            <p class="mt-2 flex items-center gap-2 text-sm text-emerald-700"><x-icon name="check" class="size-4" /> Ihre Bilder sind alle angenehm klein.</p>
        @else
            <p class="mt-1 text-sm text-slate-600">Diese Bilder sind größer als 500 KB. Laden Sie sie, wenn möglich, kleiner hoch (z. B. 1600 Pixel breit).</p>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($bigImages as $img)
                    <li class="flex justify-between gap-3 py-2"><a href="{{ route('admin.media.show', $img) }}" class="min-w-0 truncate text-nova-700 hover:underline">{{ $img->original_name }}</a><span class="shrink-0 tabular-nums text-slate-500">{{ number_format($img->size / 1024 / 1024, 1, ',', '.') }} MB</span></li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
