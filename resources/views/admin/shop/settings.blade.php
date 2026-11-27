@extends('layouts.admin')
@section('title', 'Shop-Einstellungen')
@section('width', 'max-w-6xl')

@php
    $euro = fn ($c) => $c === null ? '' : number_format(((int) $c) / 100, 2, ',', '');
    $chk = fn ($k, $d = '0') => old($k, $s('shop_'.$k, $d)) === '1' || old($k) === true;
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
    <p class="mt-1 text-slate-600">Versand, Steuern, Zahlungsarten und Rechtstexte.</p>
    @include('admin.shop.nav')

    <form method="POST" action="{{ route('admin.shop.settings.update') }}" class="mt-6 space-y-6">
        @csrf @method('PUT')

        <section class="nova-card">
            <label class="flex items-start gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="mt-1 size-5" @checked($chk('enabled'))>
                <span><span class="font-semibold">Shop ist online</span>
                    <span class="block text-sm text-slate-600">Dann erscheint „Shop“ im Menü Ihrer Website mit Warenkorb und Kasse.</span></span>
            </label>
            <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm">
                <p class="font-medium">Rechtstexte für den Shop</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($legal as $purpose => $l)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="size-2.5 rounded-full {{ $l['page'] ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                            <span>{{ $l['label'] }}:</span>
                            @if ($l['page'])
                                <a href="{{ route('admin.pages.edit', $l['page']) }}" class="text-nova-700 hover:underline">veröffentlicht</a>
                            @else
                                <span class="text-slate-500">fehlt, Vorlage unter <a href="{{ route('admin.pages.index') }}" class="text-nova-700 hover:underline">Seiten › Neue Seite</a></span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">Die Vorlagen sind ein Ausgangspunkt und ersetzen keine Rechtsberatung. Für Abmahnsicherheit empfehlen sich geprüfte Texte (z. B. Händlerbund, IT-Recht Kanzlei).</p>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="nova-card space-y-4">
                <h2 class="font-semibold">Steuern</h2>
                <div>
                    <label class="nova-label" for="country">Sitz Ihres Unternehmens</label>
                    <select class="nova-input" id="country" name="country">
                        @foreach (\App\Shop\ShopSettings::COUNTRIES as $code => $c)
                            @if ($c['rates'])<option value="{{ $code }}" @selected(old('country', \App\Shop\ShopSettings::country()) === $code)>{{ $c['label'] }} ({{ $c['currency'] }})</option>@endif
                        @endforeach
                    </select>
                    <p class="nova-help">Bestimmt Währung und Mehrwertsteuersätze.</p>
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="small_business" value="0">
                    <input type="checkbox" name="small_business" value="1" class="mt-0.5 size-4" @checked($chk('small_business'))>
                    <span><strong class="font-medium">Kleinunternehmer</strong> (keine Umsatzsteuer, z. B. § 19 UStG)</span>
                </label>
                <div>
                    <label class="nova-label" for="vat_id">USt-IdNr. oder Steuernummer (für Rechnungen)</label>
                    <input class="nova-input" id="vat_id" name="vat_id" maxlength="30" value="{{ old('vat_id', $s('shop_vat_id')) }}">
                </div>
            </section>

            <section class="nova-card space-y-4">
                <h2 class="font-semibold">Versand</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="nova-label" for="shipping">Versandkosten</label>
                        <input class="nova-input" id="shipping" name="shipping" inputmode="decimal" value="{{ old('shipping', $euro($s('shop_shipping_cents', '490'))) }}">
                    </div>
                    <div>
                        <label class="nova-label" for="free_from">Versandkostenfrei ab</label>
                        <input class="nova-input" id="free_from" name="free_from" inputmode="decimal" placeholder="leer = nie" value="{{ old('free_from', $euro($s('shop_free_from_cents'))) }}">
                    </div>
                </div>
                <div>
                    <label class="nova-label" for="delivery_time">Übliche Lieferzeit</label>
                    <input class="nova-input" id="delivery_time" name="delivery_time" maxlength="60" value="{{ old('delivery_time', $s('shop_delivery_time', '2–4 Werktage')) }}">
                </div>
                <fieldset>
                    <legend class="nova-label">Wir liefern nach</legend>
                    <div class="mt-1 grid grid-cols-2 gap-1.5 text-sm sm:grid-cols-3">
                        @foreach (\App\Shop\ShopSettings::COUNTRIES as $code => $c)
                            <label class="flex items-center gap-2"><input type="checkbox" name="ship_to[]" value="{{ $code }}" class="size-4" @checked(in_array($code, old('ship_to', $ships)))> {{ $c['label'] }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label class="nova-label" for="notify_email">Neue Bestellungen melden an</label>
                    <input class="nova-input" id="notify_email" name="notify_email" type="email" placeholder="{{ $s('contact_email') }}" value="{{ old('notify_email', $s('shop_notify_email')) }}">
                </div>
            </section>
        </div>

        <section class="nova-card">
            <h2 class="font-semibold">Zahlungsarten</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2.5 font-medium"><input type="hidden" name="pay_prepayment" value="0"><input type="checkbox" name="pay_prepayment" value="1" class="size-4" @checked($chk('pay_prepayment', '1'))> Vorkasse (Überweisung)</label>
                    <div><label class="nova-label" for="account_holder">Kontoinhaber</label><input class="nova-input" id="account_holder" name="account_holder" value="{{ old('account_holder', $s('shop_account_holder')) }}"></div>
                    <div class="grid grid-cols-[1fr_8rem] gap-3">
                        <div><label class="nova-label" for="iban">IBAN</label><input class="nova-input font-mono" id="iban" name="iban" value="{{ old('iban', $s('shop_iban')) }}"></div>
                        <div><label class="nova-label" for="bic">BIC</label><input class="nova-input font-mono" id="bic" name="bic" value="{{ old('bic', $s('shop_bic')) }}"></div>
                    </div>
                </div>
                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2.5 font-medium"><input type="hidden" name="pay_invoice" value="0"><input type="checkbox" name="pay_invoice" value="1" class="size-4" @checked($chk('pay_invoice'))> Kauf auf Rechnung</label>
                    <p class="text-sm text-slate-600">Kunden zahlen nach Erhalt der Ware. Bequem für Stammkunden, aber mit Ausfallrisiko.</p>
                </div>
                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2.5 font-medium"><input type="hidden" name="pay_paypal" value="0"><input type="checkbox" name="pay_paypal" value="1" class="size-4" @checked($chk('pay_paypal'))> PayPal</label>
                    <p class="text-sm text-slate-600">Zugangsdaten finden Sie unter developer.paypal.com › Apps &amp; Credentials.</p>
                    <div><label class="nova-label" for="paypal_client">Client ID</label><input class="nova-input font-mono text-xs" id="paypal_client" name="paypal_client" value="{{ old('paypal_client', $s('shop_paypal_client')) }}"></div>
                    <div><label class="nova-label" for="paypal_secret">Secret</label><input class="nova-input font-mono text-xs" id="paypal_secret" name="paypal_secret" type="password" autocomplete="off" placeholder="{{ $hasPaypal ? 'gespeichert, nur zum Ändern ausfüllen' : '' }}"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="paypal_sandbox" value="0"><input type="checkbox" name="paypal_sandbox" value="1" class="size-4" @checked($chk('paypal_sandbox'))> Testmodus (Sandbox)</label>
                </div>
                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2.5 font-medium"><input type="hidden" name="pay_stripe" value="0"><input type="checkbox" name="pay_stripe" value="1" class="size-4" @checked($chk('pay_stripe'))> Kreditkarte, Apple Pay, Google Pay (Stripe)</label>
                    <p class="text-sm text-slate-600">Den geheimen Schlüssel finden Sie im Stripe-Dashboard unter Entwickler › API-Schlüssel. Ein Schlüssel mit sk_test_ ist zum Ausprobieren.</p>
                    <div><label class="nova-label" for="stripe_secret">Geheimer Schlüssel</label><input class="nova-input font-mono text-xs" id="stripe_secret" name="stripe_secret" type="password" autocomplete="off" placeholder="{{ $hasStripe ? 'gespeichert, nur zum Ändern ausfüllen' : 'sk_live_…' }}"></div>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">Schlüssel werden verschlüsselt gespeichert. Weitere Zahlungsarten (z. B. Klarna, Mollie) können als Plugin ergänzt werden.</p>
        </section>

        <button class="nova-btn">Speichern</button>
    </form>
@endsection
