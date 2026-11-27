@extends('site.layout')
@section('title', $product->name.' – '.$siteName)
@section('description', $product->short_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155))
@section('meta')
    <link rel="canonical" href="{{ $product->url() }}">
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="og:type" content="product">
    @if ($product->image())<meta property="og:image" content="{{ url($product->image()) }}">@endif
    @unless (\App\Support\Seo\SiteSeo::indexingAllowed())<meta name="robots" content="noindex">@endunless
    @php
        $ld = array_filter([
            '@'.'context' => 'https://schema.org', '@'.'type' => 'Product', 'name' => $product->name, 'sku' => $product->sku,
            'description' => $product->short_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 300),
            'image' => $product->image() ? url($product->image()) : null,
            'offers' => ['@'.'type' => 'Offer', 'url' => $product->url(), 'priceCurrency' => \App\Shop\ShopSettings::currency(),
                'price' => number_format($product->price_cents / 100, 2, '.', ''),
                'availability' => $product->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'],
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav class="text-sm text-slate-500" aria-label="Brotkrumen"><a href="{{ route('shop.index') }}" class="hover:text-slate-800">Shop</a> › <span>{{ $product->name }}</span></nav>
        <div class="mt-6 grid gap-10 md:grid-cols-2">
            <div>
                @php $imgs = $product->images ?? []; @endphp
                @if ($imgs)
                    <img src="{{ $imgs[0] }}" alt="{{ $product->name }}" class="aspect-square w-full rounded-2xl bg-slate-100 object-cover" id="main-image">
                    @if (count($imgs) > 1)
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ($imgs as $img)
                                <button type="button" onclick="document.getElementById('main-image').src=this.dataset.src" data-src="{{ $img }}" class="overflow-hidden rounded-lg border border-slate-200">
                                    <img src="{{ $img }}" alt="" loading="lazy" class="aspect-square w-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="aspect-square w-full rounded-2xl bg-slate-100"></div>
                @endif
            </div>
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ $product->name }}</h1>
                @if ($product->short_description)<p class="mt-2 text-lg text-slate-600">{{ $product->short_description }}</p>@endif
                @include('shop.partials.price', ['product' => $product, 'big' => true, 'class' => 'mt-5'])

                @if ($product->inStock())
                    <p class="mt-4 text-sm text-emerald-700">Lieferbar, Lieferzeit {{ $product->deliveryTime() }}@if ($product->stock !== null && $product->stock <= 5) · nur noch {{ $product->stock }} Stück @endif</p>
                    <form method="POST" action="{{ route('shop.cart.add') }}" class="mt-5 flex items-end gap-3">
                        @csrf <input type="hidden" name="product" value="{{ $product->id }}">
                        <div>
                            <label for="quantity" class="nova-label">Menge</label>
                            <input id="quantity" name="quantity" type="number" min="1" max="{{ min(99, $product->stock ?? 99) }}" value="1" class="nova-input w-20">
                        </div>
                        <button class="nova-btn flex-1 py-3">In den Warenkorb</button>
                    </form>
                @else
                    <p class="mt-4 text-sm font-medium text-red-700">Zurzeit ausverkauft</p>
                @endif
                @if ($waLink = \App\Support\WhatsApp::productLink($product->name, $product->url()))
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#25d366]/40 bg-[#25d366]/10 px-4 py-2.5 text-sm font-semibold text-[#128c4a] hover:bg-[#25d366]/20" data-whatsapp-product>
                        <svg class="size-5" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.7a10.7 10.7 0 0 1-5.5-1.5l-.4-.2-3.9 1 1-3.8-.2-.4A10.7 10.7 0 1 1 16 26.7z"/></svg>
                        Frage per WhatsApp stellen
                    </a>
                @endif
                @if ($product->sku)<p class="mt-4 text-xs text-slate-500">Artikelnummer: {{ $product->sku }}</p>@endif

                @if ($product->description)
                    <div class="nova-prose mt-8">{!! $product->description !!}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
