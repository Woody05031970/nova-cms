{{-- TEXT --}}
<template x-if="block.type === 'text'">
    <div x-data="richText(block)">
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-100 pb-2" role="toolbar" aria-label="Textformat">
            @php
                $tb = 'rounded px-2 py-1 text-sm hover:bg-slate-100';
                $on = 'bg-nova-50 text-nova-700';
            @endphp
            <button type="button" class="{{ $tb }} font-bold" :class="active('bold') && '{{ $on }}'" @click="run('toggleBold')" title="Fett">F</button>
            <button type="button" class="{{ $tb }} italic" :class="active('italic') && '{{ $on }}'" @click="run('toggleItalic')" title="Kursiv">K</button>
            <button type="button" class="{{ $tb }} underline" :class="active('underline') && '{{ $on }}'" @click="run('toggleUnderline')" title="Unterstrichen">U</button>
            <span class="mx-1 w-px bg-slate-200"></span>
            <button type="button" class="{{ $tb }}" :class="active('heading', {level: 2}) && '{{ $on }}'" @click="run('toggleHeading', {level: 2})" title="Große Überschrift">Ü1</button>
            <button type="button" class="{{ $tb }}" :class="active('heading', {level: 3}) && '{{ $on }}'" @click="run('toggleHeading', {level: 3})" title="Kleine Überschrift">Ü2</button>
            <span class="mx-1 w-px bg-slate-200"></span>
            <button type="button" class="{{ $tb }}" :class="active('bulletList') && '{{ $on }}'" @click="run('toggleBulletList')" title="Aufzählung">• Liste</button>
            <button type="button" class="{{ $tb }}" :class="active('orderedList') && '{{ $on }}'" @click="run('toggleOrderedList')" title="Nummerierte Liste">1. Liste</button>
            <button type="button" class="{{ $tb }}" :class="active('blockquote') && '{{ $on }}'" @click="run('toggleBlockquote')" title="Zitat">„Zitat“</button>
            <button type="button" class="{{ $tb }}" :class="active('link') && '{{ $on }}'" @click="link()" title="Link">Link</button>
            <span class="mx-1 w-px bg-slate-200"></span>
            <button type="button" class="{{ $tb }} text-slate-500" @click="run('undo')" title="Rückgängig">↶</button>
            <button type="button" class="{{ $tb }} text-slate-500" @click="run('redo')" title="Wiederholen">↷</button>
            <div class="relative ml-auto" @click.outside="aiOpen = false" @keydown.escape="aiOpen = false">
                <template x-if="ai.enabled">
                    <button type="button" class="inline-flex items-center gap-1 rounded-md bg-nova-50 px-2.5 py-1 text-sm font-medium text-nova-700 hover:bg-nova-100 disabled:opacity-60"
                            @click="aiOpen = !aiOpen" :disabled="aiBusy" :aria-expanded="aiOpen" title="Text mit KI bearbeiten. Markieren Sie vorher etwas, wird nur die Markierung bearbeitet.">
                        <x-icon name="sparkles" class="size-4" ::class="aiBusy && 'animate-pulse'" /><span x-text="aiBusy ? 'KI arbeitet …' : 'KI'"></span>
                    </button>
                </template>
                <template x-if="!ai.enabled">
                    <a :href="ai.setupUrl" class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-sm text-slate-500 hover:bg-slate-100" title="KI-Anbieter einrichten, um Texte verbessern zu lassen"><x-icon name="sparkles" class="size-4" />KI einrichten</a>
                </template>
                <div x-show="aiOpen" x-transition.origin.top.right class="absolute right-0 z-30 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="menu">
                    <p class="px-3 pt-1 pb-2 text-xs text-slate-500">Gilt für die Markierung oder den ganzen Block.</p>
                    <template x-for="(label, key) in ai.actions" :key="key">
                        <button type="button" role="menuitem" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-nova-50 hover:text-nova-700" @mousedown.prevent @click="aiRun(key)" x-text="label"></button>
                    </template>
                </div>
            </div>
        </div>
        <div x-ref="ed"></div>
    </div>
</template>

{{-- TITELBILD --}}
<template x-if="block.type === 'hero'">
    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-3">
            <div><label class="nova-label">Überschrift</label><input class="nova-input" x-model="block.data.title" maxlength="160" placeholder="z. B. Frisches Brot jeden Morgen"></div>
            <div><label class="nova-label">Text darunter</label><textarea class="nova-input" rows="3" x-model="block.data.text" maxlength="400"></textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="60"></div>
                <div><label class="nova-label">Knopf-Ziel</label><input class="nova-input" x-model="block.data.button_url" placeholder="/kontakt"></div>
            </div>
        </div>
        <div>
            <label class="nova-label">Hintergrundbild</label>
            <template x-if="block.data.image">
                <div class="relative overflow-hidden rounded-lg">
                    <img :src="block.data.image" alt="" class="aspect-video w-full object-cover">
                    <button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="block.data.image = null">Ändern</button>
                </div>
            </template>
            @include('admin.pages.partials.upload', ['target' => 'block.data', 'key' => 'image', 'kind' => 'image', 'hint' => 'Querformat, mindestens 1600 Pixel breit. Ohne Bild wird eine Farbfläche gezeigt.'])
        </div>
    </div>
</template>

{{-- BILD --}}
<template x-if="block.type === 'image'">
    <div class="space-y-3">
        <template x-if="block.data.url">
            <div class="relative">
                <img :src="block.data.url" :alt="block.data.alt" class="max-h-96 w-full rounded-lg bg-slate-50 object-contain">
                <button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="block.data.url = null">Anderes Bild</button>
            </div>
        </template>
        @include('admin.pages.partials.upload', ['target' => 'block.data', 'key' => 'url', 'kind' => 'image', 'alt' => 'alt', 'hint' => 'JPG, PNG, WebP oder GIF, bis 10 MB. Große Fotos werden automatisch verkleinert.'])
        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <div>
                <label class="nova-label">Bildbeschreibung</label>
                <input class="nova-input" x-model="block.data.alt" maxlength="200" placeholder="Was ist zu sehen?">
                <p class="mt-1 text-xs" :class="block.data.alt ? 'text-slate-500' : 'text-amber-700'" x-text="block.data.alt ? 'Hilft Google und blinden Besuchern.' : 'Fehlt noch: wichtig für Google und Barrierefreiheit.'"></p>
            </div>
            <div><label class="nova-label">Bildunterschrift (optional)</label><input class="nova-input" x-model="block.data.caption" maxlength="300"></div>
            <div><label class="nova-label">Breite</label>
                <select class="nova-input" x-model="block.data.size"><option value="normal">Normal</option><option value="wide">Breit</option><option value="full">Ganze Breite</option></select>
            </div>
        </div>
    </div>
</template>

{{-- SLIDER --}}
<template x-if="block.type === 'slider'">
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <template x-for="(slide, s) in block.data.slides" :key="slide.url + s">
                <div class="rounded-lg border border-slate-200 p-2">
                    <img :src="slide.url" alt="" class="aspect-video w-full rounded object-cover">
                    <input class="nova-input mt-2 py-1.5 text-xs" x-model="slide.alt" placeholder="Bildbeschreibung">
                    <input class="nova-input mt-1 py-1.5 text-xs" x-model="slide.caption" placeholder="Text auf dem Bild (optional)">
                    <div class="mt-1 flex justify-between text-xs">
                        <span>
                            <button type="button" class="px-1 text-slate-500 disabled:opacity-30" :disabled="s === 0" @click="block.data.slides.splice(s - 1, 0, block.data.slides.splice(s, 1)[0])">←</button>
                            <button type="button" class="px-1 text-slate-500 disabled:opacity-30" :disabled="s === block.data.slides.length - 1" @click="block.data.slides.splice(s + 1, 0, block.data.slides.splice(s, 1)[0])">→</button>
                        </span>
                        <button type="button" class="text-red-700" @click="block.data.slides.splice(s, 1)">Entfernen</button>
                    </div>
                </div>
            </template>
            <label class="flex aspect-video cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 text-sm text-slate-600 hover:border-nova-500"
                   @dragover.prevent @drop.prevent="addSlides(block, $event)">
                <span x-show="!block.data._uploading">+ Bilder hinzufügen</span>
                <span x-show="block.data._uploading" class="text-nova-700">Wird hochgeladen …</span>
                <input type="file" multiple accept="image/*" class="sr-only" @change="addSlides(block, $event)">
                <button type="button" class="mt-1 text-xs font-medium text-nova-700 hover:underline" x-show="!block.data._uploading" @click.prevent="pickSlides(block)">aus der Mediathek</button>
            </label>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="block.data.autoplay" class="accent-nova-600"> Automatisch weiterblättern</label>
    </div>
</template>

{{-- VIDEO --}}
<template x-if="block.type === 'video'">
    <div class="space-y-3">
        <div>
            <label class="nova-label">Link zu YouTube oder Vimeo</label>
            <input class="nova-input" x-model="block.data.url" placeholder="https://www.youtube.com/watch?v=…" @input="if (block.data.url) block.data.file = null">
            <p class="nova-help">Das Video wird erst nach einem Klick des Besuchers geladen. So bleibt Ihre Seite datenschutzfreundlich.</p>
        </div>
        <div x-show="!block.data.url">
            <p class="mb-2 text-sm text-slate-500">oder eigene Videodatei hochladen:</p>
            <template x-if="block.data.file">
                <div class="relative"><video :src="block.data.file" controls class="w-full rounded-lg"></video>
                    <button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="block.data.file = null">Entfernen</button></div>
            </template>
            @include('admin.pages.partials.upload', ['target' => 'block.data', 'key' => 'file', 'kind' => 'video', 'hint' => 'MP4 oder WebM, bis 100 MB. Viele Hoster erlauben nur kleinere Uploads.'])
        </div>
        <div><label class="nova-label">Beschreibung (optional)</label><input class="nova-input" x-model="block.data.caption" maxlength="300"></div>
    </div>
</template>

{{-- KNOPF --}}
<template x-if="block.type === 'button'">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="nova-label">Beschriftung</label><input class="nova-input" x-model="block.data.label" maxlength="60"></div>
        <div><label class="nova-label">Ziel</label><input class="nova-input" x-model="block.data.url" placeholder="/kontakt, https://… oder tel:0221…"></div>
        <div><label class="nova-label">Aussehen</label>
            <select class="nova-input" x-model="block.data.style"><option value="primary">Auffällig</option><option value="secondary">Dezent</option></select></div>
        <div><label class="nova-label">Ausrichtung</label>
            <select class="nova-input" x-model="block.data.align"><option value="left">Links</option><option value="center">Mitte</option><option value="right">Rechts</option></select></div>
    </div>
</template>

{{-- HTML --}}
<template x-if="block.type === 'html'">
    <div x-data="codeEditor(block)">
        <p class="mb-2 text-xs text-slate-500">Für Fortgeschrittene, z. B. für Einbettungen von Google Maps oder Buchungs-Widgets. Der Code wird unverändert ausgegeben.</p>
        <div x-ref="cm" class="overflow-hidden rounded-lg border border-slate-200 text-sm"></div>
    </div>
</template>
