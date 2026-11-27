@extends('layouts.admin')
@section('title', 'Sprachen')
@section('width', 'max-w-5xl')

@php $L = \App\Support\I18n\Languages::class; @endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Sprachen</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Ihre Website ist auf Deutsch. Hier schalten Sie weitere Sprachen ein. Übersetzte Seiten erscheinen dann unter eigenen Adressen wie <span class="font-mono text-sm">/en/…</span>, Besucher wechseln oben rechts die Sprache, und Google bekommt die passenden Hinweise (hreflang).</p>

    <section class="nova-card mt-8" aria-labelledby="l-pick">
        <h2 id="l-pick" class="font-semibold">Weitere Sprachen</h2>
        <form method="POST" action="{{ route('admin.languages.update') }}" class="mt-4">
            @csrf @method('PUT')
            <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($L::AVAILABLE as $code => [$native, $german])
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm has-[:checked]:border-nova-400 has-[:checked]:bg-nova-50">
                        <input type="checkbox" name="languages[]" value="{{ $code }}" class="accent-nova-600" @checked(in_array($code, $active, true))>
                        <span>{{ $german }}<span class="block text-xs text-slate-500" lang="{{ $code }}">{{ $native }}</span></span>
                    </label>
                @endforeach
            </div>
            <button class="nova-btn mt-4 w-full sm:w-auto" data-languages-save>Speichern</button>
        </form>
    </section>

    @if ($active)
        <section class="nova-card mt-6 p-0" aria-labelledby="l-pages">
            <div class="px-6 pt-5">
                <h2 id="l-pages" class="font-semibold">Übersetzungen der Seiten</h2>
                <p class="mt-1 text-sm text-slate-600">Grün ist online, grau ein Entwurf. Fehlt eine Übersetzung, öffnen Sie die Seite und klicken Sie unter „Übersetzungen“ auf „Anlegen“. Seiten ohne Übersetzung bleiben in der deutschen Fassung erreichbar.</p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm" data-language-matrix>
                    <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-2 font-medium">Seite</th>@foreach ($active as $code)<th class="px-3 py-2 text-center font-medium"><abbr title="{{ $L::name($code, false) }}">{{ $code }}</abbr></th>@endforeach</tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pages as $p)
                            @php $byLocale = $p->translations->keyBy('locale'); @endphp
                            <tr>
                                <td class="px-6 py-2.5"><a href="{{ route('admin.pages.edit', $p) }}" class="font-medium hover:text-nova-700">{{ $p->title }}</a>@if($p->is_home)<span class="ml-2 text-xs text-slate-500">Startseite</span>@endif</td>
                                @foreach ($active as $code)
                                    <td class="px-3 py-2.5 text-center">
                                        @if ($t = $byLocale->get($code))
                                            <a href="{{ route('admin.pages.edit', $t) }}" @class(['inline-flex size-7 items-center justify-center rounded-full', 'bg-emerald-50 text-emerald-700' => $t->isPublished(), 'bg-slate-100 text-slate-500' => ! $t->isPublished()]) title="{{ $t->title }}"><x-icon name="check" class="size-4" /><span class="sr-only">{{ $L::name($code, false) }}: {{ $t->isPublished() ? 'online' : 'Entwurf' }}</span></a>
                                        @else
                                            <span class="text-slate-300" aria-label="{{ $L::name($code, false) }}: fehlt">–</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($legal)<p class="px-6 py-4 text-xs text-slate-500">Impressum, Datenschutz und andere Rechtstexte bleiben auf Deutsch, so wie es für Anbieter in Deutschland üblich ist.</p>@endif
        </section>
    @endif
@endsection
