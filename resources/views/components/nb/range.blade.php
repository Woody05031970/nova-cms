@props(['model', 'label', 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => ' px'])
<div class="nbp-field">
    <div class="flex items-center justify-between"><p class="nbp-label">{{ $label }}</p><span class="text-xs text-slate-500 tabular-nums" x-text="({{ $model }} ?? 0) + '{{ $unit }}'"></span></div>
    <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" x-model.number="{{ $model }}" class="w-full accent-nova-600" aria-label="{{ $label }}">
</div>
