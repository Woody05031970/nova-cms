@extends('site.layout')
@section('title', 'Registrieren – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')
    <form method="POST" action="{{ route('members.register.do') }}" class="space-y-4" data-member-register>
        @csrf
        <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        @foreach ([['name', 'Ihr Name', 'text', 'name'], ['email', 'E-Mail-Adresse', 'email', 'email'], ['password', 'Passwort (mindestens 10 Zeichen)', 'password', 'new-password'], ['password_confirmation', 'Passwort wiederholen', 'password', 'new-password']] as [$f, $label, $type, $ac])
            <div>
                <label class="nova-label" for="r-{{ $f }}">{{ $label }}</label>
                <input class="nova-input" id="r-{{ $f }}" type="{{ $type }}" name="{{ $f }}" required autocomplete="{{ $ac }}" @if($type !== 'password') value="{{ old($f) }}" @endif @error($f) aria-invalid="true" aria-describedby="r-{{ $f }}-error" @enderror>
                @error($f)<p id="r-{{ $f }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 accent-nova-600" required @checked(old('consent'))>
            <span>Ich bin einverstanden, dass meine Angaben für den Zugang gespeichert werden. @if ($privacy)Mehr dazu in der <a class="underline" href="{{ $privacy->url() }}">Datenschutzerklärung</a>.@endif</span></label>
        @error('consent')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
        <button class="nova-btn w-full">Registrieren</button>
        <p class="text-sm"><a href="{{ route('members.login') }}" class="text-nova-700 hover:underline">Schon registriert? Anmelden</a></p>
    </form>
@endsection
@section('content')@include('site.members._shell', ['heading' => 'Zugang beantragen', 'lead' => \App\Support\Members\Members::registration() === 'approval' ? 'Nach der Registrierung prüfen wir Ihre Angaben und schalten den Zugang frei.' : null])@endsection
