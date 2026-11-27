@extends('site.layout')
@section('title', 'Warenkorb – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">Warenkorb</h1>
        <div class="mt-6"><x-alerts /></div>

        @if ($items->isEmpty())
            <p class="text-slate-600">Ihr Warenkorb ist leer.</p>
            <a href="{{ route('shop.index') }}" class="nova-btn mt-6">Zum Shop</a>
        @else
            <form method="POST" action="{{ route('shop.cart.update') }}" class="rounded-2xl border border-slate-200">
                @csrf @method('PATCH')
                <ul class="divide-y divide-slate-100">
                    @foreach ($items as $i)
                        @php $p = $i['product']; @endphp
                        <li class="flex flex-wrap items-center gap-4 p-4">
                            <a href="{{ $p->url() }}" class="size-16 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if ($p->image())<img src="{{ $p->image() }}" alt="" class="size-full object-cover">@endif</a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $p->url() }}" class="font-medium hover:text-nova-700">{{ $p->name }}</a>
                                <p class="text-xs text-slate-500">{{ $p->price() }} je Stück · Lieferzeit {{ $p->deliveryTime() }}</p>
                                @if ($i['limited'])<p class="text-xs text-amber-700">Nur noch {{ $p->stock }} Stück vorrätig, die Menge wurde angepasst.</p>@endif
                            </div>
                            <label class="sr-only" for="q{{ $p->id }}">Menge</label>
                            <input id="q{{ $p->id }}" name="quantities[{{ $p->id }}]" type="number" min="0" max="{{ min(99, $p->stock ?? 99) }}" value="{{ $i['quantity'] }}" class="nova-input w-20" onchange="this.form.submit()">
                            <span class="w-24 text-right font-medium">{{ \App\Shop\Money::format($i['total']) }}</span>
                            <button name="remove" value="{{ $p->id }}" class="text-sm text-slate-500 hover:text-red-700" aria-label="{{ $p->name }} entfernen">✕</button>
                        </li>
                    @endforeach
                </ul>
                <noscript><div class="border-t border-slate-100 p-4"><button class="nova-btn-secondary">Aktualisieren</button></div></noscript>
            </form>

            <div class="mt-6 ml-auto max-w-sm">
                @if (\App\Licensing\Features::on('gutscheine'))
                    <div class="mb-5 rounded-2xl border border-slate-200 p-4" id="gutschein">
                        @if ($totals['coupon'])
                            <form method="POST" action="{{ route('shop.coupon.remove') }}" class="flex items-center justify-between gap-3 text-sm">@csrf @method('DELETE')
                                <span>Code <strong class="font-mono">{{ $totals['coupon']->code }}</strong> eingelöst</span>
                                <button class="text-slate-600 underline hover:text-red-700">Entfernen</button></form>
                        @else
                            <form method="POST" action="{{ route('shop.coupon') }}" data-coupon-form>
                                @csrf
                                <label class="nova-label" for="code">Gutschein oder Rabattcode</label>
                                <div class="flex gap-2">
                                    <input class="nova-input font-mono uppercase" id="code" name="code" value="{{ old('code') }}" maxlength="40" autocomplete="off" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                                    <button class="nova-btn-secondary shrink-0">Einlösen</button>
                                </div>
                                @error('code')<p id="code-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </form>
                        @endif
                    </div>
                @endif
                @include('shop.partials.totals')
                @if ($totals['free_shipping_missing'])
                    <p class="mt-3 rounded-lg bg-nova-50 px-3 py-2 text-sm text-nova-800">Noch {{ \App\Shop\Money::format($totals['free_shipping_missing']) }}, dann liefern wir versandkostenfrei.</p>
                @endif
                <a href="{{ route('shop.checkout') }}" class="nova-btn mt-5 w-full justify-center py-3">Zur Kasse</a>
                <a href="{{ route('shop.index') }}" class="mt-3 block text-center text-sm text-slate-600 hover:text-slate-900">Weiter einkaufen</a>
            </div>
        @endif
    </div>
@endsection
