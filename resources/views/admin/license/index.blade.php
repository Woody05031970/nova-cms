@extends('layouts.admin')
@section('title', 'Lizenz')

@php
    $color = ['active' => 'bg-emerald-50 text-emerald-700', 'trial' => 'bg-amber-50 text-amber-700', 'dev' => 'bg-slate-100 text-slate-600'][$status] ?? 'bg-red-50 text-red-700';
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Lizenz</h1>
    <p class="mt-1 text-slate-600">Ihre Lizenz schaltet Nova CMS und gekaufte Module frei.</p>

    @if ($status === 'trial_over')
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            <h2 class="font-semibold">Der Testzeitraum ist vorbei</h2>
            <p class="mt-1 text-sm">Ihre Website ist weiterhin für alle Besucher online. Um sie weiter zu bearbeiten, geben Sie bitte Ihren Lizenzschlüssel ein.</p>
        </div>
    @endif

    <section class="nova-card mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Status</h2>
            <span class="rounded-full px-3 py-1 text-sm font-medium {{ $color }}" data-license-status>{{ \App\Licensing\License::label($status) }}</span>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            @if ($status === 'trial')
                <div><dt class="text-slate-500">Testzeitraum</dt><dd>noch {{ $license->trialDaysLeft() }} Tage</dd></div>
            @endif
            @if ($license->key())
                <div><dt class="text-slate-500">Lizenzschlüssel</dt><dd class="font-mono">{{ \App\Licensing\License::mask($license->key()) }}</dd></div>
            @endif
            @if ($payload)
                @if (! empty($payload['customer']))<div><dt class="text-slate-500">Lizenznehmer</dt><dd>{{ $payload['customer'] }}</dd></div>@endif
                <div><dt class="text-slate-500">Website</dt><dd>{{ $payload['domain'] ?? '' }}</dd></div>
                <div><dt class="text-slate-500">Updates bis</dt><dd>{{ $license->updatesUntil()?->format('d.m.Y') ?? 'unbegrenzt' }}</dd></div>
                <div><dt class="text-slate-500">Freigeschaltete Module</dt><dd>{{ count($payload['modules'] ?? []) ?: 'keine' }}</dd></div>
                @if ($checkedAt)<div><dt class="text-slate-500">Zuletzt bestätigt</dt><dd>{{ \Carbon\Carbon::createFromTimestamp((int) $checkedAt)->timezone(config('app.timezone'))->format('d.m.Y, H:i') }} Uhr</dd></div>@endif
            @endif
            @if ($status === 'dev')
                <div class="sm:col-span-2 text-slate-600">Diese Installation hat keinen Schlüssel für den Lizenzserver. Alle Funktionen und Module sind frei (Entwicklerversion).</div>
            @endif
        </dl>
        @if ($lastError && $license->key())
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Letzte Prüfung: {{ $lastError }}</p>
        @endif
        @if ($status === 'invalid' && ($payload['status'] ?? '') === 'revoked')
            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-900">Diese Lizenz wurde gesperrt. Bitte wenden Sie sich an den Support.</p>
        @endif

        @if ($license->key())
            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <form method="POST" action="{{ route('admin.license.refresh') }}">@csrf <button class="nova-btn-secondary">Jetzt prüfen</button></form>
                <form method="POST" action="{{ route('admin.license.destroy') }}" onsubmit="return confirm('Lizenz von dieser Website entfernen? Sie können sie danach auf einer anderen Website eingeben.')">
                    @csrf @method('DELETE')
                    <button class="nova-btn-secondary text-red-700">Von dieser Website entfernen</button>
                </form>
            </div>
        @endif
    </section>

    @if ($status !== 'dev')
        <section class="nova-card mt-6">
            <h2 class="font-semibold">{{ $license->key() ? 'Anderen Lizenzschlüssel eingeben' : 'Lizenzschlüssel eingeben' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Den Schlüssel finden Sie in der E-Mail nach Ihrem Kauf.</p>
            <form method="POST" action="{{ route('admin.license.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf
                <input class="nova-input font-mono uppercase tracking-wider" name="key" required placeholder="NOVA-XXXX-XXXX-XXXX-XXXX" value="{{ old('key') }}" autocomplete="off" aria-label="Lizenzschlüssel">
                <button class="nova-btn shrink-0">Aktivieren</button>
            </form>
        </section>
    @endif
    <section class="nova-card mt-6" id="funktionen">
        <h2 class="text-base font-semibold">Freigeschaltete Funktionen</h2>
        <p class="mt-1 text-sm text-slate-500">Welche Zusatzfunktionen diese Installation nutzen darf. In der Testphase ist alles freigeschaltet.</p>
        <ul class="mt-4 divide-y divide-slate-100">
            @foreach (app(\App\Licensing\Features::class)->overview() as $key => $f)
                <li class="flex items-start justify-between gap-4 py-3" data-feature="{{ $key }}">
                    <div><p class="text-sm font-medium text-slate-900">{{ $f['name'] }}</p><p class="text-xs text-slate-500">{{ $f['description'] }}</p></div>
                    @if ($f['enabled'])
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-50 text-emerald-700">Aktiv</span>
                    @else
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600">Nicht enthalten</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endsection
