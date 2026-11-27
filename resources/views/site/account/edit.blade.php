@extends('site.layout')
@section('title', 'Meine Daten – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('content')
    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        @include('site.account._nav', ['heading' => 'Meine Daten'])
        <form method="POST" action="{{ route('account.update') }}" class="mt-8 space-y-8" data-account-form>
            @csrf @method('PUT')
            <fieldset class="rounded-2xl border border-slate-200 bg-white p-6">
                <legend class="px-1 font-semibold">Kontaktdaten</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([['name', 'Name', 'text', 'name', true], ['email', 'E-Mail-Adresse', 'email', 'email', true], ['phone', 'Telefon', 'tel', 'tel', false], ['company', 'Firma', 'text', 'organization', false]] as [$f, $label, $type, $ac, $req])
                        <div><label class="nova-label" for="c-{{ $f }}">{{ $label }}</label><input class="nova-input" id="c-{{ $f }}" type="{{ $type }}" name="{{ $f }}" @if($req) required @endif autocomplete="{{ $ac }}" value="{{ old($f, $user->$f) }}" @error($f) aria-invalid="true" aria-describedby="c-{{ $f }}-error" @enderror>
                            @error($f)<p id="c-{{ $f }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="rounded-2xl border border-slate-200 bg-white p-6">
                <legend class="px-1 font-semibold">Rechnungsadresse</legend>
                @include('site.account._address', ['p' => 'billing', 'a' => $user->billing_address ?? ['name' => $user->name, 'company' => $user->company], 'ac' => 'billing'])
            </fieldset>
            <fieldset class="group rounded-2xl border border-slate-200 bg-white p-6">
                <legend class="px-1 font-semibold">Lieferadresse</legend>
                <input type="hidden" name="shipping_same" value="0">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="shipping_same" name="shipping_same" value="1" class="accent-nova-600" @checked(old('shipping_same', $user->shipping_address ? 0 : 1)) data-shipping-same> Gleich wie Rechnungsadresse</label>
                <div class="mt-4 group-has-[#shipping_same:checked]:hidden">@include('site.account._address', ['p' => 'shipping', 'a' => $user->shipping_address ?? [], 'ac' => 'shipping'])</div>
            </fieldset>
            <button class="nova-btn w-full sm:w-auto">Speichern</button>
        </form>

        <form method="POST" action="{{ route('account.password') }}" id="passwort" class="mt-10 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PUT')
            <h2 class="font-semibold">Passwort ändern</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div><label class="nova-label" for="pw-cur">Bisheriges Passwort</label><input class="nova-input" id="pw-cur" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
                <div><label class="nova-label" for="pw-new">Neues Passwort</label><input class="nova-input" id="pw-new" type="password" name="password" required autocomplete="new-password">@error('password')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror</div>
                <div><label class="nova-label" for="pw-new2">Wiederholen</label><input class="nova-input" id="pw-new2" type="password" name="password_confirmation" required autocomplete="new-password"></div>
            </div>
            <button class="nova-btn-secondary mt-4">Passwort ändern</button>
        </form>

        <details class="mt-10 rounded-2xl border border-red-200 bg-red-50/40 p-6">
            <summary class="cursor-pointer font-semibold text-red-800">Kundenkonto löschen</summary>
            <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3" onsubmit="return confirm('Kundenkonto wirklich löschen?')">
                @csrf @method('DELETE')
                <p class="text-sm text-slate-700">Ihre Zugangsdaten und Adressen werden gelöscht. Bestellungen bewahren wir auf, weil das Gesetz es verlangt.</p>
                <div class="max-w-xs"><label class="nova-label" for="del-pw">Passwort zur Bestätigung</label><input class="nova-input" id="del-pw" type="password" name="current_password" required autocomplete="current-password"></div>
                <button class="rounded-xl bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800">Konto löschen</button>
            </form>
        </details>
    </section>
@endsection
