@php
    $on = \App\Support\Admin\AdminNav::isActive($item);
    $badge = \App\Support\Admin\AdminNav::badge($item);
@endphp
@if (! empty($item['soon']))
    <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600"><x-icon :name="$item['icon'] ?? 'puzzle'" class="size-[18px]" /> {{ $item['label'] }}</span>
@else
    <a href="{{ $item['url'] }}" @class([
        'nova-nav-link group relative flex items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
        'py-1.5' => ! empty($small),
        'py-2' => empty($small),
        'is-active bg-white/10 text-white' => $on,
        'text-slate-400 hover:bg-white/5 hover:text-white' => ! $on,
    ]) @if($on) aria-current="page" @endif>
        @if (empty($small))
            <x-icon :name="$item['icon'] ?? 'puzzle'" class="size-[18px] {{ $on ? 'text-nova-300' : 'text-slate-500 group-hover:text-slate-300' }}" />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
        @if (! empty($item['locked']))<x-icon name="lock" class="size-3.5 text-slate-500" /><span class="sr-only">(nicht in Ihrer Lizenz enthalten)</span>@endif
        @if ($badge)<span class="rounded-full bg-nova-500 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-white">{{ $badge }}</span>@endif
    </a>
@endif
