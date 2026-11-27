@extends('layouts.admin')
@section('title', 'Einstellungen')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Firmenprofil und Website</h1>
    <p class="mt-1 text-slate-600">Diese Angaben nutzt Nova CMS für Impressum, Google und später für die KI-Vorschläge.</p>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 space-y-6">
        @csrf @method('PUT')
        <section class="nova-card space-y-5">
            <h2 class="font-semibold">Website</h2>
            <div>
                <label class="nova-label" for="site_name">Name der Website</label>
                <input class="nova-input" id="site_name" name="site_name" required maxlength="120" value="{{ old('site_name', $values['site_name']) }}">
            </div>
            <div>
                <label class="nova-label" for="tagline">Kurzbeschreibung</label>
                <input class="nova-input" id="tagline" name="tagline" maxlength="200" placeholder="z. B. Handgemachte Backwaren aus Köln seit 1952" value="{{ old('tagline', $values['tagline']) }}">
                <p class="nova-help">Ein Satz, der sagt, was Sie anbieten. Erscheint in Google unter Ihrem Namen.</p>
            </div>
            <div>
                <label class="nova-label" for="industry">Branche</label>
                <input class="nova-input" id="industry" name="industry" maxlength="120" placeholder="z. B. Bäckerei, Zahnarztpraxis, Sportverein" value="{{ old('industry', $values['industry']) }}">
            </div>
        </section>

        <section class="nova-card space-y-5">
            <h2 class="font-semibold">Kontakt und Impressum</h2>
            <div>
                <label class="nova-label" for="company_name">Firmenname (wie im Handelsregister)</label>
                <input class="nova-input" id="company_name" name="company_name" maxlength="160" value="{{ old('company_name', $values['company_name']) }}">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nova-label" for="street">Straße und Hausnummer</label>
                    <input class="nova-input" id="street" name="street" maxlength="160" autocomplete="street-address" value="{{ old('street', $values['street']) }}">
                </div>
                <div>
                    <label class="nova-label" for="zip_city">PLZ und Ort</label>
                    <input class="nova-input" id="zip_city" name="zip_city" maxlength="160" value="{{ old('zip_city', $values['zip_city']) }}">
                </div>
                <div>
                    <label class="nova-label" for="phone">Telefon</label>
                    <input class="nova-input" id="phone" name="phone" maxlength="60" autocomplete="tel" value="{{ old('phone', $values['phone']) }}">
                </div>
                <div>
                    <label class="nova-label" for="contact_email">E-Mail für Anfragen</label>
                    <input class="nova-input" id="contact_email" name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $values['contact_email']) }}">
                </div>
            </div>
        </section>

        <div class="flex justify-end"><button class="nova-btn">Speichern</button></div>
    </form>
@endsection
