@if (! empty($d['label']) && ! empty($d['url']))
    @php $external = str_starts_with($d['url'], 'http'); @endphp
    @php $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$d['align'] ?? 'left'] ?? 'text-left'; @endphp
    <div class="mx-auto mt-8 max-w-3xl px-4 sm:px-6 {{ $align }}">
        <a href="{{ $d['url'] }}" @if($external) target="_blank" rel="noopener" @endif
           @class(['inline-flex rounded-lg px-5 py-3 font-semibold',
                   'bg-nova-600 text-white shadow-sm hover:bg-nova-700' => ($d['style'] ?? '') !== 'secondary',
                   'border border-slate-300 text-slate-800 hover:bg-slate-50' => ($d['style'] ?? '') === 'secondary'])>{{ $d['label'] }}</a>
    </div>
@endif
