@extends('site.community._layout')
@section('ctitle', 'Nachrichten mit '.$other->name)
@section('cnoindex', '1')
@section('main')
    <div class="flex h-[calc(100vh-11rem)] min-h-[28rem] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-conversation>
        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
            <a href="{{ route('community.messages') }}" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Zurück zu allen Nachrichten">←</a>
            @include('site.community._avatar', ['p' => $profile, 'size' => 'size-9'])
            <div class="min-w-0"><a href="{{ $profile->url() }}" class="font-semibold hover:underline">{{ $other->name }}</a><p class="text-xs text-slate-500">{{ $profile->isOnline() ? 'online' : ($profile->last_seen_at ? 'zuletzt '.$profile->last_seen_at->locale('de')->diffForHumans() : '@'.$profile->handle) }}</p></div>
        </div>
        <div class="flex-1 space-y-2 overflow-y-auto bg-slate-50/60 px-4 py-4" data-messages>
            @forelse ($messages as $m)
                @php $mine = $m->user_id === $me->id; @endphp
                <div @class(['flex', 'justify-end' => $mine]) id="nachricht-{{ $m->id }}">
                    <div @class(['max-w-[80%] rounded-2xl px-3.5 py-2 text-sm shadow-sm', 'rounded-br-md bg-nova-600 text-white' => $mine, 'rounded-bl-md bg-white text-slate-800 ring-1 ring-slate-200' => ! $mine])>
                        <div class="break-words [&_a]:!text-inherit [&_a]:underline">{{ \App\Support\Community\Text::render($m->body) }}</div>
                        <p @class(['mt-0.5 text-right text-[10px]', 'text-white/70' => $mine, 'text-slate-400' => ! $mine])>{{ $m->created_at->locale('de')->isoFormat('D. MMM, HH:mm') }}</p>
                    </div>
                </div>
                @if (! $mine && $loop->last)<div class="flex justify-start">@include('site.community._report', ['type' => 'message', 'id' => $m->id])</div>@endif
            @empty
                <p class="py-10 text-center text-sm text-slate-500">Schreiben Sie {{ $other->name }} die erste Nachricht.</p>
            @endforelse
            <span id="ende"></span>
        </div>
        @if ($reason)
            <p class="border-t border-slate-100 px-4 py-3 text-sm text-slate-600">{{ $reason }}</p>
        @else
            <form method="POST" action="{{ route('community.messages.store', $profile) }}" class="flex items-end gap-2 border-t border-slate-100 p-3">
                @csrf
                <label for="msg" class="sr-only">Nachricht an {{ $other->name }}</label>
                <textarea id="msg" name="body" rows="1" maxlength="5000" required class="nova-input max-h-40 flex-1 resize-y rounded-2xl text-sm" placeholder="Nachricht schreiben …" autofocus></textarea>
                <button class="rounded-full bg-nova-600 p-3 text-white hover:bg-nova-700" aria-label="Senden" data-send><x-icon name="send" class="size-4.5" /></button>
            </form>
        @endif
    </div>
    <script>document.querySelector('[data-messages]')?.scrollTo(0, 1e9);</script>
@endsection
