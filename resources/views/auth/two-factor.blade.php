<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Bestätigungscode · Nova CMS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4 font-sans text-slate-900 antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-6 text-center"><x-logo class="text-lg" /></div>
        <div class="nova-card">
            <h1 class="text-xl font-semibold">Bestätigungscode</h1>
            <p class="mt-1 text-sm text-slate-600">Öffnen Sie Ihre Authenticator-App und geben Sie den sechsstelligen Code für Nova CMS ein.</p>
            <div class="mt-5"><x-alerts /></div>
            <form method="POST" action="{{ route('login.two-factor.verify') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="nova-label" for="code">Code</label>
                    <input class="nova-input text-center font-mono text-2xl tracking-[0.4em]" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20">
                </div>
                <button class="nova-btn w-full">Bestätigen</button>
            </form>
            <p class="mt-4 text-xs text-slate-500">Handy verloren? Geben Sie stattdessen einen Ihrer Notfall-Codes ein. Jeder funktioniert nur einmal.</p>
        </div>
        <p class="mt-6 text-center text-sm"><a class="text-slate-500 hover:text-slate-800" href="{{ route('login') }}">Abbrechen</a></p>
    </main>
</body>
</html>
