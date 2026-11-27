@extends('layouts.admin')
@section('title', 'Menü')
@section('width', 'max-w-6xl')
@push('head') @vite('resources/js/menu.js') @endpush

@section('content')
<div x-data="menuEditor(@js(['tree' => $tree, 'pages' => $pages, 'shopEnabled' => $shopEnabled, 'url' => route('admin.menu.update')]))" x-cloak>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Menü</h1>
            <p class="mt-1 text-slate-600">Ziehen Sie die Punkte an die gewünschte Stelle. Nach rechts eingerückt wird ein Punkt zum Unterpunkt.</p>
        </div>
        <div class="flex items-center gap-3">
            <span x-show="dirty" class="text-sm text-amber-700">Ungespeicherte Änderungen</span>
            <span x-show="message" x-text="message" class="text-sm text-emerald-700" role="status"></span>
            <button type="button" class="nova-btn" @click="save()" :disabled="saving || !dirty" data-save-menu>
                <span x-text="saving ? 'Speichert …' : 'Menü speichern'"></span>
            </button>
        </div>
    </div>

    <div x-show="error" x-text="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"></div>

    {{-- Live-Vorschau der Navigation --}}
    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs" aria-label="Vorschau">
        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-500">
            <span class="flex gap-1"><span class="size-2.5 rounded-full bg-slate-300"></span><span class="size-2.5 rounded-full bg-slate-300"></span><span class="size-2.5 rounded-full bg-slate-300"></span></span>
            <span class="ml-2 font-mono">So sieht das Menü auf Ihrer Website aus</span>
        </div>
        <div class="flex flex-wrap items-start gap-x-1 gap-y-2 px-5 py-4" data-menu-preview>
            <span class="mr-4 font-semibold">{{ $siteName }}</span>
            <template x-for="(p, pi) in preview" :key="pi">
                <div class="group relative">
                    <span class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span x-text="p.label"></span>
                        <svg x-show="p.children.length" class="size-3.5 opacity-60" viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.5a.75.75 0 0 1 1.06 0L10 11.2l3.74-3.7a.75.75 0 1 1 1.06 1.06l-4.27 4.24a.75.75 0 0 1-1.06 0L5.2 8.56a.75.75 0 0 1 0-1.06Z"/></svg>
                    </span>
                    <div x-show="p.children.length" class="mt-1 ml-3 border-l border-slate-200 pl-2">
                        <template x-for="(c, ci) in p.children" :key="ci"><span class="block py-0.5 text-xs text-slate-500" x-text="c"></span></template>
                    </div>
                </div>
            </template>
            <span x-show="!preview.length" class="text-sm text-slate-400">Noch keine sichtbaren Menüpunkte.</span>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Menübaum --}}
        <section class="min-w-0">
            <template x-for="v in [version]" :key="v">
                <ul data-sortable data-level="0" class="space-y-2">
                    <template x-for="item in tree" :key="item.key">
                        <li data-item :data-key="item.key" class="rounded-xl">
                            @include('admin.partials.menu-item', ['level' => 0])
                            <ul data-sortable data-level="1" class="nova-submenu mt-2 ml-8 space-y-2 border-l-2 border-dashed border-slate-200 pl-3">
                                <template x-for="item in item.children" :key="item.key">
                                    <li data-item :data-key="item.key">@include('admin.partials.menu-item', ['level' => 1])</li>
                                </template>
                            </ul>
                        </li>
                    </template>
                </ul>
            </template>
            <p x-show="!tree.length" class="rounded-xl border-2 border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">Das Menü ist leer. Fügen Sie rechts Seiten oder Links hinzu.</p>
            <p class="mt-4 text-xs text-slate-500">Tipp: Mit den Pfeilen rücken Sie Punkte auch ohne Maus ein und aus. Seiten im Entwurf und ausgeblendete Punkte erscheinen erst auf der Website, wenn sie veröffentlicht bzw. eingeblendet sind.</p>
        </section>

        {{-- Hinzufügen --}}
        <aside class="space-y-5">
            <section class="nova-card p-5">
                <h2 class="font-semibold">Seiten hinzufügen</h2>
                <ul class="mt-3 max-h-72 space-y-1 overflow-y-auto">
                    <template x-for="p in unusedPages" :key="p.id">
                        <li class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                            <span class="min-w-0 truncate"><span x-text="p.title"></span> <span x-show="!p.published" class="text-xs text-slate-400">Entwurf</span></span>
                            <button type="button" class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-nova-700 hover:bg-nova-50" @click="addPage(p)" :aria-label="'Seite ' + p.title + ' hinzufügen'">+ Hinzufügen</button>
                        </li>
                    </template>
                </ul>
                <p x-show="!unusedPages.length" class="mt-2 text-sm text-slate-500">Alle Seiten sind schon im Menü.</p>
            </section>

            <section class="nova-card space-y-3 p-5">
                <h2 class="font-semibold">Eigener Link</h2>
                <div><label class="nova-label" for="link_label">Text im Menü</label><input id="link_label" class="nova-input" x-model="linkLabel" maxlength="80" placeholder="z. B. Instagram"></div>
                <div><label class="nova-label" for="link_url">Ziel</label><input id="link_url" class="nova-input" x-model="linkUrl" placeholder="/kontakt oder https://…" @keydown.enter.prevent="addLink()"></div>
                <button type="button" class="nova-btn-secondary w-full" @click="addLink()">Link hinzufügen</button>
            </section>

            <section class="nova-card p-5" x-show="!hasShop">
                <h2 class="font-semibold">Shop</h2>
                <p class="mt-1 text-sm text-slate-600">Legen Sie fest, wo „Shop“ im Menü steht. <span x-show="!shopEnabled">Er erscheint erst, wenn der Shop eingeschaltet ist.</span></p>
                <button type="button" class="nova-btn-secondary mt-3 w-full" @click="add({type: 'shop'})">Shop hinzufügen</button>
            </section>
        </aside>
    </div>
</div>
@endsection
