@extends('site.community._layout')
@section('ctitle', $topic->title)
@section('main')
    @php $me = \App\Support\Community\Community::me(); $T = \App\Support\Community\Text::class; @endphp
    <nav class="text-sm text-slate-500" aria-label="Pfad"><a href="{{ route('community.forums') }}" class="hover:text-nova-700">Foren</a> › <a href="{{ $topic->board->url() }}" class="hover:text-nova-700">{{ $topic->board->name }}</a></nav>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">@if($topic->is_pinned)<span title="Angeheftet">📌 </span>@endif{{ $topic->title }}</h1>
        @if ($me?->isAdmin())
            <div class="flex gap-2">
                <form method="POST" action="{{ route('community.topic.moderate', $topic) }}">@csrf @method('PUT')<input type="hidden" name="toggle" value="pin"><button class="nova-btn-secondary py-1.5 text-sm">{{ $topic->is_pinned ? 'Lösen' : 'Anheften' }}</button></form>
                <form method="POST" action="{{ route('community.topic.moderate', $topic) }}">@csrf @method('PUT')<input type="hidden" name="toggle" value="lock"><button class="nova-btn-secondary py-1.5 text-sm" data-lock>{{ $topic->is_locked ? 'Öffnen' : 'Schließen' }}</button></form>
            </div>
        @endif
    </div>
    <p class="text-sm text-slate-500">{{ $topic->replies_count }} {{ $topic->replies_count === 1 ? 'Antwort' : 'Antworten' }} · {{ $topic->views_count }} Aufrufe</p>

    @php
        $entries = collect($replies->onFirstPage() ? [['topic', $topic]] : [])->concat($replies->map(fn ($r) => ['reply', $r]));
    @endphp
    <div class="mt-5 space-y-4" data-replies>
        @foreach ($entries as [$kind, $e])
            <article @class(['rounded-2xl border bg-white shadow-sm', 'border-nova-200' => $kind === 'topic', 'border-slate-200' => $kind !== 'topic']) id="{{ $kind === 'topic' ? 'beitrag' : 'antwort-'.$e->id }}">
                <div class="flex flex-col sm:flex-row">
                    <div class="flex items-center gap-3 border-b border-slate-100 p-4 sm:w-44 sm:flex-col sm:items-start sm:border-r sm:border-b-0">
                        @include('site.community._avatar', ['p' => $e->user?->communityProfile, 'size' => 'size-12'])
                        <div class="text-sm">@include('site.community._name', ['u' => $e->user])
                            @if ($e->user?->communityProfile?->headline)<p class="text-xs text-slate-500">{{ $e->user->communityProfile->headline }}</p>@endif</div>
                    </div>
                    <div class="min-w-0 flex-1 p-4 sm:p-5">
                        <p class="text-xs text-slate-500">{{ $e->created_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr</p>
                        <div class="mt-2 leading-relaxed break-words text-slate-800">{{ $T::render($e->body) }}</div>
                        <div class="mt-3 flex justify-end gap-1 text-xs">
                            @include('site.community._report', ['type' => $kind, 'id' => $e->id])
                            @if ($kind === 'reply' && $me && ($me->id === $e->user_id || $me->isAdmin()))
                                <form method="POST" action="{{ route('community.reply.destroy', $e) }}" onsubmit="return confirm('Antwort löschen?')">@csrf @method('DELETE')<button class="rounded-md px-2 py-1 text-red-700 hover:bg-red-50">Löschen</button></form>
                            @elseif ($kind === 'topic' && $me && ($me->isAdmin() || ($me->id === $e->user_id && $e->replies_count === 0)))
                                <form method="POST" action="{{ route('community.topic.destroy', $e) }}" onsubmit="return confirm('Thema löschen?')">@csrf @method('DELETE')<button class="rounded-md px-2 py-1 text-red-700 hover:bg-red-50">Thema löschen</button></form>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
    <div class="mt-6">{{ $replies->links() }}</div>

    @if ($topic->is_locked && ! $me?->isAdmin())
        <p class="mt-6 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">Dieses Thema ist geschlossen. Antworten sind nicht mehr möglich.</p>
    @elseif ($me)
        <form method="POST" action="{{ route('community.topic.reply', $topic) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" id="antworten">
            @csrf
            <label class="nova-label" for="reply">Ihre Antwort</label>
            <textarea class="nova-input" id="reply" name="body" rows="5" required maxlength="10000">{{ old('body') }}</textarea>
            <button class="nova-btn mt-3" data-reply-submit>Antworten</button>
        </form>
    @else
        <p class="mt-6 rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-slate-200"><a href="{{ route('members.login') }}" class="font-medium text-nova-700 hover:underline">Melden Sie sich an</a>, um zu antworten.</p>
    @endif
@endsection
