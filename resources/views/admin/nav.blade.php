@extends('layouts.admin')
@section('title', 'Backoffice-Menü anpassen')
@section('width', 'max-w-4xl')
@push('head') @vite('resources/js/nav-editor.js') @endpush

@section('content')
<div x-data="navEditor(@js(['groups' => $groups, 'single' => $single]))" x-cloak>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Backoffice-Menü anpassen</h1>
            <p class="mt-1 max-w-2xl text-slate-600">Bestimmen Sie, in welcher Reihenfolge die Kategorien und Punkte links in der Verwaltung stehen. Ziehen Sie sie am Griff an die gewünschte Stelle oder nutzen Sie die Pfeile. Ausgeblendete Punkte finden Sie weiterhin über die Suche (Strg K).</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.nav.update') }}" class="mt-6" data-nav-form @submit="dirty = false">
        @csrf @method('PUT')
        <input type="hidden" name="layout" :value="layout">

        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <input type="checkbox" name="single" value="1" x-model="single" @change="changed()" class="mt-1 size-4 rounded border-slate-300 text-nova-600">
            <span>
                <span class="block text-sm font-medium">Immer nur eine Kategorie geöffnet</span>
                <span class="block text-sm text-slate-500">Öffnen Sie eine Kategorie, schließen sich die anderen. So bleibt die Seitenleiste kurz. Ohne Häkchen merkt sich Ihr Browser, welche Kategorien offen sind.</span>
            </span>
        </label>

        <div class="mt-4 space-y-3" data-groups>
            <template x-for="(g, gi) in groups" :key="g.key + '-' + version">
                <section class="rounded-xl border border-slate-200 bg-white shadow-xs" :data-group="g.key">
                    <header class="flex items-center gap-2 border-b border-slate-100 px-3 py-2.5">
                        <button type="button" class="cursor-grab rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-group-handle title="Kategorie verschieben" aria-label="Kategorie ziehen"><x-icon name="grip" class="size-4" /></button>
                        <input type="text" x-model="g.label" @input="changed()" maxlength="40" class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 py-1 font-semibold hover:border-slate-200 focus:border-nova-400 focus:outline-none" :aria-label="'Name der Kategorie ' + g.default" data-group-label>
                        <span class="hidden text-xs text-slate-400 sm:inline" x-text="visibleCount(g) + ' von ' + g.items.length + ' sichtbar'"></span>
                        <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" @click="moveGroup(g, -1)" :disabled="gi === 0" title="Nach oben" aria-label="Kategorie nach oben"><x-icon name="arrow" class="size-4 -rotate-90" /></button>
                        <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" @click="moveGroup(g, 1)" :disabled="gi === groups.length - 1" title="Nach unten" aria-label="Kategorie nach unten"><x-icon name="arrow" class="size-4 rotate-90" /></button>
                    </header>
                    <ul class="nova-nav-drop min-h-12 space-y-1 p-2" data-items>
                        <template x-for="(item, ii) in g.items" :key="item.key">
                            <li class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50" :class="item.hidden && 'opacity-50'" :data-item="item.key">
                                <button type="button" class="cursor-grab rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-item-handle title="Punkt verschieben" aria-label="Punkt ziehen"><x-icon name="grip" class="size-4" /></button>
                                <span class="min-w-0 flex-1 truncate text-sm" :class="item.hidden && 'line-through'" x-text="item.label"></span>
                                <template x-if="item.locked"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">nicht in Lizenz</span></template>
                                <select class="rounded-md border border-slate-200 bg-white py-1 pr-7 pl-2 text-xs text-slate-600" :value="g.key" @change="toGroup(g, item, $event.target.value)" :aria-label="'Kategorie für ' + item.label">
                                    <template x-for="o in groups" :key="o.key"><option :value="o.key" x-text="o.label" :selected="o.key === g.key"></option></template>
                                </select>
                                <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" @click="moveItem(g, item, -1)" :disabled="ii === 0" aria-label="Nach oben"><x-icon name="arrow" class="size-4 -rotate-90" /></button>
                                <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" @click="moveItem(g, item, 1)" :disabled="ii === g.items.length - 1" aria-label="Nach unten"><x-icon name="arrow" class="size-4 rotate-90" /></button>
                                <button type="button" class="inline-flex w-28 items-center justify-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium" :class="item.hidden ? 'border-slate-200 text-slate-500 hover:bg-slate-100' : 'border-nova-200 bg-nova-50 text-nova-700 hover:bg-nova-100'" @click="toggle(item)" :aria-pressed="(!item.hidden).toString()" data-item-toggle>
                                    <x-icon name="eye" class="size-3.5" /> <span x-text="item.hidden ? 'Ausgeblendet' : 'Sichtbar'"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!g.items.length" class="px-3 py-2 text-sm text-slate-400">Leer. Ziehen Sie Punkte hierher. Leere Kategorien erscheinen nicht im Menü.</li>
                    </ul>
                </section>
            </template>
        </div>

        <div class="sticky bottom-0 mt-6 flex flex-wrap items-center gap-3 border-t border-slate-200 bg-slate-50/90 py-4 backdrop-blur">
            <button class="nova-btn" data-save-nav>Menü speichern</button>
            <span x-show="dirty" class="text-sm text-amber-700">Ungespeicherte Änderungen</span>
        </div>
    </form>

    @if ($customized)
        <form method="POST" action="{{ route('admin.nav.reset') }}" class="mt-2" onsubmit="return confirm('Alle Anpassungen am Backoffice-Menü zurücksetzen?')">
            @csrf @method('DELETE')
            <button class="text-sm text-slate-500 underline hover:text-slate-800" data-reset-nav>Ursprüngliche Reihenfolge wiederherstellen</button>
        </form>
    @endif
</div>
@endsection
