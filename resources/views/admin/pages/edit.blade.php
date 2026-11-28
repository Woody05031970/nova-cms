{{-- Visueller Editor: links die Leiste mit Elementen und Einstellungen, rechts die Seite selbst --}}
@php $siteName = $siteName ?? \App\Models\Setting::get('site_name', config('app.name')); @endphp
@php
    $types = [
        'text' => ['label' => 'Text', 'description' => 'Absätze, Überschriften, Listen, Tabellen', 'icon' => 'pages', 'group' => 'basis'],
        'hero' => ['label' => 'Titelbild', 'description' => 'Großer Einstieg mit Überschrift und Knöpfen', 'icon' => 'sparkles', 'group' => 'basis'],
        'image' => ['label' => 'Bild', 'description' => 'Ein Foto mit Bildunterschrift', 'icon' => 'image', 'group' => 'basis'],
        'slider' => ['label' => 'Slider', 'description' => 'Mehrere Bilder zum Durchblättern', 'icon' => 'image', 'group' => 'basis'],
        'video' => ['label' => 'Video', 'description' => 'YouTube, Vimeo oder eigene Datei', 'icon' => 'external', 'group' => 'basis'],
        'button' => ['label' => 'Knopf', 'description' => 'Ein auffälliger Link, z. B. zum Kontakt', 'icon' => 'arrow', 'group' => 'basis'],
        'features' => ['label' => 'Vorteile', 'description' => 'Kacheln mit Symbol, Schritte oder Kennzahlen', 'icon' => 'layers', 'group' => 'verkauf'],
        'pricing' => ['label' => 'Preise', 'description' => 'Pakete nebeneinander, auf Wunsch mit Shop-Produkt', 'icon' => 'cart', 'group' => 'verkauf'],
        'modules' => ['label' => 'Module', 'description' => 'Übersicht mit Filter: im Kern, kostenlos, kostenpflichtig', 'icon' => 'puzzle', 'group' => 'verkauf'],
        'compare' => ['label' => 'Vergleich', 'description' => 'Tabelle: Sie im Vergleich zu anderen', 'icon' => 'chart', 'group' => 'verkauf'],
        'faq' => ['label' => 'Häufige Fragen', 'description' => 'Aufklappbare Fragen, von Google lesbar', 'icon' => 'search', 'group' => 'verkauf'],
        'cta' => ['label' => 'Aufruf', 'description' => 'Auffälliges Band mit Knöpfen', 'icon' => 'rocket', 'group' => 'verkauf'],
        'newsletter' => ['label' => 'Newsletter', 'description' => 'Anmeldung mit Bestätigung per E-Mail', 'icon' => 'mail', 'group' => 'verkauf'],
        'booking' => ['label' => 'Terminbuchung', 'description' => 'Leistungen mit freien Terminen zum Buchen', 'icon' => 'calendar', 'group' => 'verkauf'],
        'reviews' => ['label' => 'Google-Bewertungen', 'description' => 'Sterne und neueste Bewertungen aus Google', 'icon' => 'heart', 'group' => 'verkauf'],
        'contact' => ['label' => 'Kontaktformular', 'description' => 'Anfragen landen unter „Anfragen“ und per E-Mail', 'icon' => 'mail', 'group' => 'verkauf'],
        'html' => ['label' => 'HTML', 'description' => 'Eigener Code für Fortgeschrittene', 'icon' => 'settings', 'group' => 'profi'],
    ];
    $cfg = [
        'blocks' => $page->blocks ?? [],
        'title' => old('title', $page->title),
        'slug' => old('slug', $page->slug),
        'metaTitle' => old('meta_title', $page->meta_title),
        'metaDescription' => old('meta_description', $page->meta_description),
        'showInMenu' => (bool) old('show_in_menu', $page->show_in_menu),
        'isHome' => (bool) old('is_home', $page->is_home),
        'siteName' => $siteName,
        'pageId' => $page->id,
        'published' => $page->isPublished(),
        'blockPreviewUrl' => route('admin.pages.block-preview'),
        'templatesUrl' => route('admin.pages.templates'),
        'templates' => \App\Http\Controllers\Admin\PageController::builderTemplates(),
        'showTitle' => (bool) old('show_title', $page->show_title ?? true),
        'widgets' => collect(\App\Support\Blocks\Builder::WIDGETS)->all(),
        'design' => \Illuminate\Support\Arr::only(\App\Support\Design\SiteDesign::values(), ['brand', 'accent']) + ['three' => \App\Licensing\Features::on('effekte-3d')],
        'icons' => \App\Support\Blocks\BlockSanitizer::ICONS,
        'iconPaths' => \App\Support\Icons::PATHS,
        'host' => request()->getHost(),
        'types' => $types,
        'features' => collect(app(\App\Licensing\Features::class)->overview())->map(fn ($f) => $f['enabled']),
        'services' => \App\Models\Service::active()->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
        'products' => \App\Models\Product::orderBy('name')->get(['slug', 'name', 'price_cents', 'is_active'])->map(fn ($p) => ['slug' => $p->slug, 'label' => $p->name.' · '.$p->price().($p->is_active ? '' : ' (ausgeblendet)')])->values(),
        'focusKeyword' => old('focus_keyword', $page->focus_keyword),
        'purpose' => $page->purpose,
        'seoUrl' => route('admin.seo.analyse'),
        'keywordsUrl' => route('admin.seo.keywords'),
        'ai' => [
            'enabled' => app(\App\Ai\AiManager::class)->isConfigured(),
            'setupUrl' => route('admin.ai'),
            'rewriteUrl' => route('admin.ai.rewrite'),
            'descriptionUrl' => route('admin.ai.description'),
            'actions' => collect(\App\Ai\ContentAssistant::ACTIONS)->map(fn ($a) => $a[0]),
        ],
    ];
@endphp
@php
    $legacyGroups = ['basis' => 'Große Bausteine', 'verkauf' => 'Überzeugen und verkaufen', 'profi' => 'Für Profis'];
    $legacyHidden = ['text', 'image', 'button', 'video'];
    $widgetIcons = ['heading' => 'type', 'text' => 'pages', 'image' => 'image', 'button' => 'cursor', 'icon' => 'star', 'list' => 'list', 'counter' => 'hash', 'quote' => 'quote', 'video' => 'play', 'gallery' => 'layers', 'spacer' => 'spacing', 'divider' => 'minus', 'html' => 'code'];
    $tabBtn = 'flex-1 rounded-md px-2 py-1.5 text-sm font-medium';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->title }} bearbeiten · {{ $siteName }}</title>
    @vite(['resources/css/app.css', 'resources/js/editor.js'])
</head>
<body class="nova-admin h-dvh overflow-hidden bg-slate-100 font-sans text-slate-900 antialiased">
<div x-data="pageEditor(@js($cfg))" x-cloak class="flex h-full flex-col"
     @pick-image.window="const cb = $event.detail; openLibrary('image', false, ([m]) => cb(m))"
     @ai-error="error = $event.detail"
     @ai-done="error = ''; notify('Text von der KI überarbeitet. Gefällt es nicht? Strg+Z macht es rückgängig.')"
     x-init="@if (session('status')) notify(@js(session('status'))) @endif">
    <form x-ref="form" method="POST" action="{{ route('admin.pages.update', $page) }}" @submit="dirty = false" class="flex min-h-0 flex-1 flex-col">
        @csrf @method('PUT')
        <input type="hidden" name="blocks" :value="serialized()">
        <input type="hidden" name="title" :value="title">
        <input type="hidden" name="show_title" :value="showTitle ? 1 : 0">
        <input type="hidden" name="action" x-ref="action" value="save">

        {{-- Kopfzeile --}}
        <header class="nbe-top">
            <a href="{{ route('admin.pages.index') }}" class="nbe-tb" title="Zurück zu allen Seiten"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg><span class="hidden xl:inline">Seiten</span></a>
            <div class="min-w-0">
                <button type="button" class="block max-w-64 truncate text-left text-sm font-semibold text-white hover:underline" @click="select('title')" x-text="title || 'Ohne Titel'" title="Titel ändern"></button>
                <p class="flex items-center gap-2 text-[11px] text-slate-400">
                    @if ($page->isPublished())<span class="inline-flex items-center gap-1"><span class="size-1.5 rounded-full bg-emerald-400"></span>Online</span>
                    @elseif ($page->isScheduled())<span class="inline-flex items-center gap-1" data-scheduled><span class="size-1.5 rounded-full bg-sky-400"></span>Geplant für {{ $page->published_at->format('d.m.Y, H:i') }} Uhr</span>
                    @else<span class="inline-flex items-center gap-1"><span class="size-1.5 rounded-full bg-slate-400"></span>Entwurf</span>@endif
                    <span x-show="dirty" class="inline-flex items-center gap-1 text-amber-300"><span class="size-1.5 animate-pulse rounded-full bg-amber-400"></span>Ungespeichert</span>
                </p>
            </div>

            <div class="mx-auto flex items-center gap-1 rounded-lg bg-white/5 p-0.5" role="group" aria-label="Gerät">
                <button type="button" class="nbe-dev" :class="device === 'desktop' && 'is-on'" @click="device = 'desktop'" title="Computer" data-device="desktop"><x-icon name="monitor" class="size-4" /></button>
                <button type="button" class="nbe-dev" :class="device === 'tablet' && 'is-on'" @click="device = 'tablet'" title="Tablet" data-device="tablet"><x-icon name="tablet" class="size-4" /></button>
                <button type="button" class="nbe-dev" :class="device === 'phone' && 'is-on'" @click="device = 'phone'" title="Handy" data-device="phone"><x-icon name="phone" class="size-4" /></button>
            </div>

            <div class="flex items-center gap-1">
                <button type="button" class="nbe-tb" @click="undo()" :disabled="!undoStack.length && lastSnap === serialized()" title="Rückgängig (Strg+Z)" data-undo><x-icon name="undo" class="size-4" /></button>
                <button type="button" class="nbe-tb" @click="redo()" :disabled="!redoStack.length" title="Wiederholen (Strg+Y)" data-redo><x-icon name="redo" class="size-4" /></button>
                <span class="mx-1 h-5 w-px bg-white/15"></span>
                <button type="button" class="nbe-tb" :class="panel === 'tree' && 'is-on'" @click="panel = panel === 'tree' ? 'add' : 'tree'" title="Aufbau der Seite" data-panel-tree><x-icon name="layers" class="size-4" /><span class="hidden 2xl:inline">Aufbau</span></button>
                <button type="button" class="nbe-tb" :class="panel === 'page' && 'is-on'" @click="panel = panel === 'page' ? 'add' : 'page'" title="Seiteneinstellungen, SEO und Veröffentlichung" data-panel-page><x-icon name="settings" class="size-4" /><span class="hidden xl:inline">Seite</span></button>
                <a href="{{ route('admin.pages.preview', $page) }}" target="_blank" class="nbe-tb" title="Gespeicherten Stand in neuem Tab ansehen"><x-icon name="eye" class="size-4" /></a>
                <span class="mx-1 h-5 w-px bg-white/15"></span>
                @if ($page->isPublished())
                    <button type="button" class="nbe-btn2 hidden lg:inline-flex" @click="submit('unpublish')">Offline nehmen</button>
                    <button type="button" class="nbe-btn" @click="submit('save')" data-save>Veröffentlichen</button>
                @else
                    <button type="button" class="nbe-btn2" @click="submit('save')" data-save>Speichern</button>
                    <button type="button" class="nbe-btn" @click="submit('publish')">Veröffentlichen</button>
                @endif
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            {{-- ================= LEISTE ================= --}}
            <aside class="nbe-panel" aria-label="Werkzeuge">
                <div x-show="error" class="m-3 mb-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert"><span x-text="error"></span> <button type="button" class="ml-1 underline" @click="error = ''">OK</button></div>
                @if ($errors->any())
                    <div class="m-3 mb-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
                @endif
                <template x-if="converted && panel === 'add'">
                    <div class="m-3 mb-0 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900" data-converted>Diese Seite wurde für den neuen Editor in Bereiche umgewandelt. Erst beim Speichern wird das übernommen. <button type="button" class="underline" @click="converted = false">Verstanden</button></div>
                </template>

                {{-- ---------- Hinzufügen ---------- --}}
                <div x-show="panel === 'add'" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex gap-1 border-b border-slate-200 p-2" role="tablist">
                        <button type="button" role="tab" class="{{ $tabBtn }}" :class="addTab === 'widgets' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="addTab = 'widgets'">Elemente</button>
                        <button type="button" role="tab" class="{{ $tabBtn }}" :class="addTab === 'blocks' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="addTab = 'blocks'" data-tab-blocks>Bausteine</button>
                        <button type="button" role="tab" class="{{ $tabBtn }}" :class="addTab === 'templates' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="addTab = 'templates'" data-tab-templates>Vorlagen</button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3">
                        <div x-show="addTab === 'widgets'" class="space-y-5">
                            <p x-show="addTarget?.col" class="rounded-lg bg-nova-50 px-3 py-2 text-xs text-nova-800">Klicken Sie auf ein Element, um es in die gewählte Spalte zu setzen. Oder ziehen Sie es an die gewünschte Stelle.</p>
                            <div>
                                <p class="nbp-group">Neuer Bereich mit Spalten</p>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <template x-for="[key, parts] in layouts" :key="key">
                                        <button type="button" class="nbp-layout" draggable="true" @dragstart="startDrag($event, { kind: 'layout', layout: key })" @dragend="endDrag()" @click="addSection(key, addAt ?? (hit ? hit.bi + 1 : blocks.length))" :title="parts.length + (parts.length === 1 ? ' Spalte' : ' Spalten')" :data-add-layout="key">
                                            <template x-for="(f, fi) in parts" :key="fi"><i :style="'flex:' + f"></i></template>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <p class="nbp-group">Elemente</p>
                                <div class="grid grid-cols-3 gap-1.5">
                                    @foreach (\App\Support\Blocks\Builder::WIDGETS as $wk => $wl)
                                        <button type="button" class="nbp-tile" draggable="true" @dragstart="startDrag($event, { kind: 'widget', type: '{{ $wk }}' })" @dragend="endDrag()" @click="addWidget('{{ $wk }}')" data-add-widget="{{ $wk }}">
                                            <x-icon :name="$widgetIcons[$wk]" class="size-6" /><span>{{ $wl }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <p class="text-xs text-slate-500">Tipp: Ziehen Sie Elemente direkt in die Seite rechts. Texte ändern Sie, indem Sie hineinklicken und tippen.</p>
                        </div>

                        <div x-show="addTab === 'blocks'" class="space-y-5">
                            <p class="text-xs text-slate-500">Fertige Bausteine mit eigener Gestaltung, z. B. Preise, Fragen oder Kontaktformular. Klicken oder in die Seite ziehen.</p>
                            @foreach ($legacyGroups as $g => $gLabel)
                                <div>
                                    <p class="nbp-group">{{ $gLabel }}</p>
                                    <div class="space-y-1.5">
                                        @foreach ($types as $tk => $t)
                                            @continue($t['group'] !== $g || in_array($tk, $legacyHidden, true))
                                            <button type="button" class="nbp-row" draggable="true" @dragstart="startDrag($event, { kind: 'block', type: '{{ $tk }}' })" @dragend="endDrag()" @click="addLegacy('{{ $tk }}')" data-add-block="{{ $tk }}">
                                                <x-icon :name="$t['icon']" class="size-5 shrink-0 text-nova-600" />
                                                <span><strong>{{ $t['label'] }}</strong><small>{{ $t['description'] }}</small></span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div x-show="addTab === 'templates'" class="space-y-5">
                            <p class="text-xs text-slate-500">Fertig gestaltete Bereiche zum Anpassen. Einfach klicken und danach Texte und Bilder austauschen.</p>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="t in templates" :key="t.key">
                                    <button type="button" class="nbp-tpl" draggable="true" @dragstart="startDrag($event, { kind: 'template', key: t.key })" @dragend="endDrag()" @click="insertTemplate(t)" :data-template="t.key">
                                        <span class="nbp-scheme" :data-scheme="t.scheme"><i></i><i></i><i></i></span>
                                        <strong x-text="t.name"></strong><small x-text="t.desc"></small>
                                    </button>
                                </template>
                            </div>
                            <div>
                                <p class="nbp-group">Eigene Vorlagen</p>
                                <p x-show="!ownTemplates.length" class="text-xs text-slate-500">Noch keine. Wählen Sie einen Bereich und speichern Sie ihn unter „Erweitert“ als Vorlage, um ihn auf anderen Seiten wiederzuverwenden.</p>
                                <div class="space-y-1.5">
                                    <template x-for="t in ownTemplates" :key="t.id">
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="nbp-row flex-1" @click="insertTemplate(t, true)"><x-icon name="template" class="size-5 shrink-0 text-nova-600" /><span><strong x-text="t.name"></strong></span></button>
                                            <button type="button" class="rounded p-2 text-slate-400 hover:bg-red-50 hover:text-red-700" @click="deleteTemplate(t)" title="Vorlage löschen"><x-icon name="trash" class="size-4" /></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---------- Bearbeiten ---------- --}}
                <div x-show="panel === 'edit'" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center gap-1 border-b border-slate-200 px-3 py-2">
                        <nav class="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-xs text-slate-500" aria-label="Auswahl">
                            <template x-for="(c, ci) in crumbs" :key="c.id">
                                <span class="flex items-center gap-1"><span x-show="ci" aria-hidden="true">›</span>
                                    <button type="button" class="rounded px-1 py-0.5 hover:bg-slate-100" :class="ci === crumbs.length - 1 && 'font-semibold text-slate-900'" @click="select(c.kind, c.id)" x-text="c.label"></button></span>
                            </template>
                            <span x-show="sel?.kind === 'title'" class="font-semibold text-slate-900">Seitentitel</span>
                            <span x-show="sel?.kind === 'block'" class="font-semibold text-slate-900" x-text="selBlock && label(selBlock.type)"></span>
                        </nav>
                        <template x-if="sel && sel.kind !== 'title' && sel.kind !== 'col'">
                            <div class="flex shrink-0 items-center text-slate-400">
                                <button type="button" class="rounded p-1 hover:bg-slate-100 hover:text-slate-700" @click="moveBy(-1)" title="Nach oben">↑</button>
                                <button type="button" class="rounded p-1 hover:bg-slate-100 hover:text-slate-700" @click="moveBy(1)" title="Nach unten">↓</button>
                                <button type="button" class="rounded p-1 hover:bg-slate-100 hover:text-slate-700" @click="duplicate(sel.kind === 'widget' ? 'widget' : 'top', sel.id)" title="Doppeln"><x-icon name="copy" class="size-4" /></button>
                                <button type="button" class="rounded p-1 hover:bg-red-50 hover:text-red-700" @click="remove(sel.kind === 'widget' ? 'widget' : 'top', sel.id)" title="Löschen (Entf)" data-remove><x-icon name="trash" class="size-4" /></button>
                            </div>
                        </template>
                        <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="select(null)" title="Schließen (Esc)" aria-label="Schließen">✕</button>
                    </div>
                    <template x-if="sel?.kind === 'widget' || sel?.kind === 'section'">
                        <div class="flex gap-1 border-b border-slate-200 p-2" role="tablist">
                            <button type="button" role="tab" class="{{ $tabBtn }}" :class="tab === 'content' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="tab = 'content'" x-text="sel.kind === 'section' ? 'Aufbau' : 'Inhalt'"></button>
                            <button type="button" role="tab" class="{{ $tabBtn }}" :class="tab === 'style' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="tab = 'style'" data-tab-style>Stil</button>
                            <button type="button" role="tab" class="{{ $tabBtn }}" :class="tab === 'advanced' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="tab = 'advanced'" data-tab-advanced>Erweitert</button>
                        </div>
                    </template>
                    <div class="nbe-scroll flex-1 overflow-y-auto p-4">
                        <template x-if="sel?.kind === 'title'">
                            <div class="space-y-4">
                                <div class="nbp-field"><label class="nbp-label" for="page-title">Titel der Seite</label><input id="page-title" class="nova-input" x-model="title" maxlength="160" required></div>
                                <x-nb.toggle model="showTitle" label="Titel oben auf der Seite zeigen" hint="Aus, wenn der erste Bereich schon eine eigene große Überschrift hat." />
                                <p x-show="startsWithHero" class="text-xs text-slate-500">Die Seite beginnt mit einem Titelbild, einem Baustein mit Überschrift oder einer H1-Überschrift. Der Titel wird dann ohnehin nicht doppelt gezeigt.</p>
                            </div>
                        </template>
                        <template x-for="w in selWidgetList" :key="w.id">
                            <div>@include('admin.pages.builder.widget')</div>
                        </template>
                        <template x-for="c in (sel?.kind === 'col' && selCol ? [selCol] : [])" :key="c.id">
                            <div>@include('admin.pages.builder.column')</div>
                        </template>
                        <template x-for="s in (sel?.kind === 'section' && selSection ? [selSection] : [])" :key="s.id">
                            <div>@include('admin.pages.builder.section')</div>
                        </template>
                        <template x-for="block in selBlockList" :key="block.id">
                            <div class="nbe-legacy">
                                @include('admin.pages.partials.blocks')
                                @include('admin.pages.partials.blocks-sales')
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ---------- Aufbau ---------- --}}
                <div x-show="panel === 'tree'" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">Aufbau der Seite</h2><button type="button" class="text-slate-400 hover:text-slate-700" @click="panel = 'add'" aria-label="Schließen">✕</button></div>
                    <div class="flex-1 overflow-y-auto p-2">
                        <p x-show="!tree.length" class="p-3 text-sm text-slate-500">Die Seite ist noch leer.</p>
                        <template x-for="n in tree" :key="n.id">
                            <button type="button" class="nbp-node" :class="sel?.id === n.id && 'is-on'" :style="'padding-left:' + (0.6 + n.depth * 1.1) + 'rem'" @click="select(n.kind, n.id); panel = 'tree'">
                                <span class="font-medium" x-text="n.label"></span><span class="truncate text-slate-400" x-text="n.hint"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- ---------- Seite ---------- --}}
                <div x-show="panel === 'page'" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">Seiteneinstellungen</h2><button type="button" class="text-slate-400 hover:text-slate-700" @click="panel = 'add'" aria-label="Schließen">✕</button></div>
                    <div class="nbe-page flex-1 space-y-4 overflow-y-auto p-3">
                <section class="nova-card p-5">
                    <h2 class="font-semibold">Bei Google</h2>
                    <p class="mt-1 text-xs text-slate-500">So ungefähr erscheint die Seite in den Suchergebnissen.</p>
                    <div class="mt-3 rounded-lg border border-slate-200 p-3">
                        <p class="truncate text-xs text-slate-500" x-text="previewUrl"></p>
                        <p class="mt-0.5 line-clamp-1 text-[15px] text-[#1a0dab]" x-text="previewTitle"></p>
                        <p class="mt-0.5 line-clamp-2 text-xs" :class="metaDescription ? 'text-slate-600' : 'text-amber-700'" x-text="previewDescription"></p>
                    </div>
                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="flex justify-between"><label class="nova-label" for="meta_title">Titel für Google</label><span class="text-xs" :class="counterClass(metaTitle.length, 20, 60)" x-text="metaTitle.length + '/60'"></span></div>
                            <input id="meta_title" name="meta_title" x-model="metaTitle" maxlength="70" class="nova-input text-sm" :placeholder="title">
                        </div>
                        <div>
                            <div class="flex justify-between"><label class="nova-label" for="meta_description">Beschreibung für Google</label><span class="text-xs" :class="counterClass(metaDescription.length, 70, 160)" x-text="metaDescription.length + '/160'"></span></div>
                            <textarea id="meta_description" name="meta_description" x-model="metaDescription" rows="4" maxlength="320" class="nova-input text-sm" placeholder="Ein bis zwei Sätze, die neugierig machen."></textarea>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                <template x-if="ai.enabled">
                                    <button type="button" @click="descriptionWithAi()" :disabled="aiBusy" class="inline-flex items-center gap-1 text-xs font-medium text-nova-700 hover:underline disabled:opacity-50">
                                        <x-icon name="sparkles" class="size-3.5" /><span x-text="aiBusy ? 'KI schreibt …' : 'Mit KI vorschlagen'"></span>
                                    </button>
                                </template>
                                <button type="button" @click="descriptionFromText()" class="text-xs font-medium text-slate-600 hover:underline">Aus dem Seitentext übernehmen</button>
                            </div>
                        </div>
                        <div x-show="!isHome">
                            <label class="nova-label" for="slug">Adresse der Seite</label>
                            <div class="flex items-center rounded-lg border border-slate-300 bg-white text-sm focus-within:border-nova-500 focus-within:ring-2 focus-within:ring-nova-200">
                                <span class="pl-3 text-slate-400">/{{ $page->locale ? $page->locale.'/' : '' }}</span>
                                <input id="slug" name="slug" x-model="slug" maxlength="160" class="w-full border-0 bg-transparent py-2 pr-3 pl-0.5 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="nova-card p-5" aria-labelledby="seo-check">
                    <div class="flex items-center justify-between gap-2">
                        <h2 id="seo-check" class="font-semibold">SEO-Check</h2>
                        <span x-show="seo.score !== null" class="rounded-full px-2.5 py-0.5 text-sm font-semibold" :class="scoreClass(seo.score)" x-text="seo.score + ' / 100'"></span>
                    </div>
                    <div class="mt-3">
                        <label class="nova-label" for="focus_keyword">Fokus-Suchbegriff</label>
                        <input id="focus_keyword" name="focus_keyword" x-model="focusKeyword" maxlength="100" class="nova-input text-sm" placeholder="z. B. Bäckerei Köln">
                        <p class="mt-1 text-xs text-slate-500">Womit sollen Kunden diese Seite bei Google finden?</p>
                        <template x-if="ai.enabled">
                            <button type="button" @click="suggestKeywords()" :disabled="keywordBusy" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-nova-700 hover:underline disabled:opacity-50">
                                <x-icon name="sparkles" class="size-3.5" /><span x-text="keywordBusy ? 'KI überlegt …' : 'Suchbegriffe vorschlagen'"></span>
                            </button>
                        </template>
                        <div x-show="keywordIdeas.length" class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="k in keywordIdeas" :key="k">
                                <button type="button" @click="focusKeyword = k; keywordIdeas = []" class="rounded-full bg-nova-50 px-2.5 py-1 text-xs text-nova-700 hover:bg-nova-100" x-text="k"></button>
                            </template>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                        <template x-for="c in seo.checks" :key="c.key">
                            <li class="flex gap-2">
                                <span class="mt-1 size-2.5 shrink-0 rounded-full" :class="{good: 'bg-emerald-500', warn: 'bg-amber-400', bad: 'bg-red-500'}[c.status]"></span>
                                <span><span x-text="c.label" :class="c.status === 'good' ? 'text-slate-600' : 'text-slate-900'"></span>
                                    <span x-show="c.tip" class="block text-xs text-slate-500" x-text="c.tip"></span></span>
                            </li>
                        </template>
                    </ul>
                </section>

                @if (! $page->isPublished())
                    @php $planOn = \App\Licensing\Features::on('redaktionsplan'); @endphp
                    <section class="nova-card p-5" aria-labelledby="plan-title">
                        <h2 id="plan-title" class="flex items-center gap-2 font-semibold"><x-icon name="calendar" class="size-4 text-slate-500" /> Später veröffentlichen</h2>
                        @if ($planOn)
                            <p class="mt-1 text-xs text-slate-500">Die Seite geht zum gewählten Zeitpunkt automatisch online.</p>
                            <label class="nova-label mt-3" for="publish_at">Datum und Uhrzeit</label>
                            <input type="datetime-local" id="publish_at" name="publish_at" class="nova-input text-sm" min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                                   value="{{ old('publish_at', $page->isScheduled() ? $page->published_at->format('Y-m-d\TH:i') : '') }}" @error('publish_at') aria-invalid="true" aria-describedby="publish-at-error" @enderror>
                            @error('publish_at')<p id="publish-at-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="nova-btn-secondary py-2 text-sm" @click="submit('schedule')" data-schedule>{{ $page->isScheduled() ? 'Termin ändern' : 'Termin planen' }}</button>
                                @if ($page->isScheduled())<button type="button" class="py-2 text-sm text-slate-600 hover:underline" @click="submit('unpublish')">Planung aufheben</button>@endif
                            </div>
                        @else
                            <p class="mt-1 text-sm text-slate-600">Mit dem Modul „Redaktionsplan“ legen Sie fest, wann die Seite automatisch online geht. <a href="{{ route('admin.license') }}#funktionen" class="font-medium text-nova-700 hover:underline">Mehr erfahren</a></p>
                        @endif
                    </section>
                @endif

                @if (\App\Support\Social\SocialPublisher::on())
                    @php $fbOn = \App\Support\Social\Meta::connected('facebook'); $igOn = \App\Support\Social\Meta::connected('instagram'); @endphp
                    <section class="nova-card p-5" aria-labelledby="social-title" x-data="{ nets: @js(old('social_networks', [])), text: @js(old('social_text', '')), busy: false, msg: '' }">
                        <h2 id="social-title" class="flex items-center gap-2 font-semibold"><x-icon name="share" class="size-4 text-slate-500" /> Bei Facebook und Instagram teilen</h2>
                        @if (! $fbOn && ! $igOn)
                            <p class="mt-1 text-sm text-slate-600">Verbinden Sie zuerst Ihre Facebook-Seite und Ihr Instagram-Konto. <a href="{{ route('admin.social') }}#verbindung" class="font-medium text-nova-700 hover:underline">Jetzt verbinden</a></p>
                        @else
                            <p class="mt-1 text-xs text-slate-500">{{ $page->isPublished() ? 'Der Beitrag geht beim Speichern raus.' : 'Der Beitrag geht raus, sobald die Seite online ist.' }}</p>
                            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                <label class="flex items-center gap-2"><input type="checkbox" name="social_networks[]" value="facebook" x-model="nets" class="accent-nova-600" @disabled(! $fbOn) data-social-facebook> Bei Facebook posten</label>
                                <label class="flex items-center gap-2"><input type="checkbox" name="social_networks[]" value="instagram" x-model="nets" class="accent-nova-600" @disabled(! $igOn) data-social-instagram> Bei Instagram posten</label>
                            </div>
                            <div x-show="nets.length" class="mt-3">
                                <div class="flex justify-between"><label class="nova-label" for="social_text">Text des Beitrags</label><span class="text-xs text-slate-500" x-text="text.length + '/2200'"></span></div>
                                <textarea id="social_text" name="social_text" x-model="text" rows="6" maxlength="2200" class="nova-input text-sm" :required="nets.length > 0" @error('social_text') aria-invalid="true" @enderror></textarea>
                                @error('social_text')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                                <button type="button" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-nova-700 hover:underline disabled:opacity-50" :disabled="busy"
                                        @click="busy = true; msg = ''; fetch(@js(route('admin.social.suggest')), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({source: 'page:{{ $page->id }}'})}).then(r => r.json()).then(d => { if (d.text) text = d.text; else msg = d.message || ''; }).catch(() => msg = 'Keine Verbindung.').finally(() => busy = false)">
                                    <x-icon name="sparkles" class="size-3.5" /><span x-text="busy ? 'Schreibe …' : 'Text vorschlagen'"></span>
                                </button>
                                <p x-show="msg" x-text="msg" class="mt-1 text-xs text-red-700"></p>
                                @if ($igOn)<p class="mt-2 text-xs text-slate-500">Instagram nutzt das erste Bild der Seite. Ohne Bild wird nur bei Facebook gepostet.</p>@endif
                            </div>
                        @endif
                        @if ($socialPosts->isNotEmpty())
                            <ul class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600">
                                @foreach ($socialPosts as $sp)
                                    <li>{{ $sp->networkName() }}: {{ ['pending' => 'geplant', 'sent' => 'veröffentlicht am '.$sp->sent_at?->format('d.m.Y, H:i'), 'failed' => 'fehlgeschlagen'][$sp->status] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                <section class="nova-card space-y-3 p-5">
                    <h2 class="font-semibold">Optionen</h2>
                    @if ($page->locale)
                        <p class="text-sm text-slate-600" data-translation-of>{{ \App\Support\I18n\Languages::name($page->locale, false) }}e Fassung von <a href="{{ route('admin.pages.edit', $page->translation_of) }}" class="font-medium text-nova-700 hover:underline">„{{ $page->source?->title }}“</a>. Im Menü erscheint sie automatisch, wenn Besucher die Sprache wählen.</p>
                    @else
                        <input type="hidden" name="show_in_menu" value="0">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="show_in_menu" value="1" x-model="showInMenu" class="mt-0.5 accent-nova-600"> <span>Im Hauptmenü anzeigen</span></label>
                        <input type="hidden" name="is_home" value="0">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="is_home" value="1" x-model="isHome" class="mt-0.5 accent-nova-600"> <span>Als Startseite verwenden<span class="block text-xs text-slate-500">Die bisherige Startseite wird dann zu einer normalen Seite.</span></span></label>
                    @endif
                    @if (\App\Licensing\Features::on('mitgliederbereich') && ! $page->is_home)
                        <input type="hidden" name="members_only" value="0">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="members_only" value="1" class="mt-0.5 accent-nova-600" @checked(old('members_only', $page->visibility === 'members')) data-members-only> <span>Nur für Mitglieder<span class="block text-xs text-slate-500">Gäste sehen stattdessen die Anmeldung. Die Seite erscheint nicht bei Google und nur angemeldeten Mitgliedern im Menü.</span></span></label>
                    @endif
                </section>

                @if (\App\Support\I18n\Languages::on() && ! $page->locale)
                    @php $translations = $page->translations()->get()->keyBy('locale'); @endphp
                    <section class="nova-card p-5" aria-labelledby="tr-title" data-translations>
                        <h2 id="tr-title" class="font-semibold">Übersetzungen</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach (\App\Support\I18n\Languages::active() as $code)
                                <li class="flex items-center justify-between gap-2">
                                    <span><span class="mr-1 inline-block w-7 rounded bg-slate-100 text-center text-xs font-semibold uppercase">{{ $code }}</span> {{ \App\Support\I18n\Languages::name($code, false) }}</span>
                                    @if ($t = $translations->get($code))
                                        <a href="{{ route('admin.pages.edit', $t) }}" class="text-xs font-medium text-nova-700 hover:underline">{{ $t->isPublished() ? 'Bearbeiten' : 'Entwurf bearbeiten' }}</a>
                                    @else
                                        <button form="tr-{{ $code }}" class="text-xs font-medium text-nova-700 hover:underline" data-translate="{{ $code }}">Anlegen</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if (app(\App\Ai\AiManager::class)->isConfigured())
                            <label class="mt-3 flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" form="tr-ai" class="mt-0.5 accent-nova-600" checked x-ref="trAi"> Beim Anlegen mit KI übersetzen</label>
                        @else
                            <p class="mt-3 text-xs text-slate-500">Die Übersetzung startet als Kopie zum Überschreiben. Mit eingerichteter KI wird sie automatisch übersetzt.</p>
                        @endif
                    </section>
                @endif

                <section class="nova-card p-5">
                    <h2 class="font-semibold">Frühere Versionen</h2>
                    @forelse ($revisions as $rev)
                        <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                            <span class="text-slate-600">{{ $rev->created_at->format('d.m.Y, H:i') }}<span class="block text-xs text-slate-400">{{ $rev->user?->name }}</span></span>
                            <button form="rev-{{ $rev->id }}" class="shrink-0 text-xs font-medium text-nova-700 hover:underline">Wiederherstellen</button>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Nach dem ersten Speichern können Sie hier zu älteren Ständen zurückkehren.</p>
                    @endforelse
                </section>

                <button form="delete-page" class="w-full rounded-lg px-4 py-2 text-sm text-red-700 hover:bg-red-50">In den Papierkorb</button>
                    </div>
                </div>
            </aside>

            {{-- ================= LEINWAND ================= --}}
            <main class="nbe-stage" :class="'is-' + device" aria-label="Seite">
                <div class="nbe-frame" :style="'width:' + frameWidth">
                    <iframe x-ref="canvas" src="{{ route('admin.pages.canvas', $page) }}" title="Seite bearbeiten" data-canvas></iframe>
                    <div x-show="!canvasOk" class="absolute inset-0 grid place-items-center bg-white text-sm text-slate-500">Seite wird geladen …</div>
                </div>
            </main>
        </div>
    </form>

    <div x-show="toast" x-transition class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl" role="status">
        <span x-text="toast"></span>
        <button type="button" x-show="toastUndo" class="font-semibold text-nova-200 hover:underline" @click="undo(); toast = ''">Rückgängig</button>
    </div>

    @include('admin.media.picker')

    @foreach ($revisions as $rev)
        <form id="rev-{{ $rev->id }}" method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $rev]) }}" class="hidden" @submit="if (!confirm('Den Stand vom {{ $rev->created_at->format('d.m.Y, H:i') }} Uhr wiederherstellen? Der aktuelle Stand bleibt als Version erhalten.')) $event.preventDefault(); else dirty = false">@csrf</form>
    @endforeach
    @if (\App\Support\I18n\Languages::on() && ! $page->locale)
        @foreach (\App\Support\I18n\Languages::active() as $code)
            <form id="tr-{{ $code }}" method="POST" action="{{ route('admin.pages.translate', $page) }}" class="hidden" @submit="if (dirty && !confirm('Ungespeicherte Änderungen gehen verloren. Trotzdem fortfahren?')) { $event.preventDefault(); return; } dirty = false; $el.querySelector('[name=ai]').value = $refs.trAi && $refs.trAi.checked ? 1 : 0">@csrf<input type="hidden" name="locale" value="{{ $code }}"><input type="hidden" name="ai" value="0"></form>
        @endforeach
    @endif
    <form id="delete-page" method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="hidden" @submit="if (!confirm('Seite in den Papierkorb legen?')) $event.preventDefault(); else dirty = false">@csrf @method('DELETE')</form>
</div>
</body>
</html>
