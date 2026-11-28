@props(['model'])
<div class="nbp-field">
    <p class="nbp-label">Ausblenden auf</p>
    <div class="nbp-seg" style="grid-template-columns:repeat(3,minmax(0,1fr))" role="group" aria-label="Ausblenden auf">
        @foreach (['desktop' => ['Computer', 'monitor'], 'tablet' => ['Tablet', 'tablet'], 'phone' => ['Handy', 'phone']] as $dev => [$name, $icon])
            <button type="button" :class="{{ $model }}.includes('{{ $dev }}') && 'is-off'" @click="toggleIn({{ $model }}, '{{ $dev }}')" :aria-pressed="{{ $model }}.includes('{{ $dev }}')" title="Auf {{ $name }} ausblenden">
                <x-icon :name="$icon" class="size-4" /> <span class="text-xs">{{ $name }}</span>
            </button>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500" x-show="{{ $model }}.length">Durchgestrichen = dort unsichtbar. In der Vorschau des Geräts sehen Sie das Element blass.</p>
</div>
