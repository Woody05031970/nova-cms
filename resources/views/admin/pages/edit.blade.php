@extends('layouts.admin')
@section('title', $page->title)
@section('width', 'max-w-7xl')
@push('head') @vite('resources/js/editor.js') @endpush

@php
    $types = [
        'text' => ['label' => 'Text', 'description' => 'Absätze, Überschriften, Listen, Links', 'icon' => 'pages'],
        'hero' => ['label' => 'Titelbild', 'description' => 'Großes Bild mit Überschrift und Knopf', 'icon' => 'sparkles'],
        'image' => ['label' => 'Bild', 'description' => 'Ein Foto mit Bildunterschrift', 'icon' => 'image'],
        'slider' => ['label' => 'Slider', 'description' => 'Mehrere Bilder zum Durchblättern', 'icon' => 'image'],
        'video' => ['label' => 'Video', 'description' => 'YouTube, Vimeo oder eigene Datei', 'icon' => 'external'],
        'button' => ['label' => 'Knopf', 'description' => 'Ein auffälliger Link, z. B. zum Kontakt', 'icon' => 'arrow'],
        'html' => ['label' => 'HTML', 'description' => 'Eigener Code für Fortgeschrittene', 'icon' => 'settings'],
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
        'host' => request()->getHost(),
        'types' => $types,
        'ai' => [
            'enabled' => app(\App\Ai\AiManager::class)->isConfigured(),
            'setupUrl' => route('admin.ai'),
            'rewriteUrl' => route('admin.ai.rewrite'),
            'descriptionUrl' => route('admin.ai.description'),
            'actions' => collect(\App\Ai\ContentAssistant::ACTIONS)->map(fn ($a) => $a[0]),
        ],
    ];
@endphp

@section('content')
<div x-data="pageEditor(@js($cfg))" x-cloak
     @ai-error="error = $event.detail; window.scrollTo({top: 0, behavior: 'smooth'})"
     @ai-done="error = ''; aiNotice = 'Text von der KI überarbeitet. Gefällt es nicht? Mit ↶ machen Sie es rückgängig.'; setTimeout(() => aiNotice = '', 6000)">
    <form x-ref="form" method="POST" action="{{ route('admin.pages.update', $page) }}" @submit="dirty = false">
        @csrf @method('PUT')
        <input type="hidden" name="blocks" :value="JSON.stringify(blocks.map(({id, type, data}) => ({id, type, data})))">
        <input type="hidden" name="action" x-ref="action" value="save">

        {{-- Kopfzeile --}}
        <div class="sticky top-0 z-20 -mx-4 mb-6 flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50/95 px-4 py-3 backdrop-blur sm:-mx-8 sm:px-8 lg:-mx-10 lg:px-10">
            <a href="{{ route('admin.pages.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← Alle Seiten</a>
            @if ($page->isPublished())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Online</span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200/70 px-2.5 py-1 text-xs font-medium text-slate-600"><span class="size-1.5 rounded-full bg-slate-400"></span>Entwurf</span>
            @endif
            <span x-show="dirty" class="text-xs text-amber-700">Ungespeicherte Änderungen</span>
            <div class="ml-auto flex flex-wrap gap-2">
                <a href="{{ route('admin.pages.preview', $page) }}" target="_blank" class="nova-btn-secondary" title="Zeigt den zuletzt gespeicherten Stand">Vorschau</a>
                @if ($page->isPublished())
                    <button type="button" class="nova-btn-secondary" @click="submit('unpublish')">Offline nehmen</button>
                    <button type="button" class="nova-btn" @click="submit('save')">Änderungen veröffentlichen</button>
                @else
                    <button type="button" class="nova-btn-secondary" @click="submit('save')">Entwurf speichern</button>
                    <button type="button" class="nova-btn" @click="submit('publish')">Veröffentlichen</button>
                @endif
            </div>
        </div>

        <div x-show="error" x-text="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"></div>
        <div x-show="aiNotice" x-transition x-text="aiNotice" class="fixed right-4 bottom-4 z-30 max-w-sm rounded-lg bg-slate-900 px-4 py-3 text-sm text-white shadow-lg" role="status"></div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            {{-- Inhalt --}}
            <div class="min-w-0">
                <label for="title" class="sr-only">Titel der Seite</label>
                <input id="title" name="title" x-model="title" required maxlength="160" placeholder="Titel der Seite"
                       class="mb-6 w-full border-0 bg-transparent p-0 text-3xl font-semibold tracking-tight placeholder:text-slate-300 focus:ring-0 focus:outline-none">

                <template x-for="(block, i) in blocks" :key="block.id">
                    <div>
                        {{-- Einfügen zwischen Blöcken --}}
                        <div class="group relative flex h-6 items-center justify-center">
                            <button type="button" @click="picker = (picker === i ? null : i)" class="z-10 rounded-full border border-slate-200 bg-white px-2.5 text-xs text-slate-500 opacity-0 shadow-xs transition group-hover:opacity-100 focus:opacity-100" :class="picker === i && 'opacity-100'">+ Block einfügen</button>
                            <div class="absolute inset-x-0 top-1/2 h-px bg-slate-200 opacity-0 group-hover:opacity-100"></div>
                        </div>
                        <div x-show="picker === i" x-transition class="mb-3">@include('admin.pages.partials.picker')</div>

                        <section :id="'block-' + block.id" class="group/block rounded-xl border border-slate-200 bg-white shadow-xs focus-within:border-nova-200 focus-within:ring-2 focus-within:ring-nova-100">
                            <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-2">
                                <span class="text-xs font-semibold tracking-wide text-slate-500 uppercase" x-text="label(block.type)"></span>
                                <div class="ml-auto flex items-center gap-1 text-slate-400">
                                    <button type="button" class="rounded p-1 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" :disabled="i === 0" @click="move(i, -1)" title="Nach oben">↑</button>
                                    <button type="button" class="rounded p-1 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" :disabled="i === blocks.length - 1" @click="move(i, 1)" title="Nach unten">↓</button>
                                    <button type="button" class="rounded px-1.5 py-1 text-xs hover:bg-slate-100 hover:text-slate-700" @click="duplicate(i)" title="Doppeln">Doppeln</button>
                                    <button type="button" class="rounded px-1.5 py-1 text-xs hover:bg-red-50 hover:text-red-700" @click="remove(i)" title="Entfernen">Entfernen</button>
                                </div>
                            </header>
                            <div class="p-4">
                                @include('admin.pages.partials.blocks')
                            </div>
                        </section>
                    </div>
                </template>

                <div class="mt-4">
                    <button type="button" @click="picker = (picker === 'end' ? null : 'end')" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 py-4 text-sm font-medium text-slate-600 hover:border-nova-500 hover:text-nova-700">+ Block hinzufügen</button>
                    <div x-show="picker === 'end'" x-transition class="mt-3">
                        @include('admin.pages.partials.picker', ['at' => 'blocks.length'])
                    </div>
                </div>
            </div>

            {{-- Seitenleiste --}}
            <aside class="space-y-5">
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
                                <span class="pl-3 text-slate-400">/</span>
                                <input id="slug" name="slug" x-model="slug" maxlength="160" class="w-full border-0 bg-transparent py-2 pr-3 pl-0.5 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="nova-card space-y-3 p-5">
                    <h2 class="font-semibold">Optionen</h2>
                    <input type="hidden" name="show_in_menu" value="0">
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="show_in_menu" value="1" x-model="showInMenu" class="mt-0.5 accent-nova-600"> <span>Im Hauptmenü anzeigen</span></label>
                    <input type="hidden" name="is_home" value="0">
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="is_home" value="1" x-model="isHome" class="mt-0.5 accent-nova-600"> <span>Als Startseite verwenden<span class="block text-xs text-slate-500">Die bisherige Startseite wird dann zu einer normalen Seite.</span></span></label>
                </section>

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
            </aside>
        </div>
    </form>

    @include('admin.media.picker')

    @foreach ($revisions as $rev)
        <form id="rev-{{ $rev->id }}" method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $rev]) }}" class="hidden" @submit="if (!confirm('Den Stand vom {{ $rev->created_at->format('d.m.Y, H:i') }} Uhr wiederherstellen? Der aktuelle Stand bleibt als Version erhalten.')) $event.preventDefault(); else dirty = false">@csrf</form>
    @endforeach
    <form id="delete-page" method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="hidden" @submit="if (!confirm('Seite in den Papierkorb legen?')) $event.preventDefault(); else dirty = false">@csrf @method('DELETE')</form>
</div>
@endsection
