@php
    $plans = $d['plans'] ?? [];
    $products = \App\Support\Blocks\ShopLinks::products(array_column($plans, 'product'));
@endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6">
    @include('site.blocks.partials.heading')
    <div @class(['mx-auto grid items-stretch gap-6', 'max-w-md' => count($plans) === 1, 'md:grid-cols-2 max-w-4xl' => count($plans) === 2, 'md:grid-cols-3' => count($plans) >= 3])>
        @foreach ($plans as $plan)
            @php
                $product = $plan['product'] ? $products->get($plan['product']) : null;
                $price = $product ? $product->price() : $plan['price'];
                $hi = $plan['highlight'];
            @endphp
            <div @class(['relative flex flex-col rounded-3xl p-7', 'nova-tech-bg text-white shadow-xl shadow-nova-900/20 ring-1 ring-white/10' => $hi, 'border border-slate-200 bg-white' => ! $hi])>
                @if ($hi)<span class="absolute -top-3 left-7 rounded-full bg-gradient-to-r from-nova-500 to-fuchsia-500 px-3 py-1 text-xs font-semibold text-white shadow">Beliebteste Wahl</span>@endif
                <h3 @class(['text-lg font-semibold', 'text-slate-900' => ! $hi])>{{ $plan['name'] }}</h3>
                @if ($plan['text'])<p @class(['mt-1 text-sm', 'text-white/70' => $hi, 'text-slate-600' => ! $hi])>{{ $plan['text'] }}</p>@endif
                <p class="mt-6 flex items-baseline gap-2">
                    <span @class(['text-4xl font-semibold tracking-tight', 'text-slate-900' => ! $hi])>{{ $price }}</span>
                    @if ($plan['period'])<span @class(['text-sm', 'text-white/60' => $hi, 'text-slate-500' => ! $hi])>{{ $plan['period'] }}</span>@endif
                </p>
                @if ($product)<p @class(['mt-1 text-xs', 'text-white/50' => $hi, 'text-slate-500' => ! $hi])>{{ \App\Shop\ShopSettings::taxNote($product->tax_rate) }}</p>@endif
                <ul class="mt-6 flex-1 space-y-2.5 text-sm">
                    @foreach ($plan['features'] as $f)
                        <li class="flex gap-2.5"><x-icon name="check" @class(['mt-0.5 size-4 shrink-0', 'text-fuchsia-300' => $hi, 'text-nova-600' => ! $hi]) /><span>{{ $f }}</span></li>
                    @endforeach
                </ul>
                @php $btn = ['mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 font-semibold', 'bg-white text-slate-900 hover:bg-slate-100' => $hi, 'bg-slate-900 text-white hover:bg-slate-800' => ! $hi]; @endphp
                @if ($product)
                    <form method="POST" action="{{ route('shop.cart.add') }}">
                        @csrf
                        <input type="hidden" name="product" value="{{ $product->id }}">
                        <button @class($btn)>{{ $plan['button_label'] ?: 'In den Warenkorb' }}</button>
                    </form>
                @elseif ($plan['button_label'] && $plan['button_url'])
                    <a href="{{ $plan['button_url'] }}" @class($btn)>{{ $plan['button_label'] }}</a>
                @endif
            </div>
        @endforeach
    </div>
    @if (! empty($d['note']))<p class="mt-6 text-center text-sm text-slate-500">{{ $d['note'] }}</p>@endif
</section>
