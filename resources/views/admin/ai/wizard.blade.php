@extends('layouts.admin')
@section('title', 'Website-Assistent')
@push('head') @vite('resources/js/app.js') @endpush

@section('content')
    <a href="{{ route('admin.ai') }}" class="text-sm text-slate-600 hover:text-slate-900">← KI-Assistent</a>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Ihre ersten Seiten in zwei Minuten</h1>
    <p class="mt-1 text-slate-600">Erzählen Sie in eigenen Worten, was Sie machen. Die KI schreibt daraus Entwürfe, die Sie danach in Ruhe anpassen. Es wird nichts automatisch veröffentlicht.</p>

    <form method="POST" action="{{ route('admin.ai.wizard.run') }}" class="mt-8 space-y-6" x-data="busyForm" @submit="start()">
        @csrf
        <section class="nova-card space-y-5">
            <div>
                <label class="nova-label" for="industry">Branche</label>
                <input class="nova-input" id="industry" name="industry" maxlength="120" value="{{ old('industry', $industry) }}" placeholder="z. B. Bäckerei, Physiotherapie, Malerbetrieb">
            </div>
            <div>
                <label class="nova-label" for="offer">Was bieten Sie an? <span class="text-red-600">*</span></label>
                <textarea class="nova-input" id="offer" name="offer" rows="4" required minlength="10" maxlength="2000" placeholder="z. B. Wir sind eine Familienbäckerei in Köln-Ehrenfeld. Wir backen Brot mit Sauerteig, Brötchen und Kuchen nach alten Rezepten. Es gibt auch Frühstück im Café.">{{ old('offer', $tagline) }}</textarea>
                <p class="nova-help">Stichworte reichen. Je konkreter, desto besser werden die Texte.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="nova-label" for="audience">Wer sind Ihre Kunden?</label>
                    <input class="nova-input" id="audience" name="audience" maxlength="500" value="{{ old('audience') }}" placeholder="z. B. Familien und Berufstätige aus dem Viertel">
                </div>
                <div>
                    <label class="nova-label" for="highlights">Was macht Sie besonders?</label>
                    <input class="nova-input" id="highlights" name="highlights" maxlength="1000" value="{{ old('highlights') }}" placeholder="z. B. Bio-Mehl aus der Region, sonntags geöffnet">
                </div>
            </div>
            <fieldset>
                <legend class="nova-label">Wie sollen die Texte klingen?</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (['persoenlich' => ['Freundlich und persönlich', 'Sie-Form, warm'], 'professionell' => ['Sachlich und professionell', 'Sie-Form, seriös'], 'locker' => ['Locker und modern', 'Du-Form, jung']] as $value => [$label, $hint])
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-checked:border-nova-500 has-checked:bg-nova-50">
                            <input type="radio" name="tone" value="{{ $value }}" class="mt-1 accent-nova-600" @checked(old('tone', 'persoenlich') === $value)>
                            <span><span class="block text-sm font-medium">{{ $label }}</span><span class="text-xs text-slate-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="nova-label">Welche Seiten soll die KI schreiben?</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($pages as $key => [$label, $about])
                        @php $exists = in_array(mb_strtolower($label), $existing, true); @endphp
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-checked:border-nova-500 has-checked:bg-nova-50">
                            <input type="checkbox" name="pages[]" value="{{ $key }}" class="mt-1 accent-nova-600" @checked(in_array($key, old('pages', $exists ? [] : array_keys($pages)), true))>
                            <span><span class="block text-sm font-medium">{{ $label }}</span>
                                <span class="text-xs {{ $exists ? 'text-amber-700' : 'text-slate-500' }}">{{ $exists ? 'Gibt es schon. Bei Auswahl entsteht eine zweite Version.' : ucfirst(\Illuminate\Support\Str::before($about, ',')) }}</span></span>
                        </label>
                    @endforeach
                </div>
                <p class="nova-help">Impressum und Datenschutz schreibt die KI bewusst nicht. Dafür gibt es unter „Seiten“ eigene Vorlagen mit Ihren Firmendaten.</p>
            </fieldset>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-4">
            <p x-show="busy" class="text-sm text-slate-600" role="status">Die KI schreibt Ihre Seiten. Das dauert meist 30 bis 90 Sekunden … <span x-text="seconds + ' s'"></span></p>
            <button class="nova-btn" :disabled="busy">
                <x-icon name="sparkles" class="size-4" />
                <span x-text="busy ? 'Wird geschrieben …' : 'Seiten schreiben lassen'">Seiten schreiben lassen</span>
            </button>
        </div>
    </form>
@endsection
