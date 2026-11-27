@extends('layouts.admin')
@section('title', 'Weiterleitungen')
@section('width', 'max-w-6xl')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">SEO: bei Google gefunden werden</h1>
    <p class="mt-1 text-slate-600">Wenn sich eine Adresse ändert, führen alte Links und Google-Treffer weiter zur richtigen Seite.</p>
    @include('admin.seo.partials.tabs')

    <div class="mt-8 grid gap-6 lg:grid-cols-5">
        <section class="nova-card lg:col-span-2" aria-labelledby="new-redirect">
            <h2 id="new-redirect" class="font-semibold">Neue Weiterleitung</h2>
            <p class="mt-1 text-sm text-slate-600">Benennen Sie eine Seite um, legt Nova CMS die Weiterleitung automatisch an. Hier tragen Sie zusätzlich Adressen ein, z. B. von Ihrer früheren Website.</p>
            <form method="POST" action="{{ route('admin.seo.redirects.store') }}" class="mt-4 space-y-4" id="redirect-form">
                @csrf
                <div>
                    <label class="nova-label" for="from_path">Alte Adresse</label>
                    <input class="nova-input font-mono text-sm" id="from_path" name="from_path" value="{{ old('from_path', request('von')) }}" placeholder="/alte-seite.html" required @error('from_path') aria-invalid="true" aria-describedby="from-error" @enderror>
                    @error('from_path')<p id="from-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="nova-label" for="to_url">Neues Ziel</label>
                    <select class="nova-input" id="to_url" name="to_url" required>
                        <option value="">Bitte wählen …</option>
                        @foreach ($pages as $p)<option value="{{ $p->is_home ? '/' : '/'.$p->slug }}" @selected(old('to_url') === ($p->is_home ? '/' : '/'.$p->slug))>{{ $p->title }} ({{ $p->is_home ? '/' : '/'.$p->slug }})</option>@endforeach
                        @if (\App\Shop\ShopSettings::enabled())<option value="/shop">Shop (/shop)</option>@endif
                    </select>
                </div>
                <fieldset>
                    <legend class="nova-label">Art</legend>
                    <div class="mt-1 space-y-2 text-sm">
                        <label class="flex items-start gap-2"><input type="radio" name="status" value="301" checked class="mt-1 accent-nova-600"><span><strong>Dauerhaft (301)</strong><span class="block text-slate-500">Empfohlen. Google überträgt das Ranking auf die neue Adresse.</span></span></label>
                        <label class="flex items-start gap-2"><input type="radio" name="status" value="302" class="mt-1 accent-nova-600"><span><strong>Vorübergehend (302)</strong><span class="block text-slate-500">Für kurzfristige Aktionen.</span></span></label>
                    </div>
                </fieldset>
                <button class="nova-btn w-full sm:w-auto">Weiterleitung speichern</button>
            </form>
        </section>

        <section class="nova-card p-0 lg:col-span-3" aria-labelledby="redirect-list">
            <h2 id="redirect-list" class="px-6 pt-5 font-semibold">Aktive Weiterleitungen</h2>
            @if ($redirects->isEmpty())
                <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Noch keine Weiterleitungen.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm" data-redirects>
                        <thead class="bg-slate-50 text-left text-xs text-slate-600"><tr><th scope="col" class="px-6 py-2 font-semibold">Von</th><th scope="col" class="px-3 py-2 font-semibold">Nach</th><th scope="col" class="px-3 py-2 text-right font-semibold">Aufrufe</th><th scope="col" class="px-6 py-2"><span class="sr-only">Aktionen</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($redirects as $r)
                                <tr>
                                    <td class="px-6 py-2.5 font-mono text-xs break-all">{{ $r->from_path }} @if($r->automatic)<span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-sans text-[10px] text-slate-600">automatisch</span>@endif</td>
                                    <td class="px-3 py-2.5 font-mono text-xs break-all">{{ $r->to_url }} <span class="font-sans text-slate-400">({{ $r->status }})</span></td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $r->hits }}</td>
                                    <td class="px-6 py-2.5 text-right"><form method="POST" action="{{ route('admin.seo.redirects.destroy', $r) }}">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline" aria-label="Weiterleitung von {{ $r->from_path }} löschen">Löschen</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="nova-card mt-6 p-0" aria-labelledby="notfound-title">
        <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-5">
            <div>
                <h2 id="notfound-title" class="font-semibold">Nicht gefundene Adressen</h2>
                <p class="mt-1 text-sm text-slate-600">Diese Adressen wurden aufgerufen, gibt es auf Ihrer Website aber nicht. Leiten Sie häufig aufgerufene weiter.</p>
            </div>
            @if ($notFound->isNotEmpty())<form method="POST" action="{{ route('admin.seo.notfound.clear') }}">@csrf @method('DELETE')<button class="text-sm text-slate-600 hover:underline">Liste leeren</button></form>@endif
        </div>
        @if ($notFound->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Bisher keine. Sehr gut.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm" data-not-found>
                    <thead class="bg-slate-50 text-left text-xs text-slate-600"><tr><th scope="col" class="px-6 py-2 font-semibold">Adresse</th><th scope="col" class="px-3 py-2 text-right font-semibold">Aufrufe</th><th scope="col" class="px-3 py-2 font-semibold">Zuletzt</th><th scope="col" class="px-6 py-2"><span class="sr-only">Aktionen</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($notFound as $n)
                            <tr>
                                <td class="px-6 py-2.5 font-mono text-xs break-all">{{ $n->path }}@if($n->referer)<span class="block font-sans text-[11px] text-slate-400">von {{ \Illuminate\Support\Str::limit($n->referer, 60) }}</span>@endif</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $n->hits }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ $n->last_seen_at?->format('d.m.Y') }}</td>
                                <td class="px-6 py-2.5 text-right whitespace-nowrap"><a href="{{ route('admin.seo.redirects', ['von' => $n->path]) }}#redirect-form" class="font-medium text-nova-700 hover:underline">Weiterleiten</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
