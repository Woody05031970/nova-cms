@props(['model', 'label', 'hint' => null])
<label class="nbp-toggle">
    <span>{{ $label }}@if($hint)<small>{{ $hint }}</small>@endif</span>
    <input type="checkbox" x-model="{{ $model }}" class="peer sr-only">
    <span class="nbp-switch" aria-hidden="true"></span>
</label>
