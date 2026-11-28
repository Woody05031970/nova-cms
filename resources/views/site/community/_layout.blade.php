@extends('site.layout')
@php
    $C = \App\Support\Community\Community::class;
    $me = $C::me();
    $counts = $me ? $C::counts($me) : ['messages' => 0, 'notifications' => 0];
    $myProfile = $me ? $C::profile($me) : null;
    $nav = [
        ['Pinnwand', route('community.home'), 'community.home|community.post', 'home', 0],
        ['Foren', route('community.forums'), 'community.forums|community.board|community.topic*', 'chat', 0],
        ['Leute', route('community.people'), 'community.people|community.profile|community.page', 'users', 0],
    ];
    if ($me) {
        $nav[] = ['Nachrichten', route('community.messages'), 'community.messages|community.conversation', 'mail', $counts['messages']];
        $nav[] = ['Benachrichtigungen', route('community.notifications'), 'community.notifications', 'bell', $counts['notifications']];
        $nav[] = ['Mein Profil', $myProfile->url(), '__none', 'user', 0];
        if ($C::pagesAllowed()) $nav[] = ['Meine Seite', route('community.page.edit'), 'community.page.edit', 'pages', 0];
    }
    $isActive = fn ($pattern) => collect(explode('|', $pattern))->contains(fn ($p) => request()->routeIs($p));
@endphp
@section('title', trim($__env->yieldContent('ctitle', $C::title())).' – '.$siteName)
@section('meta')
    @hasSection('cnoindex')<meta name="robots" content="noindex">@else @if (! $C::isPublic())<meta name="robots" content="noindex">@endif @endif
@endsection
@section('content')
<div class="bg-slate-50/60" data-community>
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[13rem_1fr] lg:py-10 @hasSection('aside') xl:grid-cols-[13rem_1fr_18rem] @endif">
        <nav aria-label="{{ $C::title() }}" class="min-w-0 lg:sticky lg:top-24 lg:self-start">
            <a href="{{ route('community.home') }}" class="hidden text-lg font-semibold tracking-tight lg:block">{{ $C::title() }}</a>
            <ul class="flex gap-1 overflow-x-auto pb-1 lg:mt-4 lg:flex-col lg:overflow-visible">
                @foreach ($nav as [$label, $url, $pattern, $icon, $badge])
                    @php $active = $pattern === '__none' ? request()->url() === $url : ($isActive($pattern) && ! ($label === 'Leute' && $myProfile && request()->url() === $myProfile->url())); @endphp
                    <li class="shrink-0"><a href="{{ $url }}" @if($active) aria-current="page" @endif @class(['flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition', 'bg-white text-nova-700 shadow-sm ring-1 ring-slate-200' => $active, 'text-slate-600 hover:bg-white hover:text-slate-900' => ! $active])>
                        <x-icon :name="$icon" class="size-4.5 shrink-0" /><span>{{ $label }}</span>
                        @if ($badge)<span class="ml-auto rounded-full bg-fuchsia-600 px-1.5 text-[11px] font-semibold text-white" aria-label="{{ $badge }} neu">{{ $badge }}</span>@endif
                    </a></li>
                @endforeach
            </ul>
            <div class="mt-5 hidden lg:block">
                @if ($me)
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-2.5">
                        @include('site.community._avatar', ['p' => $myProfile, 'size' => 'size-9'])
                        <div class="min-w-0 text-sm"><p class="truncate font-medium">{{ $me->name }}</p><a href="{{ route('community.edit') }}" class="text-xs text-slate-500 hover:text-nova-700">Profil bearbeiten</a></div>
                    </div>
                    @unless ($me->isAdmin())<form method="POST" action="{{ route('members.logout') }}" class="mt-2">@csrf<button class="px-3 text-xs text-slate-500 hover:text-slate-800">Abmelden</button></form>@endunless
                @else
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                        <p class="font-medium">Mitmachen?</p>
                        <p class="mt-1 text-slate-600">Melden Sie sich an, um zu schreiben, zu kommentieren und Nachrichten zu schicken.</p>
                        <a href="{{ route('members.login') }}" class="mt-3 block rounded-lg bg-nova-600 px-3 py-2 text-center font-semibold text-white hover:bg-nova-700">Anmelden</a>
                        @if (\App\Support\Members\Members::registration() !== 'off')<a href="{{ route('members.register') }}" class="mt-2 block text-center text-nova-700 hover:underline">Registrieren</a>@endif
                    </div>
                @endif
            </div>
        </nav>
        <main class="min-w-0" id="community-main">
            @if (session('status'))<p class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
            @yield('main')
        </main>
        @hasSection('aside')<aside class="hidden space-y-4 xl:block">@yield('aside')</aside>@endif
    </div>
</div>
@endsection
