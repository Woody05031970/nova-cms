@extends('layouts.admin')
@section('title', 'Mitbewerber und Maßnahmen')
@section('width', 'max-w-6xl')

@php
    $prioClass = [1 => 'bg-red-50 text-red-700 ring-red-200', 2 => 'bg-amber-50 text-amber-800 ring-amber-200', 3 => 'bg-slate-100 text-slate-700 ring-slate-200'];
    $open = collect($measures)->where('done', false)->count();
    $analyzed = $competitors->filter(fn ($c) => is_array($c->data));
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">SEO: bei Google gefunden werden</h1>
    <p class="mt-1 text-slate-600">Sehen Sie, wie Ihre Mitbewerber ihre Websites aufbauen, und erhalten Sie konkrete Schritte für ein besseres Ranking.</p>
    @include('admin.seo.partials.tabs')

    {{-- Mitbewerber hinzufügen --}}
    <section class="nova-card mt-8" aria-labelledby="add-competitor">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="add-competitor" class="font-semibold">Mitbewerber analysieren</h2>
                <p class="mt-1 text-sm text-slate-600">Geben Sie die Website eines Mitbewerbers ein. Nova CMS liest die Startseite und bis zu {{ \App\Support\Seo\CompetitorCrawler::MAX_PAGES - 1 }} Seiten aus dem Menü, so wie ein Besucher sie sieht. Das dauert etwa 10 bis 30 Sekunden.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $competitors->count() }} von {{ \App\Http\Controllers\Admin\SeoToolsController::MAX_COMPETITORS }}</span>
        </div>
        <form method="POST" action="{{ route('admin.seo.competitors.store') }}" class="mt-4 flex flex-col gap-2 sm:flex-row" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <label for="competitor-url" class="sr-only">Adresse des Mitbewerbers</label>
            <input id="competitor-url" name="url" class="nova-input flex-1" placeholder="z. B. www.mitbewerber.de" value="{{ old('url') }}" required inputmode="url" @error('url') aria-invalid="true" aria-describedby="competitor-url-error" @enderror>
            <button class="nova-btn" :disabled="busy" data-analyze>
                <span x-show="!busy">Analysieren</span><span x-show="busy" x-cloak>Wird analysiert …</span>
            </button>
        </form>
        @error('url')<p id="competitor-url-error" class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
    </section>

    {{-- Maßnahmen --}}
    <section class="mt-6" id="massnahmen" aria-labelledby="measures-title">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="measures-title" class="text-lg font-semibold">Ihre Maßnahmen für ein besseres Ranking</h2>
            <p class="text-sm text-slate-600">{{ $open }} offen, {{ count($measures) - $open }} erledigt</p>
        </div>
        @if (! $analyzed->count())
            <p class="mt-2 text-sm text-slate-600">Fügen Sie oben Mitbewerber hinzu, dann vergleicht Nova CMS Funktionen, Themen und Suchbegriffe. Die Punkte unten betreffen schon jetzt Ihre eigene Website.</p>
        @endif
        @if (! $measures)
            <p class="nova-card mt-3 flex items-center gap-2 text-sm text-emerald-700"><x-icon name="check" class="size-4" /> Aktuell gibt es keine offenen Maßnahmen.</p>
        @else
            <ol class="mt-3 space-y-3" data-measures>
                @foreach ($measures as $m)
                    <li @class(['nova-card p-5', 'opacity-60' => $m['done']]) data-measure="{{ $m['id'] }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-full px-2 py-0.5 font-semibold ring-1 {{ $prioClass[$m['prio']] }}">Wirkung: {{ $priorities[$m['prio']] }}</span>
                                    <span class="text-slate-500">{{ $m['area'] }}</span>
                                </p>
                                <h3 @class(['mt-2 font-semibold text-slate-900', 'line-through' => $m['done']])>{{ $m['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $m['why'] }}</p>
                                <p class="mt-2 text-sm text-slate-800"><strong>So geht's:</strong> {{ $m['how'] }}</p>
                                @if ($m['link'])<a href="{{ $m['link'][0] }}" class="mt-2 inline-block text-sm font-medium text-nova-700 hover:underline">{{ $m['link'][1] }} →</a>@endif
                            </div>
                            <form method="POST" action="{{ route('admin.seo.measures.toggle') }}" class="shrink-0">@csrf
                                <input type="hidden" name="id" value="{{ $m['id'] }}">
                                <button class="nova-btn-secondary py-2 text-sm" aria-pressed="{{ $m['done'] ? 'true' : 'false' }}">
                                    @if ($m['done']) Wieder öffnen @else <x-icon name="check" class="size-4" /> Erledigt @endif
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
        @if ($strengths)
            <div class="nova-card mt-4 border-emerald-200 bg-emerald-50/60">
                <h3 class="font-semibold text-emerald-900">Hier liegen Sie vorn</h3>
                <ul class="mt-2 space-y-1.5 text-sm text-emerald-900">
                    @foreach ($strengths as $s)<li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0" />{{ $s }}</li>@endforeach
                </ul>
            </div>
        @endif
    </section>

    @if ($analyzed->count())
        {{-- Funktionen im Vergleich --}}
        <section class="nova-card mt-6 p-0" aria-labelledby="matrix-title">
            <h2 id="matrix-title" class="px-6 pt-5 font-semibold">Funktionen im Vergleich</h2>
            <p class="px-6 pt-1 text-sm text-slate-600">Was Besucher auf den Websites finden. Erkannt aus dem öffentlich sichtbaren Inhalt, ohne Gewähr.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm" data-feature-matrix>
                    <thead class="bg-slate-50 text-left text-xs text-slate-600">
                        <tr>
                            <th scope="col" class="px-6 py-2.5 font-semibold">Funktion</th>
                            <th scope="col" class="px-3 py-2.5 text-center font-semibold text-nova-700">Ihre Website</th>
                            @foreach ($analyzed as $c)<th scope="col" class="max-w-36 truncate px-3 py-2.5 text-center font-semibold">{{ $c->label() }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($features as $key => $label)
                            <tr>
                                <th scope="row" class="px-6 py-2.5 text-left font-medium text-slate-800">{{ $label }}</th>
                                @foreach (array_merge([$own['features'][$key] ?? false], $analyzed->map(fn ($c) => $c->data['features'][$key] ?? false)->all()) as $i => $has)
                                    <td class="px-3 py-2.5 text-center">
                                        @if ($has)<span class="inline-grid size-6 place-items-center rounded-full bg-emerald-50 text-emerald-700"><x-icon name="check" class="size-4" /><span class="sr-only">ja</span></span>
                                        @else<span class="text-slate-400" aria-hidden="true">–</span><span class="sr-only">nein</span>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Aufbau der Mitbewerber-Websites --}}
    @if ($competitors->count())
        <section class="mt-6 space-y-4" aria-labelledby="structure-title">
            <h2 id="structure-title" class="text-lg font-semibold">So sind die Websites Ihrer Mitbewerber aufgebaut</h2>
            @foreach ($competitors as $c)
                @php $d = $c->data ?? []; @endphp
                <details class="nova-card group p-0" data-competitor="{{ $c->host() }}" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 px-6 py-4">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-slate-900">{{ $c->label() }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $c->url }} · analysiert {{ $c->analyzed_at?->format('d.m.Y, H:i') ?? 'noch nicht' }}</span>
                        </span>
                        @if ($d)
                            <span class="text-xs text-slate-600">{{ count($d['pages'] ?? []) }} Seiten gelesen @if($d['sitemap_pages'] ?? null)· ca. {{ $d['sitemap_pages'] }} Seiten insgesamt @endif · Ø {{ $d['avg_words'] ?? 0 }} Wörter</span>
                        @endif
                        <x-icon name="chevron" class="size-4 text-slate-400 transition group-open:rotate-90" />
                    </summary>
                    <div class="border-t border-slate-100 px-6 py-5">
                        @if ($c->error)<p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Letzter Versuch fehlgeschlagen: {{ $c->error }}</p>@endif
                        @if ($d)
                            <div class="grid gap-6 lg:grid-cols-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Menü</h3>
                                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                        @forelse ($d['menu'] ?? [] as $item)
                                            <li>{{ $item['label'] }}
                                                @if ($item['children'])<ul class="mt-1 mb-1.5 ml-3 space-y-0.5 border-l border-slate-200 pl-3 text-xs text-slate-500">@foreach ($item['children'] as $ch)<li>{{ $ch }}</li>@endforeach</ul>@endif
                                            </li>
                                        @empty
                                            <li class="text-slate-500">Kein Menü erkannt.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                <div class="lg:col-span-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Gelesene Seiten</h3>
                                    <div class="mt-2 overflow-x-auto">
                                        <table class="w-full min-w-[480px] text-sm">
                                            <thead class="text-left text-xs text-slate-500"><tr><th scope="col" class="py-1.5 pr-3 font-medium">Seite</th><th scope="col" class="py-1.5 pr-3 font-medium">Hauptüberschrift</th><th scope="col" class="py-1.5 text-right font-medium">Wörter</th></tr></thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($d['pages'] ?? [] as $p)
                                                    <tr class="align-top">
                                                        <td class="py-2 pr-3"><span class="font-medium text-slate-800">{{ $p['label'] }}</span><span class="block font-mono text-[11px] text-slate-400">{{ $p['path'] }}</span></td>
                                                        <td class="py-2 pr-3 text-slate-700">{{ $p['h1'] ?: '–' }}
                                                            @if ($p['headings'])<span class="block text-xs text-slate-500">{{ implode(' · ', array_slice($p['headings'], 0, 4)) }}</span>@endif
                                                        </td>
                                                        <td class="py-2 text-right tabular-nums">{{ $p['words'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($d['terms'] ?? [])
                                        <h3 class="mt-5 text-sm font-semibold text-slate-900">Häufige Begriffe in Titeln und Überschriften</h3>
                                        <p class="mt-2 flex flex-wrap gap-1.5">@foreach (array_slice($d['terms'], 0, 15, true) as $t => $n)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{{ $t }} <span class="text-slate-400">{{ $n }}</span></span>@endforeach</p>
                                    @endif
                                    @if ($d['schema_types'] ?? [])
                                        <p class="mt-4 text-xs text-slate-500">Daten für Google (schema.org): {{ implode(', ', array_slice($d['schema_types'], 0, 8)) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="mt-5 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.seo.competitors.refresh', $c) }}">@csrf<button class="nova-btn-secondary py-2 text-sm">Neu analysieren</button></form>
                            <form method="POST" action="{{ route('admin.seo.competitors.destroy', $c) }}">@csrf @method('DELETE')<button class="rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Entfernen</button></form>
                        </div>
                    </div>
                </details>
            @endforeach
        </section>
    @endif

    <p class="mt-8 text-xs text-slate-500">Hinweis: Die Analyse liest nur öffentlich zugängliche Seiten und erkennt Funktionen anhand typischer Merkmale. Sie dient Ihrer eigenen Orientierung. Übernehmen Sie keine Texte oder Bilder von Mitbewerbern.</p>
@endsection

@push('head') @vite('resources/js/app.js') @endpush
