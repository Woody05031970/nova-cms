@php
    $menu = \App\Models\Page::published()->where('show_in_menu', true)->orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->get(['id', 'title', 'slug', 'is_home']);
    $legal = \App\Models\Page::published()->whereIn('purpose', ['imprint', 'privacy'])->orderBy('purpose')->get(['title', 'slug', 'is_home']);
    $s = fn ($k) => \App\Models\Setting::get($k);
    $current = request()->path();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @yield('meta')
    @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-slate-900 antialiased">
    @yield('banner')
    <header class="border-b border-slate-100">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">{{ $siteName }}</a>
            @if ($menu->isNotEmpty())
                <details class="relative sm:hidden">
                    <summary class="cursor-pointer list-none rounded-md p-2 text-slate-700 hover:bg-slate-100" aria-label="Menü">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" aria-label="Hauptmenü">
                        @foreach ($menu as $item)
                            <a href="{{ $item->url() }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">{{ $item->title }}</a>
                        @endforeach
                    </nav>
                </details>
                <nav class="hidden gap-1 sm:flex" aria-label="Hauptmenü">
                    @foreach ($menu as $item)
                        @php $active = $item->is_home ? $current === '/' : $current === $item->slug; @endphp
                        <a href="{{ $item->url() }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $active, 'text-slate-700 hover:bg-slate-50' => ! $active]) @if($active) aria-current="page" @endif>{{ $item->title }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </header>

    <main class="flex-1">@yield('content')</main>

    <footer class="border-t border-slate-100 bg-slate-50">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm text-slate-600 sm:grid-cols-2 sm:px-6">
            <div>
                <p class="font-semibold text-slate-900">{{ $s('company_name') ?? $siteName }}</p>
                @if ($s('street'))<p>{{ $s('street') }}</p>@endif
                @if ($s('zip_city'))<p>{{ $s('zip_city') }}</p>@endif
                @if ($s('phone'))<p class="mt-2">Telefon: <a class="hover:text-slate-900" href="tel:{{ preg_replace('/[^+0-9]/', '', $s('phone')) }}">{{ $s('phone') }}</a></p>@endif
                @if ($s('contact_email'))<p>E-Mail: <a class="hover:text-slate-900" href="mailto:{{ $s('contact_email') }}">{{ $s('contact_email') }}</a></p>@endif
            </div>
            <nav class="flex flex-wrap items-end gap-x-5 gap-y-2 sm:justify-end" aria-label="Rechtliches">
                @foreach ($legal as $l)<a class="hover:text-slate-900" href="{{ $l->url() }}">{{ $l->title }}</a>@endforeach
                <span class="text-slate-400">© {{ date('Y') }} {{ $siteName }}</span>
            </nav>
        </div>
    </footer>
</body>
</html>
