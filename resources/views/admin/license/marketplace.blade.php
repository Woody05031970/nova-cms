@extends('layouts.admin')
@section('title', 'Marktplatz')
@section('width', 'max-w-6xl')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Plugins</h1>
            <p class="mt-1 text-slate-600">Geprüfte Module für Ihre Website. Kostenlose Module installieren Sie mit einem Klick, gekaufte werden mit Ihrer Lizenz freigeschaltet.</p>
        </div>
        <a href="{{ route('admin.plugins.market', ['neu' => 1]) }}" class="nova-btn-secondary">Aktualisieren</a>
    </div>
    @include('admin.plugins-nav')

    @if ($error)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <p class="font-medium">Der Marktplatz ist gerade nicht erreichbar.</p>
            <p class="mt-1">{{ $error }}</p>
        </div>
    @elseif ($modules->isEmpty())
        <p class="nova-card mt-6 text-center text-slate-500">Im Marktplatz sind noch keine Module.</p>
    @else
        <ul class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach ($modules as $m)
                <li class="nova-card flex flex-col p-5" data-module="{{ $m['slug'] }}">
                    <div class="flex items-start gap-3">
                        <div class="grid size-11 shrink-0 place-items-center rounded-lg {{ $m['installed'] ? 'bg-nova-50 text-nova-600' : 'bg-slate-100 text-slate-500' }}"><x-icon name="puzzle" class="size-6" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $m['name'] }} <span class="text-sm font-normal text-slate-500">{{ $m['version'] }}</span></p>
                            <p class="text-xs text-slate-500">{{ $categories[$m['category'] ?? ''] ?? 'Modul' }}</p>
                        </div>
                        <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-sm font-medium', 'bg-emerald-50 text-emerald-700' => ! $m['paid'], 'bg-slate-100 text-slate-700' => $m['paid']])>
                            {{ $m['paid'] ? ($m['price_cents'] ? \App\Shop\Money::format($m['price_cents']) : 'kostenpflichtig') : 'kostenlos' }}</span>
                    </div>
                    @if ($m['description'])<p class="mt-3 flex-1 text-sm text-slate-600">{{ $m['description'] }}</p>@endif
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if (! $m['compatible'])
                            <span class="text-sm text-slate-500">Benötigt Nova CMS {{ $m['requires'] }} oder neuer</span>
                        @elseif ($m['installed'] && ! $m['update'])
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-sm text-emerald-700">Installiert{{ $m['enabled'] ? ' und aktiv' : '' }}</span>
                        @elseif ($m['licensed'])
                            <form method="POST" action="{{ route('admin.plugins.market.install', $m['slug']) }}">@csrf
                                <button class="nova-btn">{{ $m['update'] ? 'Auf '.$m['version'].' aktualisieren' : 'Installieren' }}</button></form>
                            @if ($m['update'])<span class="text-xs text-slate-500">installiert: {{ $m['installed_version'] }}</span>@endif
                        @else
                            @if ($m['buy_url'])
                                <a href="{{ $m['buy_url'] }}" target="_blank" rel="noopener" class="nova-btn">Kaufen</a>
                            @endif
                            <span class="text-xs text-slate-500">Nach dem Kauf hier mit Ihrer Lizenz installieren.</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
