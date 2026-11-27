@extends('layouts.admin')
@section('title', 'SEO')
@section('width', 'max-w-6xl')
@push('head') @vite('resources/js/app.js') @endpush

@php
    $badge = fn ($s) => $s >= 80 ? 'text-emerald-700 bg-emerald-50' : ($s >= 50 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50');
    $compareCfg = [
        'url' => route('admin.seo.compare'),
        'pageId' => $pages->first()['page']->id ?? null,
        'keywords' => $pages->mapWithKeys(fn ($r) => [$r['page']->id => $r['page']->focus_keyword])->all(),
    ];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">SEO: bei Google gefunden werden</h1>
    <p class="mt-1 text-slate-600">Nova CMS prüft jede Seite nach den wichtigsten Regeln und sagt Ihnen, was zu tun ist.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <section class="nova-card flex flex-col items-center justify-center text-center">
            <h2 class="text-sm font-semibold text-slate-500">Durchschnitt Ihrer veröffentlichten Seiten</h2>
            @if ($average === null)
                <p class="mt-4 text-sm text-slate-500">Noch keine Seite veröffentlicht.</p>
            @else
                <p class="mt-3 rounded-2xl px-5 py-2 text-4xl font-semibold {{ $badge($average) }}">{{ $average }}<span class="text-lg font-normal opacity-70"> / 100</span></p>
                <p class="mt-2 text-sm text-slate-500">{{ $average >= 80 ? 'Sehr gut aufgestellt.' : ($average >= 50 ? 'Ordentlich, mit Luft nach oben.' : 'Hier lässt sich viel gewinnen.') }}</p>
            @endif
        </section>

        <section class="nova-card lg:col-span-2">
            <h2 class="font-semibold">Die ganze Website</h2>
            <ul class="mt-3 space-y-2.5 text-sm">
                @foreach ($site as $c)
                    <li class="flex gap-2.5">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $c['ok'] ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                        <span>{{ $c['label'] }}@unless ($c['ok'])<span class="block text-xs text-slate-500">{{ $c['tip'] }}</span>@endunless</span>
                    </li>
                @endforeach
                <li class="flex gap-2.5"><span class="mt-1 size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                    <span>Sitemap für Google wird automatisch erstellt: <a href="{{ url('sitemap.xml') }}" target="_blank" class="font-mono text-xs text-nova-700 hover:underline">{{ url('sitemap.xml') }}</a>
                        <span class="block text-xs text-slate-500">Tipp: Tragen Sie diese Adresse einmal in der kostenlosen Google Search Console ein.</span></span></li>
            </ul>
            <form method="POST" action="{{ route('admin.seo.settings') }}" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                @csrf @method('PUT')
                <input type="hidden" name="allow_indexing" value="{{ $indexing ? 0 : 1 }}">
                <p class="text-sm">{{ $indexing ? 'Suchmaschinen dürfen Ihre Website aufnehmen.' : 'Ihre Website ist für Suchmaschinen gesperrt, z. B. während sie entsteht.' }}</p>
                <button class="{{ $indexing ? 'nova-btn-secondary' : 'nova-btn' }}">{{ $indexing ? 'Vorübergehend sperren' : 'Für Google freigeben' }}</button>
            </form>
        </section>
    </div>

    <section class="nova-card mt-6 p-0">
        <h2 class="px-6 pt-5 font-semibold">Ihre Seiten</h2>
        @if ($pages->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-500">Noch keine Seiten angelegt.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($pages as ['page' => $page, 'result' => $r])
                    @php $open = collect($r['checks'])->where('status', '!=', 'good'); @endphp
                    <li>
                        <details class="group">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-6 py-3 hover:bg-slate-50">
                                <span class="w-14 shrink-0 rounded-full py-0.5 text-center text-sm font-semibold {{ $badge($r['score']) }}">{{ $r['score'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="font-medium">{{ $page->title }}</span>
                                    @unless ($page->isPublished())<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Entwurf</span>@endunless
                                    <span class="block text-xs text-slate-500">{{ $page->focus_keyword ? 'Suchbegriff: '.$page->focus_keyword : 'Kein Suchbegriff festgelegt' }} · {{ $r['words'] }} Wörter</span>
                                </span>
                                <span class="text-sm text-slate-500">{{ $open->isEmpty() ? 'Alles erledigt' : $open->count().' '.($open->count() === 1 ? 'Tipp' : 'Tipps') }}</span>
                                <span class="text-slate-400 transition group-open:rotate-90">›</span>
                            </summary>
                            <div class="bg-slate-50/60 px-6 pt-1 pb-4">
                                <ul class="space-y-2 text-sm">
                                    @foreach ($r['checks'] as $c)
                                        <li class="flex gap-2.5">
                                            <span class="mt-1 size-2.5 shrink-0 rounded-full {{ ['good' => 'bg-emerald-500', 'warn' => 'bg-amber-400', 'bad' => 'bg-red-500'][$c['status']] }}"></span>
                                            <span>{{ $c['label'] }}@if ($c['tip'])<span class="block text-xs text-slate-500">{{ $c['tip'] }}</span>@endif</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('admin.pages.edit', $page) }}" class="nova-btn-secondary mt-4 px-3 py-1.5">Seite bearbeiten</a>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($pages->isNotEmpty())
    <section class="nova-card mt-6" x-data="seoCompare(@js($compareCfg))" x-cloak aria-labelledby="compare">
        <h2 id="compare" class="font-semibold">Vergleich mit einem Wettbewerber</h2>
        <p class="mt-1 text-sm text-slate-600">Geben Sie die Adresse eines Mitbewerbers ein, der bei Google vor Ihnen steht. Nova CMS vergleicht beide Seiten{{ $ai ? ' und die KI sagt Ihnen, wie Sie vorbeiziehen' : '' }}.</p>
        <form class="mt-4 grid gap-3 md:grid-cols-[1fr_1.4fr_1fr_auto] md:items-end" @submit.prevent="run()">
            <div>
                <label class="nova-label" for="cmp-page">Ihre Seite</label>
                <select id="cmp-page" class="nova-input" x-model.number="pageId" @change="pageChanged()">
                    @foreach ($pages as ['page' => $page])<option value="{{ $page->id }}">{{ $page->title }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="nova-label" for="cmp-url">Seite des Wettbewerbers</label>
                <input id="cmp-url" class="nova-input" x-model="url" required placeholder="https://www.mitbewerber.de">
            </div>
            <div>
                <label class="nova-label" for="cmp-kw">Suchbegriff</label>
                <input id="cmp-kw" class="nova-input" x-model="keyword" placeholder="z. B. Bäckerei Köln">
            </div>
            <button class="nova-btn" :disabled="busy"><span x-text="busy ? 'Wird verglichen … ' + seconds + ' s' : 'Vergleichen'"></span></button>
        </form>
        <p x-show="error" x-text="error" class="mt-3 text-sm text-red-700" role="alert"></p>

        <template x-if="result">
            <div class="mt-6 space-y-6">
                <p class="text-sm">
                    <strong x-text="result.wins"></strong> Punkte für Sie, <strong x-text="result.losses"></strong> für den Wettbewerber
                    <span class="text-slate-500" x-text="'(' + result.competitor.url + ', geladen in ' + result.competitor.ms + ' ms)'"></span>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead><tr class="border-b border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase">
                            <th class="py-2 pr-4 font-medium">Punkt</th><th class="py-2 pr-4 font-medium">Ihre Seite</th><th class="py-2 pr-4 font-medium">Wettbewerber</th><th class="py-2 font-medium">Ergebnis</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="row in result.rows" :key="row.label">
                                <tr><td class="py-2 pr-4" x-text="row.label"></td><td class="py-2 pr-4" x-text="row.own"></td><td class="py-2 pr-4" x-text="row.competitor"></td>
                                    <td class="py-2 font-medium" :class="winnerClass(row.winner)" x-text="winnerText(row.winner)"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <template x-if="result.advice">
                    <div class="rounded-xl border border-nova-200 bg-nova-50/60 p-5">
                        <h3 class="flex items-center gap-2 font-semibold text-nova-900"><x-icon name="sparkles" class="size-5" /> So ziehen Sie vorbei</h3>
                        <p class="mt-2 text-sm text-slate-700" x-text="result.advice.summary"></p>
                        <ol class="mt-3 space-y-3 text-sm">
                            <template x-for="(a, i) in result.advice.actions" :key="i">
                                <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-nova-600 text-xs font-semibold text-white" x-text="i + 1"></span>
                                    <span><span class="font-medium" x-text="a.title"></span><span class="block text-slate-600" x-text="a.detail"></span></span></li>
                            </template>
                        </ol>
                    </div>
                </template>
                <p x-show="result.advice_error" class="text-sm text-amber-700" x-text="'KI-Empfehlung nicht möglich: ' + result.advice_error"></p>
                @unless ($ai)
                    <p class="text-sm text-slate-600">Mit einem <a href="{{ route('admin.ai') }}" class="font-medium text-nova-700 hover:underline">KI-Anbieter</a> bekommen Sie hier zusätzlich konkrete Empfehlungen.</p>
                @endunless

                <details class="text-sm">
                    <summary class="cursor-pointer font-medium text-slate-700">Überschriften des Wettbewerbers ansehen</summary>
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-slate-600"><template x-for="h in result.competitor.headings"><li x-text="h"></li></template></ul>
                </details>
            </div>
        </template>
    </section>
    @endif
@endsection
