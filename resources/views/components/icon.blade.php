@props(['name', 'class' => 'size-5'])
@php
$paths = [
    'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    'puzzle' => 'M14 4a2 2 0 1 0-4 0H5v5a2 2 0 1 1 0 4v6h5a2 2 0 1 1 4 0h5v-6a2 2 0 1 0 0-4V4z',
    'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 2h-4l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h4l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z',
    'pages' => 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6',
    'image' => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15 9.5a1 1 0 1 0 0-.01',
    'cart' => 'M3 4h2l2.4 11h11l2-8H6.2M9 20a1 1 0 1 0 0-.01M18 20a1 1 0 1 0 0-.01',
    'shield' => 'M12 3 5 6v6c0 4.4 3 7.6 7 9 4-1.4 7-4.6 7-9V6z',
    'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5',
    'sparkles' => 'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z',
    'check' => 'M5 12.5 10 17 19 7',
    'alert' => 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    'upload' => 'M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3',
    'logout' => 'M15 12H4m0 0 4-4m-4 4 4 4M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5',
    'external' => 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
    'arrow' => 'M5 12h14m-6-6 6 6-6 6',
    'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
    'lock' => 'M6 11h12v10H6zM8 11V7a4 4 0 0 1 8 0v4',
    'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
];
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['puzzle'] }}"/></svg>
