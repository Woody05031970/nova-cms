@extends('site.layout')
@section('title', 'Neues Passwort – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')
    <form method="POST" action="{{ route('members.reset.do') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="nova-label" for="p-email">E-Mail-Adresse</label><input class="nova-input" id="p-email" type="email" name="email" required value="{{ old('email', $email) }}" autocomplete="email">
            @error('email')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
        <div><label class="nova-label" for="p-pw">Neues Passwort (mindestens 10 Zeichen)</label><input class="nova-input" id="p-pw" type="password" name="password" required autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
        <div><label class="nova-label" for="p-pw2">Passwort wiederholen</label><input class="nova-input" id="p-pw2" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="nova-btn w-full">Passwort speichern</button>
    </form>
@endsection
@section('content')@include('site.members._shell', ['heading' => 'Neues Passwort festlegen', 'eyebrow' => $siteName])@endsection
