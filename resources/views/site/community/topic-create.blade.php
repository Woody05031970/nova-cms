@extends('site.community._layout')
@section('ctitle', 'Neues Thema')
@section('cnoindex', '1')
@section('main')
    <nav class="text-sm text-slate-500" aria-label="Pfad"><a href="{{ route('community.forums') }}" class="hover:text-nova-700">Foren</a> › <a href="{{ $board->url() }}" class="hover:text-nova-700">{{ $board->name }}</a></nav>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Neues Thema</h1>
    <form method="POST" action="{{ route('community.topic.store', $board) }}" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        <div><label class="nova-label" for="title">Titel</label><input class="nova-input" id="title" name="title" value="{{ old('title') }}" required minlength="4" maxlength="160" placeholder="Worum geht es?"></div>
        <div><label class="nova-label" for="body">Ihr Beitrag</label><textarea class="nova-input" id="body" name="body" rows="10" required maxlength="10000">{{ old('body') }}</textarea><p class="mt-1 text-xs text-slate-500">Links werden klickbar, @kurzname erwähnt jemanden, **fett** hebt hervor.</p></div>
        <div class="flex gap-2"><button class="nova-btn" data-topic-submit>Thema eröffnen</button><a href="{{ $board->url() }}" class="nova-btn-secondary">Abbrechen</a></div>
    </form>
@endsection
