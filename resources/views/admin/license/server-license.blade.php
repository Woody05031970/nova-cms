@extends('layouts.admin')
@section('title', 'Lizenz '.$license->key)

@section('content')
    <a href="{{ route('admin.license-server') }}" class="text-sm text-slate-500 hover:text-slate-800">‹ Lizenzserver</a>
    <h1 class="mt-2 font-mono text-2xl font-semibold tracking-tight">{{ $license->key }}</h1>
    <p class="mt-1 text-slate-600">erstellt am {{ $license->created_at->format('d.m.Y') }}@if ($license->order_id) aus Bestellung <a href="{{ route('admin.shop.orders.show', $license->order_id) }}" class="text-nova-700 hover:underline">ansehen</a>@endif</p>

    <form method="POST" action="{{ route('admin.license-server.update', $license) }}" class="nova-card mt-6 grid gap-4 sm:grid-cols-2">
        @csrf @method('PUT')
        <div><label class="nova-label" for="customer_name">Kunde</label><input class="nova-input" id="customer_name" name="customer_name" value="{{ $license->customer_name }}"></div>
        <div><label class="nova-label" for="customer_email">E-Mail</label><input class="nova-input" id="customer_email" name="customer_email" type="email" value="{{ $license->customer_email }}"></div>
        <div><label class="nova-label" for="max_domains">Anzahl Websites</label><input class="nova-input" id="max_domains" name="max_domains" type="number" min="1" value="{{ $license->max_domains }}"></div>
        <div><label class="nova-label" for="updates_until">Updates bis</label><input class="nova-input" id="updates_until" name="updates_until" type="date" value="{{ $license->updates_until?->toDateString() }}"></div>
        <fieldset class="space-y-1.5 text-sm sm:col-span-2">
            <legend class="nova-label">Enthalten</legend>
            <label class="flex items-center gap-2"><input type="hidden" name="core" value="0"><input type="checkbox" name="core" value="1" class="size-4" @checked($license->core)> Nova CMS Grundlizenz</label>
            @foreach ($modules as $m)
                <label class="flex items-center gap-2"><input type="checkbox" name="modules[]" value="{{ $m->slug }}" class="size-4" @checked(in_array($m->slug, $license->modules ?? []))> {{ $m->name }}</label>
            @endforeach
        </fieldset>
        <div class="sm:col-span-2"><label class="nova-label" for="notes">Notiz</label><textarea class="nova-input" id="notes" name="notes" rows="2">{{ $license->notes }}</textarea></div>
        <label class="flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="status" value="revoked" class="size-4" @checked(! $license->isActive())> Lizenz sperren (z. B. nach Rückbuchung)</label>
        <div class="sm:text-right"><button class="nova-btn">Speichern</button></div>
    </form>

    <section class="nova-card mt-6">
        <h2 class="font-semibold">Websites ({{ $license->activations->count() }} von {{ $license->max_domains }})</h2>
        <p class="text-xs text-slate-500">Test-Adressen wie localhost, *.test oder staging.* zählen nicht mit.</p>
        @if ($license->activations->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Noch auf keiner Website aktiviert.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($license->activations as $a)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span><span class="font-medium">{{ $a->domain }}</span> <span class="text-slate-500">· Version {{ $a->version ?: '?' }} · zuletzt {{ $a->last_seen_at?->format('d.m.Y') }}</span></span>
                        <form method="POST" action="{{ route('admin.license-server.release', [$license, $a]) }}">@csrf @method('DELETE')
                            <button class="text-xs text-red-700 hover:underline">Lösen</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
