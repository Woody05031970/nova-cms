@extends('site.community._layout')
@section('ctitle', 'Foren')
@section('main')
    <h1 class="text-2xl font-semibold tracking-tight">Foren</h1>
    <p class="text-sm text-slate-600">Fragen stellen, Erfahrungen teilen, gemeinsam Lösungen finden.</p>
    <ul class="mt-5 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-boards>
        @forelse ($boards as $b)
            @php $t = $latest[$b->id] ?? null; @endphp
            <li class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_7rem_14rem] sm:items-center">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-nova-50 text-nova-700"><x-icon name="community" class="size-5" /></span>
                    <div><a href="{{ $b->url() }}" class="font-semibold hover:text-nova-700">{{ $b->name }}</a>@if($b->is_locked)<span class="ml-2 rounded bg-slate-100 px-1.5 text-[11px] text-slate-600">nur Betreiber</span>@endif
                        @if ($b->description)<p class="text-sm text-slate-600">{{ $b->description }}</p>@endif</div>
                </div>
                <p class="text-sm text-slate-600 sm:text-center"><span class="font-semibold text-slate-900">{{ $b->topics_count }}</span> Themen<br class="hidden sm:block"><span class="sm:hidden"> · </span><span class="font-semibold text-slate-900">{{ $b->replies_count }}</span> Antworten</p>
                <div class="min-w-0 text-sm">
                    @if ($t)<a href="{{ $t->url() }}" class="block truncate font-medium hover:text-nova-700">{{ $t->title }}</a><span class="text-xs text-slate-500">{{ ($t->lastUser ?? $t->user)?->name }} · {{ ($t->last_reply_at ?? $t->created_at)->locale('de')->diffForHumans() }}</span>
                    @else<span class="text-slate-400">Noch keine Themen</span>@endif
                </div>
            </li>
        @empty
            <li class="p-8 text-center text-sm text-slate-600">Es gibt noch keine Foren. Der Betreiber legt sie in der Verwaltung unter „Community“ an.</li>
        @endforelse
    </ul>
@endsection
