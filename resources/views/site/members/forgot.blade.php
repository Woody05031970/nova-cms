@extends('site.layout')
@section('title', 'Passwort vergessen – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')
    <form method="POST" action="{{ route('members.forgot.do') }}" class="space-y-4">
        @csrf
        <div>
            <label class="nova-label" for="f-email">E-Mail-Adresse</label>
            <input class="nova-input" id="f-email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
            @error('email')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
        </div>
        <button class="nova-btn w-full">Link zum Zurücksetzen senden</button>
        <p class="text-sm"><a href="{{ route('members.login') }}" class="text-nova-700 hover:underline">Zurück zur Anmeldung</a></p>
    </form>
@endsection
@section('content')@include('site.members._shell', ['heading' => 'Passwort vergessen?', 'lead' => 'Wir schicken Ihnen einen Link, mit dem Sie ein neues Passwort festlegen.'])@endsection
