{{-- TEXT --}}
<template x-if="block.type === 'text'">
    @include('admin.pages.partials.rich-text')
</template>

{{-- TITELBILD --}}
<template x-if="block.type === 'hero'">
    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-3">
            <div>
                <label class="nova-label">Aussehen</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" class="rounded-lg border px-3 py-2 text-left text-sm" :class="(block.data.variant || 'image') === 'image' ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200 hover:bg-slate-50'" @click="block.data.variant = 'image'"><strong class="block">Bild</strong><span class="text-xs text-slate-500">Foto im Hintergrund</span></button>
                    <button type="button" class="rounded-lg border px-3 py-2 text-left text-sm" :class="block.data.variant === 'tech' ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200 hover:bg-slate-50'" @click="block.data.variant = 'tech'"><strong class="block">Technik</strong><span class="text-xs text-slate-500">Dunkel, Bild daneben</span></button>
                    <button type="button" class="relative rounded-lg border px-3 py-2 text-left text-sm" :class="block.data.variant === 'video' ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200 hover:bg-slate-50'" @click="block.data.variant = 'video'; block.data.opacity ??= 55; block.data.overlay ??= 'dark'; block.data.height ??= 'tall'" data-hero-video><strong class="block">Video</strong><span class="text-xs text-slate-500">Film im Hintergrund</span>
                        <span x-show="!features['design-pro']" class="absolute top-1.5 right-1.5 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800">Pro</span></button>
                </div>
            </div>
            <div><label class="nova-label">Kleine Zeile darüber (optional)</label><input class="nova-input" x-model="block.data.eyebrow" maxlength="80" placeholder="z. B. Neu: Version 2"></div>
            <div><label class="nova-label">Überschrift</label><input class="nova-input" x-model="block.data.title" maxlength="160" placeholder="z. B. Frisches Brot jeden Morgen"></div>
            <div><label class="nova-label">Text darunter</label><textarea class="nova-input" rows="3" x-model="block.data.text" maxlength="400"></textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="60"></div>
                <div><label class="nova-label">Knopf-Ziel</label><input class="nova-input" x-model="block.data.button_url" placeholder="/kontakt"></div>
                <div><label class="nova-label">Zweiter Knopf (optional)</label><input class="nova-input" x-model="block.data.button2_label" maxlength="60"></div>
                <div><label class="nova-label">Ziel</label><input class="nova-input" x-model="block.data.button2_url" placeholder="/preise"></div>
            </div>
        </div>
        <div>
            <template x-if="block.data.variant === 'video'">
                <div class="mb-5 space-y-3 rounded-xl border border-nova-200 bg-nova-50/50 p-3">
                    <p x-show="!features['design-pro']" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">Hintergrundvideos gehören zu „Design Pro“. Ohne dieses Modul zeigt die Website die Technik-Variante.</p>
                    <label class="nova-label">Hintergrundvideo</label>
                    <template x-if="block.data.video">
                        <div class="relative overflow-hidden rounded-lg"><video :src="block.data.video" muted autoplay loop playsinline class="aspect-video w-full object-cover"></video>
                            <button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="block.data.video = null">Ändern</button></div>
                    </template>
                    @include('admin.pages.partials.upload', ['target' => 'block.data', 'key' => 'video', 'kind' => 'video', 'hint' => 'MP4, am besten 10 bis 30 Sekunden ohne Ton und unter 15 MB. Läuft stumm in Dauerschleife.'])
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="nova-label">Farbschleier</label>
                            <select class="nova-input" x-model="block.data.overlay"><option value="dark">Dunkel</option><option value="brand">In Ihrer Farbe</option><option value="light">Hell</option></select></div>
                        <div><label class="nova-label">Höhe</label>
                            <select class="nova-input" x-model="block.data.height"><option value="normal">Normal</option><option value="tall">Hoch</option><option value="full">Ganzer Bildschirm</option></select></div>
                        <div class="col-span-2"><label class="nova-label">Deckkraft des Schleiers: <span x-text="(block.data.opacity ?? 55) + ' %'"></span></label>
                            <input type="range" min="0" max="90" step="5" x-model.number="block.data.opacity" class="w-full accent-nova-600"></div>
                        <div class="col-span-2"><label class="nova-label">Text</label>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="(block.data.align || 'left') === 'left' ? 'border-nova-600 bg-white text-nova-800' : 'border-slate-200'" @click="block.data.align = 'left'">Links</button>
                                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="block.data.align === 'center' ? 'border-nova-600 bg-white text-nova-800' : 'border-slate-200'" @click="block.data.align = 'center'">Mittig</button>
                            </div></div>
                    </div>
                </div>
            </template>
            <label class="nova-label" x-text="block.data.variant === 'tech' ? 'Bild rechts (optional)' : (block.data.variant === 'video' ? 'Standbild, solange das Video lädt (optional)' : 'Hintergrundbild')"></label>
            <p x-show="block.data.variant === 'tech' && !block.data.image" class="mb-2 text-xs text-slate-500">Ohne eigenes Bild zeigt Nova eine gezeichnete Ansicht der Verwaltung.</p>
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
        <div>
            <p class="nova-label">Vorschaubild (optional)</p>
            <template x-if="block.data.poster">
                <div class="relative"><img :src="block.data.poster" alt="" class="aspect-video w-full rounded-lg object-cover">
                    <button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="block.data.poster = null">Entfernen</button></div>
            </template>
            @include('admin.pages.partials.upload', ['target' => 'block.data', 'key' => 'poster', 'kind' => 'image', 'hint' => 'Wird vor dem Abspielen gezeigt. Ohne Bild erscheint eine dunkle Fläche.'])
            <button type="button" x-show="block.data.url && !block.data.poster" :disabled="block.data._thumb" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-nova-700 hover:underline disabled:opacity-50" data-video-thumb
                    @click="block.data._thumb = true; fetch(@js(route('admin.media.video-thumb')), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({url: block.data.url})}).then(r => r.json()).then(d => { if (d.url) block.data.poster = d.url; else block.data._thumbMsg = d.message || 'Kein Vorschaubild gefunden.'; }).catch(() => block.data._thumbMsg = 'Keine Verbindung.').finally(() => block.data._thumb = false)">
                <x-icon name="image" class="size-4" /><span x-text="block.data._thumb ? 'Wird geholt …' : 'Vorschaubild vom Video übernehmen'"></span></button>
            <p x-show="block.data._thumbMsg && !block.data.poster" x-text="block.data._thumbMsg" class="mt-1 text-sm text-amber-800" role="status"></p>
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
