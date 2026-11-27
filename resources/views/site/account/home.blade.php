@extends('site.layout')
@section('title', 'Meine Bestellungen – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('content')
    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        @include('site.account._nav', ['heading' => 'Hallo '.$user->name])
        <h2 class="mt-10 text-lg font-semibold">Meine Bestellungen</h2>
        @if ($orders->isEmpty())
            <p class="mt-3 text-slate-600">Noch keine Bestellungen. <a href="{{ route('shop.index') }}" class="text-nova-700 hover:underline">Zum Shop</a></p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white" data-account-orders>
                @foreach ($orders as $o)
                    <li class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium">Bestellung {{ $o->number }} <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $o->statusLabel() }}</span>@unless($o->isPaid())<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-900">Zahlung offen</span>@endunless</p>
                            <p class="text-sm text-slate-600">{{ $o->created_at->format('d.m.Y') }} · {{ $o->items->sum('quantity') }} Artikel · {{ $o->money($o->total_cents) }}@if($o->tracking_number) · Sendungsnummer {{ $o->tracking_number }}@endif</p>
                        </div>
                        <a href="{{ route('shop.order', $o->token) }}" class="text-sm font-medium text-nova-700 hover:underline">Details<span class="sr-only"> zu Bestellung {{ $o->number }}</span></a>
                    </li>
                @endforeach
            </ul>
        @endif
        @php $bill = $user->billing_address; @endphp
        <h2 class="mt-10 text-lg font-semibold">Gespeicherte Adresse</h2>
        @if ($bill)
            <p class="mt-2 text-slate-600">{{ $bill['name'] ?? $user->name }}@if(! empty($bill['company'])), {{ $bill['company'] }}@endif<br>{{ $bill['street'] ?? '' }}<br>{{ $bill['zip'] ?? '' }} {{ $bill['city'] ?? '' }}</p>
        @else
            <p class="mt-2 text-slate-600">Noch keine Adresse hinterlegt.</p>
        @endif
        <a href="{{ route('account.edit') }}" class="mt-2 inline-block text-sm font-medium text-nova-700 hover:underline">Daten und Adressen bearbeiten</a>
    </section>
@endsection
