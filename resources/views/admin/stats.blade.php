@extends('layouts.admin')
@section('title', 'Besucherstatistik')
@section('width', 'max-w-6xl')

@php
    $change = $previous ? (int) round(($total - $previous) / $previous * 100) : null;
    $fmt = fn ($n) => number_format($n, 0, ',', '.');
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Besucherstatistik</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Ohne Cookies und ohne fremde Dienste: Nova zählt nur Summen pro Tag und speichert keine IP-Adressen. Ein Cookie-Banner ist dafür nicht nötig. Ihre eigenen Aufrufe als Administrator zählen nicht mit.</p>
        </div>
        <nav class="flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm" aria-label="Zeitraum">
            @foreach ([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage'] as $d => $l)
                <a href="{{ route('admin.stats', ['tage' => $d]) }}" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-slate-900 text-white' => $days === $d, 'text-slate-600 hover:bg-slate-50' => $days !== $d]) @if($days === $d) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="nova-card"><p class="text-sm text-slate-500">Seitenaufrufe</p><p class="mt-1 text-3xl font-semibold tabular-nums" data-stats-total>{{ $fmt($total) }}</p>
            @if ($change !== null)<p class="mt-1 text-sm {{ $change >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $change >= 0 ? '+' : '' }}{{ $change }} % zum Zeitraum davor</p>@endif</div>
        <div class="nova-card"><p class="text-sm text-slate-500">Pro Tag im Schnitt</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ $fmt((int) round($total / $days)) }}</p></div>
        <div class="nova-card"><p class="text-sm text-slate-500">Davon mit Handy oder Tablet</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ $mobile }} %</p></div>
    </div>

    <section class="nova-card mt-6" aria-labelledby="stats-chart">
        <h2 id="stats-chart" class="font-semibold">Aufrufe pro Tag</h2>
        <div class="mt-4 flex h-40 items-end gap-px" role="img" aria-label="Balkendiagramm: Aufrufe pro Tag, höchster Wert {{ $fmt($max) }}">
            @foreach ($series as $s)
                <div class="group relative flex-1 rounded-t bg-nova-500/80 hover:bg-nova-600" style="height: {{ max(2, round($s['views'] / $max * 100)) }}%" title="{{ $s['day']->format('d.m.') }}: {{ $fmt($s['views']) }}"></div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-slate-500"><span>{{ $series->first()['day']->format('d.m.') }}</span><span>heute</span></div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @foreach ([['Beliebteste Seiten', $pages, 'path', 'views', 'Noch keine Aufrufe gezählt.'], ['Woher die Besucher kommen', $referrers, 'value', 'hits', 'Bisher kamen alle Besucher direkt oder ohne Angabe der Herkunft.'], ['Gesucht auf Ihrer Website', $searches, 'value', 'hits', 'Noch keine Suchanfragen.']] as [$title, $rows, $col, $num, $empty])
            <section class="nova-card p-0" aria-labelledby="stats-{{ $loop->index }}">
                <h2 id="stats-{{ $loop->index }}" class="px-5 pt-4 font-semibold">{{ $title }}</h2>
                @if ($rows->isEmpty())
                    <p class="px-5 pt-2 pb-5 text-sm text-slate-600">{{ $empty }}</p>
                @else
                    @php $top = max(1, $rows->max($num)); @endphp
                    <ol class="mt-2 space-y-1 px-3 pb-4 text-sm" data-stats-list="{{ $col }}">
                        @foreach ($rows as $r)
                            <li class="relative flex items-center justify-between gap-3 overflow-hidden rounded px-2 py-1.5">
                                <span class="absolute inset-y-0 left-0 bg-nova-50" style="width: {{ round($r->{$num} / $top * 100) }}%" aria-hidden="true"></span>
                                <span class="relative min-w-0 truncate">{{ $col === 'path' ? ($titles[$r->path] ?? $r->path) : $r->value }}</span>
                                <span class="relative shrink-0 tabular-nums text-slate-600">{{ $fmt($r->{$num}) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endforeach
    </div>
    @if ($searches->isNotEmpty())
        <p class="mt-4 text-sm text-slate-600">Tipp: Was Besucher auf Ihrer Website suchen, ist ein guter Hinweis auf fehlende Inhalte. Sprechen Sie diese Themen auf Ihren Seiten an.</p>
    @endif
@endsection
