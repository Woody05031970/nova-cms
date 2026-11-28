@extends('site.community._layout')
@section('ctitle', 'Nachrichten')
@section('cnoindex', '1')
@section('main')
    <h1 class="text-2xl font-semibold tracking-tight">Nachrichten</h1>
    <p class="text-sm text-slate-600">Persönliche Nachrichten sehen nur Sie und die andere Person. Eine neue Nachricht schreiben Sie über das Profil, z. B. unter <a href="{{ route('community.people') }}" class="text-nova-700 hover:underline">Leute</a>.</p>
    <ul class="mt-5 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white" data-conversations>
        @forelse ($conversations as $c)
            @php $other = $c->other($me); $last = $c->messages->first(); $unread = $c->isUnreadFor($me); @endphp
            @if ($other?->communityProfile)
                <li><a href="{{ route('community.conversation', $other->communityProfile) }}" @class(['flex items-center gap-3 px-4 py-3 hover:bg-slate-50', 'bg-nova-50/50' => $unread])>
                    @include('site.community._avatar', ['p' => $other->communityProfile, 'size' => 'size-11'])
                    <span class="min-w-0 flex-1"><span class="flex items-baseline justify-between gap-2"><span @class(['truncate', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $other->name }}</span><span class="shrink-0 text-xs text-slate-500">{{ $c->last_message_at->locale('de')->diffForHumans() }}</span></span>
                        <span @class(['block truncate text-sm', 'text-slate-900' => $unread, 'text-slate-500' => ! $unread])>{{ $last?->user_id === $me->id ? 'Sie: ' : '' }}{{ \App\Support\Community\Text::excerpt($last?->body, 90) }}</span></span>
                    @if ($unread)<span class="size-2.5 rounded-full bg-fuchsia-600" aria-label="ungelesen"></span>@endif
                </a></li>
            @endif
        @empty
            <li class="p-8 text-center text-sm text-slate-600">Noch keine Nachrichten.</li>
        @endforelse
    </ul>
@endsection
