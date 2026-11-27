@php
    $menu = \App\Models\Page::published()->where('show_in_menu', true)->orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->get(['id', 'title', 'slug', 'is_home']);
    $legalOrder = ['imprint', 'privacy', 'terms', 'withdrawal', 'shipping'];
    $legal = \App\Models\Page::published()->whereIn('purpose', $legalOrder)->get(['title', 'slug', 'is_home', 'purpose'])
        ->sortBy(fn ($p) => array_search($p->purpose, $legalOrder))->values();
    $shopOn = \App\Shop\ShopSettings::enabled();
    $cartCount = $shopOn ? app(\App\Shop\Cart::class)->count() : 0;
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
            <div class="flex items-center gap-1">
            @if ($menu->isNotEmpty() || $shopOn)
                <details class="relative sm:hidden">
                    <summary class="cursor-pointer list-none rounded-md p-2 text-slate-700 hover:bg-slate-100" aria-label="Menü">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" aria-label="Hauptmenü">
                        @foreach ($menu as $item)
                            <a href="{{ $item->url() }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">{{ $item->title }}</a>
                        @endforeach
                        @if ($shopOn)<a href="{{ route('shop.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Shop</a>@endif
                    </nav>
                </details>
                <nav class="hidden gap-1 sm:flex" aria-label="Hauptmenü">
                    @foreach ($menu as $item)
                        @php $active = $item->is_home ? $current === '/' : $current === $item->slug; @endphp
                        <a href="{{ $item->url() }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $active, 'text-slate-700 hover:bg-slate-50' => ! $active]) @if($active) aria-current="page" @endif>{{ $item->title }}</a>
                    @endforeach
                    @if ($shopOn)
                        @php $active = str_starts_with($current, 'shop'); @endphp
                        <a href="{{ route('shop.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $active, 'text-slate-700 hover:bg-slate-50' => ! $active])>Shop</a>
                    @endif
                </nav>
            @endif
            @if ($shopOn)
                <a href="{{ route('shop.cart') }}" class="relative rounded-lg p-2 text-slate-700 hover:bg-slate-50" aria-label="Warenkorb, {{ $cartCount }} Artikel">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11h11l2-8H6.2M9 20a1 1 0 1 0 0-.01M18 20a1 1 0 1 0 0-.01"/></svg>
                    @if ($cartCount)<span class="absolute -top-0.5 -right-0.5 grid min-w-5 place-items-center rounded-full bg-nova-600 px-1 text-[11px] font-semibold text-white" data-cart-count>{{ $cartCount }}</span>@endif
                </a>
            @endif
            </div>
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
