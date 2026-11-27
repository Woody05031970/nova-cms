@extends('layouts.admin')
@section('title', 'Lizenzserver')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Lizenzserver</h1>
            <p class="mt-1 text-slate-600">Lizenzen für Ihre Kunden ausgeben und Module im Marktplatz anbieten.</p>
        </div>
        <a href="{{ route('admin.license-server.pricing') }}" class="nova-btn-secondary" data-pricing-link>Module und Preise festlegen</a>
    </div>

    <dl class="mt-6 grid grid-cols-3 gap-4">
        <div class="nova-card"><dt class="text-sm text-slate-500">Aktive Lizenzen</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['licenses'] }}</dd></div>
        <div class="nova-card"><dt class="text-sm text-slate-500">Websites</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['sites'] }}</dd></div>
        <div class="nova-card"><dt class="text-sm text-slate-500">Davon aktiv (30 Tage)</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['active30'] }}</dd></div>
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="nova-card min-w-0 p-0">
            <form method="GET" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold">Lizenzen</h2>
                <input type="search" name="suche" value="{{ $search }}" placeholder="Schlüssel, Name, E-Mail" class="nova-input w-56 py-1.5 text-sm" aria-label="Lizenzen durchsuchen">
            </form>
            @if ($licenses->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-slate-500">Noch keine Lizenzen. Sie entstehen automatisch beim Kauf im Shop oder rechts von Hand.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($licenses as $l)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
                            <a href="{{ route('admin.license-server.show', $l) }}" class="font-mono text-sm text-nova-700 hover:underline">{{ $l->key }}</a>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $l->customer_name ?: $l->customer_email ?: '–' }}</span>
                            <span class="text-xs text-slate-500">{{ $l->activations_count }} / {{ $l->max_domains }} Websites</span>
                            @unless ($l->isActive())<span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">gesperrt</span>@endunless
                        </li>
                    @endforeach
                </ul>
                <div class="px-5 py-3">{{ $licenses->links() }}</div>
            @endif
        </section>

        <form method="POST" action="{{ route('admin.license-server.store') }}" class="nova-card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">Lizenz von Hand ausgeben</h2>
            <div><label class="nova-label" for="customer_name">Kunde</label><input class="nova-input" id="customer_name" name="customer_name"></div>
            <div><label class="nova-label" for="customer_email">E-Mail</label><input class="nova-input" id="customer_email" name="customer_email" type="email"></div>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="core" value="0"><input type="checkbox" name="core" value="1" checked class="size-4"> Nova CMS Grundlizenz</label>
            @foreach ($modules->where('is_paid', true) as $m)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="modules[]" value="{{ $m->slug }}" class="size-4"> {{ $m->name }}</label>
            @endforeach
            <div class="grid grid-cols-2 gap-3">
                <div><label class="nova-label" for="max_domains">Websites</label><input class="nova-input" id="max_domains" name="max_domains" type="number" min="1" value="1"></div>
                <div><label class="nova-label" for="updates_until">Updates bis</label><input class="nova-input" id="updates_until" name="updates_until" type="date" value="{{ now()->addYear()->toDateString() }}"></div>
            </div>
            <button class="nova-btn w-full justify-center">Lizenz erstellen</button>
        </form>
    </div>

    <section class="nova-card mt-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-semibold">Module im Marktplatz</h2>
                <p class="text-sm text-slate-600">Laden Sie ein Plugin als ZIP hoch. Eine neue Version desselben Moduls ersetzt die alte; Installationen sehen dann „Aktualisieren“.</p>
            </div>
            <form method="POST" action="{{ route('admin.license-server.modules.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="module" accept=".zip" required class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-nova-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-nova-700">
                <button class="nova-btn shrink-0">Hochladen</button>
            </form>
        </div>
        @if ($modules->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Noch keine Module.</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($modules as $m)
                    <li>
                        <form method="POST" action="{{ route('admin.license-server.modules.update', $m) }}" class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-[1fr_1fr_auto]">
                            @csrf @method('PUT')
                            <div>
                                <p class="font-medium">{{ $m->name }} <span class="text-sm font-normal text-slate-500">{{ $m->version }} · {{ $m->downloads }} Downloads</span></p>
                                <textarea class="nova-input mt-2 text-sm" name="description" rows="2" maxlength="500" aria-label="Beschreibung">{{ $m->description }}</textarea>
                            </div>
                            <div class="space-y-2 text-sm">
                                <select class="nova-input py-1.5 text-sm" name="category" aria-label="Kategorie">
                                    <option value="">Kategorie</option>
                                    @foreach (\App\Models\MarketModule::CATEGORIES as $k => $c)<option value="{{ $k }}" @selected($m->category === $k)>{{ $c }}</option>@endforeach
                                </select>
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1.5"><input type="checkbox" name="is_paid" value="1" class="size-4" @checked($m->is_paid)> kostenpflichtig</label>
                                    <input class="nova-input w-24 py-1.5 text-sm" name="price" placeholder="Preis" value="{{ $m->price_cents ? number_format($m->price_cents / 100, 2, ',', '') : '' }}" aria-label="Preis">
                                </div>
                                <input class="nova-input py-1.5 text-sm" name="buy_url" placeholder="Kauf-Link (https://…/shop/…)" value="{{ $m->buy_url }}" aria-label="Kauf-Link">
                            </div>
                            <div class="flex flex-col justify-between gap-2">
                                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_published" value="1" class="size-4" @checked($m->is_published)> Veröffentlicht</label>
                                <button class="nova-btn-secondary">Speichern</button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
