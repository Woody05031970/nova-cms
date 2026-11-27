@extends('site.layout')
@section('title', 'Kasse – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">Kasse</h1>
        <div class="mt-6"><x-alerts /></div>

        <form method="POST" action="{{ route('shop.checkout.place') }}" class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            @csrf
            <div class="space-y-8">
                @if (\App\Support\Members\Accounts::on() && ! \App\Support\Members\Accounts::customer())
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700" data-checkout-login>Schon Kunde? <a href="{{ route('account.login') }}" class="font-medium text-nova-700 hover:underline">Anmelden</a>, dann sind Ihre Daten schon ausgefüllt. Oder <a href="{{ route('account.register') }}" class="font-medium text-nova-700 hover:underline">Kundenkonto anlegen</a>.</p>
                @endif
                <fieldset class="space-y-4">
                    <legend class="text-lg font-semibold">{{ \App\Support\Members\Accounts::on() ? 'Lieferadresse' : 'Ihre Adresse' }}</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="nova-label" for="email">E-Mail *</label>
                            <input class="nova-input" id="email" name="email" type="email" required autocomplete="email" value="{{ old('email', $defaults['email'] ?? '') }}"></div>
                        <div><label class="nova-label" for="name">Vor- und Nachname *</label>
                            <input class="nova-input" id="name" name="name" required autocomplete="name" value="{{ old('name', $defaults['name'] ?? '') }}"></div>
                        <div><label class="nova-label" for="company">Firma</label>
                            <input class="nova-input" id="company" name="company" autocomplete="organization" value="{{ old('company', $defaults['company'] ?? '') }}"></div>
                        <div class="sm:col-span-2"><label class="nova-label" for="street">Straße und Hausnummer *</label>
                            <input class="nova-input" id="street" name="street" required autocomplete="street-address" value="{{ old('street', $defaults['street'] ?? '') }}"></div>
                        <div><label class="nova-label" for="zip">PLZ *</label>
                            <input class="nova-input" id="zip" name="zip" required autocomplete="postal-code" value="{{ old('zip', $defaults['zip'] ?? '') }}"></div>
                        <div><label class="nova-label" for="city">Ort *</label>
                            <input class="nova-input" id="city" name="city" required autocomplete="address-level2" value="{{ old('city', $defaults['city'] ?? '') }}"></div>
                        <div><label class="nova-label" for="country">Land *</label>
                            <select class="nova-input" id="country" name="country" autocomplete="country">
                                @foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old('country', $defaults['country'] ?? null) === $code)>{{ $label }}</option>@endforeach
                            </select></div>
                        <div><label class="nova-label" for="phone">Telefon (für Rückfragen)</label>
                            <input class="nova-input" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $defaults['phone'] ?? '') }}"></div>
                        <div class="sm:col-span-2"><label class="nova-label" for="notes">Anmerkung zur Bestellung</label>
                            <textarea class="nova-input" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                    </div>
                </fieldset>

                @if (\App\Support\Members\Accounts::on())
                    @php $bill = old('billing', $defaults['billing'] ?? null); @endphp
                    <fieldset class="group space-y-4">
                        <legend class="text-lg font-semibold">Rechnungsadresse</legend>
                        <input type="hidden" name="billing_different" value="0">
                        <div class="flex items-center gap-2"><input type="checkbox" id="billing_different" name="billing_different" value="1" class="size-4" @checked(old('billing_different', $bill ? 1 : 0)) data-billing-different>
                            <label for="billing_different" class="text-sm">Rechnung an eine andere Adresse</label></div>
                        <div class="hidden group-has-[#billing_different:checked]:block" data-billing-fields>@include('site.account._address', ['p' => 'billing', 'a' => $bill ?? [], 'ac' => 'billing'])</div>
                    </fieldset>
                    @if (\App\Support\Members\Accounts::customer())
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="save_address" value="1" class="size-4" @checked(old('save_address', 1))> Adressen in meinem Kundenkonto speichern</label>
                    @endif
                @endif

                <fieldset>
                    <legend class="text-lg font-semibold">Zahlungsart</legend>
                    @if (! $methods)
                        <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Zurzeit ist keine Zahlungsart verfügbar. Bitte kontaktieren Sie uns.</p>
                    @endif
                    <div class="mt-3 space-y-2">
                        @foreach ($methods as $key => $m)
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-nova-500 has-[:checked]:bg-nova-50">
                                <input type="radio" name="payment_method" value="{{ $key }}" class="mt-1" required @checked(old('payment_method', array_key_first($methods)) === $key)>
                                <span><span class="font-medium">{{ $m->label() }}</span><span class="block text-sm text-slate-600">{{ $m->description() }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <aside class="h-fit rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:sticky lg:top-6">
                <h2 class="text-lg font-semibold">Ihre Bestellung</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($items as $i)
                        <li class="flex justify-between gap-3"><span>{{ $i['quantity'] }} × {{ $i['product']->name }}<span class="block text-xs text-slate-500">Lieferzeit {{ $i['product']->deliveryTime() }}</span></span>
                            <span class="whitespace-nowrap">{{ \App\Shop\Money::format($i['total']) }}</span></li>
                    @endforeach
                </ul>
                <div class="mt-4 border-t border-slate-200 pt-3">@include('shop.partials.totals')</div>

                <label class="mt-5 flex gap-2.5 text-sm">
                    <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 size-4 shrink-0" @checked(old('accept_terms'))>
                    <span>Ich habe die
                        @if ($terms)<a href="{{ $terms->url() }}" target="_blank" class="underline">AGB</a>@else AGB @endif
                        gelesen und akzeptiere sie. Die
                        @if ($withdrawal)<a href="{{ $withdrawal->url() }}" target="_blank" class="underline">Widerrufsbelehrung</a>@else Widerrufsbelehrung @endif
                        habe ich zur Kenntnis genommen.</span>
                </label>
                @if ($privacy)<p class="mt-2 text-xs text-slate-500">Ihre Daten verwenden wir nur zur Abwicklung der Bestellung, siehe <a href="{{ $privacy->url() }}" target="_blank" class="underline">Datenschutzerklärung</a>.</p>@endif

                {{-- § 312j BGB: eindeutige Beschriftung des Bestellknopfs --}}
                <button class="nova-btn mt-5 w-full justify-center py-3 text-base" @disabled(! $methods)>Zahlungspflichtig bestellen</button>
                <a href="{{ route('shop.cart') }}" class="mt-3 block text-center text-sm text-slate-600 hover:text-slate-900">Zurück zum Warenkorb</a>
            </aside>
        </form>
    </div>
@endsection
