@extends('layouts.admin')
@section('title', 'Datensicherung')
@section('width', 'max-w-5xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Datensicherung</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Eine Sicherung enthält alle Seiten, Einstellungen, Bestellungen, Termine, Mitglieder, Bilder und Dateien. Damit holen Sie nach einem Fehler einen früheren Stand zurück oder ziehen mit der Website auf einen anderen Server um.</p>
        </div>
        <form method="POST" action="{{ route('admin.backups.store') }}">@csrf<button class="nova-btn" data-backup-create>Jetzt sichern</button></form>
    </div>
    @error('backup')<p class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">{{ $message }}</p>@enderror

    <section class="nova-card mt-8 p-0" aria-labelledby="b-list">
        <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-5">
            <h2 id="b-list" class="font-semibold">Vorhandene Sicherungen</h2>
            <p class="text-sm text-slate-500">@if($last)Letzte Sicherung: {{ \Illuminate\Support\Carbon::parse($last)->format('d.m.Y, H:i') }} Uhr @else Noch keine Sicherung @endif @if($free) · {{ number_format($free / 1073741824, 1, ',', '.') }} GB frei @endif</p>
        </div>
        @if ($backups->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-amber-800">Es gibt noch keine Sicherung. Klicken Sie auf „Jetzt sichern“ oder schalten Sie unten die automatische Sicherung ein.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100" data-backups>
                @foreach ($backups as $b)
                    <li class="flex flex-col gap-2 px-6 py-3 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1 text-sm"><p class="font-medium">{{ $b['date']->format('d.m.Y, H:i') }} Uhr <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $b['kind'] }}</span></p><p class="text-slate-500">{{ number_format($b['size'] / 1048576, 1, ',', '.') }} MB</p></div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <a href="{{ route('admin.backups.download', $b['name']) }}" class="font-medium text-nova-700 hover:underline">Herunterladen</a>
                            <form method="POST" action="{{ route('admin.backups.restore') }}" onsubmit="return confirm('Diesen Stand zurückspielen? Alle Änderungen seitdem werden ersetzt. Der aktuelle Stand wird vorher gesichert.')">@csrf<input type="hidden" name="name" value="{{ $b['name'] }}"><input type="hidden" name="confirm" value="1"><button class="text-nova-700 hover:underline" data-backup-restore>Zurückspielen</button></form>
                            <form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}" onsubmit="return confirm('Sicherung löschen?')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Löschen</button></form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="nova-card" aria-labelledby="b-auto">
            <h2 id="b-auto" class="font-semibold">Automatisch sichern</h2>
            <p class="mt-1 text-sm text-slate-600">Läuft von selbst, sobald jemand die Website besucht. Ein Cronjob ist nicht nötig.</p>
            <form method="POST" action="{{ route('admin.backups.settings') }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="nova-label" for="b-int">Wie oft?</label><select id="b-int" name="backup_auto" class="nova-input">@foreach (\App\Support\Backup\Backups::INTERVALS as $k => $label)<option value="{{ $k }}" @selected($auto === $k)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="nova-label" for="b-keep">Aufbewahren</label><select id="b-keep" name="backup_keep" class="nova-input">@foreach ([3, 7, 14, 30] as $n)<option value="{{ $n }}" @selected($keep === $n)>die letzten {{ $n }}</option>@endforeach</select></div>
                </div>
                <button class="nova-btn w-full sm:w-auto">Speichern</button>
            </form>
            <p class="mt-4 text-xs text-slate-500">Tipp: Sicherungen liegen auf demselben Server wie die Website. Laden Sie ab und zu eine herunter und speichern Sie sie an einem anderen Ort.</p>
        </section>

        <section class="nova-card" aria-labelledby="b-upload">
            <h2 id="b-upload" class="font-semibold">Sicherung hochladen und zurückspielen</h2>
            <p class="mt-1 text-sm text-slate-600">Zum Beispiel nach einem Umzug: Neue Installation einrichten, hier die ZIP-Datei hochladen, fertig. Ihre Lizenz auf diesem Server bleibt erhalten.</p>
            <form method="POST" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <label class="nova-label" for="b-file">ZIP-Datei</label>
                <input class="nova-input py-1.5" id="b-file" type="file" name="file" accept=".zip,application/zip" required>
                @error('file')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-1 accent-nova-600" required> Ich weiß, dass der aktuelle Stand ersetzt wird. Er wird vorher gesichert.</label>
                @error('confirm')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                <button class="nova-btn-secondary">Hochladen und zurückspielen</button>
            </form>
        </section>
    </div>
@endsection
