@props(['model', 'label', 'none' => 'Automatisch'])
@php
    $d = \App\Support\Design\SiteDesign::values();
    $pal = \App\Support\Design\SiteDesign::palette($d['brand']);
    $sw = ['brand' => $d['brand'], 'brand-dark' => $pal[900], 'brand-light' => $pal[50], 'accent' => $d['accent'], 'dark' => '#0f172a', 'gray' => '#64748b', 'light' => '#f1f5f9', 'white' => '#ffffff'];
@endphp
<div class="nbp-field">
    <p class="nbp-label">{{ $label }}</p>
    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ $label }}">
        <button type="button" class="nbp-sw nbp-sw-none" :class="!{{ $model }} && 'is-on'" @click="{{ $model }} = null" title="{{ $none }}" aria-label="{{ $none }}"></button>
        @foreach (\App\Support\Blocks\Builder::COLORS as $k => [$name])
            <button type="button" class="nbp-sw" style="background:{{ $sw[$k] }}" :class="{{ $model }} === '{{ $k }}' && 'is-on'" @click="{{ $model }} = '{{ $k }}'" title="{{ $name }}" aria-label="{{ $name }}"></button>
        @endforeach
        <label class="nbp-sw nbp-sw-custom" title="Eigene Farbe wählen" :class="({{ $model }} || '').startsWith('#') && 'is-on'" :style="({{ $model }} || '').startsWith('#') ? 'background:' + {{ $model }} : ''">
            <input type="color" class="sr-only" :value="({{ $model }} || '').startsWith('#') ? {{ $model }} : '#2563eb'" @input="{{ $model }} = $event.target.value" aria-label="Eigene Farbe">
        </label>
    </div>
</div>
