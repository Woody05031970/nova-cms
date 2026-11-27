@extends('layouts.admin')
@section('title', 'Warenwirtschaft')
@section('width', 'max-w-5xl')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Warenwirtschaft</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Gleichen Sie Produkte, Preise, Lagerbestände und Bestellungen mit Ihrer Warenwirtschaft, Buchhaltung oder Ihrem Lager ab. Entweder per CSV-Datei (öffnet sich in Excel) oder automatisch über die Schnittstelle.</p>

    @if (session('erp_skipped'))
        <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900" role="status" data-erp-skipped>
            <p class="font-medium">Nicht übernommen:</p>
            <ul class="mt-1 list-disc pl-5">@foreach (session('erp_skipped') as $line)<li>{{ $line }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="nova-card" aria-labelledby="e-products">
            <h2 id="e-products" class="font-semibold">Produkte</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $products }} Produkte im Shop. Die Zuordnung beim Einlesen läuft über die Artikelnummer.@if($withoutSku) <span class="text-amber-800">{{ $withoutSku }} {{ $withoutSku === 1 ? 'Produkt hat' : 'Produkte haben' }} noch keine Artikelnummer.</span>@endif</p>
            <a href="{{ route('admin.erp.products') }}" class="nova-btn-secondary mt-4 inline-flex" data-erp-export>Produkte als CSV herunterladen</a>
            <form method="POST" action="{{ route('admin.erp.import') }}" enctype="multipart/form-data" class="mt-5 border-t border-slate-100 pt-4">
                @csrf
                <label class="nova-label" for="erp-file">Geänderte CSV einlesen</label>
                <input class="nova-input py-1.5" id="erp-file" type="file" name="file" accept=".csv,text/csv" required>
                @error('file')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Übernommen werden Name, Preis, Statt-Preis, Bestand (leer = unbegrenzt), aktiv (ja/nein) und Lieferzeit. Leere Felder für Name und Preis bleiben unverändert.</p>
                <button class="nova-btn mt-3">Einlesen</button>
            </form>
        </section>

        <section class="nova-card" aria-labelledby="e-orders">
            <h2 id="e-orders" class="font-semibold">Bestellungen</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $orders }} Bestellungen. Eine Zeile pro Artikel, z. B. für die Buchhaltung.</p>
            <form method="GET" action="{{ route('admin.erp.orders') }}" class="mt-4 grid grid-cols-2 gap-3">
                <div><label class="nova-label" for="von">Von</label><input class="nova-input" type="date" id="von" name="von" value="{{ now()->startOfMonth()->toDateString() }}"></div>
                <div><label class="nova-label" for="bis">Bis</label><input class="nova-input" type="date" id="bis" name="bis" value="{{ now()->toDateString() }}"></div>
                <button class="nova-btn-secondary col-span-2 sm:col-span-1">Als CSV herunterladen</button>
            </form>
        </section>
    </div>

    <section class="nova-card mt-6" id="schnittstelle" aria-labelledby="e-api">
        <h2 id="e-api" class="font-semibold">Schnittstelle (REST)</h2>
        <p class="mt-1 text-sm text-slate-600">Für Ihre Warenwirtschaft oder einen Dienstleister, z. B. über JTL, Xentral, lexoffice, Billbee, Make oder Zapier. Geben Sie ihm die Adresse und den Zugangsschlüssel.</p>
        @if (session('erp_token'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="status">
                <p class="text-sm font-medium text-emerald-900">Ihr neuer Zugangsschlüssel. Er wird nur dieses eine Mal angezeigt, bitte jetzt kopieren:</p>
                <p class="mt-2 break-all rounded-lg bg-white px-3 py-2 font-mono text-sm" data-erp-token>{{ session('erp_token') }}</p>
            </div>
        @elseif ($hint)
            <p class="mt-4 text-sm">Aktiver Schlüssel: <span class="font-mono">{{ $hint }}</span></p>
        @else
            <p class="mt-4 text-sm text-slate-600">Noch kein Schlüssel. Ohne Schlüssel ist die Schnittstelle gesperrt.</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.erp.token') }}" @if($hint) onsubmit="return confirm('Neuen Schlüssel erzeugen? Der alte funktioniert dann nicht mehr.')" @endif>@csrf<button class="nova-btn">{{ $hint ? 'Neuen Schlüssel erzeugen' : 'Zugangsschlüssel erzeugen' }}</button></form>
            @if ($hint)<form method="POST" action="{{ route('admin.erp.revoke') }}">@csrf @method('DELETE')<button class="nova-btn-secondary">Schnittstelle sperren</button></form>@endif
        </div>
        <details class="mt-5 rounded-xl border border-slate-200">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium">Technische Beschreibung für Entwickler</summary>
            <div class="space-y-2 border-t border-slate-100 p-4 font-mono text-xs leading-relaxed text-slate-700">
                <p>Basis: {{ url('/api/nova/ww') }}</p>
                <p>Kopfzeile: Authorization: Bearer &lt;Schlüssel&gt; · Antworten als JSON, Beträge in Cent</p>
                <p>GET /produkte?seit=2026-12-01T00:00:00Z</p>
                <p>PATCH /produkte/{artikelnummer} {"price_cents": 1990, "stock": 12, "active": true}</p>
                <p>GET /bestellungen?seit=2026-12-01T00:00:00Z</p>
                <p>PATCH /bestellungen/{nummer} {"status": "shipped", "tracking_number": "00340434…", "payment_status": "paid"}</p>
                <p>Status: {{ implode(', ', array_keys(\App\Models\Order::STATUSES)) }}</p>
            </div>
        </details>
    </section>
@endsection
