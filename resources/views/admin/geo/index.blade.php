@extends('layouts.admin')
@section('title', 'KI-Sichtbarkeit')
@section('width', 'max-w-6xl')

@php
    $badge = fn ($s) => $s >= 80 ? 'text-emerald-700 bg-emerald-50' : ($s >= 50 ? 'text-amber-800 bg-amber-50' : 'text-red-700 bg-red-50');
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">SEO: bei Google gefunden werden</h1>
    <p class="mt-1 text-slate-600">Immer mehr Menschen fragen ChatGPT, Gemini oder Perplexity statt Google. Hier sehen Sie, wie gut KI-Suchen Ihre Website verstehen und empfehlen können.</p>
    @include('admin.seo.partials.tabs')

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <section class="nova-card flex flex-col items-center justify-center text-center" aria-labelledby="geo-score">
            <h2 id="geo-score" class="text-sm font-semibold text-slate-500">KI-Sichtbarkeit Ihrer Website</h2>
            <p class="mt-3 rounded-2xl px-5 py-2 text-4xl font-semibold {{ $badge($score) }}" data-geo-score>{{ $score }}<span class="text-lg font-normal opacity-70"> / 100</span></p>
            <p class="mt-3 text-sm text-slate-600">Nova ergänzt auf jeder Seite automatisch strukturierte Daten, damit KI-Systeme Seite, Unternehmen und Fragen sicher erkennen.</p>
            @if (\App\Support\Geo\Geo::llmsEnabled())
                <a href="{{ route('llms') }}" target="_blank" rel="noopener" class="mt-3 text-sm font-medium text-nova-700 hover:underline">llms.txt ansehen<span class="sr-only"> (öffnet in neuem Tab)</span></a>
            @endif
        </section>

        <section class="nova-card lg:col-span-2" aria-labelledby="geo-site">
            <h2 id="geo-site" class="font-semibold">Die ganze Website</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach ($site as $c)
                    <li class="flex gap-3" data-geo-check="{{ $c['key'] }}" data-ok="{{ $c['ok'] ? '1' : '0' }}">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $c['ok'] ? 'bg-emerald-500' : 'bg-amber-400' }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900"><span class="sr-only">{{ $c['ok'] ? 'Erfüllt:' : 'Noch offen:' }}</span> {{ $c['label'] }}</p>
                            @unless ($c['ok'])
                                <p class="mt-0.5 text-slate-600">{{ $c['tip'] }}</p>
                                @isset($c['link'])<a href="{{ $c['link'] }}" class="mt-1 inline-block font-medium text-nova-700 hover:underline">Jetzt ändern →</a>@endisset
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="nova-card mt-6 p-0" aria-labelledby="geo-pages">
        <div class="px-6 pt-5">
            <h2 id="geo-pages" class="font-semibold">Ihre Seiten</h2>
            <p class="mt-1 text-sm text-slate-600">Die Seiten mit dem größten Potenzial stehen oben. Klappen Sie eine Seite auf, um die Tipps zu sehen.</p>
        </div>
        @if ($pages->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Noch keine veröffentlichten Seiten.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100">
                @foreach ($pages as $row)
                    @php $open = collect($row['checks'])->where('ok', false); @endphp
                    <li data-geo-page="{{ $row['page']->slug }}">
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-3 px-6 py-3.5 hover:bg-slate-50">
                                <span class="w-12 shrink-0 rounded-lg px-2 py-1 text-center text-sm font-semibold tabular-nums {{ $badge($row['score']) }}">{{ $row['score'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-slate-900">{{ $row['page']->title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $open->isEmpty() ? 'Alles erfüllt' : $open->count().' '.($open->count() === 1 ? 'Tipp' : 'Tipps') }}</span>
                                </span>
                                <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <div class="px-6 pb-5">
                                <ul class="space-y-2 text-sm">
                                    @foreach ($row['checks'] as $c)
                                        <li class="flex gap-3">
                                            <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $c['ok'] ? 'bg-emerald-500' : 'bg-amber-400' }}" aria-hidden="true"></span>
                                            <div><p class="text-slate-800"><span class="sr-only">{{ $c['ok'] ? 'Erfüllt:' : 'Tipp:' }}</span> {{ $c['label'] }}</p>@unless($c['ok'])<p class="text-slate-500">{{ $c['tip'] }}</p>@endunless</div>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('admin.pages.edit', $row['page']) }}" class="mt-3 inline-block text-sm font-medium text-nova-700 hover:underline">Seite bearbeiten →</a>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="nova-card mt-6" id="einstellungen" aria-labelledby="geo-settings">
        <h2 id="geo-settings" class="font-semibold">Einstellungen</h2>
        <form method="POST" action="{{ route('admin.geo.update') }}" class="mt-4 space-y-6">
            @csrf @method('PUT')
            <div>
                <label class="nova-label" for="geo_summary">Kurzprofil Ihres Unternehmens</label>
                <textarea class="nova-input" id="geo_summary" name="geo_summary" rows="3" maxlength="600" aria-describedby="geo-summary-hint" placeholder="z. B. Bäckerei Müller backt seit 1987 in Erfurt Brot und Kuchen aus regionalem Mehl. Zwei Filialen, Lieferung für Firmen.">{{ old('geo_summary', $v['geo_summary']) }}</textarea>
                <p id="geo-summary-hint" class="mt-1 text-sm text-slate-500">Zwei, drei Sätze: wer Sie sind, was Sie anbieten, wo. Erscheint in der llms.txt und in den strukturierten Daten.</p>
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="geo_llms" value="1" class="mt-1 accent-nova-600" @checked(old('geo_llms', $v['geo_llms']) === '1')>
                <span><span class="font-medium">Übersicht für KI-Systeme veröffentlichen (llms.txt)</span><span class="block text-sm text-slate-500">Eine kurze Liste Ihrer Seiten und Produkte mit Beschreibung unter {{ url('llms.txt') }}.</span></span>
            </label>

            <fieldset>
                <legend class="nova-label">KI-Crawler</legend>
                <div class="mt-1 space-y-2 text-sm">
                    <label class="flex items-start gap-2"><input type="radio" name="geo_ai_bots" value="allow" class="mt-1 accent-nova-600" @checked(old('geo_ai_bots', $v['geo_ai_bots']) === 'allow')><span><strong>Erlauben</strong> (empfohlen)<span class="block text-slate-500">ChatGPT, Claude, Gemini, Perplexity und Co. dürfen Ihre Seiten lesen und Sie empfehlen.</span></span></label>
                    <label class="flex items-start gap-2"><input type="radio" name="geo_ai_bots" value="block" class="mt-1 accent-nova-600" @checked(old('geo_ai_bots', $v['geo_ai_bots']) === 'block')><span><strong>Aussperren</strong><span class="block text-slate-500">Bittet {{ count(\App\Support\Geo\Geo::AI_BOTS) }} bekannte KI-Crawler in der robots.txt, Ihre Seiten nicht zu lesen. Google und Bing bleiben erlaubt.</span></span></label>
                </div>
            </fieldset>

            <fieldset>
                <legend class="nova-label">Ihre Profile</legend>
                <p class="text-sm text-slate-500">Optional. Zeigt KI-Systemen, dass diese Profile zu Ihrer Website gehören.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    @foreach (\App\Support\Geo\Geo::PROFILES as $key => $name)
                        <div>
                            <label class="nova-label" for="{{ $key }}">{{ $name }}</label>
                            <input class="nova-input" type="url" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $v[$key]) }}" placeholder="https://" @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror>
                            @error($key)<p id="{{ $key }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <button class="nova-btn w-full sm:w-auto" data-save-geo>Speichern</button>
        </form>
    </section>
@endsection
