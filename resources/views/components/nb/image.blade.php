@props(['target', 'key', 'label' => 'Bild', 'alt' => null, 'hint' => 'JPG, PNG oder WebP, bis 10 MB. Große Fotos werden automatisch verkleinert.'])
<div class="nbp-field">
    <p class="nbp-label">{{ $label }}</p>
    <template x-if="{{ $target }}.{{ $key }}">
        <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
            <img :src="{{ $target }}.{{ $key }}" alt="" class="max-h-44 w-full object-cover">
            <div class="absolute top-2 right-2 flex gap-1">
                <button type="button" class="rounded-md bg-white/95 px-2 py-1 text-xs font-medium shadow" @click="pickInto({{ $target }}, '{{ $key }}', 'image', {{ $alt ? "'".$alt."'" : 'null' }})">Ändern</button>
                <button type="button" class="rounded-md bg-white/95 px-2 py-1 text-xs shadow" @click="{{ $target }}.{{ $key }} = null" aria-label="Bild entfernen">✕</button>
            </div>
        </div>
    </template>
    @include('admin.pages.partials.upload', ['target' => $target, 'key' => $key, 'kind' => 'image', 'hint' => $hint] + ($alt ? ['alt' => $alt] : []))
</div>
