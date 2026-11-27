@extends('layouts.admin')
@section('title', 'Gutscheine und Rabatte')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Gutscheine und Rabatte</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Rabattcodes für Aktionen und Geschenkgutscheine mit Guthaben. Kunden geben den Code im Warenkorb ein. Gutscheine zum Verkaufen legen Sie als Produkt an und setzen dort das Häkchen „Geschenkgutschein“: Nach der Zahlung bekommt der Käufer den Code per E-Mail.</p>
        </div>
        @if ($open > 0)<p class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-800">Offenes Guthaben: {{ \App\Shop\Money::format($open) }}</p>@endif
    </div>

    <section class="nova-card mt-8" aria-labelledby="c-new">
        <h2 id="c-new" class="font-semibold">Neuer Code</h2>
        <form method="POST" action="{{ route('admin.coupons.store') }}" class="mt-4 grid gap-4 sm:grid-cols-6" x-data="{ kind: '{{ old('kind', 'percent') }}', gift: {{ old('is_gift') ? 'true' : 'false' }} }" data-coupon-new>
            @csrf
            <div class="sm:col-span-2">
                <label class="nova-label" for="code">Code</label>
                <input class="nova-input font-mono uppercase" id="code" name="code" maxlength="40" value="{{ old('code') }}" placeholder="leer = automatisch" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')<p id="code-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2" x-show="!gift">
                <label class="nova-label" for="kind">Art</label>
                <select class="nova-input" id="kind" name="kind" x-model="kind">
                    @foreach (\App\Models\Coupon::KINDS as $k => $label)<option value="{{ $k }}" @selected(old('kind', 'percent') === $k)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2" x-show="gift || kind !== 'shipping'">
                <label class="nova-label" for="value"><span x-text="gift ? 'Guthaben (€)' : (kind === 'percent' ? 'Rabatt in %' : 'Betrag (€)')">Wert</span></label>
                <input class="nova-input" id="value" name="value" inputmode="decimal" value="{{ old('value') }}" placeholder="z. B. 10" @error('value') aria-invalid="true" aria-describedby="value-error" @enderror>
                @error('value')<p id="value-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><label class="nova-label" for="min_order">Mindestbestellwert (€)</label><input class="nova-input" id="min_order" name="min_order" inputmode="decimal" value="{{ old('min_order') }}" placeholder="optional"></div>
            <div><label class="nova-label" for="starts_at">Gültig ab</label><input class="nova-input" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at') }}"></div>
            <div>
                <label class="nova-label" for="ends_at">Gültig bis</label><input class="nova-input" type="date" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" @error('ends_at') aria-invalid="true" aria-describedby="ends-error" @enderror>
                @error('ends_at')<p id="ends-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2" x-show="!gift"><label class="nova-label" for="max_uses">Wie oft einlösbar?</label><input class="nova-input" type="number" id="max_uses" name="max_uses" min="1" value="{{ old('max_uses') }}" placeholder="leer = unbegrenzt"></div>
            <div class="sm:col-span-4"><label class="nova-label" for="note">Notiz</label><input class="nova-input" id="note" name="note" maxlength="255" value="{{ old('note') }}" placeholder="z. B. Herbstaktion Newsletter"></div>
            <label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" name="is_gift" value="1" class="accent-nova-600" x-model="gift" @checked(old('is_gift'))> Geschenkgutschein mit Guthaben (Restwert bleibt für den nächsten Einkauf)</label>
            <div class="sm:col-span-2 sm:text-right"><button class="nova-btn w-full sm:w-auto">Code anlegen</button></div>
        </form>
    </section>

    <section class="nova-card mt-6 p-0" aria-labelledby="c-list">
        <div class="flex flex-wrap items-center gap-2 px-6 pt-5">
            <h2 id="c-list" class="sr-only">Vorhandene Codes</h2>
            <a href="{{ route('admin.coupons') }}" @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-nova-600 text-white' => ! $gift, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => $gift]) @if(! $gift) aria-current="page" @endif>Rabattcodes ({{ $counts['codes'] }})</a>
            <a href="{{ route('admin.coupons', ['art' => 'geschenk']) }}" @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-nova-600 text-white' => $gift, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! $gift]) @if($gift) aria-current="page" @endif>Geschenkgutscheine ({{ $counts['gifts'] }})</a>
        </div>
        @if ($coupons->isEmpty())
            <p class="px-6 pt-3 pb-6 text-sm text-slate-600">{{ $gift ? 'Noch keine Geschenkgutscheine.' : 'Noch keine Rabattcodes.' }}</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm" data-coupons>
                    <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-2 font-medium">Code</th><th class="px-3 py-2 font-medium">{{ $gift ? 'Restguthaben' : 'Rabatt' }}</th><th class="px-3 py-2 font-medium">Bedingungen</th><th class="px-3 py-2 font-medium">Eingelöst</th><th class="px-6 py-2"><span class="sr-only">Aktionen</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($coupons as $c)
                            @php
                                $state = match (true) {
                                    ! $c->is_active => ['Pausiert', 'bg-slate-100 text-slate-600'],
                                    $c->ends_at && $c->ends_at->isPast() => ['Abgelaufen', 'bg-slate-100 text-slate-600'],
                                    $c->is_gift && $c->value <= 0 => ['Aufgebraucht', 'bg-slate-100 text-slate-600'],
                                    $c->max_uses && $c->used >= $c->max_uses => ['Aufgebraucht', 'bg-slate-100 text-slate-600'],
                                    $c->starts_at && $c->starts_at->isFuture() => ['Geplant', 'bg-amber-50 text-amber-900'],
                                    default => ['Aktiv', 'bg-emerald-50 text-emerald-800'],
                                };
                                $rules = array_filter([
                                    $c->min_order_cents ? 'ab '.\App\Shop\Money::format($c->min_order_cents) : null,
                                    $c->starts_at ? 'ab '.$c->starts_at->format('d.m.Y') : null,
                                    $c->ends_at ? 'bis '.$c->ends_at->format('d.m.Y') : null,
                                ]);
                            @endphp
                            <tr data-coupon="{{ $c->code }}">
                                <td class="px-6 py-3"><span class="font-mono font-semibold">{{ $c->code }}</span> <span class="ml-1 rounded px-1.5 py-0.5 text-xs {{ $state[1] }}">{{ $state[0] }}</span>@if($c->note)<p class="text-xs text-slate-500">{{ $c->note }}</p>@endif</td>
                                <td class="px-3 py-3">{{ $c->label() }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $rules ? implode(', ', $rules) : '–' }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $c->used }}{{ $c->max_uses ? ' von '.$c->max_uses : '' }}×</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-3">
                                        <form method="POST" action="{{ route('admin.coupons.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="toggle" value="1"><button class="text-sm text-nova-700 hover:underline">{{ $c->is_active ? 'Pausieren' : 'Aktivieren' }}</button></form>
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $c) }}" onsubmit="return confirm('Code löschen?')">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Löschen</button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@push('head') @vite('resources/js/app.js') @endpush
