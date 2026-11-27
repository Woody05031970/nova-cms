@php
    $core = [
        ['label' => 'Dashboard', 'url' => route('admin.dashboard'), 'icon' => 'home', 'order' => 0, 'active' => 'admin.dashboard'],
        ['label' => 'Seiten', 'url' => route('admin.pages.index'), 'icon' => 'pages', 'order' => 10, 'active' => 'admin.pages.*'],
        ['label' => 'Medien', 'url' => route('admin.media.index'), 'icon' => 'image', 'order' => 20, 'active' => 'admin.media.*'],
        ['label' => 'KI-Assistent', 'url' => route('admin.ai'), 'icon' => 'sparkles', 'order' => 30, 'active' => 'admin.ai*'],
        ['label' => 'Shop', 'url' => route('admin.shop.index'), 'icon' => 'cart', 'order' => 40, 'active' => 'admin.shop.*'],
        ['label' => 'SEO', 'url' => route('admin.seo'), 'icon' => 'search', 'order' => 60, 'active' => 'admin.seo*'],
        ['label' => 'Sicherheit', 'url' => route('admin.security'), 'icon' => 'shield', 'order' => 70, 'active' => 'admin.security*'],
        ['label' => 'Plugins', 'url' => route('admin.plugins'), 'icon' => 'puzzle', 'order' => 80, 'active' => 'admin.plugins*'],
        ['label' => 'Einstellungen', 'url' => route('admin.settings'), 'icon' => 'settings', 'order' => 90, 'active' => 'admin.settings*'],
    ];
    if (\App\Licensing\LicenseServer::enabled()) {
        $core[] = ['label' => 'Lizenzserver', 'url' => route('admin.license-server'), 'icon' => 'key', 'order' => 95, 'active' => 'admin.license-server*'];
    }
    $lic = app(\App\Licensing\License::class);
    $licStatus = $lic->status();
    $menu = collect(app(\App\Plugins\Hooks::class)->applyFilters('admin.menu', $core))->sortBy('order');
    $isActive = function ($item) {
        if (! empty($item['active'])) return request()->routeIs($item['active']);
        return $item['url'] && request()->url() === $item['url'];
    };
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Verwaltung') · {{ $siteName }}</title>
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="lg:flex">
        <aside class="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:border-r lg:border-b-0">
            <div class="flex items-center justify-between px-5 py-4">
                <a href="{{ route('admin.dashboard') }}"><x-logo /></a>
                <button type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-controls="nav" aria-expanded="false"
                        onclick="var n=document.getElementById('nav');n.classList.toggle('hidden');this.setAttribute('aria-expanded',!n.classList.contains('hidden'))">
                    <span class="sr-only">Menü</span>
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
            <nav id="nav" class="hidden px-3 pb-4 lg:flex lg:h-[calc(100vh-68px)] lg:flex-col" aria-label="Hauptmenü">
                <ul class="space-y-0.5">
                    @foreach ($menu as $item)
                        <li>
                            @if (! empty($item['soon']))
                                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400" title="Kommt in einem der nächsten Schritte">
                                    <x-icon :name="$item['icon']" />
                                    {{ $item['label'] }}
                                    <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">bald</span>
                                </span>
                            @else
                                <a href="{{ $item['url'] }}" @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                                    'bg-nova-50 text-nova-700' => $isActive($item),
                                    'text-slate-700 hover:bg-slate-100' => ! $isActive($item),
                                ]) @if($isActive($item)) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" />
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="mt-6 space-y-0.5 border-t border-slate-100 pt-4 lg:mt-auto">
                    <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                        <x-icon name="external" /> Website ansehen
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                            <x-icon name="logout" /> Abmelden
                        </button>
                    </form>
                    <a href="{{ route('admin.account') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-100', 'bg-nova-50 font-medium text-nova-700' => request()->routeIs('admin.account*'), 'text-slate-600' => ! request()->routeIs('admin.account*')])>
                        <x-icon name="user" /> Mein Konto
                    </a>
                    <a href="{{ route('admin.license') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-100', 'bg-nova-50 font-medium text-nova-700' => request()->routeIs('admin.license', 'admin.license.*'), 'text-slate-600' => ! request()->routeIs('admin.license', 'admin.license.*')])>
                        <x-icon name="key" /> Lizenz
                        <span class="ml-auto rounded-full px-2 py-0.5 text-[11px] font-medium {{ ['active' => 'bg-emerald-50 text-emerald-700', 'trial' => 'bg-amber-50 text-amber-700', 'dev' => 'bg-slate-100 text-slate-500'][$licStatus] ?? 'bg-red-50 text-red-700' }}">{{ \App\Licensing\License::label($licStatus) }}</span>
                    </a>
                    <p class="px-3 pt-2 text-xs text-slate-400">Angemeldet als {{ auth()->user()->name }}</p>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-10">
            <div class="mx-auto @yield('width', 'max-w-5xl')">
                @if ($licStatus === 'trial' && ! request()->routeIs('admin.license'))
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" data-license-banner>
                        <span>Testversion: noch {{ $lic->trialDaysLeft() }} {{ $lic->trialDaysLeft() === 1 ? 'Tag' : 'Tage' }}. Danach brauchen Sie für die Verwaltung eine Lizenz. Ihre Website bleibt immer online.</span>
                        <a href="{{ route('admin.license') }}" class="font-medium underline">Lizenz eingeben</a>
                    </div>
                @elseif ($licStatus === 'invalid' && ! request()->routeIs('admin.license'))
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-900" data-license-banner>
                        Ihre Lizenz konnte nicht bestätigt werden. <a href="{{ route('admin.license') }}" class="font-medium underline">Mehr erfahren</a>
                    </div>
                @endif
                <x-alerts />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
