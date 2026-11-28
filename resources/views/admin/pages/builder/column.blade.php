{{-- Einstellungen der gewählten Spalte („c“) --}}
<div class="space-y-4">
    <button type="button" class="nova-btn w-full justify-center" @click="panel = 'add'; addTab = 'widgets'; addTarget = { col: c.id }"><x-icon name="plus" class="size-4" /> Element in diese Spalte</button>
    <x-nb.seg model="c.style.valign" label="Inhalt senkrecht" :options="['top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten']" cols="3" />
    <x-nb.seg model="c.style.align" label="Ausrichtung" :options="['' => 'Wie Bereich', 'left' => 'Links', 'center' => 'Mitte', 'right' => 'Rechts']" cols="4" />
    <x-nb.color model="c.style.bg" label="Hintergrund" none="Ohne" />
    <x-nb.range model="c.style.pad" label="Innenabstand" min="0" max="80" step="4" />
    <x-nb.range model="c.style.radius" label="Ecken abrunden" min="0" max="40" step="2" />
    <x-nb.toggle model="c.style.shadow" label="Schatten" />
    <x-nb.toggle model="c.style.border" label="Rahmen" />
</div>
