@extends('site.layout')
@section('title', ($q ? 'Suche: '.$q : 'Suche').' – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">Suche</h1>
        @include('site.partials.search-form', ['value' => $q, 'autofocus' => ! $q])
        @if ($q !== '')
            <p class="mt-6 text-sm text-slate-600" role="status">
                @if (mb_strlen($q) < 2) Bitte geben Sie mindestens zwei Buchstaben ein.
                @elseif (! $results) Keine Treffer für „{{ $q }}“. Versuchen Sie ein anderes Wort.
                @else {{ count($results) }} {{ count($results) === 1 ? 'Treffer' : 'Treffer' }} für „{{ $q }}“
                @endif
            </p>
            @if ($results)
                <ul class="mt-4 space-y-3" data-search-results>
                    @foreach ($results as $r)
                        <li>
                            <a href="{{ $r['url'] }}" class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-nova-300 hover:shadow-sm">
                                <span class="text-xs font-semibold tracking-wide text-nova-700 uppercase">{{ $r['type'] }}</span>
                                <span class="mt-1 block text-lg font-semibold text-slate-900">{{ $r['title'] }}</span>
                                @if ($r['excerpt'])<span class="mt-1 block text-sm text-slate-600">{{ $r['excerpt'] }}</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
@endsection
