@props(['model', 'label' => null, 'options', 'cols' => null])
<div class="nbp-field">
    @if ($label)<p class="nbp-label">{{ $label }}</p>@endif
    <div class="nbp-seg" role="group" @if($label) aria-label="{{ $label }}" @endif @if($cols) style="grid-template-columns:repeat({{ $cols }},minmax(0,1fr))" @endif>
        @foreach ($options as $value => $opt)
            @php [$text, $icon] = is_array($opt) ? $opt : [$opt, null]; @endphp
            <button type="button" :class="{{ $model }} === @js($value) && 'is-on'" @click="{{ $model }} = @js($value)" title="{{ $text }}" :aria-pressed="{{ $model }} === @js($value)">
                @if ($icon){!! $icon !!}<span class="sr-only">{{ $text }}</span>@else{{ $text }}@endif
            </button>
        @endforeach
    </div>
</div>
