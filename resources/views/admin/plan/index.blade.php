@extends('layouts.admin')
@section('title', 'Redaktionsplan')
@section('width', 'max-w-7xl')

@php
    $color = ['website' => 'bg-nova-50 text-nova-800 ring-nova-200', 'newsletter' => 'bg-amber-50 text-amber-900 ring-amber-200', 'facebook' => 'bg-blue-50 text-blue-900 ring-blue-200',
        'instagram' => 'bg-pink-50 text-pink-900 ring-pink-200', 'linkedin' => 'bg-sky-50 text-sky-900 ring-sky-200', 'aktion' => 'bg-emerald-50 text-emerald-900 ring-emerald-200'];
    $start = $month->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $end = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $label = fn ($e) => match ($e['kind']) { 'page' => 'Seite geplant', 'published' => ($e['count'] === 1 ? 'Seite' : $e['count'].' Seiten').' online', 'social' => $channels[$e['channel']] ?? $e['channel'], default => $channels[$e['channel']] ?? '' };
    $doneLabel = fn ($e) => match ($e['kind']) { 'social' => 'gesendet', 'published' => '', default => 'erledigt' };
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Redaktionsplan</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Planen Sie Themen, Aktionen und Beiträge. Seiten mit Termin gehen automatisch online, Beiträge für Facebook und Instagram gleich mit.</p>
        </div>
        <nav class="flex items-center gap-1" aria-label="Monat wählen">
            <a href="{{ route('admin.plan', ['monat' => $month->copy()->subMonth()->format('Y-m')]) }}" class="nova-btn-secondary px-3" aria-label="Vorheriger Monat">‹</a>
            <p class="min-w-36 text-center font-semibold" aria-live="polite" data-plan-month>{{ $month->locale('de')->isoFormat('MMMM YYYY') }}</p>
            <a href="{{ route('admin.plan', ['monat' => $month->copy()->addMonth()->format('Y-m')]) }}" class="nova-btn-secondary px-3" aria-label="Nächster Monat">›</a>
            @unless ($month->isSameMonth(now()))<a href="{{ route('admin.plan') }}" class="ml-1 text-sm text-nova-700 hover:underline">Heute</a>@endunless
        </nav>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Kalender: ab Tablet quer als Monatsraster, auf dem Handy als Liste --}}
        <section class="nova-card overflow-hidden p-0" aria-label="Kalender {{ $month->locale('de')->isoFormat('MMMM YYYY') }}">
            <div class="hidden grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold text-slate-600 md:grid" aria-hidden="true">
                @foreach ($weekdays as $w)<div class="py-2">{{ $w }}</div>@endforeach
            </div>
            <ol class="md:grid md:grid-cols-7" data-plan-calendar>
                @for ($d = $start->copy(); $d->lte($end); $d->addDay())
                    @php $key = $d->toDateString(); $entries = $days->get($key, collect()); $inMonth = $d->isSameMonth($month); @endphp
                    <li @class(['border-slate-100 md:min-h-28 md:border-r md:border-b md:p-1.5', 'hidden md:block' => ! $inMonth || $entries->isEmpty(), 'border-b px-4 py-3 md:px-1.5 md:py-1.5' => $inMonth, 'bg-slate-50/60' => ! $inMonth]) @if($entries->isNotEmpty()) data-plan-day="{{ $key }}" @endif>
                        <p @class(['text-xs font-semibold', 'text-slate-400' => ! $inMonth, 'text-slate-700' => $inMonth])>
                            <span @class(['inline-grid size-6 place-items-center rounded-full', 'bg-nova-600 text-white' => $d->isToday()])>{{ $d->day }}</span>
                            <span class="md:hidden">{{ $d->locale('de')->isoFormat('dddd, D. MMMM') }}</span>
                        </p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($entries as $e)
                                <li class="rounded-md px-1.5 py-1 text-xs ring-1 ring-inset {{ $color[$e['channel']] ?? 'bg-slate-50 text-slate-800 ring-slate-200' }}">
                                    <span class="block font-mono text-[10px] tracking-wide uppercase opacity-80">{{ $label($e) }}@isset($e['time']) · {{ $e['time'] }}@endisset @if(! empty($e['failed'])) · fehlgeschlagen @elseif($e['done'] && $doneLabel($e)) · {{ $doneLabel($e) }} @endif</span>
                                    @if (in_array($e['kind'], ['page', 'published'], true) && $e['page'])
                                        <a href="{{ route('admin.pages.edit', $e['page']) }}" class="font-medium hover:underline">{{ $e['title'] }}</a>
                                    @elseif ($e['kind'] === 'item')
                                        <span @class(['font-medium', 'line-through' => $e['done']])>{{ $e['title'] }}</span>
                                        @if ($e['item']->notes)<span class="block opacity-80">{{ \Illuminate\Support\Str::limit($e['item']->notes, 80) }}</span>@endif
                                        <span class="mt-1 flex flex-wrap gap-x-2">
                                            <form method="POST" action="{{ route('admin.plan.update', $e['item']) }}">@csrf @method('PUT')<input type="hidden" name="toggle" value="1"><button class="underline">{{ $e['done'] ? 'Wieder öffnen' : 'Erledigt' }}</button></form>
                                            <form method="POST" action="{{ route('admin.plan.destroy', $e['item']) }}">@csrf @method('DELETE')<button class="underline" aria-label="„{{ $e['title'] }}“ entfernen">Entfernen</button></form>
                                        </span>
                                    @else
                                        <span>{{ $e['title'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endfor
            </ol>
            @if ($days->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-slate-600 md:hidden">In diesem Monat ist noch nichts geplant.</p>
            @endif
        </section>

        <div class="space-y-6">
            <section class="nova-card" aria-labelledby="plan-new">
                <h2 id="plan-new" class="font-semibold">Thema oder Aktion eintragen</h2>
                <form method="POST" action="{{ route('admin.plan.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="nova-label" for="title">Thema</label>
                        <input class="nova-input" id="title" name="title" value="{{ old('title') }}" required maxlength="160" placeholder="z. B. Sommerschlussverkauf ankündigen" @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                        @error('title')<p id="title-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="nova-label" for="date">Datum</label>
                            <input class="nova-input" type="date" id="date" name="date" value="{{ old('date', $month->isSameMonth(now()) ? now()->toDateString() : $month->toDateString()) }}" required>
                        </div>
                        <div>
                            <label class="nova-label" for="channel">Wo?</label>
                            <select class="nova-input" id="channel" name="channel">@foreach ($channels as $k => $n)<option value="{{ $k }}" @selected(old('channel') === $k)>{{ $n }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div>
                        <label class="nova-label" for="notes">Notizen</label>
                        <textarea class="nova-input" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                    </div>
                    <button class="nova-btn w-full" data-plan-add>Eintragen</button>
                </form>
            </section>

            <section class="nova-card" aria-labelledby="plan-upcoming">
                <h2 id="plan-upcoming" class="font-semibold">Geplante Seiten</h2>
                @forelse ($upcoming as $p)
                    <p class="mt-3 text-sm"><a href="{{ route('admin.pages.edit', $p) }}" class="font-medium text-slate-900 hover:text-nova-700">{{ $p->title }}</a>
                        <span class="block text-slate-500">{{ $p->published_at->locale('de')->isoFormat('dd, D. MMMM, HH:mm') }} Uhr</span></p>
                @empty
                    <p class="mt-2 text-sm text-slate-600">Keine. Im Seiten-Editor wählen Sie unter „Später veröffentlichen“ einen Termin.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
