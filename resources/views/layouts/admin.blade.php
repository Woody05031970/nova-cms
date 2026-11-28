@php
    use App\Support\Admin\AdminNav;
    $nav = AdminNav::tree();
    $lic = app(\App\Licensing\License::class);
    $licStatus = $lic->status();
    // Ausgeblendete Punkte bleiben über die Schnellsuche erreichbar
    $allItems = collect(AdminNav::tree(true)['groups'])->pluck('items')->flatten(1)->merge($nav['top']);
    $isActive = fn ($item) => AdminNav::isActive($item);
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $palette = $allItems->reject(fn ($i) => ! empty($i['soon']))->map(fn ($i) => ['label' => $i['label'], 'url' => $i['url'], 'group' => 'Bereiche'])->values()->merge([
        ['label' => 'Neue Seite anlegen', 'url' => route('admin.pages.index').'#neu', 'group' => 'Aktionen'],
        ['label' => 'Bilder hochladen', 'url' => route('admin.media.index'), 'group' => 'Aktionen'],
        ['label' => 'Menü sortieren', 'url' => route('admin.menu'), 'group' => 'Aktionen'],
        ['label' => 'Backoffice-Menü anpassen', 'url' => route('admin.nav'), 'group' => 'Aktionen'],
        ['label' => 'Farben, Vorlage und Logo ändern', 'url' => route('admin.design'), 'group' => 'Aktionen'],
        ['label' => 'Social-Media-Beitrag schreiben', 'url' => route('admin.social'), 'group' => 'Aktionen'],
        ['label' => 'Seite zeitgesteuert veröffentlichen', 'url' => route('admin.plan'), 'group' => 'Aktionen'],
        ['label' => 'Bei ChatGPT und Co. gefunden werden', 'url' => route('admin.geo'), 'group' => 'Aktionen'],
        ['label' => 'Sicherheits-Check starten', 'url' => route('admin.security'), 'group' => 'Aktionen'],
        ['label' => 'Mein Konto und Passwort', 'url' => route('admin.account'), 'group' => 'Aktionen'],
        ['label' => 'Zwei-Faktor-Anmeldung', 'url' => route('admin.account').'#zwei-faktor', 'group' => 'Aktionen'],
        ['label' => 'Lizenz', 'url' => route('admin.license'), 'group' => 'Aktionen'],
        ['label' => 'Website ansehen', 'url' => route('home'), 'group' => 'Aktionen', 'external' => true],
    ]);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Verwaltung') · {{ $siteName }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    @stack('head')
</head>
<body class="nova-admin min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#inhalt" class="nova-skip-link">Zum Inhalt springen</a>
    <div class="lg:flex">
        {{-- Seitenleiste --}}
        <aside id="sidebar" class="nova-sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-slate-950 text-slate-300 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:translate-x-0" aria-label="Seitenleiste">
            <div class="flex items-center justify-between px-5 pt-5 pb-4">
                <a href="{{ route('admin.dashboard') }}" class="text-white"><x-logo /></a>
                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" data-sidebar-close aria-label="Menü schließen">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <button type="button" data-palette-open class="mx-3 mb-3 flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-left text-sm text-slate-400 hover:border-white/20 hover:text-slate-200">
                <x-icon name="search" class="size-4" /> <span class="flex-1">Suchen …</span>
                <kbd class="rounded border border-white/15 px-1.5 font-mono text-[10px] text-slate-400">Strg K</kbd>
            </button>
            <nav class="nova-nav flex-1 overflow-y-auto px-3 pb-4" aria-label="Hauptmenü" data-nav data-nav-single="{{ $nav['single'] ? '1' : '0' }}">
                <ul class="space-y-0.5">
                    @foreach ($nav['top'] as $item)
                        <li>@include('admin.partials.nav-link', ['item' => $item])</li>
                    @endforeach
                </ul>
                @foreach ($nav['groups'] as $group)
                    @php
                        $groupActive = collect($group['items'])->contains(fn ($i) => $isActive($i));
                        $groupBadge = collect($group['items'])->sum(fn ($i) => AdminNav::badge($i) ?? 0);
                    @endphp
                    <div class="nova-nav-group mt-1" data-nav-group="{{ $group['key'] }}" @if($groupActive) data-nav-current @endif>
                        <button type="button" data-nav-toggle aria-expanded="{{ $groupActive ? 'true' : 'false' }}" aria-controls="nav-{{ $group['key'] }}" @class([
                            'group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                            'text-white' => $groupActive,
                            'text-slate-300 hover:bg-white/5 hover:text-white' => ! $groupActive,
                        ])>
                            <x-icon :name="$group['icon']" class="size-[18px] {{ $groupActive ? 'text-nova-300' : 'text-slate-500 group-hover:text-slate-300' }}" />
                            <span class="min-w-0 flex-1 truncate">{{ $group['label'] }}</span>
                            @if ($groupBadge)<span class="nova-nav-group-badge rounded-full bg-nova-500 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-white">{{ $groupBadge }}</span>@endif
                            <x-icon name="chevron" class="nova-nav-chevron size-3.5 text-slate-500 transition-transform duration-200" />
                        </button>
                        <div id="nav-{{ $group['key'] }}" class="nova-nav-panel" @if(! $groupActive) data-closed @endif>
                            <ul class="min-h-0 space-y-0.5 overflow-hidden border-l border-white/10 ml-[1.35rem] pl-2">
                                @foreach ($group['items'] as $item)
                                    <li>@include('admin.partials.nav-link', ['item' => $item, 'small' => true])</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
                <a href="{{ route('admin.nav') }}" class="mt-4 flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs text-slate-500 hover:bg-white/5 hover:text-slate-300" data-nav-customize>
                    <x-icon name="menu" class="size-3.5" /> Menü anpassen
                </a>
            </nav>
            <div class="border-t border-white/10 p-3">
                <a href="{{ route('admin.license') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-white/5', 'bg-white/10 text-white' => request()->routeIs('admin.license', 'admin.license.*'), 'text-slate-400' => ! request()->routeIs('admin.license', 'admin.license.*')])>
                    <x-icon name="key" class="size-[18px] text-slate-500" /> Lizenz
                    <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-semibold {{ ['active' => 'bg-emerald-400/15 text-emerald-300', 'trial' => 'bg-amber-400/15 text-amber-300', 'dev' => 'bg-white/10 text-slate-400'][$licStatus] ?? 'bg-red-400/15 text-red-300' }}">{{ \App\Licensing\License::label($licStatus) }}</span>
                </a>
                <div class="mt-2 flex items-center gap-3 rounded-lg px-3 py-2">
                    <a href="{{ route('admin.account') }}" class="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-nova-500 to-fuchsia-500 text-xs font-bold text-white" title="Mein Konto" aria-hidden="true" tabindex="-1">{{ $initials }}</a>
                    <a href="{{ route('admin.account') }}" class="min-w-0 flex-1 leading-tight">
                        <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-slate-500">Mein Konto</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md p-1.5 text-slate-500 hover:bg-white/10 hover:text-white" title="Abmelden" aria-label="Abmelden"><x-icon name="logout" class="size-[18px]" /></button>
                    </form>
                </div>
            </div>
        </aside>
        <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

        <div class="min-w-0 flex-1">
            {{-- Kopfleiste --}}
            <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-md sm:px-8 lg:px-10">
                <button type="button" class="-ml-1 rounded-md p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Menü öffnen">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="lg:hidden"><x-logo /></a>
                <nav class="hidden min-w-0 items-center gap-1.5 text-sm text-slate-500 lg:flex" aria-label="Pfad">
                    <span class="truncate">{{ $siteName }}</span>
                    <x-icon name="chevron" class="size-3.5 text-slate-300" />
                    <span class="truncate font-medium text-slate-900">@yield('title', 'Verwaltung')</span>
                </nav>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" data-palette-open class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-500 shadow-xs hover:border-slate-300 sm:flex" aria-label="Suchen">
                        <x-icon name="search" class="size-4" /> Suchen oder springen
                        <kbd class="ml-4 rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-[10px] text-slate-500">Strg K</kbd>
                    </button>
                    <button type="button" data-palette-open class="rounded-md p-1.5 text-slate-600 hover:bg-slate-100 sm:hidden" aria-label="Suchen"><x-icon name="search" /></button>
                    <a href="{{ route('home') }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100" title="Website in neuem Tab öffnen" aria-label="Website in neuem Tab öffnen">
                        <x-icon name="external" class="size-4" /><span class="hidden md:inline" aria-hidden="true">Website</span>
                    </a>
                </div>
            </header>

            <main id="inhalt" tabindex="-1" class="px-4 py-8 sm:px-8 lg:px-10">
                <div class="mx-auto @yield('width', 'max-w-5xl')">
                    @if ($licStatus === 'trial' && ! request()->routeIs('admin.license'))
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" data-license-banner>
                            <span>Testversion: noch {{ $lic->trialDaysLeft() }} {{ $lic->trialDaysLeft() === 1 ? 'Tag' : 'Tage' }}. Danach brauchen Sie für die Verwaltung eine Lizenz. Ihre Website bleibt immer online.</span>
                            <a href="{{ route('admin.license') }}" class="font-medium underline">Lizenz eingeben</a>
                        </div>
                    @elseif ($licStatus === 'invalid' && ! request()->routeIs('admin.license'))
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-900" data-license-banner>
                            Ihre Lizenz konnte nicht bestätigt werden. <a href="{{ route('admin.license') }}" class="font-medium underline">Mehr erfahren</a>
                        </div>
                    @endif
                    <x-alerts />
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Schnellsuche (Strg+K) --}}
    <dialog id="palette" class="nova-palette m-auto mt-[12vh] w-[min(36rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm" aria-label="Schnellsuche">
        <div class="flex items-center gap-3 border-b border-slate-100 px-4">
            <x-icon name="search" class="size-5 text-slate-400" />
            <input type="text" data-palette-input class="h-14 flex-1 border-0 bg-transparent text-base focus:outline-none" placeholder="Seite, Produkt, Bestellung oder Bereich suchen …" autocomplete="off" aria-label="Suchbegriff" role="combobox" aria-expanded="true" aria-controls="palette-results">
            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-[10px] text-slate-500">Esc</kbd>
        </div>
        <ul id="palette-results" data-palette-results class="max-h-[50vh] overflow-y-auto p-2" role="listbox"></ul>
        <div class="flex items-center gap-4 border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-500">
            <span><kbd class="font-mono">↑↓</kbd> auswählen</span><span><kbd class="font-mono">Enter</kbd> öffnen</span><span class="ml-auto">Nova CMS</span>
        </div>
    </dialog>
    <script type="application/json" id="palette-data">@json(['items' => $palette, 'searchUrl' => route('admin.search')])</script>
</body>
</html>
