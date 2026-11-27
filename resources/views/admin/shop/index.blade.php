@extends('layouts.admin')
@section('title', 'Shop')
@section('width', 'max-w-6xl')

@php $badge = ['new' => 'bg-nova-50 text-nova-700', 'processing' => 'bg-amber-50 text-amber-700', 'shipped' => 'bg-sky-50 text-sky-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-slate-100 text-slate-500']; @endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
            <p class="mt-1 text-slate-600">Bestellungen, Produkte und Einstellungen Ihres Online-Shops.</p>
        </div>
        @if ($enabled)
            <a href="{{ route('shop.index') }}" target="_blank" class="nova-btn-secondary"><x-icon name="external" class="size-4" /> Shop ansehen</a>
        @endif
    </div>
    @include('admin.shop.nav')

    @unless ($enabled)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-900">Der Shop ist noch ausgeschaltet</h2>
            <p class="mt-1 text-sm text-amber-900">Legen Sie Produkte an, richten Sie Versand und Zahlungsarten ein und schalten Sie den Shop dann unter „Einstellungen“ ein. Besucher sehen bis dahin nichts davon.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.shop.products.create') }}" class="nova-btn">Erstes Produkt anlegen</a>
                <a href="{{ route('admin.shop.settings') }}" class="nova-btn-secondary">Zu den Einstellungen</a>
            </div>
        </div>
    @endunless

    <dl class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="nova-card"><dt class="text-sm text-slate-500">Offene Bestellungen</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['open'] }}</dd></div>
        <div class="nova-card"><dt class="text-sm text-slate-500">Noch nicht bezahlt</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['unpaid'] }}</dd></div>
        <div class="nova-card"><dt class="text-sm text-slate-500">Umsatz {{ now()->translatedFormat('F') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ \App\Shop\Money::format($stats['month']) }}</dd></div>
        <div class="nova-card"><dt class="text-sm text-slate-500">Aktive Produkte</dt><dd class="mt-1 text-2xl font-semibold">{{ $stats['products'] }}</dd>
            @if ($stats['soldOut'])<p class="text-xs text-red-700">{{ $stats['soldOut'] }} ausverkauft</p>@endif</div>
    </dl>

    <section class="nova-card mt-6 p-0">
        <form class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3" method="GET">
            @foreach (['' => 'Alle', 'new' => 'Neu', 'unbezahlt' => 'Unbezahlt', 'processing' => 'In Bearbeitung', 'shipped' => 'Versendet', 'completed' => 'Abgeschlossen', 'cancelled' => 'Storniert'] as $k => $l)
                <a href="{{ route('admin.shop.index', array_filter(['status' => $k, 'suche' => $search])) }}" @class(['rounded-full px-3 py-1 text-sm', 'bg-slate-900 text-white' => ($status ?? '') === $k, 'text-slate-600 hover:bg-slate-100' => ($status ?? '') !== $k])>{{ $l }}</a>
            @endforeach
            <input type="search" name="suche" value="{{ $search }}" placeholder="Nummer, Name, E-Mail" class="nova-input ml-auto w-56 py-1.5 text-sm" aria-label="Bestellungen durchsuchen">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        </form>
        @if ($orders->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-slate-500">Noch keine Bestellungen{{ $status || $search ? ' mit dieser Auswahl' : '' }}.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-slate-500"><tr><th class="px-5 py-2 font-medium">Nummer</th><th class="px-3 py-2 font-medium">Datum</th><th class="px-3 py-2 font-medium">Kunde</th><th class="px-3 py-2 font-medium">Status</th><th class="px-3 py-2 font-medium">Zahlung</th><th class="px-5 py-2 text-right font-medium">Summe</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orders as $o)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2.5"><a href="{{ route('admin.shop.orders.show', $o) }}" class="font-medium text-nova-700 hover:underline">{{ $o->number }}</a></td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-600">{{ $o->created_at->format('d.m.Y H:i') }}</td>
                                <td class="px-3 py-2.5">{{ $o->name }}<span class="block text-xs text-slate-500">{{ $o->city }}</span></td>
                                <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[$o->status] ?? '' }}">{{ $o->statusLabel() }}</span></td>
                                <td class="px-3 py-2.5"><span @class(['text-xs', 'text-emerald-700' => $o->isPaid(), 'text-amber-700' => ! $o->isPaid()])>{{ $o->isPaid() ? 'Bezahlt' : 'Offen' }}</span></td>
                                <td class="px-5 py-2.5 text-right font-medium whitespace-nowrap">{{ $o->money($o->total_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
