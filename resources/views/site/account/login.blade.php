@extends('site.layout')
@section('title', 'Anmelden – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')
    <form method="POST" action="{{ route('account.login.do') }}" class="space-y-4" data-account-login>
        @csrf
        <div>
            <label class="nova-label" for="a-email">E-Mail-Adresse</label>
            <input class="nova-input" id="a-email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="a-email-error" @enderror>
            @error('email')<p id="a-email-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
        </div>
        <div><label class="nova-label" for="a-password">Passwort</label><input class="nova-input" id="a-password" type="password" name="password" required autocomplete="current-password"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="accent-nova-600"> Angemeldet bleiben</label>
        <button class="nova-btn w-full">Anmelden</button>
        <p class="flex flex-wrap justify-between gap-2 text-sm">
            <a href="{{ route('account.forgot') }}" class="text-nova-700 hover:underline">Passwort vergessen?</a>
            <a href="{{ route('account.register') }}" class="text-nova-700 hover:underline">Neu hier? Konto anlegen</a>
        </p>
    </form>
@endsection
@section('content')@include('site.members._shell', ['heading' => 'Anmelden', 'eyebrow' => 'Kundenkonto', 'lead' => 'Mit Ihrem Kundenkonto sehen Sie Ihre Bestellungen, und die Kasse ist schon ausgefüllt.'])@endsection
