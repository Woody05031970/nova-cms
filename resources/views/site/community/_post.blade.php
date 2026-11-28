@php
    $me = \App\Support\Community\Community::me();
    $T = \App\Support\Community\Text::class;
    $own = $me && ($me->id === $post->user_id || $me->isAdmin());
    $isLiked = in_array($post->id, $liked ?? [], true);
    $showAll = $showAll ?? false;
    $comments = $showAll ? $post->comments : $post->comments->slice(-3);
@endphp
<article id="beitrag-{{ $post->id }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm" data-post="{{ $post->id }}">
    <div class="flex items-start gap-3 p-4 sm:p-5">
        @include('site.community._avatar', ['p' => $post->user?->communityProfile])
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <p class="text-sm leading-tight">@include('site.community._name', ['u' => $post->user])
                    <span class="block text-xs text-slate-500"><a href="{{ $post->url() }}" class="hover:underline">{{ $post->created_at->locale('de')->diffForHumans() }}</a>@if($post->is_pinned) · <span class="font-medium text-nova-700">Angeheftet</span>@endif</span></p>
                <div class="flex items-center">
                    @include('site.community._report', ['type' => 'post', 'id' => $post->id])
                    @if ($me?->isAdmin())<form method="POST" action="{{ route('community.posts.pin', $post) }}">@csrf<button class="rounded-md px-2 py-1 text-xs text-slate-500 hover:bg-slate-100">{{ $post->is_pinned ? 'Lösen' : 'Anheften' }}</button></form>@endif
                    @if ($own)<form method="POST" action="{{ route('community.posts.destroy', $post) }}" onsubmit="return confirm('Beitrag löschen?')">@csrf @method('DELETE')<button class="rounded-md px-2 py-1 text-xs text-red-700 hover:bg-red-50" data-post-delete>Löschen</button></form>@endif
                </div>
            </div>
            <div class="mt-2 text-[15px] leading-relaxed break-words text-slate-800">{{ $T::render($post->body) }}</div>
        </div>
    </div>
    @if ($post->image)<a href="{{ $post->image }}" target="_blank" rel="noopener" class="block border-y border-slate-100 bg-slate-50"><img src="{{ $post->image }}" alt="Bild zum Beitrag von {{ $post->user?->name }}" class="mx-auto max-h-[32rem] w-auto" loading="lazy"></a>@endif
    <div class="flex items-center gap-1 border-t border-slate-100 px-3 py-1.5 text-sm">
        @if ($me)
            <form method="POST" action="{{ route('community.posts.like', $post) }}">@csrf
                <button @class(['flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-medium hover:bg-slate-100', 'text-fuchsia-600' => $isLiked, 'text-slate-600' => ! $isLiked]) aria-pressed="{{ $isLiked ? 'true' : 'false' }}" data-like>
                    <svg viewBox="0 0 24 24" class="size-4.5" fill="{{ $isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/></svg>
                    Gefällt mir @if($post->likes_count)<span class="text-slate-500">{{ $post->likes_count }}</span>@endif
                </button>
            </form>
        @elseif ($post->likes_count)
            <span class="px-2.5 py-1.5 text-slate-500">{{ $post->likes_count }} × gefällt das</span>
        @endif
        <a href="{{ $post->url() }}#kommentare" class="rounded-lg px-2.5 py-1.5 text-slate-600 hover:bg-slate-100">{{ $post->comments_count }} {{ $post->comments_count === 1 ? 'Kommentar' : 'Kommentare' }}</a>
    </div>
    @if ($comments->isNotEmpty() || $me)
        <div class="space-y-3 border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-5" id="{{ $showAll ? 'kommentare' : 'k-'.$post->id }}">
            @if (! $showAll && $post->comments_count > 3)<a href="{{ $post->url() }}#kommentare" class="text-xs font-medium text-nova-700 hover:underline">Alle {{ $post->comments_count }} Kommentare ansehen</a>@endif
            @foreach ($comments as $c)
                <div class="flex gap-2.5" id="kommentar-{{ $c->id }}">
                    @include('site.community._avatar', ['p' => $c->user?->communityProfile, 'size' => 'size-8'])
                    <div class="min-w-0 flex-1">
                        <div class="rounded-2xl bg-white px-3 py-2 text-sm ring-1 ring-slate-200/70">@include('site.community._name', ['u' => $c->user])<div class="mt-0.5 break-words text-slate-800">{{ $T::render($c->body) }}</div></div>
                        <div class="mt-0.5 flex items-center gap-1 pl-2 text-xs text-slate-500">
                            <span>{{ $c->created_at->locale('de')->diffForHumans() }}</span>
                            @include('site.community._report', ['type' => 'comment', 'id' => $c->id])
                            @if ($me && ($me->id === $c->user_id || $me->isAdmin()))<form method="POST" action="{{ route('community.comments.destroy', $c) }}">@csrf @method('DELETE')<button class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-red-700">Löschen</button></form>@endif
                        </div>
                    </div>
                </div>
            @endforeach
            @if ($me)
                <form method="POST" action="{{ route('community.comments.store', $post) }}" class="flex items-start gap-2.5">
                    @csrf
                    @include('site.community._avatar', ['p' => \App\Support\Community\Community::profile($me), 'size' => 'size-8'])
                    <label class="sr-only" for="c-{{ $post->id }}">Kommentar schreiben</label>
                    <input id="c-{{ $post->id }}" name="body" maxlength="2000" required class="nova-input flex-1 rounded-full py-1.5 text-sm" placeholder="Kommentar schreiben …">
                    <button class="rounded-full bg-nova-600 p-2 text-white hover:bg-nova-700" aria-label="Kommentar senden"><x-icon name="send" class="size-4" /></button>
                </form>
            @endif
        </div>
    @endif
</article>
