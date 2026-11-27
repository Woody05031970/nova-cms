@extends('layouts.admin')
@section('title', 'Bestellung '.$order->number)
@section('width', 'max-w-6xl')

@section('content')
    <a href="{{ route('admin.shop.index') }}" class="text-sm text-slate-500 hover:text-slate-800">‹ Alle Bestellungen</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Bestellung {{ $order->number }}</h1>
        <a href="{{ route('admin.shop.orders.invoice', $order) }}" target="_blank" class="nova-btn-secondary">Rechnung drucken</a>
    </div>
    <p class="mt-1 text-slate-600">vom {{ $order->created_at->format('d.m.Y, H:i') }} Uhr · Status: <strong data-status>{{ $order->statusLabel() }}</strong></p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            <section class="nova-card p-0">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-500"><tr><th class="px-5 py-2 font-medium">Artikel</th><th class="px-3 py-2 text-right font-medium">Menge</th><th class="px-3 py-2 text-right font-medium">Einzelpreis</th><th class="px-5 py-2 text-right font-medium">Summe</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-5 py-2.5">@if ($item->product)<a href="{{ route('admin.shop.products.edit', $item->product) }}" class="hover:text-nova-700">{{ $item->name }}</a>@else{{ $item->name }}@endif
                                    @if ($item->sku)<span class="block text-xs text-slate-500">Art.-Nr. {{ $item->sku }}</span>@endif</td>
                                <td class="px-3 py-2.5 text-right">{{ $item->quantity }}</td>
                                <td class="px-3 py-2.5 text-right">{{ $order->money($item->price_cents) }}</td>
                                <td class="px-5 py-2.5 text-right">{{ $order->money($item->total_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="space-y-1 border-t border-slate-100 px-5 py-3 text-sm">
                    @if ($order->discount_cents)<div class="flex justify-between"><dt>Rabatt {{ $order->coupon_code }}</dt><dd>−{{ $order->money($order->discount_cents) }}</dd></div>@endif
                    <div class="flex justify-between"><dt>Versand</dt><dd>{{ $order->shipping_cents ? $order->money($order->shipping_cents) : 'kostenlos' }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Gesamtsumme</dt><dd>{{ $order->money($order->total_cents) }}</dd></div>
                    @foreach ($order->taxes ?? [] as $t)
                        <div class="flex justify-between text-xs text-slate-500"><dt>enthaltene MwSt. {{ \App\Shop\ShopSettings::rate($t['rate']) }} %</dt><dd>{{ $order->money($t['cents']) }}</dd></div>
                    @endforeach
                </dl>
            </section>

            <div class="grid gap-6 sm:grid-cols-2">
                <section class="nova-card text-sm">
                    <h2 class="font-semibold">Lieferadresse</h2>
                    <p class="mt-2">{{ $order->name }}<br>@if ($order->company){{ $order->company }}<br>@endif{{ $order->street }}<br>{{ $order->zip }} {{ $order->city }}<br>{{ \App\Shop\ShopSettings::COUNTRIES[$order->country]['label'] ?? $order->country }}</p>
                    @if ($order->billing)<p class="mt-3 text-sm"><span class="font-medium">Rechnungsadresse:</span><br>{{ $order->billing['name'] ?? '' }}<br>@if (! empty($order->billing['company'])){{ $order->billing['company'] }}<br>@endif{{ $order->billing['street'] ?? '' }}<br>{{ $order->billing['zip'] ?? '' }} {{ $order->billing['city'] ?? '' }}</p>@endif
                    @if ($order->user_id)<p class="mt-2 text-xs text-slate-500">Bestellt mit Kundenkonto</p>@endif
                    <p class="mt-2"><a href="mailto:{{ $order->email }}" class="text-nova-700 hover:underline">{{ $order->email }}</a>@if ($order->phone)<br>{{ $order->phone }}@endif</p>
                </section>
                <section class="nova-card text-sm">
                    <h2 class="font-semibold">Zahlung</h2>
                    <p class="mt-2">{{ $payment?->label() ?? $order->payment_method }}</p>
                    <p class="mt-1"><span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-emerald-50 text-emerald-700' => $order->isPaid(), 'bg-amber-50 text-amber-700' => ! $order->isPaid()])>{{ $order->isPaid() ? 'Bezahlt am '.$order->paid_at?->format('d.m.Y') : 'Offen' }}</span></p>
                    @if ($order->payment_reference)<p class="mt-2 font-mono text-xs break-all text-slate-500">{{ $order->payment_reference }}</p>@endif
                    <p class="mt-2 text-xs text-slate-500">AGB bestätigt: {{ $order->terms_accepted_at?->format('d.m.Y H:i') }}</p>
                </section>
            </div>
            @if ($order->notes)
                <section class="nova-card text-sm"><h2 class="font-semibold">Anmerkung des Kunden</h2><p class="mt-2 whitespace-pre-line">{{ $order->notes }}</p></section>
            @endif
        </div>

        <aside class="space-y-4">
            @if ($order->status !== 'cancelled')
                <section class="nova-card space-y-3">
                    <h2 class="font-semibold">Nächster Schritt</h2>
                    @unless ($order->isPaid())
                        <form method="POST" action="{{ route('admin.shop.orders.update', $order) }}">@csrf @method('PUT')
                            <input type="hidden" name="action" value="paid"><button class="nova-btn w-full justify-center">Zahlung ist eingegangen</button></form>
                    @endunless
                    @if (in_array($order->status, ['new', 'processing']))
                        <form method="POST" action="{{ route('admin.shop.orders.update', $order) }}" class="space-y-2 rounded-lg border border-slate-200 p-3">@csrf @method('PUT')
                            <input type="hidden" name="action" value="shipped">
                            <label class="nova-label" for="tracking_number">Sendungsnummer (freiwillig)</label>
                            <input class="nova-input" id="tracking_number" name="tracking_number" maxlength="80">
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="size-4"> Kunden per E-Mail informieren</label>
                            <button class="{{ $order->isPaid() || $order->payment_method === 'invoice' ? 'nova-btn' : 'nova-btn-secondary' }} w-full justify-center">Als versendet markieren</button>
                        </form>
                    @endif
                    @if ($order->status === 'shipped')
                        <p class="text-sm text-slate-600">Versendet am {{ $order->shipped_at?->format('d.m.Y') }}@if ($order->tracking_number), Sendungsnummer {{ $order->tracking_number }}@endif.</p>
                        <form method="POST" action="{{ route('admin.shop.orders.update', $order) }}">@csrf @method('PUT')
                            <input type="hidden" name="action" value="completed"><button class="nova-btn w-full justify-center">Abschließen</button></form>
                    @endif
                    @if ($order->status !== 'completed')
                        <form method="POST" action="{{ route('admin.shop.orders.update', $order) }}" onsubmit="return confirm('Bestellung wirklich stornieren? Der Lagerbestand wird zurückgebucht.')">@csrf @method('PUT')
                            <input type="hidden" name="action" value="cancelled"><button class="w-full text-center text-sm text-red-700 hover:underline">Stornieren</button></form>
                    @endif
                </section>
            @else
                <p class="rounded-xl bg-slate-100 p-4 text-sm text-slate-600">Diese Bestellung ist storniert.</p>
            @endif
        </aside>
    </div>
@endsection
