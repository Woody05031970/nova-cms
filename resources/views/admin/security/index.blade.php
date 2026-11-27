@extends('layouts.admin')
@section('title', 'Sicherheit')
@section('width', 'max-w-6xl')

@php
    $badge = fn ($s) => $s >= 80 ? 'text-emerald-700 bg-emerald-50' : ($s >= 50 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50');
    $dot = ['good' => 'bg-emerald-500', 'warn' => 'bg-amber-400', 'bad' => 'bg-red-500'];
    $fixes = ['uploads_htaccess' => ['uploads', 'Jetzt beheben'], 'integrity' => ['integrity', 'Aktuellen Stand als sicher speichern']];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Sicherheit</h1>
    <p class="mt-1 text-slate-600">Nova CMS wehrt Angriffe automatisch ab und prüft Ihre Website auf Schwachstellen.</p>


    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <section class="nova-card flex flex-col items-center justify-center text-center">
            <h2 class="text-sm font-semibold text-slate-500">Sicherheits-Check</h2>
            @if ($scan)
                <p class="mt-3 rounded-2xl px-5 py-2 text-4xl font-semibold {{ $badge($scan['score']) }}" data-security-score>{{ $scan['score'] }}<span class="text-lg font-normal opacity-70"> / 100</span></p>
                <p class="mt-2 text-sm text-slate-500">Zuletzt geprüft am {{ \Carbon\Carbon::parse($scan['at'])->timezone(config('app.timezone'))->format('d.m.Y, H:i') }} Uhr</p>
            @else
                <p class="mt-3 text-sm text-slate-500">Noch nicht geprüft.</p>
            @endif
            <form method="POST" action="{{ route('admin.security.scan') }}" class="mt-4">
                @csrf
                <button class="nova-btn"><x-icon name="shield" class="size-4" /> Jetzt prüfen</button>
            </form>
        </section>

        <section class="nova-card lg:col-span-2">
            <h2 class="font-semibold">Die letzten 7 Tage</h2>
            <dl class="mt-4 grid grid-cols-3 gap-4 text-center">
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs text-slate-500">Angriffe abgewehrt</dt>
                    <dd class="mt-1 text-2xl font-semibold">{{ $stats['attacks'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs text-slate-500">Falsche Anmeldungen</dt>
                    <dd class="mt-1 text-2xl font-semibold">{{ $stats['failed'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs text-slate-500">Gesperrte Adressen</dt>
                    <dd class="mt-1 text-2xl font-semibold">{{ $stats['blocked'] }}</dd>
                </div>
            </dl>
            <form method="POST" action="{{ route('admin.security.settings') }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-sm">
                @csrf @method('PUT')
                <input type="hidden" name="firewall" value="0">
                <input type="hidden" name="autoblock" value="0">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="firewall" value="1" class="mt-0.5 size-4 rounded border-slate-300" @checked($firewall)>
                    <span><strong class="font-medium">Angriffsschutz</strong><span class="block text-slate-500">Blockiert typische Angriffe wie Datenbank-Tricks, eingeschleuste Skripte und Suche nach Schwachstellen.</span></span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="autoblock" value="1" class="mt-0.5 size-4 rounded border-slate-300" @checked($autoblock)>
                    <span><strong class="font-medium">Angreifer automatisch sperren</strong><span class="block text-slate-500">Nach 10 Angriffen in 10 Minuten wird die Adresse für eine Stunde gesperrt.</span></span>
                </label>
                <p class="text-slate-500">Immer aktiv: Anmeldesperre nach 5 falschen Passwörtern für 15 Minuten, sichere Browser-Einstellungen (Sicherheits-Header).</p>
                <button class="nova-btn-secondary">Speichern</button>
            </form>
        </section>
    </div>

    @if ($scan)
        <section class="nova-card mt-6">
            <h2 class="font-semibold">Ergebnis im Einzelnen</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach ($scan['checks'] as $c)
                    <li class="flex gap-2.5" data-check="{{ $c['key'] }}" data-status="{{ $c['status'] }}">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $dot[$c['status']] }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium">{{ $c['label'] }}</span>
                            @if ($c['status'] !== 'good' && $c['tip'])<span class="block text-slate-600">{{ $c['tip'] }}</span>@endif
                            @if ($c['status'] !== 'good' && $c['details'])
                                <ul class="mt-1 space-y-0.5 font-mono text-xs break-all text-slate-500">
                                    @foreach ($c['details'] as $d)<li>{{ $d }}</li>@endforeach
                                </ul>
                            @endif
                            @if ($c['status'] !== 'good' && isset($fixes[$c['key']]))
                                <form method="POST" action="{{ route('admin.security.fix') }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="what" value="{{ $fixes[$c['key']][0] }}">
                                    <button class="nova-btn-secondary px-3 py-1 text-xs">{{ $fixes[$c['key']][1] }}</button>
                                </form>
                            @endif
                            @if ($c['key'] === '2fa' && $c['status'] !== 'good')
                                <a href="{{ route('admin.account') }}#zwei-faktor" class="mt-1 inline-block text-xs font-medium text-nova-700 hover:underline">Zu "Mein Konto"</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="nova-card min-w-0 p-0 lg:col-span-2">
            <h2 class="px-6 pt-5 font-semibold">Protokoll</h2>
            <p class="px-6 text-xs text-slate-500">Einträge werden nach {{ \App\Models\SecurityEvent::KEEP_DAYS }} Tagen automatisch gelöscht (Datenschutz).</p>
            @if ($events->isEmpty())
                <p class="px-6 pt-2 pb-6 text-sm text-slate-500">Noch keine Ereignisse.</p>
            @else
                <ul class="mt-3 max-h-[28rem] divide-y divide-slate-100 overflow-y-auto text-sm">
                    @foreach ($events as $e)
                        <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-6 py-2">
                            <span @class(['font-medium', 'text-amber-700' => $e->isWarning()])>{{ $e->label() }}</span>
                            @if ($e->detail)<span class="min-w-0 flex-1 truncate text-slate-600 max-sm:basis-full" title="{{ $e->detail }}">{{ $e->detail }}</span>@else<span class="flex-1"></span>@endif
                            <span class="text-xs text-slate-500">{{ $e->created_at->format('d.m., H:i') }} · {{ $e->user?->name ?? $e->ip }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="nova-card min-w-0">
            <h2 class="font-semibold">Gesperrte Adressen</h2>
            @if ($blocked->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Zurzeit ist keine Adresse gesperrt.</p>
            @else
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($blocked as $b)
                        <li class="flex items-start justify-between gap-2">
                            <span><span class="font-mono">{{ $b->ip }}</span>
                                <span class="block text-xs text-slate-500">{{ $b->reason }} · {{ $b->until ? 'bis '.$b->until->format('d.m., H:i') : 'dauerhaft' }}</span></span>
                            <form method="POST" action="{{ route('admin.security.unblock', $b) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs font-medium text-nova-700 hover:underline">Freigeben</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('admin.security.block') }}" class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                @csrf
                <label class="nova-label" for="ip">Adresse von Hand sperren</label>
                <input class="nova-input font-mono" id="ip" name="ip" required placeholder="z. B. 203.0.113.7">
                <input class="nova-input" name="reason" maxlength="200" placeholder="Grund (freiwillig)">
                <button class="nova-btn-secondary">Sperren</button>
            </form>
        </section>
    </div>
@endsection
