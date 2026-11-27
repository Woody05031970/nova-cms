<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Anmelden · Nova CMS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4 font-sans text-slate-900 antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-6 text-center"><x-logo class="text-lg" /></div>
        <div class="nova-card">
            <h1 class="text-xl font-semibold">Anmelden</h1>
            <p class="mt-1 text-sm text-slate-600">Willkommen zurück in der Verwaltung Ihrer Website.</p>
            <div class="mt-5"><x-alerts /></div>
            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="nova-label" for="email">E-Mail-Adresse</label>
                    <input class="nova-input" id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}">
                </div>
                <div>
                    <label class="nova-label" for="password">Passwort</label>
                    <input class="nova-input" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remember" class="accent-nova-600"> Angemeldet bleiben
                </label>
                <button class="nova-btn w-full">Anmelden</button>
            </form>
        </div>
        <p class="mt-6 text-center text-sm"><a class="text-slate-500 hover:text-slate-800" href="{{ route('home') }}">Zur Website</a></p>
    </main>
</body>
</html>
