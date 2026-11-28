@php
    $menu = \App\Support\Menu\SiteMenu::tree();
    $legalOrder = ['imprint', 'privacy', 'terms', 'withdrawal', 'shipping', 'accessibility'];
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
    $searchOn = \App\Models\Setting::get('site_search', '1') === '1';
    $L = \App\Support\I18n\Languages::class;
    $languages = $L::switcher(isset($page) && $page instanceof \App\Models\Page ? $page : null);
    $variants = isset($page) && $page instanceof \App\Models\Page && empty($preview) ? $L::variants($page) : collect();
@endphp
<!DOCTYPE html>
<html lang="{{ $L::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @yield('meta')
    @foreach ($variants as $code => $v)<link rel="alternate" hreflang="{{ $code }}" href="{{ $v->url() }}">
    @endforeach
    @if ($variants->has('de'))<link rel="alternate" hreflang="x-default" href="{{ $variants['de']->url() }}">@endif
    {!! \App\Support\Design\Favicon::tags() !!}
    @vite(['resources/css/app.css', 'resources/js/site.js'])
    <style>{!! \App\Support\Design\SiteDesign::css($design) !!}</style>
</head>
<body class="nova-site flex min-h-screen flex-col font-sans text-slate-900 antialiased" data-theme="{{ $design['theme'] }}" data-anim="{{ $design['animation'] }}" @if($design['heading_font'] || $design['font'] !== 'system') data-heading-font @endif>
    <a href="#inhalt" class="nova-skip-link">{{ $L::t('Zum Inhalt springen') }}</a>
    @yield('banner')
    <header @class(['nova-header sticky top-0 z-40', 'border-b border-slate-100 bg-white' => $design['header'] === 'light', 'is-glass border-b border-white/40 bg-white/70 backdrop-blur-xl' => $design['header'] === 'glass', 'is-dark bg-slate-950 text-white' => $darkHeader]) data-site-header>
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <a href="{{ $L::homeUrl() }}" class="flex min-w-0 items-center gap-2.5 text-lg font-semibold tracking-tight" data-site-brand @if($design['logo'] && ! $design['show_name']) aria-label="{{ $siteName }}, {{ $L::t('zur Startseite') }}" @endif>
                @if ($design['logo'])<img src="{{ $design['logo'] }}" alt="" style="height: {{ $design['logo_height'] }}px" class="w-auto max-w-[55vw] shrink-0 object-contain object-left">@endif
                @if (! $design['logo'] || $design['show_name'])<span class="truncate">{{ $siteName }}</span>@endif
            </a>
            <div class="flex shrink-0 items-center gap-1">
            @if ($menu)
                <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ $L::t('Hauptmenü') }}">
                    @foreach ($menu as $item)
                        @php $cls = ['inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium', 'text-nova-700 bg-nova-50' => $item['active'] && ! $darkHeader, 'text-slate-700 hover:bg-slate-50' => ! $item['active'] && ! $darkHeader, 'bg-white/10 text-white' => $item['active'] && $darkHeader, 'text-white/75 hover:bg-white/10 hover:text-white' => ! $item['active'] && $darkHeader]; @endphp
                        @if ($item['children'])
                            <div class="group relative" data-has-submenu>
                                <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class($cls) @if($item['active']) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                    <svg class="size-3.5 opacity-60 transition group-hover:rotate-180 group-focus-within:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.2 7.5a.75.75 0 0 1 1.06 0L10 11.2l3.74-3.7a.75.75 0 1 1 1.06 1.06l-4.27 4.24a.75.75 0 0 1-1.06 0L5.2 8.56a.75.75 0 0 1 0-1.06Z"/></svg>
                                </a>
                                <div class="invisible absolute left-0 z-30 pt-2 opacity-0 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                    <ul class="min-w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg" aria-label="Unterpunkte von {{ $item['label'] }}">
                                        @foreach ($item['children'] as $child)
                                            <li><a href="{{ $child['url'] }}" @if($child['new_tab']) target="_blank" rel="noopener" @endif @class(['block rounded-lg px-3 py-2 text-sm', 'bg-nova-50 font-medium text-nova-700' => $child['active'], 'text-slate-700 hover:bg-slate-50' => ! $child['active']]) @if($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class($cls) @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif
            @if ($languages)
                <nav class="flex items-center" aria-label="{{ $L::t('Sprache') }}" data-language-switcher>
                    @foreach ($languages as $lang)
                        <a href="{{ $lang['url'] }}" hreflang="{{ $lang['locale'] }}" lang="{{ $lang['locale'] }}" title="{{ $lang['name'] }}" @if($lang['current']) aria-current="true" @endif
                           @class(['rounded-md px-1.5 py-1 text-xs font-semibold uppercase tracking-wide', 'text-nova-700 underline underline-offset-4' => $lang['current'] && ! $darkHeader, 'text-slate-500 hover:text-slate-900' => ! $lang['current'] && ! $darkHeader, 'text-white underline underline-offset-4' => $lang['current'] && $darkHeader, 'text-white/60 hover:text-white' => ! $lang['current'] && $darkHeader])><span class="sr-only">{{ $lang['name'] }}</span><span aria-hidden="true">{{ $lang['locale'] }}</span></a>
                    @endforeach
                </nav>
            @endif
            @if ($searchOn)
                <a href="{{ route('search') }}" @class(['hidden rounded-lg p-2 sm:inline-flex', 'text-slate-700 hover:bg-slate-50' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader]) aria-label="{{ $L::t('Website durchsuchen') }}" data-search-link>
                    <x-icon name="search" class="size-6" />
                </a>
            @endif
            @if ($shopOn && \App\Support\Members\Accounts::on())
                <a href="{{ route('account.home') }}" @class(['rounded-lg p-2', 'text-slate-700 hover:bg-slate-50' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader]) aria-label="{{ \App\Support\Members\Accounts::customer() ? 'Mein Kundenkonto' : 'Anmelden oder Kundenkonto anlegen' }}" data-account-link>
                    <x-icon name="user" class="size-6" />
                </a>
            @endif
            @if ($shopOn)
                <a href="{{ route('shop.cart') }}" @class(['relative rounded-lg p-2', 'text-slate-700 hover:bg-slate-50' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader]) aria-label="Warenkorb, {{ $cartCount }} {{ $cartCount === 1 ? 'Artikel' : 'Artikel' }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.4 11h11l2-8H6.2M9 20a1 1 0 1 0 0-.01M18 20a1 1 0 1 0 0-.01"/></svg>
                    @if ($cartCount)<span class="absolute -top-0.5 -right-0.5 grid min-w-5 place-items-center rounded-full bg-nova-600 px-1 text-[11px] font-semibold text-white" data-cart-count aria-hidden="true">{{ $cartCount }}</span>@endif
                </a>
            @endif
            @if ($menu)
                <button type="button" @class(['inline-flex size-11 items-center justify-center rounded-lg lg:hidden', 'text-slate-700 hover:bg-slate-100' => ! $darkHeader, 'text-white hover:bg-white/10' => $darkHeader])
                        aria-expanded="false" aria-controls="mobile-menu" data-menu-toggle>
                    <span class="sr-only" data-menu-label data-open="{{ $L::t('Menü öffnen') }}" data-close="{{ $L::t('Menü schließen') }}">{{ $L::t('Menü öffnen') }}</span>
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path class="nova-burger-open" d="M4 7h16M4 12h16M4 17h16"/><path class="nova-burger-close" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            @endif
            </div>
        </div>
        @if ($menu)
            {{-- Handy und Tablet: Menü über die ganze Breite, Unterpunkte zum Aufklappen --}}
            <nav id="mobile-menu" class="nova-mobile-menu lg:hidden" aria-label="{{ $L::t('Hauptmenü') }}" hidden data-mobile-menu>
                @if ($searchOn)<div class="mx-auto max-w-6xl px-4 sm:px-6">@include('site.partials.search-form', ['id' => 'menu'])</div>@endif
                <ul class="mx-auto max-w-6xl space-y-1 px-4 pt-3 pb-6 sm:px-6">
                    @foreach ($menu as $i => $item)
                        <li>
                            <div class="flex items-stretch gap-1">
                                <a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @class(['flex min-h-12 flex-1 items-center rounded-xl px-4 text-base font-medium', 'bg-nova-50 text-nova-800' => $item['active'], 'text-slate-800 hover:bg-slate-50' => ! $item['active']]) @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                                @if ($item['children'])
                                    <button type="button" class="inline-flex w-12 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-50" aria-expanded="{{ collect($item['children'])->contains('active', true) ? 'true' : 'false' }}" aria-controls="submenu-{{ $i }}" data-submenu-toggle>
                                        <span class="sr-only">Unterpunkte von {{ $item['label'] }}</span>
                                        <svg class="size-5 transition" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.2 7.5a.75.75 0 0 1 1.06 0L10 11.2l3.74-3.7a.75.75 0 1 1 1.06 1.06l-4.27 4.24a.75.75 0 0 1-1.06 0L5.2 8.56a.75.75 0 0 1 0-1.06Z"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($item['children'])
                                <ul id="submenu-{{ $i }}" class="mt-1 ml-4 space-y-1 border-l-2 border-slate-100 pl-3" @unless(collect($item['children'])->contains('active', true)) hidden @endunless>
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" @if($child['new_tab']) target="_blank" rel="noopener" @endif @class(['flex min-h-11 items-center rounded-lg px-3 text-[15px]', 'font-semibold text-nova-700' => $child['active'], 'text-slate-600 hover:bg-slate-50' => ! $child['active']]) @if($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    <main id="inhalt" class="flex-1" tabindex="-1">@yield('content')</main>

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
            <nav class="flex flex-wrap items-end gap-x-5 gap-y-2 sm:justify-end" aria-label="{{ $L::t('Rechtliches') }}">
                @foreach ($legal as $l)<a class="hover:text-slate-900" href="{{ $l->url() }}">{{ $l->title }}</a>@endforeach
                @if (\App\Support\Members\Members::on() && \App\Models\Setting::get('members_footer_link', '1') === '1')<a class="hover:text-slate-900" href="{{ route('members.home') }}" data-members-link>{{ \App\Support\Members\Members::title() }}</a>@endif
                @if (\App\Support\Community\Community::on() && \App\Models\Setting::get('community_footer_link', '1') === '1')<a class="hover:text-slate-900" href="{{ route('community.home') }}" data-community-link>{{ \App\Support\Community\Community::title() }}</a>@endif
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
    @if ($errors->any())
        {{-- Fehler am Feld vorlesbar machen (aria-invalid und Beschreibung), erstes Feld bekommt den Fokus --}}
        <script type="application/json" id="nova-form-errors">{!! json_encode($errors->getMessages(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
</body>
</html>
