@extends('layouts.admin')
@section('title', 'Google-Bewertungen')
@section('width', 'max-w-5xl')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Google-Bewertungen</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Zeigen Sie Ihre Sterne und die neuesten Bewertungen aus Google auf der Website: Fügen Sie auf einer Seite den Baustein „Google-Bewertungen“ ein. Mit dem Bewertungslink und dem QR-Code bitten Sie zufriedene Kunden um eine Bewertung.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section class="nova-card" aria-labelledby="g-setup">
            <h2 id="g-setup" class="font-semibold">Verbindung zu Google</h2>
            <form method="POST" action="{{ route('admin.reviews.update') }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="nova-label" for="place_id">Place-ID Ihres Unternehmens</label>
                    <input class="nova-input font-mono text-sm" id="place_id" name="place_id" required value="{{ old('place_id', $placeId) }}" placeholder="ChIJ…" @error('place_id') aria-invalid="true" aria-describedby="place-error" @enderror>
                    @error('place_id')<p id="place-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Finden Sie im „Place ID Finder“ von Google: Unternehmen suchen, die angezeigte ID kopieren.</p>
                </div>
                <div>
                    <label class="nova-label" for="api_key">API-Schlüssel (Google Places API)</label>
                    <input class="nova-input font-mono text-sm" id="api_key" name="api_key" type="password" autocomplete="off" placeholder="{{ $hasKey ? 'gespeichert, leer lassen zum Behalten' : 'AIza…' }}">
                    <p class="mt-1 text-xs text-slate-500">In der Google Cloud Console „Places API (New)“ aktivieren und einen Schlüssel anlegen. Für kleine Websites reicht in der Regel das kostenlose Kontingent. Der Schlüssel wird verschlüsselt gespeichert.</p>
                </div>
                <div>
                    <label class="nova-label" for="min">Bewertungen anzeigen ab</label>
                    <select class="nova-input sm:w-60" id="min" name="reviews_min_rating">@foreach ([1, 3, 4, 5] as $n)<option value="{{ $n }}" @selected($minRating === $n)>{{ $n }} {{ $n === 1 ? 'Stern (alle)' : 'Sternen' }}</option>@endforeach</select>
                    <p class="mt-1 text-xs text-slate-500">Google liefert die fünf relevantesten Bewertungen. Die Gesamtnote zeigt immer den echten Durchschnitt.</p>
                </div>
                <div class="flex flex-wrap gap-3"><button class="nova-btn">Speichern und verbinden</button></div>
            </form>
            @if ($hasKey && $placeId)<form method="POST" action="{{ route('admin.reviews.refresh') }}" class="mt-3">@csrf<button class="text-sm text-nova-700 hover:underline">Jetzt neu abrufen</button></form>@endif

            @if ($data)
                <div class="mt-6 border-t border-slate-100 pt-5" data-reviews-preview>
                    <p class="text-sm text-slate-500">So sieht es aus:</p>
                    <p class="mt-1 text-lg font-semibold">{{ $data['name'] }}</p>
                    <p class="text-amber-500" aria-label="{{ number_format((float) $data['rating'], 1, ',', '') }} von 5 Sternen">@for ($i = 1; $i <= 5; $i++){{ $i <= round((float) $data['rating']) ? '★' : '☆' }}@endfor <span class="text-sm text-slate-700">{{ number_format((float) $data['rating'], 1, ',', '') }} · {{ $data['count'] }} Bewertungen</span></p>
                    <ul class="mt-3 space-y-2 text-sm">@foreach ($data['reviews'] as $r)<li><span class="font-medium">{{ $r['author'] }}</span> <span class="text-amber-500">{{ str_repeat('★', $r['rating']) }}</span> <span class="text-slate-500">{{ $r['time'] }}</span><p class="line-clamp-2 text-slate-600">{{ $r['text'] }}</p></li>@endforeach</ul>
                </div>
            @endif
        </section>

        <section class="nova-card text-center" aria-labelledby="g-ask">
            <h2 id="g-ask" class="font-semibold">Um Bewertungen bitten</h2>
            @if ($writeUrl)
                <div class="mx-auto mt-4 w-48" data-review-qr>{!! $qr !!}</div>
                <p class="mt-3 text-sm text-slate-600">QR-Code ausdrucken, z. B. für Theke, Rechnung oder Visitenkarte. Oder den Link per E-Mail oder WhatsApp schicken:</p>
                <input class="nova-input mt-2 font-mono text-xs" readonly value="{{ $writeUrl }}" onclick="this.select()" aria-label="Bewertungslink">
                <a href="{{ $writeUrl }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm text-nova-700 hover:underline">Link testen</a>
            @else
                <p class="mt-3 text-sm text-slate-600">Sobald die Place-ID gespeichert ist, erscheinen hier Link und QR-Code.</p>
            @endif
        </section>
    </div>
@endsection
