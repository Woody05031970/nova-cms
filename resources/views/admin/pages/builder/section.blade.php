{{-- Einstellungen des gewählten Bereichs („s“) --}}
@php
    $svg = fn ($d) => '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="'.$d.'"/></svg>';
@endphp
<div x-show="tab === 'content'" class="space-y-4">
    <div class="nbp-field">
        <p class="nbp-label">Spalten</p>
        <div class="grid grid-cols-4 gap-1.5">
            <template x-for="[key, parts] in layouts" :key="key">
                <button type="button" class="nbp-layout" :class="s.data.layout === key && 'is-on'" @click="setLayout(s, key)" :title="parts.length + (parts.length === 1 ? ' Spalte' : ' Spalten')" :data-layout="key">
                    <template x-for="(f, fi) in parts" :key="fi"><i :style="'flex:' + f"></i></template>
                </button>
            </template>
        </div>
        <p class="mt-1 text-xs text-slate-500">Auf dem Handy stehen die Spalten automatisch untereinander.</p>
    </div>
    <x-nb.seg model="s.data.style.width" label="Breite des Inhalts" :options="['narrow' => 'Schmal', 'boxed' => 'Normal', 'full' => 'Ganze Breite']" cols="3" />
    <x-nb.seg model="s.data.style.height" label="Mindesthöhe" :options="['auto' => 'Passend', 's' => 'Halb', 'm' => 'Hoch', 'screen' => 'Bildschirm']" cols="4" />
    <x-nb.seg model="s.data.style.valign" label="Inhalt senkrecht" :options="['top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten']" cols="3" />
    <x-nb.seg model="s.data.style.gap" label="Abstand zwischen Spalten" :options="['none' => 'Ohne', 's' => 'Klein', 'm' => 'Mittel', 'l' => 'Groß']" cols="4" />
</div>

<div x-show="tab === 'style'" class="space-y-4">
    <x-nb.seg model="s.data.style.bg" label="Hintergrund" :options="['none' => 'Ohne', 'color' => 'Farbe', 'gradient' => 'Verlauf', 'image' => 'Bild']" cols="4" />
    <template x-if="s.data.style.bg === 'color'"><x-nb.color model="s.data.style.bg_color" label="Hintergrundfarbe" none="Keine" /></template>
    <template x-if="s.data.style.bg === 'gradient'">
        <div class="space-y-4">
            <x-nb.color model="s.data.style.bg_color" label="Von" none="Hauptfarbe" />
            <x-nb.color model="s.data.style.bg_color2" label="Nach" none="Akzentfarbe" />
            <x-nb.range model="s.data.style.bg_angle" label="Richtung" min="0" max="360" step="15" unit="°" />
        </div>
    </template>
    <template x-if="s.data.style.bg === 'image'">
        <div class="space-y-4">
            <x-nb.image target="s.data.style" key="bg_image" label="Hintergrundbild" hint="Querformat, am besten mindestens 1920 Pixel breit." />
            <x-nb.seg model="s.data.style.bg_pos" label="Ausschnitt" :options="['top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten']" cols="3" />
            <x-nb.color model="s.data.style.overlay" label="Farbschleier über dem Bild" none="Kein Schleier" />
            <template x-if="s.data.style.overlay"><x-nb.range model="s.data.style.overlay_opacity" label="Stärke des Schleiers" min="0" max="90" step="5" unit=" %" /></template>
            <x-nb.toggle model="s.data.style.bg_fixed" label="Bild bleibt beim Scrollen stehen" hint="Parallax-Effekt am Computer" />
        </div>
    </template>
    <x-nb.seg model="s.data.style.text" label="Schriftfarbe" :options="['auto' => 'Automatisch', 'dark' => 'Dunkel', 'light' => 'Hell']" cols="3" />
    <x-nb.range model="s.data.style.pt" label="Abstand oben" min="0" max="240" step="8" />
    <x-nb.range model="s.data.style.pb" label="Abstand unten" min="0" max="240" step="8" />
    <x-nb.toggle model="s.data.style.card" label="Als Karte" hint="Hintergrund nur hinter dem Inhalt, mit runden Ecken" />
</div>

<div x-show="tab === 'advanced'" class="space-y-4">
    <div class="nbp-field"><label class="nbp-label">Sprungmarke (optional)</label>
        <div class="flex items-center rounded-lg border border-slate-300 bg-white text-sm"><span class="pl-3 text-slate-400">#</span>
            <input class="w-full border-0 bg-transparent py-2 pr-3 pl-0.5 focus:outline-none" x-model="s.data.style.anchor" @input="s.data.style.anchor = $event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').slice(0, 40)" placeholder="z. B. preise"></div>
        <p class="mt-1 text-xs text-slate-500">Damit führt ein Knopf mit dem Ziel <code>#preise</code> direkt zu diesem Bereich.</p></div>
    <x-nb.toggle model="s.data.style.reverse" label="Auf dem Handy umgekehrt" hint="Die rechte Spalte steht dann oben, z. B. das Bild vor dem Text." />
    <x-nb.devices model="s.data.style.hide" />
    <button type="button" class="nova-btn-secondary w-full justify-center" @click="saveTemplate(s)" data-save-template><x-icon name="template" class="size-4" /> Als eigene Vorlage speichern</button>
</div>
