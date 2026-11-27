@extends('layouts.admin')
@section('title', 'Mein Konto')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Mein Konto</h1>
    <p class="mt-1 text-slate-600">Ihre Zugangsdaten und die Zwei-Faktor-Anmeldung.</p>


    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.account.update') }}" class="nova-card space-y-4">
            @csrf @method('PUT')
            <h2 class="font-semibold">Ihre Angaben</h2>
            <div>
                <label class="nova-label" for="name">Name</label>
                <input class="nova-input" id="name" name="name" required maxlength="120" value="{{ old('name', $user->name) }}">
            </div>
            <div>
                <label class="nova-label" for="email">E-Mail (zum Anmelden)</label>
                <input class="nova-input" id="email" name="email" type="email" required value="{{ old('email', $user->email) }}">
            </div>
            <button class="nova-btn">Speichern</button>
        </form>

        <form method="POST" action="{{ route('admin.account.password') }}" class="nova-card space-y-4">
            @csrf @method('PUT')
            <h2 class="font-semibold">Passwort ändern</h2>
            <div>
                <label class="nova-label" for="current_password">Bisheriges Passwort</label>
                <input class="nova-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nova-label" for="password">Neues Passwort</label>
                    <input class="nova-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <div>
                    <label class="nova-label" for="password_confirmation">Wiederholen</label>
                    <input class="nova-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
            </div>
            <p class="nova-help">Mindestens 10 Zeichen mit Buchstaben und Zahlen. Andere Geräte werden danach abgemeldet.</p>
            <button class="nova-btn">Passwort ändern</button>
        </form>
    </div>

    <section id="zwei-faktor" class="nova-card mt-6 scroll-mt-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">Zwei-Faktor-Anmeldung</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Beim Anmelden fragt Nova CMS zusätzlich nach einem Code aus einer App auf Ihrem Handy,
                    zum Beispiel Google Authenticator, Microsoft Authenticator oder 1Password. Ein gestohlenes Passwort allein reicht dann nicht mehr.</p>
            </div>
            @if ($user->hasTwoFactor())
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">Aktiv seit {{ $user->two_factor_confirmed_at->format('d.m.Y') }}</span>
            @else
                <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-700">Nicht eingerichtet</span>
            @endif
        </div>

        @if ($recoveryCodes)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
                <h3 class="font-semibold text-amber-900">Ihre Notfall-Codes: jetzt aufschreiben oder ausdrucken</h3>
                <p class="mt-1 text-sm text-amber-900">Falls Ihr Handy verloren geht, melden Sie sich mit einem dieser Codes an. Jeder Code funktioniert nur einmal.
                    Diese Codes werden nur jetzt angezeigt.</p>
                <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-base sm:grid-cols-4" data-recovery-codes>
                    @foreach ($recoveryCodes as $code)
                        <li class="rounded-md bg-white px-3 py-2 text-center tracking-wider">{{ $code }}</li>
                    @endforeach
                </ul>
                <button type="button" class="nova-btn-secondary mt-4" onclick="window.print()">Drucken</button>
            </div>
        @endif

        @if ($user->hasTwoFactor())
            <p class="mt-5 text-sm text-slate-600">Noch {{ $remaining }} von 8 Notfall-Codes übrig.</p>
            <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <form method="POST" action="{{ route('admin.account.2fa.recovery') }}" class="space-y-3">
                    @csrf
                    <label class="nova-label" for="pw_recovery">Neue Notfall-Codes erstellen</label>
                    <input class="nova-input" id="pw_recovery" name="current_password" type="password" required placeholder="Ihr Passwort" autocomplete="current-password">
                    <button class="nova-btn-secondary">Neue Codes erstellen</button>
                </form>
                <form method="POST" action="{{ route('admin.account.2fa.disable') }}" class="space-y-3">
                    @csrf @method('DELETE')
                    <label class="nova-label" for="pw_disable">Zwei-Faktor-Anmeldung ausschalten</label>
                    <input class="nova-input" id="pw_disable" name="current_password" type="password" required placeholder="Ihr Passwort" autocomplete="current-password">
                    <button class="nova-btn-secondary text-red-700">Ausschalten</button>
                </form>
            </div>
        @elseif ($setup)
            <ol class="mt-5 grid gap-6 md:grid-cols-[auto_1fr]">
                <li class="flex flex-col items-center">
                    <div class="rounded-xl border border-slate-200 bg-white p-3 [&_svg]:size-48" data-qr>{!! $setup['qr'] !!}</div>
                    <p class="mt-2 max-w-48 text-center text-xs text-slate-500">Kein Scan möglich? Diesen Schlüssel in der App eintippen:</p>
                    <p class="mt-1 font-mono text-xs tracking-wider" data-secret>{{ $setup['secret'] }}</p>
                </li>
                <li>
                    <p class="text-sm"><strong>1.</strong> Öffnen Sie die Authenticator-App auf Ihrem Handy und scannen Sie den Code links.</p>
                    <p class="mt-2 text-sm"><strong>2.</strong> Die App zeigt nun alle 30 Sekunden einen neuen sechsstelligen Code. Geben Sie den aktuellen hier ein:</p>
                    <form method="POST" action="{{ route('admin.account.2fa.confirm') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label class="nova-label" for="code">Code aus der App</label>
                            <input class="nova-input w-40 text-center font-mono text-lg tracking-widest" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
                        </div>
                        <button class="nova-btn">Bestätigen und einschalten</button>
                    </form>
                    @error('code')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </li>
            </ol>
        @else
            <form method="POST" action="{{ route('admin.account.2fa.start') }}" class="mt-5">
                @csrf
                <button class="nova-btn"><x-icon name="lock" class="size-4" /> Jetzt einrichten</button>
            </form>
        @endif
    </section>

    <section class="nova-card mt-6">
        <h2 class="font-semibold">Ihre letzten Anmeldungen und Änderungen</h2>
        @if ($events->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Noch keine Einträge.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($events as $e)
                    <li class="flex flex-wrap justify-between gap-2 py-2">
                        <span @class(['text-amber-700' => $e->isWarning()])>{{ $e->label() }}</span>
                        <span class="text-slate-500">{{ $e->created_at->format('d.m.Y, H:i') }} Uhr · {{ $e->ip }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
