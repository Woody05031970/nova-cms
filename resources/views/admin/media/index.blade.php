@extends('layouts.admin')
@section('title', 'Medien')
@section('width', 'max-w-7xl')
@push('head') @vite('resources/js/app.js') @endpush

@section('content')
<div x-data="mediaLibrary(@js(['items' => $items, 'ai' => $ai]))" x-cloak
     @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="upload($event)">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Medien</h1>
            <p class="mt-1 text-slate-600">Alle Bilder und Videos Ihrer Website. <span class="text-slate-500" x-show="items.length" x-text="stats"></span></p>
        </div>
        <label class="nova-btn cursor-pointer">
            <x-icon name="upload" class="size-4" /> Dateien hochladen
            <input type="file" multiple accept="image/*,video/mp4,video/webm" class="sr-only" @change="upload($event)">
        </label>
    </div>

    <div x-show="error" x-text="error" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"></div>

    {{-- Hinweis: fehlende Bildbeschreibungen --}}
    <div x-show="missingAlt > 0" class="mt-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
        <x-icon name="alert" class="size-5 shrink-0 text-amber-600" />
        <p class="flex-1 text-sm text-amber-900">
            <strong x-text="missingAlt + (missingAlt === 1 ? ' Bild hat' : ' Bilder haben')"></strong> noch keine Beschreibung. Google und Screenreader blinder Besucher wissen sonst nicht, was darauf zu sehen ist.
        </p>
        @if ($ai)
            <button type="button" class="nova-btn shrink-0" @click="describeAll()" :disabled="bulk">
                <x-icon name="sparkles" class="size-4" />
                <span x-text="bulk ? `KI beschreibt … ${bulk.done} von ${bulk.total}` : 'Alle mit KI beschreiben'"></span>
            </button>
        @else
            <a href="{{ route('admin.ai') }}" class="nova-btn-secondary shrink-0">KI einrichten, um das zu automatisieren</a>
        @endif
    </div>

    {{-- Filter --}}
    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'Alle', 'image' => 'Bilder', 'video' => 'Videos', 'noalt' => 'Ohne Beschreibung'] as $value => $label)
            <button type="button" @click="filter = '{{ $value }}'" class="rounded-full px-3 py-1.5 text-sm font-medium"
                    :class="filter === '{{ $value }}' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'">{{ $label }}</button>
        @endforeach
        <input type="search" x-model="search" placeholder="Suchen …" aria-label="Medien durchsuchen" class="nova-input ml-auto w-full py-1.5 text-sm sm:w-64">
    </div>

    {{-- Laufende Uploads --}}
    <template x-if="uploads.length">
        <ul class="mt-4 space-y-1 text-sm">
            <template x-for="u in uploads"><li :class="u.failed ? 'text-red-700' : (u.done ? 'text-emerald-700' : 'text-nova-700')"
                x-text="(u.failed ? '✕ ' : (u.done ? '✓ ' : '… ')) + u.name"></li></template>
        </ul>
    </template>

    {{-- Raster --}}
    <div class="relative mt-6">
        <div x-show="dragging" class="pointer-events-none absolute inset-0 z-10 grid place-items-center rounded-xl border-2 border-dashed border-nova-500 bg-nova-50/90 text-nova-700">Zum Hochladen loslassen</div>

        <div x-show="!items.length" class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-slate-300 px-6 py-16 text-center">
            <x-icon name="image" class="size-10 text-slate-300" />
            <p class="font-medium">Noch keine Bilder oder Videos</p>
            <p class="max-w-sm text-sm text-slate-500">Ziehen Sie Dateien hierher oder klicken Sie auf „Dateien hochladen“. Große Handy-Fotos werden automatisch verkleinert.</p>
        </div>
        <p x-show="items.length && !visible.length" class="text-sm text-slate-500">Keine Treffer.</p>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
            <template x-for="m in visible" :key="m.id">
                <button type="button" @click="open(m)" class="group overflow-hidden rounded-xl border bg-white text-left shadow-xs transition hover:shadow-md"
                        :class="selected === m ? 'border-nova-500 ring-2 ring-nova-200' : 'border-slate-200'">
                    <div class="relative">
                        <template x-if="m.kind === 'image'"><img :src="m.thumb" :alt="m.alt || ''" loading="lazy" class="aspect-square w-full bg-slate-100 object-cover"></template>
                        <template x-if="m.kind === 'video'"><div class="grid aspect-square w-full place-items-center bg-slate-900 text-3xl text-white/80">▶</div></template>
                        <span x-show="m.kind === 'image' && !m.alt" class="absolute top-2 left-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">ohne Beschreibung</span>
                    </div>
                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-medium" x-text="m.name"></p>
                        <p class="text-xs text-slate-500" x-text="(m.width ? m.width + ' × ' + m.height + ' · ' : '') + m.size"></p>
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- Details --}}
    <div x-show="selected" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/30" @click="close()"></div>
    <aside x-show="selected" x-transition:enter="transition" x-transition:enter-start="translate-x-full" x-transition:leave="transition" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl" role="dialog" aria-label="Details zur Datei" @keydown.escape.window="close()">
        <template x-if="selected">
            <div class="flex h-full flex-col">
                <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="flex-1 truncate font-semibold" x-text="selected.name"></h2>
                    <button type="button" @click="close()" class="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Schließen">✕</button>
                </header>
                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <template x-if="selected.kind === 'image'"><img :src="selected.url" :alt="selected.alt || ''" class="max-h-72 w-full rounded-lg bg-slate-100 object-contain"></template>
                    <template x-if="selected.kind === 'video'"><video :src="selected.url" controls class="w-full rounded-lg"></video></template>

                    <p x-show="!detail" class="text-sm text-slate-500">Lädt …</p>
                    <template x-if="detail">
                        <form class="space-y-4" @submit.prevent="save()">
                            <div x-show="selected.kind === 'image'">
                                <div class="flex items-end justify-between gap-2">
                                    <label class="nova-label" for="m-alt">Bildbeschreibung</label>
                                    @if ($ai)
                                        <button type="button" @click="describe()" :disabled="busyAlt" class="mb-1.5 inline-flex items-center gap-1 text-xs font-medium text-nova-700 hover:underline disabled:opacity-50">
                                            <x-icon name="sparkles" class="size-3.5" /><span x-text="busyAlt ? 'KI sieht sich das Bild an …' : 'Mit KI beschreiben'"></span>
                                        </button>
                                    @endif
                                </div>
                                <textarea id="m-alt" class="nova-input text-sm" rows="2" maxlength="200" x-model="detail.alt" placeholder="Was ist zu sehen? z. B. Frisch gebackene Brote im Holzofen"></textarea>
                                <p class="nova-help">Wird automatisch übernommen, wenn Sie das Bild auf einer Seite einsetzen.</p>
                            </div>
                            <div>
                                <label class="nova-label" for="m-title">Name in der Mediathek</label>
                                <input id="m-title" class="nova-input text-sm" maxlength="160" x-model="detail.title" :placeholder="detail.original_name">
                            </div>
                            <div class="flex items-center gap-3">
                                <button class="nova-btn">Speichern</button>
                                <span x-show="saved" class="text-sm text-emerald-700">Gespeichert</span>
                            </div>
                        </form>
                    </template>

                    <template x-if="detail">
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 border-t border-slate-100 pt-4 text-sm">
                            <dt class="text-slate-500">Datei</dt><dd class="truncate" x-text="detail.original_name"></dd>
                            <dt class="text-slate-500">Größe</dt><dd x-text="(detail.width ? detail.width + ' × ' + detail.height + ' Pixel, ' : '') + detail.size"></dd>
                            <dt class="text-slate-500">Hochgeladen</dt><dd x-text="(detail.created || '') + (detail.user ? ' von ' + detail.user : '')"></dd>
                            <dt class="text-slate-500">Adresse</dt><dd class="flex min-w-0 gap-2"><span class="truncate font-mono text-xs leading-5" x-text="detail.url"></span><button type="button" class="shrink-0 text-xs text-nova-700 hover:underline" @click="copy()">Kopieren</button></dd>
                            <dt class="text-slate-500">Verwendet auf</dt>
                            <dd>
                                <span x-show="!detail.usages.length" class="text-slate-500">keiner Seite</span>
                                <template x-for="u in detail.usages"><span class="block"><template x-if="u.url"><a :href="u.url" class="text-nova-700 hover:underline" x-text="u.title"></a></template><template x-if="!u.url"><span x-text="u.title"></span></template></span></template>
                            </dd>
                        </dl>
                    </template>
                </div>
                <footer class="border-t border-slate-200 px-5 py-3">
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-red-700 hover:bg-red-50" @click="remove()">Endgültig löschen</button>
                </footer>
            </div>
        </template>
    </aside>
</div>
@endsection
