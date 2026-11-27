@if (! empty($d['url']))
    @php $w = ['normal' => 'max-w-3xl px-4 sm:px-6', 'wide' => 'max-w-6xl px-4 sm:px-6', 'full' => 'max-w-none'][$d['size'] ?? 'normal'] ?? 'max-w-3xl px-4'; @endphp
    <figure class="mx-auto mt-8 {{ $w }}">
        <img src="{{ $d['url'] }}" alt="{{ $d['alt'] ?? '' }}" loading="lazy" class="w-full {{ ($d['size'] ?? '') === 'full' ? '' : 'rounded-xl' }}">
        @if (! empty($d['caption']))<figcaption class="mt-2 text-center text-sm text-slate-500">{{ $d['caption'] }}</figcaption>@endif
    </figure>
@endif
