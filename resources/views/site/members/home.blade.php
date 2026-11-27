@extends('site.layout')
@section('title', \App\Support\Members\Members::title().' – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('content')
    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        @if (session('status'))<p class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</p>@endif
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-nova-700">Willkommen, {{ auth()->user()->name }}</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ \App\Support\Members\Members::title() }}</h1>
            </div>
            <form method="POST" action="{{ route('members.logout') }}">@csrf<button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" data-member-logout>Abmelden</button></form>
        </div>
        @if ($intro)<p class="mt-4 max-w-2xl whitespace-pre-line text-slate-600">{{ $intro }}</p>@endif

        @if ($pages->isNotEmpty())
            <h2 class="mt-10 text-lg font-semibold">Inhalte für Mitglieder</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2" data-member-pages>
                @foreach ($pages as $p)
                    <li><a href="{{ $p->url() }}" class="block rounded-2xl border border-slate-200 bg-white p-5 hover:border-nova-300 hover:shadow-sm"><span class="font-semibold">{{ $p->title }}</span>@if($p->meta_description)<span class="mt-1 block text-sm text-slate-600">{{ $p->meta_description }}</span>@endif</a></li>
                @endforeach
            </ul>
        @endif

        @if ($files->isNotEmpty())
            <h2 class="mt-10 text-lg font-semibold">Downloads</h2>
            <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white" data-member-files>
                @foreach ($files as $f)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <span><span class="font-medium">{{ $f->title }}</span> <span class="text-sm text-slate-500">· {{ $f->extension() }}, {{ $f->sizeLabel() }}</span></span>
                        <a href="{{ route('members.download', $f) }}" class="shrink-0 rounded-lg bg-nova-50 px-3 py-1.5 text-sm font-medium text-nova-700 hover:bg-nova-100">Herunterladen</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($pages->isEmpty() && $files->isEmpty())<p class="mt-8 text-slate-600">Hier erscheinen bald Inhalte für Mitglieder.</p>@endif
    </section>
@endsection
