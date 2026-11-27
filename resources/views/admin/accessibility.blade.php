@extends('layouts.admin')
@section('title', 'Barrierefreiheit')
@section('width', 'max-w-6xl')

@php
    $badge = $score >= 90 ? 'text-emerald-700 bg-emerald-50' : ($score >= 60 ? 'text-amber-800 bg-amber-50' : 'text-red-700 bg-red-50');
    $dot = ['error' => 'bg-red-500', 'warning' => 'bg-amber-400', 'info' => 'bg-sky-400'];
    $levelLabel = ['error' => 'Muss behoben werden', 'warning' => 'Sollte behoben werden', 'info' => 'Hinweis'];
    $fixLink = ['design' => [route('admin.design'), 'Zum Design'], 'media' => [route('admin.media.index'), 'Zur Mediathek'], 'products' => [route('admin.shop.products.index'), 'Zu den Produkten']];
@endphp

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Barrierefreiheit</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Seit dem 28. Juni 2025 müssen Online-Shops in der EU barrierefrei sein (Barrierefreiheitsstärkungsgesetz, BFSG). Nova CMS erfüllt die technischen Anforderungen nach WCAG 2.1 AA von Haus aus. Dieser Check prüft, ob auch Ihre Inhalte passen.</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <section class="nova-card flex flex-col items-center justify-center text-center" aria-labelledby="a11y-score">
            <h2 id="a11y-score" class="text-sm font-semibold text-slate-500">Ergebnis für Ihre Website</h2>
            <p class="mt-3 rounded-2xl px-5 py-2 text-4xl font-semibold {{ $badge }}" data-a11y-score>{{ $score }}<span class="text-lg font-normal opacity-70"> / 100</span></p>
            <p class="mt-3 text-sm text-slate-600">
                @if ($counts['error'] + $counts['warning'] === 0) Sehr gut. Es wurden keine Barrieren gefunden.
                @else {{ $counts['error'] }} {{ $counts['error'] === 1 ? 'Punkt muss' : 'Punkte müssen' }}, {{ $counts['warning'] }} {{ $counts['warning'] === 1 ? 'sollte' : 'sollten' }} behoben werden.
                @endif
            </p>
        </section>

        <section class="nova-card lg:col-span-2" aria-labelledby="a11y-site">
            <h2 id="a11y-site" class="font-semibold">Die ganze Website</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach ($site as $c)
                    <li class="flex gap-3" data-a11y-check="{{ $c['key'] }}" data-ok="{{ $c['ok'] ? '1' : '0' }}">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $c['ok'] ? 'bg-emerald-500' : $dot[$c['level']] }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900"><span class="sr-only">{{ $c['ok'] ? 'Erfüllt:' : $levelLabel[$c['level']].':' }}</span> {{ $c['label'] }}</p>
                            @unless ($c['ok'])
                                <p class="mt-0.5 text-slate-600">{{ $c['tip'] }}</p>
                                @if (($c['fix'] ?? null) === 'statement')
                                    <form method="POST" action="{{ route('admin.accessibility.statement') }}" class="mt-2">@csrf
                                        <button class="nova-btn py-2" data-create-statement>Erklärung jetzt anlegen</button></form>
                                @elseif (isset($fixLink[$c['fix'] ?? '']))
                                    <a href="{{ $fixLink[$c['fix']][0] }}" class="mt-1 inline-block font-medium text-nova-700 hover:underline">{{ $fixLink[$c['fix']][1] }} →</a>
                                @endif
                            @endunless
                            @if ($c['key'] === 'statement' && $c['ok'] && $statement)
                                <p class="mt-0.5 text-slate-600"><a class="font-medium text-nova-700 hover:underline" href="{{ route('admin.pages.edit', $statement) }}">Erklärung bearbeiten</a> · <a class="text-nova-700 hover:underline" href="{{ $statement->url() }}" target="_blank" rel="noopener">auf der Website ansehen</a></p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="nova-card mt-6" aria-labelledby="a11y-pages">
        <h2 id="a11y-pages" class="font-semibold">Ihre Seiten</h2>
        @if (! $pages)
            <p class="mt-3 flex items-center gap-2 text-sm text-emerald-700"><x-icon name="check" class="size-4" /> Auf Ihren veröffentlichten Seiten wurden keine Barrieren gefunden.</p>
        @else
            <p class="mt-1 text-sm text-slate-600">Klicken Sie auf eine Seite, um sie im Editor zu öffnen und die Punkte zu beheben.</p>
            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($pages as $row)
                    <li class="py-4" data-a11y-page="{{ $row['page']->slug }}">
                        <a href="{{ route('admin.pages.edit', $row['page']) }}" class="font-medium text-slate-900 hover:text-nova-700">{{ $row['page']->title }}</a>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($row['issues'] as $i)
                                <li class="flex gap-3">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dot[$i['level']] }}" aria-hidden="true"></span>
                                    <div><p class="text-slate-800"><span class="sr-only">{{ $levelLabel[$i['level']] }}:</span> {{ $i['label'] }}</p><p class="text-slate-500">{{ $i['tip'] }}</p></div>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="nova-card" aria-labelledby="a11y-builtin">
            <h2 id="a11y-builtin" class="font-semibold">Das erledigt Nova CMS automatisch</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                @foreach ($builtin as $b)<li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" />{{ $b }}</li>@endforeach
            </ul>
        </section>
        <section class="nova-card" aria-labelledby="a11y-who">
            <h2 id="a11y-who" class="font-semibold">Gut zu wissen</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
                <li>Das BFSG gilt für Online-Shops und Dienstleistungen für Verbraucher. Ausgenommen sind Kleinstunternehmen mit weniger als 10 Beschäftigten und höchstens 2 Mio. Euro Jahresumsatz.</li>
                <li>„Barrierefrei-Plugins“ mit Vorlese- oder Kontrastknopf ersetzen keine barrierefreien Inhalte. Wichtig sind Bildbeschreibungen, klare Linktexte, gute Kontraste und eine logische Überschriften-Reihenfolge.</li>
                <li>Testen Sie selbst: Bedienen Sie Ihre Website einmal nur mit der Tab-Taste, und vergrößern Sie den Text im Browser auf 200 %.</li>
                <li>Dieser Check ersetzt keine rechtliche Prüfung. Er findet die häufigsten Barrieren in Ihren Inhalten.</li>
            </ul>
        </section>
    </div>
@endsection
