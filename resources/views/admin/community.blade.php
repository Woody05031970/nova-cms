@extends('layouts.admin')
@section('title', 'Community')
@section('width', 'max-w-6xl')

@section('content')
    @php $A = \App\Http\Controllers\Admin\CommunityAdminController::class; $types = \App\Models\CommunityReport::TYPES; @endphp
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Community</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Ein Treffpunkt für Ihre Mitglieder: Pinnwand mit Beiträgen und Fotos, Profile mit eigener Seite, persönliche Nachrichten und Foren. Erreichbar unter <a href="{{ route('community.home') }}" class="font-mono text-sm text-nova-700 hover:underline" target="_blank" rel="noopener">{{ route('community.home') }}</a>.</p>
        </div>
        <a href="{{ route('community.home') }}" target="_blank" rel="noopener" class="nova-btn-secondary"><x-icon name="external" class="size-4" /> Community öffnen</a>
    </div>

    <dl class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-5" data-community-stats>
        @foreach ($stats as $label => $n)
            <div class="nova-card py-4"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 text-2xl font-semibold">{{ number_format($n, 0, ',', '.') }}</dd></div>
        @endforeach
    </dl>

    <section class="nova-card mt-6 p-0" id="meldungen" aria-labelledby="c-reports">
        <div class="px-6 pt-5"><h2 id="c-reports" class="font-semibold">Meldungen @if($reports->isNotEmpty())<span class="ml-1 rounded-full bg-amber-100 px-2 text-sm text-amber-900">{{ $reports->count() }}</span>@endif</h2>
            <p class="mt-1 text-sm text-slate-600">Mitglieder können Beiträge, Kommentare, Nachrichten und Profile melden. Prüfen Sie die Meldung und entfernen Sie den Inhalt, wenn er gegen Ihre Regeln verstößt.</p></div>
        @if ($reports->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Keine offenen Meldungen.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100" data-reports>
                @foreach ($reports as $r)
                    @php [$text, $url, $author] = $A::describe($r); @endphp
                    <li class="flex flex-col gap-3 px-6 py-4 lg:flex-row lg:items-center">
                        <div class="min-w-0 flex-1 text-sm">
                            <p><span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium">{{ $types[$r->type] ?? $r->type }}</span> @if($author)von <span class="font-medium">{{ $author->name }}</span>@endif · gemeldet von {{ $r->user?->name ?? 'unbekannt' }}, {{ $r->created_at->locale('de')->diffForHumans() }}</p>
                            <p class="mt-1 text-slate-800">„{{ $text }}“ @if($url)<a href="{{ $url }}" target="_blank" rel="noopener" class="text-nova-700 hover:underline">ansehen</a>@endif</p>
                            <p class="mt-1 text-amber-900">Grund: {{ $r->reason }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.community.reports.resolve', $r) }}">@csrf @method('PUT')<input type="hidden" name="action" value="delete"><button class="nova-btn py-2 text-sm" data-report-delete>Inhalt entfernen</button></form>
                            <form method="POST" action="{{ route('admin.community.reports.resolve', $r) }}">@csrf @method('PUT')<input type="hidden" name="action" value="dismiss"><button class="nova-btn-secondary py-2 text-sm">In Ordnung</button></form>
                            @if ($author && ! $author->isAdmin())<form method="POST" action="{{ route('admin.community.ban', $author) }}" onsubmit="return confirm('{{ $author->name }} in der Community sperren?')">@csrf @method('PUT')<button class="nova-btn-secondary py-2 text-sm text-red-700">Person sperren</button></form>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="nova-card mt-6" id="foren" aria-labelledby="c-boards">
        <h2 id="c-boards" class="font-semibold">Foren</h2>
        <p class="mt-1 text-sm text-slate-600">Legen Sie Bereiche für Ihre Themen an, z. B. „Vorstellungsrunde“, „Fragen und Hilfe“ oder „Ankündigungen“. Ein Forum „nur für Betreiber“ eignet sich für Neuigkeiten, auf die alle antworten dürfen.</p>
        <div class="mt-4 space-y-3">
            @foreach ($boards as $b)
                <details class="rounded-xl border border-slate-200" data-board="{{ $b->slug }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3"><span><span class="font-medium">{{ $b->name }}</span> <span class="text-sm text-slate-500">· {{ $b->topics_count }} Themen · {{ $b->replies_count }} Antworten @if($b->is_locked)· nur Betreiber @endif</span></span><x-icon name="chevron-down" class="size-4 text-slate-400" /></summary>
                    <div class="border-t border-slate-100 p-4">
                        <form method="POST" action="{{ route('admin.community.boards.update', $b) }}" class="grid gap-3 sm:grid-cols-[1fr_2fr_6rem]">
                            @csrf @method('PUT')
                            <div><label class="nova-label" for="bn{{ $b->id }}">Name</label><input class="nova-input" id="bn{{ $b->id }}" name="name" value="{{ $b->name }}" required maxlength="120"></div>
                            <div><label class="nova-label" for="bd{{ $b->id }}">Beschreibung</label><input class="nova-input" id="bd{{ $b->id }}" name="description" value="{{ $b->description }}" maxlength="255"></div>
                            <div><label class="nova-label" for="bp{{ $b->id }}">Reihenfolge</label><input class="nova-input" type="number" id="bp{{ $b->id }}" name="position" value="{{ $b->position }}" min="0" max="999"></div>
                            <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="is_locked" value="1" class="accent-nova-600" @checked($b->is_locked)> Neue Themen nur durch Betreiber (Antworten bleiben für alle möglich)</label>
                            <div class="sm:col-span-3"><button class="nova-btn py-2 text-sm">Speichern</button></div>
                        </form>
                        <form method="POST" action="{{ route('admin.community.boards.destroy', $b) }}" class="mt-2" onsubmit="return confirm('Forum mit allen Themen löschen?')">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Forum löschen</button></form>
                    </div>
                </details>
            @endforeach
            <details class="rounded-xl border border-dashed border-slate-300" @if($boards->isEmpty()) open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 font-medium text-nova-700">+ Neues Forum</summary>
                <form method="POST" action="{{ route('admin.community.boards.store') }}" class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-[1fr_2fr_6rem]">
                    @csrf
                    <div><label class="nova-label" for="nb-name">Name</label><input class="nova-input" id="nb-name" name="name" required maxlength="120" placeholder="Fragen und Hilfe"></div>
                    <div><label class="nova-label" for="nb-desc">Beschreibung</label><input class="nova-input" id="nb-desc" name="description" maxlength="255" placeholder="Hier helfen sich Mitglieder gegenseitig."></div>
                    <div><label class="nova-label" for="nb-pos">Reihenfolge</label><input class="nova-input" type="number" id="nb-pos" name="position" value="{{ $boards->count() + 1 }}" min="0" max="999"></div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="is_locked" value="1" class="accent-nova-600"> Neue Themen nur durch Betreiber</label>
                    <div class="sm:col-span-3"><button class="nova-btn py-2 text-sm" data-board-create>Forum anlegen</button></div>
                </form>
            </details>
        </div>
    </section>

    <section class="nova-card mt-6" id="einstellungen" aria-labelledby="c-settings">
        <h2 id="c-settings" class="font-semibold">Einstellungen</h2>
        <form method="POST" action="{{ route('admin.community.settings') }}" class="mt-4 space-y-5">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="nova-label" for="ct">Name der Community</label><input class="nova-input" id="ct" name="community_title" value="{{ $s('community_title') }}" placeholder="Community" maxlength="60"></div>
                <div><label class="nova-label" for="cv">Wer darf mitlesen?</label>
                    <select class="nova-input" id="cv" name="community_visibility"><option value="public" @selected($s('community_visibility', 'public') === 'public')>Alle Besucher (Schreiben nur mit Konto)</option><option value="members" @selected($s('community_visibility') === 'members')>Nur angemeldete Mitglieder</option></select></div>
            </div>
            <fieldset><legend class="nova-label">Wie kommen neue Mitglieder dazu?</legend>
                @foreach (['open' => 'Jeder kann sich registrieren und sofort mitmachen', 'approval' => 'Registrierung, aber ich schalte jedes Mitglied frei', 'off' => 'Keine Registrierung, nur auf Einladung (unter „Mitglieder“)'] as $v => $l)
                    <label class="mt-1 flex items-start gap-2 text-sm"><input type="radio" name="members_registration" value="{{ $v }}" class="mt-1 accent-nova-600" @checked(\App\Support\Members\Members::registration() === $v)><span>{{ $l }}</span></label>
                @endforeach
                <p class="mt-1 text-xs text-slate-500">Gilt auch für den Mitgliederbereich. Mitglieder verwalten Sie unter <a href="{{ route('admin.members') }}" class="text-nova-700 hover:underline">Mitglieder</a>.</p>
            </fieldset>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="community_pages" value="1" class="mt-1 accent-nova-600" @checked($s('community_pages', '1') === '1')><span>Mitglieder dürfen eine eigene Seite gestalten</span></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="community_mail" value="1" class="mt-1 accent-nova-600" @checked($s('community_mail', '1') === '1')><span>Hinweise per E-Mail verschicken (neue Nachricht, Antwort im eigenen Thema, Erwähnung)</span></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="community_footer_link" value="1" class="mt-1 accent-nova-600" @checked($s('community_footer_link', '1') === '1')><span>Link zur Community in der Fußzeile der Website</span></label>
            </div>
            <p class="text-sm text-slate-600">Tipp: Unter <a href="{{ route('admin.menu') }}" class="text-nova-700 hover:underline">Menü</a> können Sie die Community auch ins Hauptmenü aufnehmen (Adresse <span class="font-mono">/community</span>).</p>
            <button class="nova-btn" data-community-settings>Speichern</button>
        </form>
    </section>

    @if ($banned->isNotEmpty())
        <section class="nova-card mt-6" aria-labelledby="c-banned">
            <h2 id="c-banned" class="font-semibold">Gesperrte Personen</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($banned as $p)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm"><span><span class="font-medium">{{ $p->user?->name }}</span> · seit {{ $p->banned_at->format('d.m.Y') }}</span>
                        <form method="POST" action="{{ route('admin.community.ban', $p->user) }}">@csrf @method('PUT')<button class="nova-btn-secondary py-1.5 text-sm">Sperre aufheben</button></form></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
