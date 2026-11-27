@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Hallo {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}!</h1>
        <p class="mt-1 text-slate-600">Hier sehen Sie auf einen Blick, wie gut Ihre Website aufgestellt ist und was als Nächstes zu tun ist.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @php
            $tone = $score >= 80 ? ['text-emerald-600', 'Sehr gut'] : ($score >= 50 ? ['text-amber-500', 'Auf einem guten Weg'] : ['text-red-500', 'Hier fehlt noch einiges']);
            $circ = 2 * pi() * 52;
        @endphp
        <section class="nova-card flex flex-col items-center text-center" aria-labelledby="health">
            <h2 id="health" class="text-sm font-semibold text-slate-500">Website-Gesundheit</h2>
            <div class="relative mt-4 size-36">
                <svg viewBox="0 0 120 120" class="size-36 -rotate-90" aria-hidden="true">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10"/>
                    <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round"
                            class="{{ $tone[0] }}" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $score / 100) }}"/>
                </svg>
                <span class="absolute inset-0 grid place-items-center text-3xl font-semibold">{{ $score }}<span class="sr-only"> von 100</span></span>
            </div>
            <p class="mt-3 font-medium {{ $tone[0] }}">{{ $tone[1] }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $doneCount }} von {{ $total }} Punkten erledigt</p>
        </section>

        <section class="nova-card lg:col-span-2" aria-labelledby="todo">
            <h2 id="todo" class="font-semibold">Das fehlt noch</h2>
            @if ($open->isEmpty())
                <p class="mt-4 flex items-center gap-2 text-emerald-700"><x-icon name="check" /> Alles erledigt. Stark!</p>
            @else
                <ul class="mt-4 divide-y divide-slate-100">
                    @foreach ($open->take(6) as $hint)
                        <li class="flex items-start gap-3 py-3">
                            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-700"><x-icon name="alert" class="size-3.5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $hint['title'] }} <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-normal text-slate-500">{{ $hint['area'] }}</span></p>
                                <p class="text-sm text-slate-600">{{ $hint['help'] }}</p>
                            </div>
                            @if ($hint['url'])
                                <a href="{{ $hint['url'] }}" class="nova-btn-secondary shrink-0 px-3 py-1.5">Erledigen</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($open->count() > 6)<p class="mt-2 text-sm text-slate-500">und {{ $open->count() - 6 }} weitere</p>@endif
            @endif
        </section>
    </div>

    @if ($widgets->isNotEmpty())
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            @foreach ($widgets as $widget)
                <section class="nova-card">
                    <h2 class="mb-3 font-semibold">{{ $widget['title'] }}</h2>
                    {!! $widget['html'] !!}
                </section>
            @endforeach
        </div>
    @endif

    <section class="mt-6 rounded-xl border border-dashed border-nova-200 bg-nova-50/60 p-6">
        <div class="flex items-start gap-3">
            <x-icon name="sparkles" class="size-6 shrink-0 text-nova-600" />
            <div>
                <h2 class="font-semibold text-nova-900">Demnächst: Ihre Website mit KI erstellen</h2>
                <p class="mt-1 text-sm text-slate-700">Im nächsten Ausbauschritt beantworten Sie ein paar Fragen zu Ihrem Angebot, und die KI schlägt Seiten, Texte und Bilder vor. Je vollständiger Ihr Firmenprofil, desto besser die Vorschläge.</p>
            </div>
        </div>
    </section>
@endsection
