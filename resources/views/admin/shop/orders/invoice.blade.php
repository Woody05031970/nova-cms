<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Rechnung {{ $order->number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 18mm 16mm; } @media print { .no-print { display: none } body { background: #fff } }</style>
</head>
<body class="bg-slate-100 font-sans text-[13px] text-slate-900">
    <div class="no-print mx-auto flex max-w-[210mm] justify-end gap-2 px-4 py-4">
        <button onclick="window.print()" class="nova-btn">Drucken oder als PDF speichern</button>
    </div>
    <main class="mx-auto max-w-[210mm] bg-white p-12 shadow-sm print:p-0 print:shadow-none">
        <div class="flex justify-between gap-6">
            <div class="text-xs text-slate-500">{{ $s('company_name') ?? $s('site_name') }} · {{ $s('street') }} · {{ $s('zip_city') }}</div>
            <div class="text-right">
                <p class="text-lg font-semibold">{{ $s('company_name') ?? $s('site_name') }}</p>
                <p>{{ $s('street') }}<br>{{ $s('zip_city') }}</p>
                @if ($s('phone'))<p>Tel. {{ $s('phone') }}</p>@endif
                @if ($s('contact_email'))<p>{{ $s('contact_email') }}</p>@endif
            </div>
        </div>
        @php $ba = $order->billing ?: ['name' => $order->name, 'company' => $order->company, 'street' => $order->street, 'zip' => $order->zip, 'city' => $order->city, 'country' => $order->country]; @endphp
        <p class="mt-6">{{ $ba['name'] ?? '' }}<br>@if (! empty($ba['company'])){{ $ba['company'] }}<br>@endif{{ $ba['street'] ?? '' }}<br>{{ $ba['zip'] ?? '' }} {{ $ba['city'] ?? '' }}<br>{{ \App\Shop\ShopSettings::COUNTRIES[$ba['country'] ?? 'DE']['label'] ?? ($ba['country'] ?? '') }}</p>
        @if ($order->billing)<p class="mt-2 text-xs text-slate-500">Lieferung an: {{ $order->name }}, {{ $order->street }}, {{ $order->zip }} {{ $order->city }}</p>@endif

        <div class="mt-10 flex flex-wrap justify-between gap-4">
            <h1 class="text-2xl font-semibold">Rechnung</h1>
            <table class="text-right text-sm"><tr><td class="pr-3 text-slate-500">Rechnungsnummer</td><td>{{ $order->number }}</td></tr>
                <tr><td class="pr-3 text-slate-500">Rechnungsdatum</td><td>{{ $order->created_at->format('d.m.Y') }}</td></tr>
                <tr><td class="pr-3 text-slate-500">Lieferdatum</td><td>{{ ($order->shipped_at ?? $order->created_at)->format('d.m.Y') }}</td></tr></table>
        </div>

        <table class="mt-8 w-full text-left">
            <thead class="border-b-2 border-slate-900 text-xs"><tr><th class="py-2">Pos.</th><th>Artikel</th><th class="text-right">Menge</th>@unless ($order->small_business)<th class="text-right">MwSt.</th>@endunless<th class="text-right">Einzelpreis</th><th class="text-right">Gesamt</th></tr></thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($order->items as $item)
                    <tr><td class="py-2">{{ $loop->iteration }}</td><td>{{ $item->name }}@if ($item->sku)<span class="block text-xs text-slate-500">Art.-Nr. {{ $item->sku }}</span>@endif</td>
                        <td class="text-right">{{ $item->quantity }}</td>@unless ($order->small_business)<td class="text-right">{{ \App\Shop\ShopSettings::rate($item->tax_rate) }} %</td>@endunless
                        <td class="text-right">{{ $order->money($item->price_cents) }}</td><td class="text-right">{{ $order->money($item->total_cents) }}</td></tr>
                @endforeach
                @if ($order->discount_cents)<tr><td></td><td>Rabatt {{ $order->coupon_code }}</td><td colspan="{{ $order->small_business ? 2 : 3 }}"></td><td class="text-right">−{{ $order->money($order->discount_cents) }}</td></tr>@endif
                <tr><td></td><td>Versand</td><td colspan="{{ $order->small_business ? 2 : 3 }}"></td><td class="text-right">{{ $order->money($order->shipping_cents) }}</td></tr>
            </tbody>
        </table>
        <div class="mt-4 ml-auto w-72 space-y-1">
            @php $tax = collect($order->taxes ?? [])->sum('cents'); @endphp
            @unless ($order->small_business)
                <div class="flex justify-between"><span>Summe netto</span><span>{{ $order->money($order->total_cents - $tax) }}</span></div>
                @foreach ($order->taxes ?? [] as $t)
                    <div class="flex justify-between"><span>MwSt. {{ \App\Shop\ShopSettings::rate($t['rate']) }} %</span><span>{{ $order->money($t['cents']) }}</span></div>
                @endforeach
            @endunless
            <div class="flex justify-between border-t-2 border-slate-900 pt-1 text-base font-semibold"><span>Rechnungsbetrag</span><span>{{ $order->money($order->total_cents) }}</span></div>
        </div>

        <div class="mt-10 space-y-2 text-sm">
            @if ($order->small_business)<p>{{ \App\Shop\ShopSettings::smallBusinessNote() }}</p>@endif
            <p>Zahlungsart: {{ $payment?->label() ?? $order->payment_method }}. {{ $order->isPaid() ? 'Der Betrag wurde am '.$order->paid_at?->format('d.m.Y').' bezahlt. Vielen Dank!' : 'Bitte überweisen Sie den Betrag innerhalb von 14 Tagen unter Angabe der Rechnungsnummer.' }}</p>
        </div>

        <footer class="mt-16 grid grid-cols-3 gap-4 border-t border-slate-200 pt-3 text-[11px] text-slate-500">
            <p>{{ $s('company_name') ?? $s('site_name') }}<br>{{ $s('street') }}<br>{{ $s('zip_city') }}</p>
            <p>@if ($s('shop_vat_id'))USt-IdNr.: {{ $s('shop_vat_id') }}<br>@endif @if ($s('contact_email')){{ $s('contact_email') }}@endif</p>
            <p>@if ($s('shop_iban'))IBAN: {{ $s('shop_iban') }}<br>@endif @if ($s('shop_bic'))BIC: {{ $s('shop_bic') }}@endif</p>
        </footer>
    </main>
</body>
</html>
