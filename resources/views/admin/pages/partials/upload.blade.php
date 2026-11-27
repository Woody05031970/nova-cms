{{-- Erwartet: $target (JS-Ausdruck des Objekts), $key (Feldname), $kind (image|video), $hint --}}
<div x-show="!{{ $target }}.{{ $key }}" class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 p-6 text-center"
     @dragover.prevent @drop.prevent="uploadTo({{ $target }}, '{{ $key }}', $event, '{{ $kind }}')">
    <x-icon name="upload" class="size-7 text-slate-400" />
    <p class="text-sm text-slate-600" x-show="!{{ $target }}._uploading">Datei hierher ziehen oder</p>
    <p class="text-sm text-nova-700" x-show="{{ $target }}._uploading">Wird hochgeladen …</p>
    <label class="nova-btn-secondary cursor-pointer px-3 py-1.5" x-show="!{{ $target }}._uploading">
        Datei auswählen
        <input type="file" class="sr-only" accept="{{ $kind === 'video' ? 'video/mp4,video/webm' : 'image/*' }}" @change="uploadTo({{ $target }}, '{{ $key }}', $event, '{{ $kind }}')">
    </label>
    <p class="text-xs text-slate-400">{{ $hint }}</p>
</div>
