@extends('site.community._layout')
@section('ctitle', $board->name)
@section('main')
    @php $me = \App\Support\Community\Community::me(); @endphp
    <nav class="text-sm text-slate-500" aria-label="Pfad"><a href="{{ route('community.forums') }}" class="hover:text-nova-700">Foren</a> › <span class="text-slate-800">{{ $board->name }}</span></nav>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-semibold tracking-tight">{{ $board->name }}</h1>@if($board->description)<p class="text-sm text-slate-600">{{ $board->description }}</p>@endif</div>
        @if ($me && (! $board->is_locked || $me->isAdmin()))
            <a href="{{ route('community.topic.create', $board) }}" class="nova-btn py-2 text-sm" data-new-topic>Neues Thema</a>
        @elseif (! $me)
            <a href="{{ route('members.login') }}" class="nova-btn-secondary py-2 text-sm">Anmelden zum Mitschreiben</a>
        @endif
    </div>
    <ul class="mt-5 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-topics>
        @forelse ($topics as $t)
            <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                @include('site.community._avatar', ['p' => $t->user?->communityProfile, 'size' => 'size-9'])
                <div class="min-w-0 flex-1">
                    <a href="{{ $t->url() }}" class="font-medium hover:text-nova-700">@if($t->is_pinned)<span class="mr-1 text-nova-700" title="Angeheftet">📌</span>@endif{{ $t->title }}@if($t->is_locked)<span class="ml-1.5 text-xs text-slate-500">(geschlossen)</span>@endif</a>
                    <p class="text-xs text-slate-500">von {{ $t->user?->name ?? 'Gelöschtes Konto' }} · {{ $t->created_at->locale('de')->isoFormat('D. MMM YYYY') }}</p>
                </div>
                <div class="hidden w-24 text-center text-sm sm:block"><span class="font-semibold">{{ $t->replies_count }}</span><span class="block text-xs text-slate-500">Antworten</span></div>
                <div class="hidden w-40 text-right text-xs text-slate-500 md:block">{{ ($t->last_reply_at ?? $t->created_at)->locale('de')->diffForHumans() }}<br>{{ $t->lastUser?->name }}</div>
            </li>
        @empty
            <li class="p-8 text-center text-sm text-slate-600">Noch keine Themen. Fangen Sie an!</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $topics->links() }}</div>
@endsection
