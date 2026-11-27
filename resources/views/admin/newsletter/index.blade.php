@extends('layouts.admin')
@section('title', 'Newsletter')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Newsletter</h1>
            <p class="mt-1 text-slate-600">Abonnenten melden sich über den Baustein „Newsletter“ an und bestätigen per E-Mail. So ist die Anmeldung DSGVO-konform.</p>
        </div>
        <a href="{{ route('admin.newsletter.create') }}" class="nova-btn" data-new-campaign><x-icon name="plus" class="size-4" /> Neuer Newsletter</a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-4">
        @foreach ([['confirmed', 'Abonnenten', 'text-slate-900'], ['last30', 'Neu in 30 Tagen', 'text-emerald-700'], ['pending', 'Warten auf Bestätigung', 'text-amber-700'], ['unsubscribed', 'Abgemeldet', 'text-slate-500']] as [$k, $label, $cls])
            <div class="nova-card p-5"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-1 text-3xl font-semibold {{ $cls }}">{{ $stats[$k] }}</p></div>
        @endforeach
    </div>

    <section class="nova-card mt-6 p-0">
        <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Ausgaben</h2>
        @forelse ($campaigns as $c)
            <a href="{{ route('admin.newsletter.edit', $c) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 hover:bg-slate-50">
                <span class="font-medium">{{ $c->subject }}</span>
                <span class="flex items-center gap-3 text-sm text-slate-500">
                    @if ($c->status === 'sent'){{ $c->sent_count }} von {{ $c->total }} versendet · {{ $c->sent_at?->format('d.m.Y H:i') }} @endif
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-slate-100 text-slate-600' => $c->status === 'draft', 'bg-amber-50 text-amber-800' => $c->status === 'sending', 'bg-emerald-50 text-emerald-700' => $c->status === 'sent'])>{{ $c->statusLabel() }}</span>
                </span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">Noch kein Newsletter geschrieben.</p>
        @endforelse
    </section>

    <section class="nova-card mt-6 p-0">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Abonnenten</h2>
            <div class="flex items-center gap-2">
                <form method="GET"><input name="suche" value="{{ $search }}" class="nova-input py-1.5 text-sm" placeholder="Suchen …"></form>
                <a href="{{ route('admin.newsletter.export') }}" class="nova-btn-secondary px-3 py-1.5 text-sm">Als CSV herunterladen</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500"><tr><th class="px-5 py-2 font-medium">E-Mail</th><th class="px-5 py-2 font-medium">Name</th><th class="px-5 py-2 font-medium">Status</th><th class="px-5 py-2 font-medium">Seit</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($subscribers as $s)
                        <tr>
                            <td class="px-5 py-2.5">{{ $s->email }}</td>
                            <td class="px-5 py-2.5 text-slate-600">{{ $s->name }}</td>
                            <td class="px-5 py-2.5"><span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $s->status === 'confirmed', 'bg-amber-50 text-amber-800' => $s->status === 'pending', 'bg-slate-100 text-slate-500' => $s->status === 'unsubscribed'])>{{ $s->statusLabel() }}</span></td>
                            <td class="px-5 py-2.5 text-slate-500">{{ ($s->confirmed_at ?? $s->created_at)->format('d.m.Y') }}</td>
                            <td class="px-5 py-2.5 text-right">
                                <form method="POST" action="{{ route('admin.newsletter.subscribers.destroy', $s) }}" onsubmit="return confirm('Diesen Eintrag endgültig löschen?')">@csrf @method('DELETE')
                                    <button class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-700" title="Löschen"><x-icon name="trash" class="size-4" /></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Noch keine Abonnenten. Fügen Sie einer Seite den Baustein „Newsletter“ hinzu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $subscribers->links() }}</div>
    </section>
@endsection
