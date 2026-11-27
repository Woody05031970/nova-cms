<form method="POST" action="{{ route('members.login.do') }}" class="space-y-4" data-member-login>
    @csrf
    <div>
        <label class="nova-label" for="m-email">E-Mail-Adresse</label>
        <input class="nova-input" id="m-email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="m-email-error" @enderror>
        @error('email')<p id="m-email-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="nova-label" for="m-password">Passwort</label>
        <input class="nova-input" id="m-password" type="password" name="password" required autocomplete="current-password">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="accent-nova-600"> Angemeldet bleiben</label>
    <button class="nova-btn w-full">Anmelden</button>
    <p class="flex flex-wrap justify-between gap-2 text-sm">
        <a href="{{ route('members.forgot') }}" class="text-nova-700 hover:underline">Passwort vergessen?</a>
        @if (\App\Support\Members\Members::registration() !== 'off')<a href="{{ route('members.register') }}" class="text-nova-700 hover:underline">Noch kein Zugang? Registrieren</a>@endif
    </p>
</form>
