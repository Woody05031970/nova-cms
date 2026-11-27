@php
    $core = [
        ['label' => 'Dashboard', 'url' => route('admin.dashboard'), 'icon' => 'home', 'order' => 0, 'active' => 'admin.dashboard'],
        ['label' => 'Seiten', 'url' => null, 'icon' => 'pages', 'order' => 10, 'soon' => true],
        ['label' => 'Medien', 'url' => null, 'icon' => 'image', 'order' => 20, 'soon' => true],
        ['label' => 'KI-Assistent', 'url' => null, 'icon' => 'sparkles', 'order' => 30, 'soon' => true],
        ['label' => 'Shop', 'url' => null, 'icon' => 'cart', 'order' => 40, 'soon' => true],
        ['label' => 'SEO', 'url' => null, 'icon' => 'search', 'order' => 60, 'soon' => true],
        ['label' => 'Sicherheit', 'url' => null, 'icon' => 'shield', 'order' => 70, 'soon' => true],
        ['label' => 'Plugins', 'url' => route('admin.plugins'), 'icon' => 'puzzle', 'order' => 80, 'active' => 'admin.plugins*'],
        ['label' => 'Einstellungen', 'url' => route('admin.settings'), 'icon' => 'settings', 'order' => 90, 'active' => 'admin.settings*'],
    ];
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
    <title>@yield('title', 'Verwaltung') · {{ $siteName }}</title>
    @vite(['resources/css/app.css'])
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
                    <p class="px-3 pt-2 text-xs text-slate-400">Angemeldet als {{ auth()->user()->name }}</p>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-10">
            <div class="mx-auto max-w-5xl">
                <x-alerts />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
