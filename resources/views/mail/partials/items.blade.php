<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px">
    <tr style="border-bottom:1px solid #e2e8f0;text-align:left"><th>Artikel</th><th style="text-align:right">Menge</th><th style="text-align:right">Preis</th></tr>
    @foreach ($order->items as $item)
        <tr style="border-bottom:1px solid #f1f5f9">
            <td>{{ $item->name }}@if ($item->sku)<br><span style="color:#64748b;font-size:12px">Art.-Nr. {{ $item->sku }}</span>@endif</td>
            <td style="text-align:right">{{ $item->quantity }}</td>
            <td style="text-align:right;white-space:nowrap">{{ $order->money($item->total_cents) }}</td>
        </tr>
    @endforeach
    <tr><td colspan="2">Zwischensumme</td><td style="text-align:right;white-space:nowrap">{{ $order->money($order->subtotal_cents) }}</td></tr>
    @if ($order->discount_cents)<tr><td colspan="2">Rabatt {{ $order->coupon_code }}</td><td style="text-align:right;white-space:nowrap">−{{ $order->money($order->discount_cents) }}</td></tr>@endif
    <tr><td colspan="2">Versand</td><td style="text-align:right;white-space:nowrap">{{ $order->shipping_cents ? $order->money($order->shipping_cents) : 'kostenlos' }}</td></tr>
    <tr style="font-weight:bold;border-top:2px solid #0f172a"><td colspan="2">Gesamtsumme</td><td style="text-align:right;white-space:nowrap">{{ $order->money($order->total_cents) }}</td></tr>
    @if ($order->small_business)
        <tr><td colspan="3" style="color:#64748b;font-size:12px">{{ \App\Shop\ShopSettings::smallBusinessNote() }}</td></tr>
    @else
        @foreach ($order->taxes ?? [] as $t)
            <tr style="color:#64748b;font-size:12px"><td colspan="2">enthaltene MwSt. {{ \App\Shop\ShopSettings::rate($t['rate']) }} %</td><td style="text-align:right;white-space:nowrap">{{ $order->money($t['cents']) }}</td></tr>
        @endforeach
    @endif
</table>
