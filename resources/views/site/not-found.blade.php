@extends('site.layout')
@section('title', 'Seite nicht gefunden – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <section class="mx-auto max-w-xl px-4 py-20 text-center sm:px-6">
        <p class="font-mono text-sm font-semibold text-nova-700">Fehler 404</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Diese Seite gibt es leider nicht</h1>
        <p class="mt-3 text-lg text-slate-600">Vielleicht wurde sie verschoben oder umbenannt. Suchen Sie, was Sie brauchen, oder gehen Sie zur Startseite.</p>
        <div class="text-left">@include('site.partials.search-form', ['id' => '404'])</div>
        <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-800 hover:bg-slate-50">Zur Startseite</a>
    </section>
@endsection
