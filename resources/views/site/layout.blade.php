@php
    $menu = \App\Support\Menu\SiteMenu::tree();
    $legalOrder = ['imprint', 'privacy', 'terms', 'withdrawal', 'shipping'];
    $legal = \App\Models\Page::published()->whereIn('purpose', $legalOrder)->get(['title', 'slug', 'is_home', 'purpose'])
        ->sortBy(fn ($p) => array_search($p->purpose, $legalOrder))->values();
    $shopOn = \App\Shop\ShopSettings::enabled();
    $cartCount = $shopOn ? app(\App\Shop\Cart::class)->count() : 0;
    $s = fn ($k) => \App\Models\Setting::get($k);
    $current = request()->path();
    // Vorschau aus dem Design-Bereich (nur für angemeldete Administratoren)
    \App\Support\Design\SiteDesign::preview(request()->filled('design_vorschau') && auth()->user()?->isAdmin()
        ? (json_decode(base64_decode((string) request('design_vorschau')), true) ?: null) : null);
    $design = \App\Support\Design\SiteDesign::values();
    $wa = \App\Support\WhatsApp::button();
    $darkHeader = $design['header'] === 'dark';
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
    <style>{!! \App\Support\Design\SiteDesign::css($design) !!}</style>
</head>
<body class="nova-site flex min-h-screen flex-col font-sans text-slate-900 antialiased" data-theme="{{ $design['theme'] }}" data-anim="{{ $design['animation'] }}" @if($design['heading_font'] || $design['font'] !== 'system') data-heading-font @endif>
    @yield('banner')
    <header @class(['nova-header sticky top-0 z-40', 'border-b border-slate-100 bg-white' => $design['header'] === 'light', 'is-glass border-b border-white/40 bg-white/70 backdrop-blur-xl' => $design['header'] === 'glass', 'is-dark bg-slate-950 text-white' => $darkHeader])>
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-semibold tracking-tight" data-site-brand>
                @if ($design['logo'])<img src="{{ $design['logo'] }}" alt="{{ $design['show_name'] ? '' : $siteName }}" style="height: {{ $design['logo_height'] }}px" class="w-auto">@endif
                @if (! $design['logo'] || $design['show_name'])<span>{{ $siteName }}</span>@endif
            </a>
            <div class="flex items-center gap-1">
            @if ($menu)
                <details class="relative sm:hidden" data-mobile-menu>
                    <summary @class(['cursor-pointer list-none rounded-md p-2', 'text-slate-700 hover:bg-slate-100' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader]) aria-label="Menü">
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
                        @php $cls = ['inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $item['active'] && ! $darkHeader, 'text-slate-700 hover:bg-slate-50' => ! $item['active'] && ! $darkHeader, 'bg-white/10 text-white' => $item['active'] && $darkHeader, 'text-white/75 hover:bg-white/10 hover:text-white' => ! $item['active'] && $darkHeader]; @endphp
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
                <a href="{{ route('shop.cart') }}" @class(['relative rounded-lg p-2', 'text-slate-700 hover:bg-slate-50' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader]) aria-label="Warenkorb, {{ $cartCount }} Artikel">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11h11l2-8H6.2M9 20a1 1 0 1 0 0-.01M18 20a1 1 0 1 0 0-.01"/></svg>
                    @if ($cartCount)<span class="absolute -top-0.5 -right-0.5 grid min-w-5 place-items-center rounded-full bg-nova-600 px-1 text-[11px] font-semibold text-white" data-cart-count>{{ $cartCount }}</span>@endif
                </a>
            @endif
            </div>
        </div>
    </header>

    <main class="flex-1">@yield('content')</main>

    @php $darkFooter = $design['footer'] === 'dark'; @endphp
    <footer @class(['border-t border-slate-100 bg-slate-50' => ! $darkFooter, 'nova-footer-dark border-t border-white/5 bg-slate-950 text-white/70' => $darkFooter])>
        <div @class(['mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm sm:grid-cols-2 sm:px-6', 'text-slate-600' => ! $darkFooter])>
            <div>
                @if ($design['logo'] && ! $darkFooter)<img src="{{ $design['logo'] }}" alt="" class="mb-3 h-8 w-auto">@endif
                <p @class(['font-semibold', 'text-slate-900' => ! $darkFooter, 'text-white' => $darkFooter])>{{ $s('company_name') ?? $siteName }}</p>
                @if ($s('street'))<p>{{ $s('street') }}</p>@endif
                @if ($s('zip_city'))<p>{{ $s('zip_city') }}</p>@endif
                @if ($s('phone'))<p class="mt-2">Telefon: <a class="hover:text-slate-900" href="tel:{{ preg_replace('/[^+0-9]/', '', $s('phone')) }}">{{ $s('phone') }}</a></p>@endif
                @if ($s('contact_email'))<p>E-Mail: <a class="hover:text-slate-900" href="mailto:{{ $s('contact_email') }}">{{ $s('contact_email') }}</a></p>@endif
            </div>
            <nav class="flex flex-wrap items-end gap-x-5 gap-y-2 sm:justify-end" aria-label="Rechtliches">
                @foreach ($legal as $l)<a class="hover:text-slate-900" href="{{ $l->url() }}">{{ $l->title }}</a>@endforeach
                <span class="opacity-60">© {{ date('Y') }} {{ $siteName }}</span>
            </nav>
        </div>
    </footer>
    @if ($wa)
        <a href="{{ $wa['url'] }}" target="_blank" rel="noopener" data-whatsapp-button
           @class(['fixed bottom-5 z-50 inline-flex items-center gap-2 rounded-full bg-[#25d366] py-3 pr-4 pl-3 font-semibold text-white shadow-xl shadow-black/15 transition hover:scale-105 hover:bg-[#1ebe5b]', 'right-5' => $wa['position'] === 'right', 'left-5' => $wa['position'] === 'left'])
           aria-label="Per WhatsApp schreiben">
            <svg class="size-7" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.7a10.7 10.7 0 0 1-5.5-1.5l-.4-.2-3.9 1 1-3.8-.2-.4A10.7 10.7 0 1 1 16 26.7zm5.9-8c-.3-.2-1.9-1-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.8 8.8 0 0 1-4.4-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.6l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .9 2.8 1 3.9.8a3.3 3.3 0 0 0 2.1-1.5 2.7 2.7 0 0 0 .2-1.5c-.1-.2-.3-.3-.6-.4z"/></svg>
            @if ($wa['label'])<span class="hidden text-sm sm:inline">{{ $wa['label'] }}</span>@endif
        </a>
    @endif
</body>
</html>
