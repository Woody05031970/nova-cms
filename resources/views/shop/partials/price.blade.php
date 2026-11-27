{{-- Preis mit Pflichtangaben nach Preisangabenverordnung --}}
@php $shipping = \App\Shop\ShopSettings::legalPage('shipping'); @endphp
<div class="{{ $class ?? '' }}">
    <p class="flex flex-wrap items-baseline gap-x-2">
        <span class="{{ $big ?? false ? 'text-3xl' : 'text-lg' }} font-semibold">{{ $product->price() }}</span>
        @if ($product->compare_price_cents && $product->compare_price_cents > $product->price_cents)
            <span class="text-sm text-slate-500 line-through">statt {{ \App\Shop\Money::format($product->compare_price_cents) }}</span>
        @endif
    </p>
    <p class="text-xs text-slate-500">
        {{ \App\Shop\ShopSettings::taxNote($product->tax_rate) }}, zzgl. @if ($shipping)<a href="{{ $shipping->url() }}" class="underline hover:text-slate-700">Versandkosten</a>@else Versandkosten @endif
        @if ($bp = $product->basePrice())<span class="block">Grundpreis: {{ $bp }}</span>@endif
    </p>
</div>
