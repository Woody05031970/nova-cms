@php
    $menu = \App\Support\Menu\SiteMenu::tree();
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
            @if ($menu)
                <details class="relative sm:hidden" data-mobile-menu>
                    <summary class="cursor-pointer list-none rounded-md p-2 text-slate-700 hover:bg-slate-100" aria-label="Menü">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" aria-label="Hauptmenü">
                        @foreach ($menu as $item)
                            <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class(['block rounded-lg px-3 py-2 text-sm hover:bg-slate-50', 'font-semibold text-nova-700' => $item['active']])>{{ $item['label'] }}</a>
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['url'] }}" @if($child['new_tab']) target="_blank" rel="noopener" @endif @class(['ml-3 block rounded-lg border-l border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50', 'font-semibold text-nova-700' => $child['active']])>{{ $child['label'] }}</a>
                            @endforeach
                        @endforeach
                    </nav>
                </details>
                <nav class="hidden items-center gap-1 sm:flex" aria-label="Hauptmenü">
                    @foreach ($menu as $item)
                        @php $cls = ['inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $item['active'], 'text-slate-700 hover:bg-slate-50' => ! $item['active']]; @endphp
                        @if ($item['children'])
                            <div class="group relative" data-has-submenu>
                                <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class($cls) aria-haspopup="true" @if($item['active']) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                    <svg class="size-3.5 opacity-60 transition group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.2 7.5a.75.75 0 0 1 1.06 0L10 11.2l3.74-3.7a.75.75 0 1 1 1.06 1.06l-4.27 4.24a.75.75 0 0 1-1.06 0L5.2 8.56a.75.75 0 0 1 0-1.06Z"/></svg>
                                </a>
                                <div class="invisible absolute left-0 z-30 pt-2 opacity-0 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                    <div class="min-w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                                        @foreach ($item['children'] as $child)
                                            <a href="{{ $child['url'] }}" @if($child['new_tab']) target="_blank" rel="noopener" @endif @class(['block rounded-lg px-3 py-2 text-sm', 'bg-nova-50 font-medium text-nova-700' => $child['active'], 'text-slate-700 hover:bg-slate-50' => ! $child['active']])>{{ $child['label'] }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class($cls) @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endif
                    @endforeach
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
