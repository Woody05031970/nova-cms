@php $size = $size ?? 'size-10'; @endphp
@if ($p?->avatar)
    <img src="{{ $p->avatar }}" alt="" class="{{ $size }} shrink-0 rounded-full object-cover ring-1 ring-black/5" loading="lazy">
@else
    <span class="{{ $size }} grid shrink-0 place-items-center rounded-full bg-gradient-to-br from-nova-500 to-fuchsia-500 text-xs font-semibold text-white" aria-hidden="true">{{ $p?->initials() ?? '?' }}</span>
@endif
