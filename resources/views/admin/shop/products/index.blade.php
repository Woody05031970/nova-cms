@extends('layouts.admin')
@section('title', 'Produkte')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
            <p class="mt-1 text-slate-600">Ihre Produkte mit Preis und Lagerbestand.</p>
        </div>
        <a href="{{ route('admin.shop.products.create') }}" class="nova-btn">+ Neues Produkt</a>
    </div>
    @include('admin.shop.nav')

    <section class="nova-card mt-6 p-0">
        <form method="GET" class="border-b border-slate-100 px-5 py-3">
            <input type="search" name="suche" value="{{ $search }}" placeholder="Name oder Artikelnummer" class="nova-input w-64 py-1.5 text-sm" aria-label="Produkte durchsuchen">
        </form>
        @if ($products->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $search ? 'Kein Produkt gefunden.' : 'Noch keine Produkte. Legen Sie Ihr erstes an.' }}</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($products as $p)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 hover:bg-slate-50">
                        <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if ($p->image())<img src="{{ $p->image() }}" alt="" class="size-full object-cover">@endif</span>
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('admin.shop.products.edit', $p) }}" class="font-medium hover:text-nova-700">{{ $p->name }}</a>
                            @unless ($p->is_active)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">ausgeblendet</span>@endunless
                            <span class="block text-xs text-slate-500">{{ $p->sku ? 'Art.-Nr. '.$p->sku.' · ' : '' }}{{ \App\Shop\ShopSettings::rate($p->tax_rate) }} % MwSt.</span>
                        </span>
                        <span @class(['w-28 text-sm', 'text-red-700' => $p->stock !== null && $p->stock <= 0, 'text-amber-700' => $p->stock !== null && $p->stock > 0 && $p->stock <= 5, 'text-slate-600' => $p->stock === null || $p->stock > 5])>
                            {{ $p->stock === null ? 'unbegrenzt' : ($p->stock <= 0 ? 'ausverkauft' : $p->stock.' auf Lager') }}</span>
                        <span class="w-24 text-right font-medium">{{ $p->price() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 py-3">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
