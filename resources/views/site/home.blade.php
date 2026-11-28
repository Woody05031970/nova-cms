<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }}</title>
    @if ($tagline)<meta name="description" content="{{ $tagline }}">@endif
    {!! \App\Support\Design\Favicon::tags() !!}
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-b from-nova-50 to-white px-4 font-sans text-slate-900 antialiased">
    <main class="max-w-xl text-center">
        <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">{{ $siteName }}</h1>
        @if ($tagline)<p class="mt-4 text-lg text-slate-600">{{ $tagline }}</p>@endif
        <p class="mt-10 text-sm text-slate-500">Hier entsteht eine neue Website.</p>
    </main>
</body>
</html>
