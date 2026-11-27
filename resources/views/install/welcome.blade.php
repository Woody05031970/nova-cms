@extends('layouts.install')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Willkommen bei Nova CMS</h1>
    <p class="mt-2 text-slate-600">In wenigen Minuten ist Ihre Website eingerichtet. Zuerst prüfen wir, ob Ihr Webspace alles Nötige mitbringt.</p>

    <ul class="mt-6 divide-y divide-slate-100 rounded-lg border border-slate-200">
        @foreach ($checks as $check)
            <li class="flex items-start gap-3 px-4 py-3">
                @if ($check['ok'])
                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="size-3.5" /></span>
                    <span class="text-sm text-slate-700">{{ $check['label'] }}</span>
                @else
                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-red-100 text-red-700"><x-icon name="alert" class="size-3.5" /></span>
                    <span class="text-sm">
                        <span class="font-medium text-slate-900">{{ $check['label'] }}</span>
                        <span class="mt-0.5 block text-slate-600">{{ $check['help'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="mt-8 flex items-center justify-between gap-4">
        @if ($passes)
            <p class="text-sm text-emerald-700">Alles bereit.</p>
            <a href="{{ route('install.database') }}" class="nova-btn">Weiter <x-icon name="arrow" class="size-4" /></a>
        @else
            <p class="text-sm text-slate-600">Bitte beheben Sie die rot markierten Punkte und laden Sie die Seite neu.</p>
            <a href="{{ route('install.welcome') }}" class="nova-btn-secondary">Erneut prüfen</a>
        @endif
    </div>
@endsection
