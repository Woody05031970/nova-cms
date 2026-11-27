@extends('site.layout')
@section('title', 'Bestellung '.$order->number.' – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <x-alerts />
        @if (request('abgebrochen'))
            <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Die Zahlung wurde abgebrochen. Ihre Bestellung ist gespeichert, Sie können unten erneut bezahlen.</p>
        @endif
        <h1 class="text-3xl font-semibold tracking-tight">Vielen Dank für Ihre Bestellung!</h1>
        <p class="mt-2 text-slate-600">Bestellnummer <strong class="text-slate-900">{{ $order->number }}</strong>. Eine Bestätigung haben wir an {{ $order->email }} geschickt.</p>

        <section class="mt-8 rounded-2xl border border-slate-200 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Zahlung: {{ $method?->label() ?? $order->payment_method }}</h2>
                <span @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-emerald-50 text-emerald-700' => $order->isPaid(), 'bg-amber-50 text-amber-700' => ! $order->isPaid()])>{{ $order->isPaid() ? 'Bezahlt' : 'Offen' }}</span>
            </div>
            @if ($instructions && ! $order->isPaid())<div class="nova-prose mt-3 text-sm">{!! $instructions !!}</div>@endif
            @if ($canPayOnline)
                <form method="POST" action="{{ route('shop.pay', $order->token) }}" class="mt-4">
                    @csrf <button class="nova-btn">Jetzt bezahlen</button>
                </form>
            @endif
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold">Ihre Artikel</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3"><span>{{ $item->quantity }} × {{ $item->name }}</span><span>{{ $order->money($item->total_cents) }}</span></li>
                @endforeach
            </ul>
            <dl class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-sm">
                <div class="flex justify-between"><dt>Versand</dt><dd>{{ $order->shipping_cents ? $order->money($order->shipping_cents) : 'kostenlos' }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Gesamtsumme</dt><dd>{{ $order->money($order->total_cents) }}</dd></div>
            </dl>
        </section>
        <a href="{{ route('shop.index') }}" class="mt-8 inline-block text-sm text-nova-700 hover:underline">Weiter einkaufen</a>
    </div>
@endsection
