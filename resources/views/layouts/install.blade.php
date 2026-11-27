<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Nova CMS einrichten</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:py-16">
        <div class="mb-8 flex items-center justify-between">
            <x-logo class="text-lg" />
            <span class="text-sm text-slate-500">Einrichtung</span>
        </div>

        @php $steps = ['Systemprüfung', 'Datenbank', 'Ihre Website', 'Fertig']; @endphp
        <ol class="mb-8 grid grid-cols-4 gap-2" aria-label="Fortschritt">
            @foreach ($steps as $i => $label)
                @php $n = $i + 1; @endphp
                <li class="flex flex-col gap-2">
                    <span class="h-1.5 rounded-full {{ $n <= $step ? 'bg-nova-600' : 'bg-slate-200' }}"></span>
                    <span class="text-xs {{ $n === $step ? 'font-semibold text-nova-700' : 'text-slate-500' }}" @if($n === $step) aria-current="step" @endif>{{ $n }}. {{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <main class="nova-card sm:p-8">
            <x-alerts />
            @yield('content')
        </main>

        <p class="mt-6 text-center text-xs text-slate-400">Nova CMS {{ config('nova.version') }}</p>
    </div>
</body>
</html>
