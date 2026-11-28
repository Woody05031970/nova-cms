{{-- Einstellungen des gewählten Elements. Im Alpine-Bereich steht es als „w“. --}}
@php
    $svg = fn ($d) => '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="'.$d.'"/></svg>';
    $align = ['' => ['Wie die Spalte', $svg('M4 12h16M12 4v16')], 'left' => ['Links', $svg('M4 6h16M4 12h10M4 18h14')], 'center' => ['Mitte', $svg('M4 6h16M7 12h10M5 18h14')], 'right' => ['Rechts', $svg('M4 6h16M10 12h10M6 18h14')]];
    $sizes = ['s' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', 'xxl' => 'XXL'];
@endphp

{{-- ===== INHALT ===== --}}
<div x-show="tab === 'content'" class="space-y-4">
    <template x-if="w.type === 'heading'">
        <div class="space-y-4">
            <div class="nbp-field"><label class="nbp-label">Text</label><textarea class="nova-input text-sm" rows="2" x-model="w.data.text" maxlength="300"></textarea>
                <p class="mt-1 text-xs text-slate-500">Sie können auch direkt in der Seite rechts tippen.</p></div>
            <x-nb.seg model="w.style.tag" label="Art der Überschrift" :options="['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'Text']" cols="5" />
            <p class="-mt-2 text-xs text-slate-500">H1 nur einmal pro Seite, für Google die wichtigste Überschrift.</p>
            <div class="nbp-field"><label class="nbp-label">Link (optional)</label><input class="nova-input text-sm" x-model="w.data.link" placeholder="/kontakt oder https://…"></div>
        </div>
    </template>

    <template x-if="w.type === 'text'">
        <div x-data="{ block: w }">@include('admin.pages.partials.rich-text', ['toolbar' => 'sticky top-0 z-10 -mx-1'])</div>
    </template>

    <template x-if="w.type === 'image'">
        <div class="space-y-4">
            <x-nb.image target="w.data" key="url" label="Bild" alt="alt" />
            <div class="nbp-field"><label class="nbp-label">Bildbeschreibung</label><input class="nova-input text-sm" x-model="w.data.alt" maxlength="200" placeholder="Was ist zu sehen?">
                <p class="mt-1 text-xs" :class="w.data.alt ? 'text-slate-500' : 'text-amber-700'" x-text="w.data.alt ? 'Hilft Google und blinden Besuchern.' : 'Fehlt noch: wichtig für Google und Barrierefreiheit.'"></p></div>
            <div class="nbp-field"><label class="nbp-label">Bildunterschrift (optional)</label><input class="nova-input text-sm" x-model="w.data.caption" maxlength="300"></div>
            <div class="nbp-field"><label class="nbp-label">Link beim Klick (optional)</label><input class="nova-input text-sm" x-model="w.data.link" placeholder="/angebot"></div>
        </div>
    </template>

    <template x-if="w.type === 'button'">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-2">
                <div class="nbp-field"><label class="nbp-label">Beschriftung</label><input class="nova-input text-sm" x-model="w.data.label" maxlength="60"></div>
                <div class="nbp-field"><label class="nbp-label">Ziel</label><input class="nova-input text-sm" x-model="w.data.url" placeholder="/kontakt"></div>
            </div>
            <div class="rounded-lg border border-dashed border-slate-300 p-3">
                <p class="nbp-label">Zweiter Knopf daneben (optional)</p>
                <div class="grid grid-cols-2 gap-2">
                    <input class="nova-input text-sm" x-model="w.data.label2" maxlength="60" placeholder="z. B. Anrufen" aria-label="Beschriftung zweiter Knopf">
                    <input class="nova-input text-sm" x-model="w.data.url2" placeholder="tel:0221…" aria-label="Ziel zweiter Knopf">
                </div>
            </div>
            <p class="text-xs text-slate-500">Ziele: eigene Seiten wie /kontakt, Adressen mit https://, E-Mail mit mailto: oder Telefon mit tel:</p>
        </div>
    </template>

    <template x-if="w.type === 'icon'">
        <div class="space-y-4">
            <div class="nbp-field"><p class="nbp-label">Symbol</p>
                <div class="grid grid-cols-8 gap-1">
                    @foreach (\App\Support\Blocks\BlockSanitizer::ICONS as $ic)
                        <button type="button" class="grid aspect-square place-items-center rounded-md border" :class="w.data.icon === '{{ $ic }}' ? 'border-nova-600 bg-nova-50 text-nova-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" @click="w.data.icon = '{{ $ic }}'" title="{{ $ic }}"><x-icon :name="$ic" class="size-4" /></button>
                    @endforeach
                </div></div>
            <div class="nbp-field"><label class="nbp-label">Titel</label><input class="nova-input text-sm" x-model="w.data.title" maxlength="120"></div>
            <div class="nbp-field"><label class="nbp-label">Text</label><textarea class="nova-input text-sm" rows="3" x-model="w.data.text" maxlength="600"></textarea></div>
            <div class="nbp-field"><label class="nbp-label">Link (optional)</label><input class="nova-input text-sm" x-model="w.data.link" placeholder="/leistungen"></div>
        </div>
    </template>

    <template x-if="w.type === 'list'">
        <div class="nbp-field"><label class="nbp-label">Punkte, einer pro Zeile</label>
            <textarea class="nova-input text-sm" rows="7" :value="listText(w)" @input="setList(w, $event.target.value)"></textarea></div>
    </template>

    <template x-if="w.type === 'counter'">
        <div class="space-y-4">
            <div class="grid grid-cols-[1fr_2fr_1fr] gap-2">
                <div class="nbp-field"><label class="nbp-label">Davor</label><input class="nova-input text-sm" x-model="w.data.prefix" maxlength="10" placeholder="ab"></div>
                <div class="nbp-field"><label class="nbp-label">Zahl</label><input class="nova-input text-sm" x-model="w.data.number" maxlength="20"></div>
                <div class="nbp-field"><label class="nbp-label">Danach</label><input class="nova-input text-sm" x-model="w.data.suffix" maxlength="10" placeholder="%"></div>
            </div>
            <div class="nbp-field"><label class="nbp-label">Beschriftung</label><input class="nova-input text-sm" x-model="w.data.label" maxlength="120"></div>
        </div>
    </template>

    <template x-if="w.type === 'quote'">
        <div class="space-y-4">
            <div class="nbp-field"><label class="nbp-label">Zitat</label><textarea class="nova-input text-sm" rows="4" x-model="w.data.text" maxlength="1000"></textarea></div>
            <div class="grid grid-cols-2 gap-2">
                <div class="nbp-field"><label class="nbp-label">Name</label><input class="nova-input text-sm" x-model="w.data.name" maxlength="80"></div>
                <div class="nbp-field"><label class="nbp-label">Firma oder Ort</label><input class="nova-input text-sm" x-model="w.data.role" maxlength="120"></div>
            </div>
            <x-nb.image target="w.data" key="image" label="Foto der Person (optional)" />
        </div>
    </template>

    <template x-if="w.type === 'video'">
        <div class="space-y-4">
            <div class="nbp-field"><label class="nbp-label">Link zu YouTube oder Vimeo</label>
                <input class="nova-input text-sm" x-model="w.data.url" placeholder="https://www.youtube.com/watch?v=…" @input="if (w.data.url) w.data.file = null">
                <p class="mt-1 text-xs text-slate-500">Das Video lädt erst nach einem Klick des Besuchers, das ist datenschutzfreundlich.</p></div>
            <div x-show="!w.data.url"><p class="nbp-label">oder eigene Videodatei</p>
                <template x-if="w.data.file"><div class="relative"><video :src="w.data.file" controls class="w-full rounded-lg"></video><button type="button" class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-1 text-xs shadow" @click="w.data.file = null">Entfernen</button></div></template>
                @include('admin.pages.partials.upload', ['target' => 'w.data', 'key' => 'file', 'kind' => 'video', 'hint' => 'MP4 oder WebM.'])</div>
            <x-nb.image target="w.data" key="poster" label="Vorschaubild (optional)" />
            <div class="nbp-field"><label class="nbp-label">Beschreibung (optional)</label><input class="nova-input text-sm" x-model="w.data.caption" maxlength="300"></div>
        </div>
    </template>

    <template x-if="w.type === 'gallery'">
        <div class="space-y-3">
            <div class="grid grid-cols-3 gap-2">
                <template x-for="(img, gi) in w.data.items" :key="img.url + gi">
                    <div class="group relative">
                        <img :src="img.url" alt="" class="aspect-square w-full rounded-md object-cover">
                        <div class="absolute inset-x-1 bottom-1 flex justify-between opacity-0 transition group-hover:opacity-100">
                            <button type="button" class="rounded bg-white/95 px-1.5 text-xs shadow disabled:opacity-40" :disabled="gi === 0" @click="listMove(w.data.items, gi, -1)" aria-label="Nach vorn">←</button>
                            <button type="button" class="rounded bg-white/95 px-1.5 text-xs text-red-700 shadow" @click="listRemove(w.data.items, gi)" aria-label="Entfernen">✕</button>
                            <button type="button" class="rounded bg-white/95 px-1.5 text-xs shadow disabled:opacity-40" :disabled="gi === w.data.items.length - 1" @click="listMove(w.data.items, gi, 1)" aria-label="Nach hinten">→</button>
                        </div>
                        <input class="mt-1 w-full rounded border border-slate-200 px-1.5 py-0.5 text-[11px]" x-model="img.alt" placeholder="Beschreibung" aria-label="Bildbeschreibung">
                    </div>
                </template>
                <label class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-slate-300 text-center text-xs text-slate-600 hover:border-nova-500" @dragover.prevent @drop.prevent="addGallery(w, $event)">
                    <span x-show="!w.data._uploading">+ Bilder</span><span x-show="w.data._uploading" class="text-nova-700">Lädt …</span>
                    <input type="file" multiple accept="image/*" class="sr-only" @change="addGallery(w, $event)">
                </label>
            </div>
            <button type="button" class="text-sm font-medium text-nova-700 hover:underline" @click="pickGallery(w)">Aus der Mediathek wählen</button>
        </div>
    </template>

    <template x-if="w.type === 'spacer'">
        <x-nb.range model="w.style.height" label="Höhe" min="0" max="400" step="4" />
    </template>

    <template x-if="w.type === 'divider'">
        <p class="text-sm text-slate-600">Eine Linie zum Trennen von Abschnitten. Farbe, Stärke und Breite stellen Sie unter „Stil“ ein.</p>
    </template>

    <template x-if="w.type === 'html'">
        <div x-data="codeEditor({ data: w.data })">
            <p class="mb-2 text-xs text-slate-500">Für Fortgeschrittene, z. B. für Einbettungen. Der Code wird unverändert ausgegeben und erst auf der Website ausgeführt.</p>
            <div x-ref="cm" class="overflow-hidden rounded-lg border border-slate-200 text-sm"></div>
        </div>
    </template>
</div>

{{-- ===== STIL ===== --}}
<div x-show="tab === 'style'" class="space-y-4">
    <template x-if="w.type === 'heading'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.size" label="Größe" :options="$sizes" cols="5" />
            <x-nb.seg model="w.style.weight" label="Schriftstärke" :options="['normal' => 'Normal', 'semibold' => 'Kräftig', 'bold' => 'Fett']" cols="3" />
            <x-nb.color model="w.style.color" label="Farbe" />
            <x-nb.toggle model="w.style.gradient" label="Farbverlauf in der Schrift" hint="Haupt- und Akzentfarbe ineinander" />
        </div>
    </template>
    <template x-if="w.type === 'text'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.size" label="Schriftgröße" :options="['s' => 'Klein', 'm' => 'Normal', 'l' => 'Groß']" cols="3" />
            <x-nb.color model="w.style.color" label="Textfarbe" />
        </div>
    </template>
    <template x-if="w.type === 'image'">
        <div class="space-y-4">
            <x-nb.range model="w.style.width" label="Breite" min="10" max="100" step="5" unit=" %" />
            <x-nb.seg model="w.style.ratio" label="Format" :options="['auto' => 'Original', '1:1' => '1:1', '4:3' => '4:3', '3:2' => '3:2', '16:9' => '16:9', '3:4' => 'Hoch']" cols="3" />
            <x-nb.seg model="w.style.radius" label="Ecken" :options="['none' => 'Eckig', 's' => 'Leicht', 'm' => 'Rund', 'l' => 'Sehr rund', 'full' => 'Kreis']" cols="5" />
            <x-nb.toggle model="w.style.shadow" label="Schatten" />
        </div>
    </template>
    <template x-if="w.type === 'button'">
        <div class="space-y-4">
            @php $btnStyles = ['primary' => 'Hauptfarbe', 'accent' => 'Akzent', 'outline' => 'Umriss', 'light' => 'Weiß', 'dark' => 'Dunkel']; @endphp
            <x-nb.seg model="w.style.variant" label="Aussehen" :options="$btnStyles" cols="3" />
            <template x-if="w.data.label2"><x-nb.seg model="w.style.variant2" label="Aussehen zweiter Knopf" :options="$btnStyles" cols="3" /></template>
            <x-nb.seg model="w.style.size" label="Größe" :options="['s' => 'Klein', 'm' => 'Normal', 'l' => 'Groß']" cols="3" />
            <x-nb.toggle model="w.style.full" label="Ganze Breite" />
        </div>
    </template>
    <template x-if="w.type === 'icon'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.layout" label="Anordnung" :options="['top' => 'Symbol oben', 'left' => 'Symbol links']" cols="2" />
            <x-nb.seg model="w.style.shape" label="Form" :options="['plain' => 'Nur Symbol', 'circle' => 'Kreis', 'square' => 'Quadrat']" cols="3" />
            <x-nb.seg model="w.style.size" label="Größe" :options="['s' => 'Klein', 'm' => 'Normal', 'l' => 'Groß']" cols="3" />
            <x-nb.color model="w.style.color" label="Farbe des Symbols" />
        </div>
    </template>
    <template x-if="w.type === 'list'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.icon" label="Zeichen" :options="['check' => '✓ Haken', 'arrow' => '→ Pfeil', 'dot' => '• Punkt', 'star' => '★ Stern']" cols="2" />
            <x-nb.seg model="w.style.size" label="Schriftgröße" :options="['s' => 'Klein', 'm' => 'Normal', 'l' => 'Groß']" cols="3" />
            <x-nb.color model="w.style.color" label="Farbe der Zeichen" />
        </div>
    </template>
    <template x-if="w.type === 'counter'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.size" label="Größe" :options="['m' => 'Normal', 'l' => 'Groß', 'xl' => 'Riesig']" cols="3" />
            <x-nb.color model="w.style.color" label="Farbe der Zahl" />
        </div>
    </template>
    <template x-if="w.type === 'quote'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.variant" label="Aussehen" :options="['card' => 'Karte', 'plain' => 'Schlicht']" cols="2" />
            <x-nb.toggle model="w.style.stars" label="Fünf Sterne zeigen" />
        </div>
    </template>
    <template x-if="w.type === 'gallery'">
        <div class="space-y-4">
            <x-nb.range model="w.style.cols" label="Bilder pro Reihe" min="2" max="6" unit="" />
            <x-nb.seg model="w.style.ratio" label="Format" :options="['1:1' => '1:1', '4:3' => '4:3', '3:2' => '3:2', '16:9' => '16:9', '3:4' => 'Hoch']" cols="5" />
            <x-nb.seg model="w.style.gap" label="Abstand" :options="['none' => 'Ohne', 's' => 'Klein', 'm' => 'Mittel']" cols="3" />
            <x-nb.seg model="w.style.radius" label="Ecken" :options="['none' => 'Eckig', 's' => 'Leicht', 'm' => 'Rund', 'l' => 'Sehr rund']" cols="4" />
        </div>
    </template>
    <template x-if="w.type === 'divider'">
        <div class="space-y-4">
            <x-nb.seg model="w.style.line" label="Linie" :options="['solid' => 'Durchgehend', 'dashed' => 'Gestrichelt', 'dotted' => 'Gepunktet']" cols="3" />
            <x-nb.range model="w.style.width" label="Breite" min="10" max="100" step="5" unit=" %" />
            <x-nb.range model="w.style.weight" label="Stärke" min="1" max="10" />
            <x-nb.color model="w.style.color" label="Farbe" none="Hellgrau" />
        </div>
    </template>
    <template x-if="['spacer', 'video', 'html'].includes(w.type)">
        <p class="text-sm text-slate-500">Für dieses Element gibt es keine weiteren Stil-Einstellungen.</p>
    </template>
    <template x-if="!['spacer', 'divider', 'html'].includes(w.type)">
        <x-nb.seg model="w.style.align" label="Ausrichtung" :options="$align" cols="4" />
    </template>
</div>

{{-- ===== ERWEITERT ===== --}}
<div x-show="tab === 'advanced'" class="space-y-4">
    <x-nb.range model="w.style.mb" label="Abstand darunter" min="0" max="160" step="4" />
    <x-nb.devices model="w.style.hide" />
</div>
