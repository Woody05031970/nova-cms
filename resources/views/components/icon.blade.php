@props(['name', 'class' => 'size-5'])
@php
$paths = \App\Support\Icons::PATHS;
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['puzzle'] }}"/></svg>
