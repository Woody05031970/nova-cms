@extends('layouts.install')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Wo sollen Ihre Inhalte gespeichert werden?</h1>
    <p class="mt-2 text-slate-600">Wenn Sie unsicher sind, nehmen Sie einfach die erste Möglichkeit.</p>

    @php $driver = old('driver', $db['driver'] ?? 'sqlite'); @endphp
    <form method="POST" action="{{ route('install.database.save') }}" class="mt-6 space-y-6" id="db-form">
        @csrf
        <fieldset class="grid gap-3 sm:grid-cols-2">
            <legend class="sr-only">Art der Datenbank</legend>
            <label class="flex cursor-pointer flex-col rounded-lg border border-slate-200 p-4 has-checked:border-nova-500 has-checked:ring-2 has-checked:ring-nova-200 {{ $sqliteAvailable ? '' : 'pointer-events-none opacity-50' }}">
                <span class="flex items-center gap-2 font-medium">
                    <input type="radio" name="driver" value="sqlite" class="accent-nova-600" @checked($driver === 'sqlite') @disabled(! $sqliteAvailable)>
                    Einfach (empfohlen)
                </span>
                <span class="mt-1 text-sm text-slate-600">Keine Zugangsdaten nötig. Ideal für kleine und mittlere Websites.</span>
            </label>
            <label class="flex cursor-pointer flex-col rounded-lg border border-slate-200 p-4 has-checked:border-nova-500 has-checked:ring-2 has-checked:ring-nova-200 {{ $mysqlAvailable ? '' : 'pointer-events-none opacity-50' }}">
                <span class="flex items-center gap-2 font-medium">
                    <input type="radio" name="driver" value="mysql" class="accent-nova-600" @checked($driver === 'mysql') @disabled(! $mysqlAvailable)>
                    MySQL-Datenbank
                </span>
                <span class="mt-1 text-sm text-slate-600">Für große Shops. Die Daten finden Sie bei Ihrem Hoster unter "Datenbanken".</span>
            </label>
        </fieldset>

        <div id="mysql-fields" class="grid gap-4 sm:grid-cols-2 {{ $driver === 'mysql' ? '' : 'hidden' }}">
            <div class="sm:col-span-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                Bei IONOS steht der Server z. B. als "db5000….hosting-data.io", bei Strato als "rdbms.strato.de".
            </div>
            <div>
                <label class="nova-label" for="host">Datenbank-Server</label>
                <input class="nova-input" id="host" name="host" value="{{ old('host', $db['host'] ?? 'localhost') }}">
            </div>
            <div>
                <label class="nova-label" for="port">Port</label>
                <input class="nova-input" id="port" name="port" inputmode="numeric" value="{{ old('port', $db['port'] ?? '3306') }}">
            </div>
            <div class="sm:col-span-2">
                <label class="nova-label" for="database">Name der Datenbank</label>
                <input class="nova-input" id="database" name="database" value="{{ old('database', $db['database'] ?? '') }}">
            </div>
            <div>
                <label class="nova-label" for="username">Benutzer</label>
                <input class="nova-input" id="username" name="username" autocomplete="off" value="{{ old('username', $db['username'] ?? '') }}">
            </div>
            <div>
                <label class="nova-label" for="password">Passwort</label>
                <input class="nova-input" id="password" name="password" type="password" autocomplete="off">
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('install.welcome') }}" class="text-sm text-slate-600 hover:text-slate-900">Zurück</a>
            <button class="nova-btn">Weiter <x-icon name="arrow" class="size-4" /></button>
        </div>
    </form>

    <script>
        document.querySelectorAll('input[name=driver]').forEach(function (el) {
            el.addEventListener('change', function () {
                document.getElementById('mysql-fields').classList.toggle('hidden', el.value !== 'mysql');
            });
        });
    </script>
@endsection
