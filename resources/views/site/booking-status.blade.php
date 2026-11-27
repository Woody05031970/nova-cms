@extends('site.layout')
@section('title', 'Ihr Termin – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@php $b = $booking; $past = $b->starts_at->isPast(); @endphp
@section('content')
    <section class="mx-auto max-w-xl px-4 py-16 sm:px-6">
        @if (session('status'))<p class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</p>@endif
        <p class="font-mono text-sm font-semibold text-nova-700" data-booking-status="{{ $b->status }}">{{ $b->statusLabel() }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">
            {{ match ($b->status) { 'confirmed' => 'Ihr Termin ist gebucht', 'pending' => 'Ihre Anfrage ist angekommen', default => 'Dieser Termin ist abgesagt' } }}
        </h1>
        @if ($b->status === 'pending')<p class="mt-3 text-slate-600">Wir prüfen den Termin und schicken Ihnen eine Bestätigung an {{ $b->email }}.</p>
        @elseif ($b->status === 'confirmed')<p class="mt-3 text-slate-600">Eine Bestätigung ist unterwegs an {{ $b->email }}.</p>@endif

        <dl class="mt-6 space-y-2 rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Leistung</dt><dd class="text-right font-medium">{{ $b->service_name }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Termin</dt><dd class="text-right font-medium">{{ $b->when() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Name</dt><dd class="text-right">{{ $b->name }}</dd></div>
        </dl>

        @if ($b->status !== 'cancelled' && ! $past)
            @if (! empty($cancel))
                <form method="POST" action="{{ route('booking.cancel.do', $b->token) }}" class="mt-6 rounded-2xl bg-amber-50 p-5">
                    @csrf
                    <p class="font-medium text-amber-900">Möchten Sie diesen Termin wirklich absagen?</p>
                    <button class="mt-3 rounded-xl bg-red-700 px-5 py-2.5 font-semibold text-white hover:bg-red-800" data-booking-cancel>Ja, Termin absagen</button>
                </form>
            @else
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($b->status === 'confirmed')<a href="{{ route('booking.ics', $b->token) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 font-medium hover:bg-slate-50">In meinen Kalender</a>@endif
                    <a href="{{ route('booking.cancel', $b->token) }}" class="rounded-xl px-4 py-2.5 font-medium text-red-700 hover:bg-red-50">Termin absagen</a>
                </div>
            @endif
        @endif
        <a href="{{ route('home') }}" class="mt-10 inline-block text-nova-700 hover:underline">Zur Startseite</a>
    </section>
@endsection
