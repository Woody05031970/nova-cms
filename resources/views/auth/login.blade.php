<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Anmelden · Nova CMS</title>
    @php $three = \App\Licensing\Features::on('effekte-3d'); @endphp
    @vite(array_merge(['resources/css/app.css'], $three ? ['resources/js/login3d.js'] : []))
</head>
<body @class(['nova-login grid min-h-screen place-items-center px-4 font-sans text-slate-900 antialiased', 'is-3d bg-slate-950' => $three, 'bg-slate-50' => ! $three])>
    @if ($three)
        <div class="nova-login-3d" data-nova-3d="formen" data-c1="var(--color-nova-500)" data-c2="var(--color-fuchsia-500)" aria-hidden="true"></div>
    @endif
    <main class="relative z-10 w-full max-w-sm">
        <div @class(['mb-6 text-center', 'text-white' => $three])><x-logo class="text-lg" /></div>
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
        <p class="mt-6 text-center text-sm"><a @class(['hover:text-slate-800', 'text-slate-500' => ! $three, 'text-slate-300 hover:text-white' => $three]) href="{{ route('home') }}">Zur Website</a></p>
    </main>
</body>
</html>
