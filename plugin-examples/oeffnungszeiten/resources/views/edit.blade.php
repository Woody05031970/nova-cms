@extends('layouts.admin')
@section('title', 'Öffnungszeiten')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Öffnungszeiten</h1>
    <p class="mt-1 text-slate-600">Haken setzen, wenn Sie an dem Tag geöffnet haben, und Uhrzeiten eintragen.</p>

    <form method="POST" action="{{ route('oeffnungszeiten.update') }}" class="nova-card mt-8">
        @csrf @method('PUT')
        <div class="divide-y divide-slate-100">
            @foreach ($tage as $i => $t)
                <div class="grid grid-cols-[1fr_auto] items-center gap-3 py-3 sm:grid-cols-[160px_1fr]">
                    <label class="flex items-center gap-2 font-medium">
                        <input type="checkbox" name="tage[{{ $i }}][offen]" value="1" class="accent-nova-600" @checked(! $t['geschlossen'])>
                        {{ $t['tag'] }}
                    </label>
                    <div class="col-span-2 flex items-center gap-2 sm:col-span-1">
                        <input type="time" name="tage[{{ $i }}][von]" value="{{ $t['von'] }}" class="nova-input max-w-36" aria-label="{{ $t['tag'] }} von">
                        <span class="text-slate-500">bis</span>
                        <input type="time" name="tage[{{ $i }}][bis]" value="{{ $t['bis'] }}" class="nova-input max-w-36" aria-label="{{ $t['tag'] }} bis">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6 flex justify-end"><button class="nova-btn">Speichern</button></div>
    </form>
@endsection
