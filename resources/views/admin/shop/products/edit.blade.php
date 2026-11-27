@extends('layouts.admin')
@section('title', $product->exists ? $product->name : 'Neues Produkt')
@section('width', 'max-w-6xl')
@push('head') @vite('resources/js/product.js') @endpush

@php
    $cfg = [
        'images' => old('images', $product->images ?? []),
        'description' => old('description', $product->description),
        'short' => old('short_description', $product->short_description),
        'name' => old('name', $product->name),
        'aiUrl' => route('admin.ai.product'),
    ];
    $euro = fn ($c) => $c === null ? '' : number_format($c / 100, 2, ',', '');
    $tb = 'rounded px-2 py-1 text-sm hover:bg-slate-100';
    $on = 'bg-nova-50 text-nova-700';
    $aiOn = app(\App\Ai\AiManager::class)->isConfigured();
@endphp

@section('content')
    <a href="{{ route('admin.shop.products.index') }}" class="text-sm text-slate-500 hover:text-slate-800">‹ Alle Produkte</a>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $product->exists ? $product->name : 'Neues Produkt' }}</h1>

    <form method="POST" action="{{ $product->exists ? route('admin.shop.products.update', $product) : route('admin.shop.products.store') }}"
          x-data="productForm(@js($cfg))" class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        @csrf @if ($product->exists) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            <section class="nova-card space-y-4">
                <div>
                    <label class="nova-label" for="name">Produktname</label>
                    <input class="nova-input" id="name" name="name" required maxlength="160" x-model="name">
                </div>
                <div>
                    <label class="nova-label" for="short_description">Kurzbeschreibung</label>
                    <input class="nova-input" id="short_description" name="short_description" maxlength="300" x-model="short" placeholder="Ein Satz, der in der Produktübersicht erscheint">
                </div>
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="nova-label">Beschreibung</span>
                        @if ($aiOn)
                            <button type="button" class="inline-flex items-center gap-1 rounded-md bg-nova-50 px-2.5 py-1 text-sm font-medium text-nova-700 hover:bg-nova-100 disabled:opacity-60"
                                    @click="writeWithAi()" :disabled="aiBusy" title="Die KI schreibt Kurzbeschreibung und Beschreibung. Ihre Stichpunkte im Text werden berücksichtigt.">
                                <x-icon name="sparkles" class="size-4" /><span x-text="aiBusy ? 'KI schreibt …' : 'Texte mit KI schreiben'"></span>
                            </button>
                        @else
                            <a href="{{ route('admin.ai') }}" class="text-xs text-slate-500 hover:text-nova-700">KI einrichten, um Texte schreiben zu lassen</a>
                        @endif
                    </div>
                    <p x-show="aiError" x-text="aiError" class="mt-1 text-sm text-red-700"></p>
                    <div class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 focus-within:border-nova-500 focus-within:ring-2 focus-within:ring-nova-100">
                        <div class="mb-2 flex flex-wrap gap-1 border-b border-slate-100 pb-2" role="toolbar" aria-label="Textformat">
                            <button type="button" class="{{ $tb }} font-bold" :class="active('bold') && '{{ $on }}'" @click="run('toggleBold')" title="Fett">F</button>
                            <button type="button" class="{{ $tb }} italic" :class="active('italic') && '{{ $on }}'" @click="run('toggleItalic')" title="Kursiv">K</button>
                            <button type="button" class="{{ $tb }}" :class="active('heading', {level: 3}) && '{{ $on }}'" @click="run('toggleHeading', {level: 3})" title="Überschrift">Ü</button>
                            <button type="button" class="{{ $tb }}" :class="active('bulletList') && '{{ $on }}'" @click="run('toggleBulletList')" title="Aufzählung">• Liste</button>
                        </div>
                        <div x-ref="ed"></div>
                    </div>
                    <p class="nova-help">Tipp: Schreiben Sie ein paar Stichpunkte (Material, Größe, Besonderheiten) und lassen Sie dann die KI daraus einen Text machen.</p>
                    <input type="hidden" name="description" :value="description">
                </div>
            </section>

            <section class="nova-card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold">Bilder</h2>
                    <div class="flex gap-2">
                        <button type="button" class="nova-btn-secondary px-3 py-1.5 text-sm" @click="pickImages()">Aus der Mediathek</button>
                        <label class="nova-btn-secondary cursor-pointer px-3 py-1.5 text-sm">
                            <span x-text="uploading ? 'Lädt hoch …' : 'Hochladen'"></span>
                            <input type="file" accept="image/*" multiple class="sr-only" @change="uploadImages([...$event.target.files]); $event.target.value = ''">
                        </label>
                    </div>
                </div>
                <p x-show="!images.length" class="mt-3 text-sm text-slate-500">Noch keine Bilder. Das erste Bild ist das Hauptbild im Shop.</p>
                <ul class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    <template x-for="(url, i) in images" :key="url + i">
                        <li class="group relative overflow-hidden rounded-lg border border-slate-200">
                            <img :src="url" alt="" class="aspect-square w-full object-cover">
                            <input type="hidden" name="images[]" :value="url">
                            <span x-show="i === 0" class="absolute top-1 left-1 rounded bg-nova-600 px-1.5 py-0.5 text-[11px] font-medium text-white">Hauptbild</span>
                            <div class="absolute inset-x-0 bottom-0 flex justify-between bg-white/90 px-1 py-0.5 text-sm">
                                <button type="button" class="px-1.5 hover:text-nova-700" @click="move(i, -1)" title="Nach vorne" aria-label="Nach vorne">‹</button>
                                <button type="button" class="px-1.5 text-red-700" @click="images.splice(i, 1)" title="Entfernen" aria-label="Bild entfernen">✕</button>
                                <button type="button" class="px-1.5 hover:text-nova-700" @click="move(i, 1)" title="Nach hinten" aria-label="Nach hinten">›</button>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="nova-card space-y-4">
                <label class="flex items-center gap-2.5 text-sm font-medium">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="size-4" @checked(old('is_active', $product->is_active))> Im Shop anzeigen
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="nova-label" for="price">Preis (brutto)</label>
                        <input class="nova-input" id="price" name="price" required inputmode="decimal" placeholder="12,90" value="{{ old('price', $euro($product->price_cents)) }}">
                    </div>
                    <div>
                        <label class="nova-label" for="compare_price">Statt-Preis</label>
                        <input class="nova-input" id="compare_price" name="compare_price" inputmode="decimal" placeholder="optional" value="{{ old('compare_price', $euro($product->compare_price_cents)) }}">
                    </div>
                </div>
                <div>
                    <label class="nova-label" for="tax_rate">Mehrwertsteuer</label>
                    <select class="nova-input" id="tax_rate" name="tax_rate">
                        @foreach (\App\Shop\ShopSettings::taxRates() as $r)
                            <option value="{{ $r }}" @selected((float) old('tax_rate', $product->tax_rate) === (float) $r)>{{ \App\Shop\ShopSettings::rate($r) }} %{{ $loop->first ? ' (Normalsatz)' : '' }}</option>
                        @endforeach
                    </select>
                    @if (\App\Shop\ShopSettings::smallBusiness())<p class="nova-help">Sie sind als Kleinunternehmer eingestellt: Im Shop wird keine MwSt. ausgewiesen.</p>@endif
                </div>
                <div>
                    <label class="nova-label">Grundpreis (bei Ware nach Gewicht oder Menge)</label>
                    <div class="flex gap-2">
                        <input class="nova-input w-24" name="base_quantity" inputmode="decimal" placeholder="z. B. 500" value="{{ old('base_quantity', $product->base_quantity ? rtrim(rtrim(number_format($product->base_quantity, 3, ',', ''), '0'), ',') : '') }}" aria-label="Inhalt">
                        <select class="nova-input" name="base_unit" aria-label="Einheit">
                            <option value="">Einheit</option>
                            @foreach (\App\Shop\ShopSettings::BASE_UNITS as $k => $l)<option value="{{ $k }}" @selected(old('base_unit', $product->base_unit) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <p class="nova-help">Pflicht z. B. bei Lebensmitteln: Inhalt der Packung, z. B. 500 g. Nova CMS rechnet den Preis pro kg aus.</p>
                </div>
            </section>

            <section class="nova-card space-y-4">
                <div>
                    <label class="nova-label" for="stock">Lagerbestand</label>
                    <input class="nova-input" id="stock" name="stock" type="number" min="0" placeholder="leer = unbegrenzt" value="{{ old('stock', $product->stock) }}">
                </div>
                <div>
                    <label class="nova-label" for="sku">Artikelnummer</label>
                    <input class="nova-input" id="sku" name="sku" maxlength="60" value="{{ old('sku', $product->sku) }}">
                </div>
                <div>
                    <label class="nova-label" for="delivery_time">Lieferzeit</label>
                    <input class="nova-input" id="delivery_time" name="delivery_time" maxlength="60" placeholder="{{ \App\Shop\ShopSettings::deliveryTime() }}" value="{{ old('delivery_time', $product->delivery_time) }}">
                </div>
                <div>
                    <label class="nova-label" for="slug">Adresse im Shop</label>
                    <div class="flex items-center text-sm text-slate-500"><span class="shrink-0">/shop/</span>
                        <input class="nova-input ml-1" id="slug" name="slug" maxlength="180" placeholder="wird automatisch erstellt" value="{{ old('slug', $product->slug) }}"></div>
                </div>
                <div>
                    <label class="nova-label" for="sort">Reihenfolge</label>
                    <input class="nova-input w-24" id="sort" name="sort" type="number" min="0" value="{{ old('sort', $product->sort ?? 0) }}">
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <button class="nova-btn flex-1 justify-center">{{ $product->exists ? 'Speichern' : 'Produkt anlegen' }}</button>
                @if ($product->exists && \App\Shop\ShopSettings::enabled() && $product->is_active)
                    <a href="{{ $product->url() }}" target="_blank" class="nova-btn-secondary">Ansehen</a>
                @endif
            </div>
        </aside>

        @include('admin.media.picker')
    </form>

    @if ($product->exists)
        <form method="POST" action="{{ route('admin.shop.products.destroy', $product) }}" class="mt-6" onsubmit="return confirm('Produkt wirklich löschen?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-700 hover:underline">Produkt löschen</button>
        </form>
    @endif
@endsection
