@php
    $items = $d['items'] ?? [];
    $products = \App\Support\Blocks\ShopLinks::products(array_column($items, 'product'));
    $kinds = ['core' => 'Im Kern enthalten', 'free' => 'Kostenloses Modul', 'paid' => 'Kostenpflichtiges Modul'];
    $counts = collect($items)->countBy('kind');
    $builder = ! empty($d['builder']) && $products->isNotEmpty();
@endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" data-modules>
    @include('site.blocks.partials.heading')
    @if ($counts->count() > 1)
    <div class="mb-6 flex flex-wrap justify-center gap-2" role="toolbar" aria-label="Module filtern">
        <button type="button" class="nova-chip is-on" data-filter="all" aria-pressed="true">Alle <span>{{ count($items) }}</span></button>
        @foreach ($kinds as $k => $label)
            @if ($counts->get($k))
                <button type="button" class="nova-chip" data-filter="{{ $k }}" aria-pressed="false">{{ ['core' => 'Im Kern', 'free' => 'Kostenlos', 'paid' => 'Kostenpflichtig'][$k] }} <span>{{ $counts->get($k) }}</span></button>
            @endif
        @endforeach
    </div>
    @endif
    <form method="POST" action="{{ route('shop.cart.add') }}" @if($builder) data-builder @endif>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $i)
                @php
                    $product = $i['product'] ? $products->get($i['product']) : null;
                    $price = $product ? $product->price() : ($i['kind'] === 'paid' ? $i['price'] : ($i['kind'] === 'free' ? 'kostenlos' : 'inklusive'));
                @endphp
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-nova-300 hover:shadow-md" data-kind="{{ $i['kind'] }}" @if($product) data-price="{{ $product->price_cents }}" @endif>
                    <div class="flex items-start justify-between gap-3">
                        <span @class(['rounded-full px-2.5 py-1 font-mono text-[11px] font-semibold tracking-wide uppercase',
                            'bg-slate-100 text-slate-600' => $i['kind'] === 'core',
                            'bg-emerald-50 text-emerald-700' => $i['kind'] === 'free',
                            'bg-fuchsia-50 text-fuchsia-700' => $i['kind'] === 'paid'])>{{ $kinds[$i['kind']] }}</span>
                        @if ($i['soon'] && ! $product)<span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Bald verfügbar</span>@endif
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $i['name'] }}</h3>
                    @if ($i['category'])<p class="font-mono text-xs text-slate-400">{{ $i['category'] }}</p>@endif
                    <p class="mt-2 flex-1 text-sm text-slate-600">{{ $i['text'] }}</p>
                    <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <span class="font-semibold text-slate-900">{{ $price }}</span>
                        @if ($product)
                            @if ($builder)
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium has-[:checked]:border-nova-600 has-[:checked]:bg-nova-600 has-[:checked]:text-white">
                                    <input type="checkbox" name="products[]" value="{{ $product->id }}" class="sr-only"> Auswählen
                                </label>
                            @else
                                <button name="product" value="{{ $product->id }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">In den Warenkorb</button>
                            @endif
                        @elseif ($i['kind'] === 'free')
                            <span class="text-xs text-slate-500">im Backoffice installierbar</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        @if ($builder)
            <div class="sticky bottom-4 z-20 mx-auto mt-6 flex max-w-xl items-center justify-between gap-4 rounded-2xl bg-slate-900/95 px-5 py-3 text-white shadow-xl backdrop-blur" data-builder-bar hidden>
                <p class="text-sm"><span data-builder-count>0</span> Module ausgewählt · <strong data-builder-sum>0,00 €</strong></p>
                <button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100">In den Warenkorb</button>
            </div>
        @endif
    </form>
</section>
