@extends('site.layout')
@section('title', 'Shop – '.$siteName)
@section('description', 'Alle Produkte von '.$siteName.' bequem online bestellen.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">Shop</h1>
        @if ($products->isEmpty())
            <p class="mt-6 text-slate-600">Hier gibt es bald unsere Produkte. Schauen Sie gern wieder vorbei.</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $p)
                    <li class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <a href="{{ $p->url() }}" class="block aspect-[4/3] overflow-hidden bg-slate-100">
                            @if ($p->image())<img src="{{ $p->image() }}" alt="{{ $p->name }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105">@endif
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="font-semibold"><a href="{{ $p->url() }}" class="hover:text-nova-700">{{ $p->name }}</a></h2>
                            @if ($p->short_description)<p class="mt-1 text-sm text-slate-600">{{ $p->short_description }}</p>@endif
                            <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                                @include('shop.partials.price', ['product' => $p])
                                @if ($p->inStock())
                                    <form method="POST" action="{{ route('shop.cart.add') }}">
                                        @csrf <input type="hidden" name="product" value="{{ $p->id }}">
                                        <button class="nova-btn shrink-0 px-3 py-2 text-sm" aria-label="{{ $p->name }} in den Warenkorb">In den Warenkorb</button>
                                    </form>
                                @else
                                    <span class="shrink-0 text-sm text-slate-500">Ausverkauft</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
