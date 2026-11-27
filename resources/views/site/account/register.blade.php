@extends('site.layout')
@section('title', 'Kundenkonto anlegen – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')
    <form method="POST" action="{{ route('account.register.do') }}" class="space-y-4" data-account-register>
        @csrf
        <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        @foreach ([['name', 'Vor- und Nachname', 'text', 'name', true], ['email', 'E-Mail-Adresse', 'email', 'email', true], ['company', 'Firma (optional)', 'text', 'organization', false], ['phone', 'Telefon (optional)', 'tel', 'tel', false], ['street', 'Straße und Hausnummer (optional)', 'text', 'street-address', false]] as [$f, $label, $type, $ac, $req])
            <div><label class="nova-label" for="r-{{ $f }}">{{ $label }}</label><input class="nova-input" id="r-{{ $f }}" type="{{ $type }}" name="{{ $f }}" @if($req) required @endif autocomplete="{{ $ac }}" value="{{ old($f) }}" @error($f) aria-invalid="true" aria-describedby="r-{{ $f }}-error" @enderror>
                @error($f)<p id="r-{{ $f }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
        @endforeach
        <div class="grid grid-cols-[7rem_1fr] gap-3">
            <div><label class="nova-label" for="r-zip">PLZ</label><input class="nova-input" id="r-zip" name="zip" autocomplete="postal-code" value="{{ old('zip') }}"></div>
            <div><label class="nova-label" for="r-city">Ort</label><input class="nova-input" id="r-city" name="city" autocomplete="address-level2" value="{{ old('city') }}"></div>
        </div>
        <div><label class="nova-label" for="r-country">Land</label><select class="nova-input" id="r-country" name="country" autocomplete="country">@foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old('country', 'DE') === $code)>{{ $label }}</option>@endforeach</select></div>
        @foreach ([['password', 'Passwort (mindestens 10 Zeichen)'], ['password_confirmation', 'Passwort wiederholen']] as [$f, $label])
            <div><label class="nova-label" for="r-{{ $f }}">{{ $label }}</label><input class="nova-input" id="r-{{ $f }}" type="password" name="{{ $f }}" required autocomplete="new-password" @error($f) aria-invalid="true" @enderror>
                @error($f)<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
        @endforeach
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 accent-nova-600" required @checked(old('consent'))>
            <span>Ich bin einverstanden, dass meine Angaben für mein Kundenkonto gespeichert werden. Ich kann das Konto jederzeit löschen. @if ($privacy)Mehr dazu in der <a class="underline" href="{{ $privacy->url() }}">Datenschutzerklärung</a>.@endif</span></label>
        @error('consent')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
        <button class="nova-btn w-full">Konto anlegen</button>
        <p class="text-sm"><a href="{{ route('account.login') }}" class="text-nova-700 hover:underline">Schon ein Konto? Anmelden</a></p>
    </form>
@endsection
@section('content')@include('site.members._shell', ['heading' => 'Kundenkonto anlegen', 'eyebrow' => 'Kundenkonto', 'lead' => 'Bestellungen jederzeit einsehen und schneller bestellen, weil Ihre Adressen gespeichert sind.'])@endsection
