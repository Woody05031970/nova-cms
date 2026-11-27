<dl class="space-y-1.5 text-sm">
    <div class="flex justify-between"><dt>Zwischensumme</dt><dd>{{ \App\Shop\Money::format($totals['subtotal']) }}</dd></div>
    @if (! empty($totals['discount']))
        <div class="flex justify-between text-emerald-700" data-discount><dt>{{ $totals['coupon']?->is_gift ? 'Gutschein' : 'Rabatt' }} {{ $totals['coupon']?->code }}</dt><dd>−{{ \App\Shop\Money::format($totals['discount']) }}</dd></div>
    @endif
    <div class="flex justify-between"><dt>Versand</dt><dd>{{ $totals['shipping'] ? \App\Shop\Money::format($totals['shipping']) : 'kostenlos' }}</dd></div>
    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Gesamtsumme</dt><dd data-total>{{ \App\Shop\Money::format($totals['total']) }}</dd></div>
    @if (\App\Shop\ShopSettings::smallBusiness())
        <p class="text-xs text-slate-500">{{ \App\Shop\ShopSettings::smallBusinessNote() }}</p>
    @else
        @foreach ($totals['taxes'] as $t)
            <div class="flex justify-between text-xs text-slate-500"><dt>enthaltene MwSt. {{ \App\Shop\ShopSettings::rate($t['rate']) }} %</dt><dd>{{ \App\Shop\Money::format($t['cents']) }}</dd></div>
        @endforeach
    @endif
</dl>
