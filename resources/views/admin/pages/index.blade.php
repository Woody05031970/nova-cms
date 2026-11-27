@extends('layouts.admin')
@section('title', 'Seiten')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $trash ? 'Papierkorb' : 'Seiten' }}</h1>
            <p class="mt-1 text-slate-600">{{ $trash ? 'Gelöschte Seiten lassen sich hier wiederherstellen.' : 'Alle Seiten Ihrer Website. Klicken Sie auf eine Seite, um sie zu bearbeiten.' }}</p>
        </div>
        <div class="flex gap-2">
            @if ($trash)
                <a href="{{ route('admin.pages.index') }}" class="nova-btn-secondary">Zurück zu den Seiten</a>
            @else
                @if ($trashCount)<a href="{{ route('admin.pages.index', ['papierkorb' => 1]) }}" class="nova-btn-secondary">Papierkorb ({{ $trashCount }})</a>@endif
                <button type="button" class="nova-btn" onclick="document.getElementById('new-page').showModal()">+ Neue Seite</button>
            @endif
        </div>
    </div>

    @if ($pages->isEmpty())
        <div class="nova-card mt-8 text-center">
            @if ($trash)
                <p class="text-slate-500">Der Papierkorb ist leer.</p>
            @else
                <x-icon name="pages" class="mx-auto size-10 text-nova-500" />
                <h2 class="mt-3 text-lg font-semibold">Noch keine Seiten</h2>
                <p class="mt-1 text-slate-600">Am besten fangen Sie mit der Startseite an.</p>
                <form method="POST" action="{{ route('admin.pages.store') }}" class="mt-5 inline-block">@csrf
                    <input type="hidden" name="template" value="start">
                    <button class="nova-btn">Startseite anlegen</button>
                </form>
                <button type="button" class="nova-btn-secondary ml-2" onclick="document.getElementById('new-page').showModal()">Andere Vorlage wählen</button>
            @endif
        </div>
    @else
        <div class="nova-card mt-8 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Titel</th><th class="hidden px-5 py-3 sm:table-cell">Adresse</th><th class="px-5 py-3">Status</th><th class="hidden px-5 py-3 md:table-cell">Geändert</th><th class="px-5 py-3"><span class="sr-only">Aktionen</span></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pages as $page)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5">
                                @if ($trash)
                                    <span class="font-medium">{{ $page->title }}</span>
                                @else
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium text-slate-900 hover:text-nova-700">{{ $page->title }}</a>
                                @endif
                                @if ($page->is_home)<span class="ml-2 rounded-full bg-nova-50 px-2 py-0.5 text-xs font-medium text-nova-700">Startseite</span>@endif
                                @if (! $page->show_in_menu && ! $page->is_home)<span class="ml-2 text-xs text-slate-400">nicht im Menü</span>@endif
                            </td>
                            <td class="hidden px-5 py-3.5 text-slate-500 sm:table-cell">{{ $page->is_home ? '/' : '/'.$page->slug }}</td>
                            <td class="px-5 py-3.5">
                                @if ($page->isPublished())
                                    <span class="inline-flex items-center gap-1.5 text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Online</span>
                                @elseif ($page->isScheduled())
                                    <span class="inline-flex items-center gap-1.5 text-sky-800"><span class="size-2 rounded-full bg-sky-500"></span>Geplant {{ $page->published_at->format('d.m., H:i') }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-slate-500"><span class="size-2 rounded-full bg-slate-300"></span>Entwurf</span>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3.5 text-slate-500 md:table-cell">{{ $page->updated_at->diffForHumans() }}</td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                @if ($trash)
                                    <form method="POST" action="{{ route('admin.pages.restore', $page->id) }}" class="inline">@csrf<button class="font-medium text-nova-700 hover:underline">Wiederherstellen</button></form>
                                    <form method="POST" action="{{ route('admin.pages.force-delete', $page->id) }}" class="ml-3 inline" onsubmit="return confirm('Endgültig löschen? Das lässt sich nicht rückgängig machen.')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Endgültig löschen</button></form>
                                @else
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium text-nova-700 hover:underline">Bearbeiten</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <dialog id="new-page" class="m-auto w-full max-w-2xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.pages.store') }}" class="p-6">
            @csrf
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Neue Seite</h2>
                    <p class="text-sm text-slate-600">Wählen Sie eine Vorlage. Alle Inhalte lassen sich danach ändern.</p>
                </div>
                <button type="button" class="rounded-md p-1 text-slate-400 hover:text-slate-700" onclick="this.closest('dialog').close()" aria-label="Schließen">✕</button>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($templates as $key => $tpl)
                    <button name="template" value="{{ $key }}" class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-left hover:border-nova-500 hover:bg-nova-50/50">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-nova-50 text-nova-600"><x-icon :name="$tpl['icon']" /></span>
                        <span><span class="block font-medium">{{ $tpl['label'] }}</span><span class="block text-sm text-slate-600">{{ $tpl['description'] }}</span></span>
                    </button>
                @endforeach
            </div>
        </form>
    </dialog>
@endsection
@push('head')
    <script>document.addEventListener('DOMContentLoaded', () => { if (location.hash === '#neu') document.getElementById('new-page')?.showModal(); });</script>
@endpush
