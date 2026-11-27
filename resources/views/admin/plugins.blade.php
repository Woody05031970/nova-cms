@extends('layouts.admin')
@section('title', 'Plugins')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Plugins</h1>
            <p class="mt-1 text-slate-600">Erweitern Sie Ihre Website um neue Funktionen. Plugins lassen sich jederzeit wieder ausschalten.</p>
        </div>
    </div>
    @include('admin.plugins-nav')

    <section class="nova-card mt-8" aria-labelledby="upload">
        <h2 id="upload" class="font-semibold">Plugin hochladen</h2>
        <form method="POST" action="{{ route('admin.plugins.upload') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            @csrf
            <input type="file" name="plugin" accept=".zip,application/zip" required
                   class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-nova-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-nova-700 hover:file:bg-nova-100">
            <button class="nova-btn shrink-0"><x-icon name="upload" class="size-4" /> Installieren</button>
        </form>
        <p class="nova-help mt-3">ZIP-Datei mit einer plugin.json. Installieren Sie nur Plugins aus vertrauenswürdigen Quellen. Geprüfte Module finden Sie im <a href="{{ route('admin.plugins.market') }}" class="text-nova-700 hover:underline">Marktplatz</a>.</p>
    </section>

    <section class="mt-6" aria-labelledby="installed">
        <h2 id="installed" class="mb-3 font-semibold">Installiert ({{ $plugins->count() }})</h2>

        @if ($plugins->isEmpty())
            <div class="nova-card text-center text-slate-500">Noch keine Plugins installiert.</div>
        @else
            <ul class="space-y-3">
                @foreach ($plugins as $plugin)
                    <li class="nova-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="grid size-11 shrink-0 place-items-center rounded-lg {{ $plugin->enabled ? 'bg-nova-50 text-nova-600' : 'bg-slate-100 text-slate-400' }}">
                            <x-icon name="puzzle" class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ $plugin->name }}
                                <span class="ml-1 text-sm font-normal text-slate-500">Version {{ $plugin->version }}</span>
                                @if ($plugin->enabled)
                                    <span class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>
                                @else
                                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Aus</span>
                                @endif
                            </p>
                            @if ($plugin->description)<p class="mt-0.5 text-sm text-slate-600">{{ $plugin->description }}</p>@endif
                            @if ($plugin->author)<p class="mt-0.5 text-xs text-slate-400">von {{ $plugin->author }}</p>@endif
                            @if ($plugin->last_error)
                                <p class="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">{{ $plugin->last_error }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @if ($plugin->enabled)
                                <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin->slug) }}">@csrf
                                    <button class="nova-btn-secondary">Deaktivieren</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.plugins.activate', $plugin->slug) }}">@csrf
                                    <button class="nova-btn">Aktivieren</button>
                                </form>
                                <form method="POST" action="{{ route('admin.plugins.destroy', $plugin->slug) }}"
                                      onsubmit="return confirm('Plugin „{{ addslashes($plugin->name) }}“ wirklich entfernen?')">@csrf @method('DELETE')
                                    <button class="nova-btn-secondary text-red-700">Entfernen</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @foreach ($broken as $folder => $error)
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Ordner „{{ $folder }}“: {{ $error }}</p>
        @endforeach
    </section>
@endsection
