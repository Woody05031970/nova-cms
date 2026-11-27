@extends('layouts.install')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Ihre Website und Ihr Zugang</h1>
    <p class="mt-2 text-slate-600">Mit diesen Daten melden Sie sich später in der Verwaltung an. Alles lässt sich nachträglich ändern.</p>

    <form method="POST" action="{{ route('install.finish') }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label class="nova-label" for="site_name">Name Ihrer Website</label>
            <input class="nova-input" id="site_name" name="site_name" required maxlength="120" placeholder="z. B. Bäckerei Müller" value="{{ old('site_name') }}">
            <p class="nova-help">Meist der Name Ihrer Firma oder Ihres Vereins.</p>
        </div>
        <hr class="border-slate-100">
        <div>
            <label class="nova-label" for="name">Ihr Name</label>
            <input class="nova-input" id="name" name="name" required autocomplete="name" value="{{ old('name') }}">
        </div>
        <div>
            <label class="nova-label" for="email">Ihre E-Mail-Adresse</label>
            <input class="nova-input" id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="nova-label" for="password">Passwort</label>
                <input class="nova-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
                <p class="nova-help">Mindestens 10 Zeichen, mit Buchstaben und Zahlen.</p>
            </div>
            <div>
                <label class="nova-label" for="password_confirmation">Passwort wiederholen</label>
                <input class="nova-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.database') }}" class="text-sm text-slate-600 hover:text-slate-900">Zurück</a>
            <button class="nova-btn">Nova CMS installieren</button>
        </div>
    </form>
@endsection
