{{-- TEXT --}}
<template x-if="block.type === 'text'">
    <div x-data="richText(block)" class="nova-rte">
        @php
            $tb = 'grid h-8 min-w-8 place-items-center rounded-md px-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30';
            $on = 'bg-nova-50 text-nova-700';
            $sep = '<span class="mx-0.5 h-5 w-px self-center bg-slate-200"></span>';
            $svg = fn ($d, $extra = '') => '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
        @endphp
        <div class="sticky top-[113px] z-10 -mx-4 -mt-4 mb-3 flex flex-wrap items-center gap-0.5 rounded-t-xl border-b border-slate-100 bg-white/95 px-2 py-1.5 backdrop-blur" role="toolbar" aria-label="Textformat">
            <template x-if="mode === 'visual'">
                <div class="contents">
                    {{-- Absatzformat --}}
                    <div class="relative" @click.outside="styleOpen = false">
                        <button type="button" class="flex h-8 w-32 items-center justify-between rounded-md border border-slate-200 px-2 text-sm hover:bg-slate-50" @click="styleOpen = !styleOpen" :aria-expanded="styleOpen" title="Absatzformat">
                            <span x-text="styleLabel"></span><svg class="size-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.5a.75.75 0 0 1 1.06 0L10 11.2l3.74-3.7a.75.75 0 1 1 1.06 1.06l-4.27 4.24a.75.75 0 0 1-1.06 0L5.2 8.56a.75.75 0 0 1 0-1.06Z"/></svg>
                        </button>
                        <div x-show="styleOpen" x-transition.origin.top.left class="absolute left-0 z-30 mt-1 w-52 rounded-lg border border-slate-200 bg-white p-1 shadow-lg" role="menu">
                            <button type="button" role="menuitem" class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-slate-50" @mousedown.prevent @click="setStyle(null)">Absatz</button>
                            <button type="button" role="menuitem" class="block w-full rounded px-3 py-1.5 text-left text-xl font-semibold hover:bg-slate-50" @mousedown.prevent @click="setStyle(2)">Überschrift 1</button>
                            <button type="button" role="menuitem" class="block w-full rounded px-3 py-1.5 text-left text-lg font-semibold hover:bg-slate-50" @mousedown.prevent @click="setStyle(3)">Überschrift 2</button>
                            <button type="button" role="menuitem" class="block w-full rounded px-3 py-1.5 text-left font-semibold hover:bg-slate-50" @mousedown.prevent @click="setStyle(4)">Überschrift 3</button>
                        </div>
                    </div>
                    {!! $sep !!}
                    <button type="button" class="{{ $tb }} font-bold" :class="active('bold') && '{{ $on }}'" @click="run('toggleBold')" title="Fett (Strg+B)">B</button>
                    <button type="button" class="{{ $tb }} italic font-serif" :class="active('italic') && '{{ $on }}'" @click="run('toggleItalic')" title="Kursiv (Strg+I)">I</button>
                    <button type="button" class="{{ $tb }} underline" :class="active('underline') && '{{ $on }}'" @click="run('toggleUnderline')" title="Unterstrichen (Strg+U)">U</button>
                    <button type="button" class="{{ $tb }} line-through" :class="active('strike') && '{{ $on }}'" @click="run('toggleStrike')" title="Durchgestrichen">S</button>
                    <button type="button" class="{{ $tb }}" :class="active('highlight') && '{{ $on }}'" @click="run('toggleHighlight')" title="Textmarker">{!! $svg('<path d="m9 11-6 6v3h9l3-3M22 12l-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/>') !!}</button>
                    {!! $sep !!}
                    <button type="button" class="{{ $tb }}" :class="aligned('left') && '{{ $on }}'" @click="run('setTextAlign', 'left')" title="Linksbündig">{!! $svg('<path d="M4 6h16M4 12h10M4 18h14"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" :class="aligned('center') && '{{ $on }}'" @click="run('setTextAlign', 'center')" title="Zentriert">{!! $svg('<path d="M4 6h16M7 12h10M5 18h14"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" :class="aligned('right') && '{{ $on }}'" @click="run('setTextAlign', 'right')" title="Rechtsbündig">{!! $svg('<path d="M4 6h16M10 12h10M6 18h14"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" :class="aligned('justify') && '{{ $on }}'" @click="run('setTextAlign', 'justify')" title="Blocksatz">{!! $svg('<path d="M4 6h16M4 12h16M4 18h16"/>') !!}</button>
                    {!! $sep !!}
                    <button type="button" class="{{ $tb }}" :class="active('bulletList') && '{{ $on }}'" @click="run('toggleBulletList')" title="Aufzählung">{!! $svg('<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" :class="active('orderedList') && '{{ $on }}'" @click="run('toggleOrderedList')" title="Nummerierte Liste">{!! $svg('<path d="M10 6h10M10 12h10M10 18h10M4 5h1v4M4 9h2M4 15h2l-2 3h2"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" :class="active('blockquote') && '{{ $on }}'" @click="run('toggleBlockquote')" title="Zitat">{!! $svg('<path d="M7 7h4v5H6v-1a4 4 0 0 1 4-4M15 7h4v5h-5v-1a4 4 0 0 1 4-4"/>') !!}</button>
                    {!! $sep !!}
                    <button type="button" class="{{ $tb }}" :class="active('link') && '{{ $on }}'" @click="link()" title="Link einfügen">{!! $svg('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" @click="image()" title="Bild aus der Mediathek einfügen">{!! $svg('<path d="M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4"/>') !!}</button>
                    <div class="relative" @click.outside="tableOpen = false">
                        <button type="button" class="{{ $tb }}" :class="inTable() && '{{ $on }}'" @click="tableOpen = !tableOpen" title="Tabelle" :aria-expanded="tableOpen">{!! $svg('<path d="M4 5h16v14H4zM4 10h16M4 15h16M10 5v14"/>') !!}</button>
                        <div x-show="tableOpen" x-transition.origin.top.left class="absolute left-0 z-30 mt-1 w-56 rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-lg" role="menu">
                            <button type="button" class="block w-full rounded px-3 py-1.5 text-left hover:bg-slate-50" @mousedown.prevent @click="table('insert')">Tabelle einfügen (3 × 3)</button>
                            <template x-if="inTable()">
                                <div class="mt-1 border-t border-slate-100 pt-1">
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left hover:bg-slate-50" @mousedown.prevent @click="table('addRowAfter')">Zeile darunter</button>
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left hover:bg-slate-50" @mousedown.prevent @click="table('addColumnAfter')">Spalte rechts</button>
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left hover:bg-slate-50" @mousedown.prevent @click="table('deleteRow')">Zeile löschen</button>
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left hover:bg-slate-50" @mousedown.prevent @click="table('deleteColumn')">Spalte löschen</button>
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left text-red-700 hover:bg-red-50" @mousedown.prevent @click="table('deleteTable')">Tabelle löschen</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <button type="button" class="{{ $tb }}" @click="run('setHorizontalRule')" title="Trennlinie">{!! $svg('<path d="M4 12h16"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" @click="clearFormat()" title="Formatierung entfernen">{!! $svg('<path d="M4 7V5h12v2M10 5l-3 14M14 14l6 6M20 14l-6 6"/>') !!}</button>
                    {!! $sep !!}
                    <button type="button" class="{{ $tb }}" @click="run('undo')" :disabled="!can('undo')" title="Rückgängig (Strg+Z)">{!! $svg('<path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-2"/>') !!}</button>
                    <button type="button" class="{{ $tb }}" @click="run('redo')" :disabled="!can('redo')" title="Wiederholen">{!! $svg('<path d="m15 14 5-5-5-5M20 9H10a6 6 0 0 0 0 12h2"/>') !!}</button>
                </div>
            </template>
            <template x-if="mode === 'source'">
                <p class="px-2 text-xs text-slate-500">HTML-Quelltext. Erlaubt sind Textformate, Links, Bilder und Tabellen; für Skripte und eigene Einbettungen gibt es den Block „HTML“.</p>
            </template>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 font-mono text-xs font-semibold" :class="mode === 'source' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="toggleSource()" :aria-pressed="mode === 'source'" title="Zwischen Editor und HTML-Quelltext wechseln" data-html-toggle>
                    {!! $svg('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>') !!}<span x-text="mode === 'source' ? 'Editor' : 'HTML'"></span>
                </button>
                <div class="relative" @click.outside="aiOpen = false" @keydown.escape="aiOpen = false">
                    <template x-if="ai.enabled">
                        <button type="button" class="inline-flex h-8 items-center gap-1 rounded-md bg-gradient-to-r from-nova-600 to-fuchsia-500 px-2.5 text-sm font-medium text-white shadow-sm hover:opacity-90 disabled:opacity-60"
                                @click="aiOpen = !aiOpen" :disabled="aiBusy" :aria-expanded="aiOpen" title="Text mit KI bearbeiten. Markieren Sie vorher etwas, wird nur die Markierung bearbeitet.">
                            <x-icon name="sparkles" class="size-4" ::class="aiBusy && 'animate-pulse'" /><span x-text="aiBusy ? 'KI arbeitet …' : 'KI'"></span>
                        </button>
                    </template>
                    <template x-if="!ai.enabled">
                        <a :href="ai.setupUrl" class="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-sm text-slate-500 hover:bg-slate-100" title="KI-Anbieter einrichten, um Texte verbessern zu lassen"><x-icon name="sparkles" class="size-4" />KI</a>
                    </template>
                    <div x-show="aiOpen" x-transition.origin.top.right class="absolute right-0 z-30 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="menu">
                        <p class="px-3 pt-1 pb-2 text-xs text-slate-500">Gilt für die Markierung oder den ganzen Block.</p>
                        <template x-for="(label, key) in ai.actions" :key="key">
                            <button type="button" role="menuitem" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-nova-50 hover:text-nova-700" @mousedown.prevent @click="aiRun(key)" x-text="label"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div x-ref="ed" x-show="mode === 'visual'"></div>
        <div x-ref="src" x-show="mode === 'source'" class="nova-source overflow-hidden rounded-lg border border-slate-200"></div>
    </div>
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
