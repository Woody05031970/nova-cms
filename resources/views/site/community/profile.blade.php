@extends('site.community._layout')
@section('ctitle', $user->name)
@section('main')
    @php $me = \App\Support\Community\Community::me(); @endphp
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-profile="{{ $profile->handle }}">
        <div @class(['h-36 bg-gradient-to-r sm:h-48', $profile->colorClass()]) @if($profile->cover) style="background-image:url('{{ $profile->cover }}');background-size:cover;background-position:center" @endif></div>
        <div class="px-5 pb-5 sm:px-6">
            <div class="-mt-12 flex flex-wrap items-end justify-between gap-3">
                <span class="rounded-full bg-white p-1 shadow">@include('site.community._avatar', ['p' => $profile, 'size' => 'size-24 text-2xl'])</span>
                <div class="flex flex-wrap gap-2">
                    @if ($isMe)
                        <a href="{{ route('community.edit') }}" class="nova-btn-secondary py-2 text-sm">Profil bearbeiten</a>
                        @if (\App\Support\Community\Community::pagesAllowed())<a href="{{ route('community.page.edit') }}" class="nova-btn py-2 text-sm">Meine Seite gestalten</a>@endif
                    @elseif ($me)
                        <form method="POST" action="{{ route('community.follow', $profile) }}">@csrf<button @class(['py-2 text-sm', 'nova-btn-secondary' => $following, 'nova-btn' => ! $following]) data-follow>{{ $following ? 'Folge ich' : 'Folgen' }}</button></form>
                        @unless ($blocked)<a href="{{ route('community.conversation', $profile) }}" class="nova-btn-secondary py-2 text-sm">Nachricht schreiben</a>@endunless
                        <details class="relative">
                            <summary class="nova-btn-secondary cursor-pointer list-none px-3 py-2 text-sm" aria-label="Weitere Aktionen">…</summary>
                            <div class="absolute right-0 z-20 mt-1 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                <form method="POST" action="{{ route('community.block', $profile) }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $blocked ? 'Blockierung aufheben' : 'Blockieren' }}</button></form>
                                <div class="px-1">@include('site.community._report', ['type' => 'profile', 'id' => $profile->id])</div>
                            </div>
                        </details>
                    @endif
                </div>
            </div>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ $user->name }}@if($user->isAdmin())<span class="ml-2 rounded bg-nova-50 px-1.5 py-0.5 align-middle text-xs font-semibold text-nova-700">Team</span>@endif</h1>
            <p class="text-sm text-slate-500">{{ '@'.$profile->handle }}@if($profile->isOnline()) · <span class="text-emerald-700">online</span>@elseif($profile->last_seen_at) · zuletzt {{ $profile->last_seen_at->locale('de')->diffForHumans() }}@endif</p>
            @if ($profile->isBanned())<p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">Dieses Profil ist gesperrt.</p>@endif
            @if ($profile->headline)<p class="mt-3 font-medium text-slate-800">{{ $profile->headline }}</p>@endif
            @if ($profile->bio)<div class="mt-2 max-w-2xl text-slate-700">{{ \App\Support\Community\Text::render($profile->bio) }}</div>@endif
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                @if ($profile->location)<span>📍 {{ $profile->location }}</span>@endif
                @if ($profile->website)<a href="{{ $profile->website }}" rel="nofollow ugc noopener" target="_blank" class="text-nova-700 hover:underline">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($profile->website, '/')) }}</a>@endif
                <span>Dabei seit {{ $profile->created_at->locale('de')->isoFormat('MMMM YYYY') }}</span>
            </p>
            <dl class="mt-4 flex flex-wrap gap-6 border-t border-slate-100 pt-4">
                @foreach ($stats as $label => $n)<div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="text-lg font-semibold">{{ $n }}</dd></div>@endforeach
            </dl>
            @if (\App\Support\Community\Community::pagesAllowed() && ($profile->page_published || $isMe) && ! empty($profile->page_sections))
                <a href="{{ route('community.page', $profile) }}" class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium hover:bg-slate-100" data-page-link>
                    <span>{{ $profile->page_title ?: 'Die Seite von '.$user->name }}@unless($profile->page_published) <span class="text-slate-500">(nur für Sie sichtbar)</span>@endunless</span><x-icon name="arrow" class="size-4" />
                </a>
            @endif
        </div>
    </section>

    <h2 class="mt-8 text-lg font-semibold">Beiträge</h2>
    <div class="mt-3 space-y-4">
        @forelse ($posts as $post)
            @include('site.community._post', ['post' => $post])
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600">Noch keine Beiträge.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
@endsection
