@extends('layouts.admin')
@section('title', 'Kunden')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Kunden</h1>
            <p class="mt-1 max-w-3xl text-slate-600">Kunden mit Kundenkonto. Sie registrieren sich im Shop über das Personen-Symbol oben rechts oder an der Kasse und verwalten ihre Daten selbst unter <a href="{{ route('account.home') }}" target="_blank" rel="noopener" class="font-mono text-sm text-nova-700 hover:underline">{{ route('account.home') }}</a>.</p>
        </div>
        <p class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">{{ $total }} {{ $total === 1 ? 'Kundenkonto' : 'Kundenkonten' }}</p>
    </div>

    <section class="nova-card mt-8 p-0" aria-labelledby="c-list">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
            <h2 id="c-list" class="font-semibold">Alle Kunden</h2>
            <form method="GET" class="flex gap-2" role="search"><label for="cq" class="sr-only">Kunden suchen</label><input id="cq" name="q" value="{{ $q }}" class="nova-input py-1.5 text-sm" placeholder="Name, Firma oder E-Mail"><button class="nova-btn-secondary py-1.5 text-sm">Suchen</button></form>
        </div>
        @if ($customers->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">{{ $q ? 'Keine Treffer.' : 'Noch keine Kundenkonten.' }}</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm" data-customers>
                    <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-2 font-medium">Kunde</th><th class="px-3 py-2 font-medium">Adresse</th><th class="px-3 py-2 font-medium">Bestellungen</th><th class="px-3 py-2 font-medium">Umsatz</th><th class="px-6 py-2"><span class="sr-only">Aktionen</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customers as $c)
                            @php $a = $c->billing_address ?? []; @endphp
                            <tr>
                                <td class="px-6 py-3"><p class="font-medium">{{ $c->name }}</p><p class="text-slate-600"><a href="mailto:{{ $c->email }}" class="hover:underline">{{ $c->email }}</a>@if($c->phone) · {{ $c->phone }}@endif</p>@if($c->company)<p class="text-xs text-slate-500">{{ $c->company }}</p>@endif</td>
                                <td class="px-3 py-3 text-slate-600">{{ trim(($a['street'] ?? '').', '.($a['zip'] ?? '').' '.($a['city'] ?? ''), ', ') ?: '–' }}</td>
                                <td class="px-3 py-3">{{ $c->orders_count }}</td>
                                <td class="px-3 py-3">{{ \App\Shop\Money::format((int) $c->orders_sum_total_cents) }}</td>
                                <td class="px-6 py-3 text-right"><form method="POST" action="{{ route('admin.customers.destroy', $c) }}" onsubmit="return confirm('Kundenkonto löschen? Bestellungen bleiben erhalten.')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Löschen</button></form></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
