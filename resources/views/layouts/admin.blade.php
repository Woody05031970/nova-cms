@php
    $core = [
        ['label' => 'Dashboard', 'url' => route('admin.dashboard'), 'icon' => 'home', 'order' => 0, 'active' => 'admin.dashboard', 'group' => 'Inhalte'],
        ['label' => 'Seiten', 'url' => route('admin.pages.index'), 'icon' => 'pages', 'order' => 10, 'active' => 'admin.pages.*', 'group' => 'Inhalte'],
        ['label' => 'Menü', 'url' => route('admin.menu'), 'icon' => 'menu', 'order' => 12, 'active' => 'admin.menu*', 'group' => 'Inhalte'],
        ['label' => 'Medien', 'url' => route('admin.media.index'), 'icon' => 'image', 'order' => 20, 'active' => 'admin.media.*', 'group' => 'Inhalte'],
        ['label' => 'Anfragen', 'url' => route('admin.messages'), 'icon' => 'inbox', 'order' => 25, 'active' => 'admin.messages*', 'group' => 'Inhalte', 'badge' => \App\Models\ContactMessage::unread() ?: null],
        ['label' => 'Design', 'url' => route('admin.design'), 'icon' => 'palette', 'order' => 22, 'active' => 'admin.design*', 'group' => 'Inhalte'],
        ['label' => 'Newsletter', 'url' => route('admin.newsletter.index'), 'icon' => 'mail', 'order' => 50, 'active' => 'admin.newsletter.*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('newsletter')],
        ['label' => 'WhatsApp', 'url' => route('admin.whatsapp'), 'icon' => 'chat', 'order' => 55, 'active' => 'admin.whatsapp*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('whatsapp')],
        ['label' => 'KI-Assistent', 'url' => route('admin.ai'), 'icon' => 'sparkles', 'order' => 30, 'active' => 'admin.ai*', 'group' => 'Wachstum'],
        ['label' => 'Shop', 'url' => route('admin.shop.index'), 'icon' => 'cart', 'order' => 40, 'active' => 'admin.shop.*', 'group' => 'Wachstum'],
        ['label' => 'SEO', 'url' => route('admin.seo'), 'icon' => 'search', 'order' => 60, 'active' => ['admin.seo*', 'admin.geo*'], 'group' => 'Wachstum'],
        ['label' => 'Social Media', 'url' => route('admin.social'), 'icon' => 'share', 'order' => 57, 'active' => 'admin.social*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('social')],
        ['label' => 'Statistik', 'url' => route('admin.stats'), 'icon' => 'chart', 'order' => 35, 'active' => 'admin.stats*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('statistik')],
        ['label' => 'Gutscheine', 'url' => route('admin.coupons'), 'icon' => 'tag', 'order' => 42, 'active' => 'admin.coupons*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('gutscheine')],
        ['label' => 'Sprachen', 'url' => route('admin.languages'), 'icon' => 'globe', 'order' => 13, 'active' => 'admin.languages*', 'group' => 'Inhalte', 'locked' => ! \App\Licensing\Features::on('mehrsprachigkeit')],
        ['label' => 'Community', 'url' => route('admin.community'), 'icon' => 'community', 'order' => 29, 'active' => 'admin.community*', 'group' => 'Inhalte', 'locked' => ! \App\Licensing\Features::on('community'), 'badge' => \App\Licensing\Features::on('community') ? (\App\Support\Community\Community::openReports() ?: null) : null],
        ['label' => 'Mitglieder', 'url' => route('admin.members'), 'icon' => 'users', 'order' => 28, 'active' => 'admin.members*', 'group' => 'Inhalte', 'locked' => ! \App\Licensing\Features::on('mitgliederbereich') && ! \App\Licensing\Features::on('community'), 'badge' => (\App\Licensing\Features::on('mitgliederbereich') || \App\Licensing\Features::on('community')) ? (\App\Models\User::where('role', 'member_pending')->count() ?: null) : null],
        ['label' => 'Warenwirtschaft', 'url' => route('admin.erp'), 'icon' => 'layers', 'order' => 44, 'active' => 'admin.erp*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('warenwirtschaft')],
        ['label' => 'Bewertungen', 'url' => route('admin.reviews'), 'icon' => 'heart', 'order' => 58, 'active' => 'admin.reviews*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('google-bewertungen')],
        ['label' => 'Kunden', 'url' => route('admin.customers'), 'icon' => 'user', 'order' => 41, 'active' => 'admin.customers*', 'group' => 'Wachstum', 'locked' => ! \App\Licensing\Features::on('kundenkonto')],
        ['label' => 'Termine', 'url' => route('admin.bookings'), 'icon' => 'clock', 'order' => 27, 'active' => 'admin.bookings*', 'group' => 'Inhalte', 'locked' => ! \App\Licensing\Features::on('terminbuchung'), 'badge' => \App\Licensing\Features::on('terminbuchung') ? (\App\Models\Booking::where('status', 'pending')->where('starts_at', '>=', now())->count() ?: null) : null],
        ['label' => 'Redaktionsplan', 'url' => route('admin.plan'), 'icon' => 'calendar', 'order' => 14, 'active' => 'admin.plan*', 'group' => 'Inhalte', 'locked' => ! \App\Licensing\Features::on('redaktionsplan')],
        ['label' => 'Datensicherung', 'url' => route('admin.backups'), 'icon' => 'upload', 'order' => 72, 'active' => 'admin.backups*', 'group' => 'System', 'locked' => ! \App\Licensing\Features::on('datensicherung')],
        ['label' => 'Turbo', 'url' => route('admin.turbo'), 'icon' => 'bolt', 'order' => 68, 'active' => 'admin.turbo*', 'group' => 'System', 'locked' => ! \App\Licensing\Features::on('turbo')],
        ['label' => 'Barrierefreiheit', 'url' => route('admin.accessibility'), 'icon' => 'accessibility', 'order' => 65, 'active' => 'admin.accessibility*', 'group' => 'System'],
        ['label' => 'Sicherheit', 'url' => route('admin.security'), 'icon' => 'shield', 'order' => 70, 'active' => 'admin.security*', 'group' => 'System'],
        ['label' => 'Plugins', 'url' => route('admin.plugins'), 'icon' => 'puzzle', 'order' => 80, 'active' => 'admin.plugins*', 'group' => 'System'],
        ['label' => 'Einstellungen', 'url' => route('admin.settings'), 'icon' => 'settings', 'order' => 90, 'active' => 'admin.settings*', 'group' => 'System'],
    ];
    if (\App\Licensing\LicenseServer::enabled()) {
        $core[] = ['label' => 'Lizenzserver', 'url' => route('admin.license-server'), 'icon' => 'key', 'order' => 95, 'active' => 'admin.license-server*', 'group' => 'System'];
    }
    $lic = app(\App\Licensing\License::class);
    $licStatus = $lic->status();
    $menu = collect(app(\App\Plugins\Hooks::class)->applyFilters('admin.menu', $core))->sortBy('order');
    $groups = $menu->groupBy(fn ($i) => $i['group'] ?? 'Erweiterungen')->sortBy(fn ($items, $g) => array_search($g, ['Inhalte', 'Wachstum', 'Erweiterungen', 'System']));
    $isActive = function ($item) {
        if (! empty($item['active'])) return request()->routeIs($item['active']);
        return $item['url'] && request()->url() === $item['url'];
    };
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $palette = $menu->reject(fn ($i) => ! empty($i['soon']))->map(fn ($i) => ['label' => $i['label'], 'url' => $i['url'], 'group' => 'Bereiche'])->values()->merge([
        ['label' => 'Neue Seite anlegen', 'url' => route('admin.pages.index').'#neu', 'group' => 'Aktionen'],
        ['label' => 'Bilder hochladen', 'url' => route('admin.media.index'), 'group' => 'Aktionen'],
        ['label' => 'Menü sortieren', 'url' => route('admin.menu'), 'group' => 'Aktionen'],
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
            <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Hauptmenü">
                @foreach ($groups as $group => $items)
                    <p class="mt-4 mb-1.5 px-3 font-mono text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($items as $item)
                            <li>
                                @if (! empty($item['soon']))
                                    <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600"><x-icon :name="$item['icon']" class="size-[18px]" /> {{ $item['label'] }}</span>
                                @else
                                    <a href="{{ $item['url'] }}" @class([
                                        'nova-nav-link group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                        'is-active bg-white/10 text-white' => $isActive($item),
                                        'text-slate-400 hover:bg-white/5 hover:text-white' => ! $isActive($item),
                                    ]) @if($isActive($item)) aria-current="page" @endif>
                                        <x-icon :name="$item['icon']" class="size-[18px] {{ $isActive($item) ? 'text-nova-300' : 'text-slate-500 group-hover:text-slate-300' }}" />
                                        {{ $item['label'] }}
                                        @if (! empty($item['locked']))<x-icon name="lock" class="ml-auto size-3.5 text-slate-500" /><span class="sr-only">(nicht in Ihrer Lizenz enthalten)</span>@endif
                                        @if (! empty($item['badge']))<span class="ml-auto rounded-full bg-nova-500 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-white">{{ $item['badge'] }}</span>@endif
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
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
