@extends('site.community._layout')
@section('ctitle', 'Leute')
@section('main')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-semibold tracking-tight">Leute</h1><p class="text-sm text-slate-600">Alle Mitglieder der {{ \App\Support\Community\Community::title() }}.</p></div>
        <form method="GET" class="flex gap-2" role="search"><label for="q" class="sr-only">Mitglied suchen</label><input id="q" name="q" value="{{ $q }}" class="nova-input py-2 text-sm" placeholder="Name suchen"><button class="nova-btn-secondary py-2 text-sm">Suchen</button></form>
    </div>
    <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-people>
        @forelse ($profiles as $p)
            <li>
                <a href="{{ $p->url() }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 hover:border-nova-300 hover:shadow-sm">
                    <span class="relative">@include('site.community._avatar', ['p' => $p, 'size' => 'size-12'])@if($p->isOnline())<span class="absolute right-0 bottom-0 size-3 rounded-full bg-emerald-500 ring-2 ring-white" title="Gerade online"></span>@endif</span>
                    <span class="min-w-0"><span class="block truncate font-semibold">{{ $p->user->name }}</span><span class="block truncate text-sm text-slate-500">{{ $p->headline ?: '@'.$p->handle }}</span></span>
                </a>
            </li>
        @empty
            <li class="text-slate-600">Niemand gefunden.</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $profiles->links() }}</div>
@endsection
