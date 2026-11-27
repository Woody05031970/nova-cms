@extends('layouts.admin')
@section('title', 'Mitglieder')
@section('width', 'max-w-6xl')

@php $badge = ['member' => 'bg-emerald-50 text-emerald-800', 'member_pending' => 'bg-amber-50 text-amber-900', 'member_blocked' => 'bg-slate-100 text-slate-600']; @endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Mitglieder</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Geschützte Inhalte für angemeldete Mitglieder: Setzen Sie im Seiten-Editor unter „Optionen“ das Häkchen „Nur für Mitglieder“ und stellen Sie hier Dateien zum Herunterladen bereit. Der Bereich ist erreichbar unter <a href="{{ route('members.home') }}" class="font-mono text-sm text-nova-700 hover:underline" target="_blank" rel="noopener">{{ route('members.home') }}</a>.</p>
        </div>
        @if ($pending)<p class="rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-900">{{ $pending }} {{ $pending === 1 ? 'Registrierung wartet' : 'Registrierungen warten' }} auf Freigabe</p>@endif
    </div>

    <section class="nova-card mt-8 p-0" aria-labelledby="m-list">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
            <h2 id="m-list" class="font-semibold">Alle Mitglieder</h2>
            <form method="GET" class="flex gap-2" role="search"><label for="mq" class="sr-only">Mitglieder suchen</label><input id="mq" name="q" value="{{ $q }}" class="nova-input py-1.5 text-sm" placeholder="Name oder E-Mail"><button class="nova-btn-secondary py-1.5 text-sm">Suchen</button></form>
        </div>
        @if ($users->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">{{ $q ? 'Keine Treffer.' : 'Noch keine Mitglieder. Laden Sie unten jemanden ein oder schalten Sie die Registrierung ein.' }}</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100" data-members>
                @foreach ($users as $u)
                    <li class="flex flex-col gap-2 px-6 py-3 sm:flex-row sm:items-center" data-member="{{ $u->email }}">
                        <div class="min-w-0 flex-1 text-sm">
                            <p><span class="font-medium">{{ $u->name }}</span> <span class="ml-1 rounded px-1.5 py-0.5 text-xs {{ $badge[$u->role] }}">{{ \App\Support\Members\Members::STATUS[$u->role] }}</span></p>
                            <p class="text-slate-600">{{ $u->email }} · seit {{ $u->created_at->format('d.m.Y') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1">
                            @foreach (array_filter(['approve' => $u->role === 'member_pending' ? 'Freischalten' : null, 'unblock' => $u->role === 'member_blocked' ? 'Entsperren' : null, 'reset' => $u->role === 'member' ? 'Passwort-Link senden' : null, 'block' => $u->role !== 'member_blocked' ? 'Sperren' : null]) as $action => $label)
                                <form method="POST" action="{{ route('admin.members.update', $u) }}">@csrf @method('PUT')<input type="hidden" name="action" value="{{ $action }}"><button @class(['text-sm hover:underline', 'font-semibold text-nova-700' => $action === 'approve', 'text-nova-700' => $action !== 'approve' && $action !== 'block', 'text-slate-600' => $action === 'block']) data-member-action="{{ $action }}">{{ $label }}</button></form>
                            @endforeach
                            <form method="POST" action="{{ route('admin.members.destroy', $u) }}" onsubmit="return confirm('Mitglied endgültig löschen?')">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Löschen</button></form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('admin.members.invite') }}" class="grid gap-3 border-t border-slate-100 px-6 py-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" data-member-invite>
            @csrf
            <div><label class="nova-label" for="i-name">Name</label><input class="nova-input" id="i-name" name="name" required maxlength="120" value="{{ old('name') }}">@error('name')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
            <div><label class="nova-label" for="i-email">E-Mail-Adresse</label><input class="nova-input" id="i-email" type="email" name="email" required value="{{ old('email') }}">@error('email')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
            <button class="nova-btn">Einladen</button>
        </form>
    </section>

    <section class="nova-card mt-6" id="downloads" aria-labelledby="m-files">
        <h2 id="m-files" class="font-semibold">Downloads für Mitglieder</h2>
        <p class="mt-1 text-sm text-slate-600">Die Dateien liegen geschützt auf dem Server und sind nur nach der Anmeldung erreichbar.</p>
        @if ($files->isNotEmpty())
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200" data-member-files>
                @foreach ($files as $f)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span><span class="font-medium">{{ $f->title }}</span> <span class="text-slate-500">· {{ $f->original_name }}, {{ $f->sizeLabel() }}</span></span>
                        <form method="POST" action="{{ route('admin.members.files.destroy', $f) }}" onsubmit="return confirm('Datei löschen?')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Löschen</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('admin.members.files.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            @csrf
            <div><label class="nova-label" for="f-title">Titel</label><input class="nova-input" id="f-title" name="title" maxlength="160" placeholder="z. B. Preisliste 2027"></div>
            <div><label class="nova-label" for="f-file">Datei (bis 50 MB)</label><input class="nova-input py-1.5" id="f-file" type="file" name="file" required>@error('file')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
            <button class="nova-btn">Hochladen</button>
        </form>
    </section>

    <section class="nova-card mt-6" id="einstellungen" aria-labelledby="m-settings">
        <h2 id="m-settings" class="font-semibold">Einstellungen</h2>
        <form method="POST" action="{{ route('admin.members.settings') }}" class="mt-4 space-y-5">
            @csrf @method('PUT')
            <fieldset>
                <legend class="nova-label">Wer darf sich registrieren?</legend>
                @foreach (['approval' => 'Jeder, aber ich schalte den Zugang frei', 'open' => 'Jeder, sofort freigeschaltet', 'off' => 'Niemand, nur auf Einladung'] as $v => $label)
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="radio" name="members_registration" value="{{ $v }}" class="accent-nova-600" @checked(\App\Support\Members\Members::registration() === $v)> {{ $label }}</label>
                @endforeach
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="nova-label" for="m-title">Name des Bereichs</label><input class="nova-input" id="m-title" name="members_title" maxlength="80" value="{{ $s('members_title') }}" placeholder="Mitgliederbereich"></div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="members_footer_link" value="1" class="accent-nova-600" @checked($s('members_footer_link', '1') === '1')> Link im Fuß der Website zeigen</label>
            </div>
            <div><label class="nova-label" for="m-intro">Begrüßungstext nach der Anmeldung</label><textarea class="nova-input" id="m-intro" name="members_intro" rows="3" maxlength="2000">{{ $s('members_intro') }}</textarea></div>
            <button class="nova-btn w-full sm:w-auto">Speichern</button>
        </form>
    </section>
@endsection
