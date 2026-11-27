@extends('layouts.admin')
@section('title', 'Einstellungen')
@push('head') @vite('resources/js/app.js') @endpush

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

    <form method="POST" action="{{ route('admin.settings.mail') }}" class="nova-card mt-8 space-y-5" x-data="{ mode: @js(old('mail_mode', $mail['mail_mode'] ?? '')) }">
        @csrf @method('PUT')
        <div>
            <h2 class="font-semibold">E-Mail-Versand</h2>
            <p class="mt-1 text-sm text-slate-600">Damit Bestellbestätigungen und Nachrichten zuverlässig ankommen, verschickt Nova CMS E-Mails am besten über das Postfach bei Ihrem Hoster.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach (['' => ['Noch nicht eingerichtet', 'E-Mails werden nur protokolliert.'], 'smtp' => ['Über mein Postfach (SMTP)', 'Empfohlen, z. B. IONOS oder Strato.'], 'sendmail' => ['PHP-Versand des Servers', 'Einfach, landet aber öfter im Spam.']] as $k => [$l, $h])
                <label class="flex cursor-pointer gap-2.5 rounded-xl border border-slate-200 p-3 text-sm has-[:checked]:border-nova-500 has-[:checked]:bg-nova-50">
                    <input type="radio" name="mail_mode" value="{{ $k }}" x-model="mode" class="mt-0.5"><span><span class="font-medium">{{ $l }}</span><span class="block text-slate-500">{{ $h }}</span></span>
                </label>
            @endforeach
        </div>
        <div x-show="mode === 'smtp'" class="grid gap-4 sm:grid-cols-2">
            <div><label class="nova-label" for="mail_host">Postausgangsserver</label>
                <input class="nova-input" id="mail_host" name="mail_host" placeholder="z. B. smtp.ionos.de oder smtp.strato.de" value="{{ old('mail_host', $mail['mail_host']) }}"></div>
            <div><label class="nova-label" for="mail_port">Port</label>
                <input class="nova-input" id="mail_port" name="mail_port" inputmode="numeric" placeholder="587" value="{{ old('mail_port', $mail['mail_port']) }}"></div>
            <div><label class="nova-label" for="mail_user">Benutzername (meist die E-Mail-Adresse)</label>
                <input class="nova-input" id="mail_user" name="mail_user" autocomplete="off" value="{{ old('mail_user', $mail['mail_user']) }}"></div>
            <div><label class="nova-label" for="mail_password">Passwort des Postfachs</label>
                <input class="nova-input" id="mail_password" name="mail_password" type="password" autocomplete="new-password" placeholder="{{ $hasMailPassword ? 'gespeichert, nur zum Ändern ausfüllen' : '' }}"></div>
        </div>
        <div class="max-w-md"><label class="nova-label" for="mail_from">Absenderadresse</label>
            <input class="nova-input" id="mail_from" name="mail_from" type="email" placeholder="{{ $values['contact_email'] ?: 'info@ihre-domain.de' }}" value="{{ old('mail_from', $mail['mail_from']) }}">
            <p class="nova-help">Sollte zu Ihrer Domain gehören, sonst landen E-Mails im Spam.</p></div>
        <div class="flex flex-wrap justify-end gap-2">
            <button class="nova-btn-secondary" form="mail-test">Test-E-Mail an mich senden</button>
            <button class="nova-btn">E-Mail-Einstellungen speichern</button>
        </div>
    </form>
    <form id="mail-test" method="POST" action="{{ route('admin.settings.mail.test') }}">@csrf</form>

    <section class="nova-card mt-6 overflow-hidden p-0" id="startpakete">
        <div class="nova-tech-bg px-6 py-5 text-white">
            <p class="font-mono text-[10px] font-semibold tracking-[0.14em] text-white/60 uppercase">Startpaket</p>
            <h2 class="mt-1 text-lg font-semibold">Verkaufswebsite für Nova CMS</h2>
        </div>
        <div class="space-y-3 p-6 text-sm text-slate-600">
            <p>Richtet eine komplette Website zum Verkauf von Nova CMS ein: Startseite, Funktionen, Module, Vergleich, Preise, häufige Fragen, Testversion und Kontakt, dazu Shop-Produkte für die Lizenzen und kostenpflichtige Module. Bestehende Seiten bleiben erhalten; die neue Startseite ersetzt nur die bisherige als Startseite.</p>
            @if (\App\Support\Starter\NovaSalesSite::installed())
                <p class="flex items-center gap-2 font-medium text-emerald-700"><x-icon name="check" class="size-4" /> Bereits eingerichtet am {{ \Illuminate\Support\Carbon::parse(\App\Models\Setting::get(\App\Support\Starter\NovaSalesSite::SETTING))->format('d.m.Y') }}.</p>
                <form method="POST" action="{{ route('admin.starter.update') }}">
                    @csrf
                    <p class="mb-2">Nach einem Update auf eine neue Version können Sie neue Module und Shop-Produkte ergänzen. Ihre eigenen Texte und Preise bleiben dabei erhalten.</p>
                    <button class="nova-btn-secondary" data-starter-refresh>Neue Module ergänzen</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.starter.store') }}" onsubmit="return confirm('Verkaufswebsite jetzt einrichten?')">
                    @csrf
                    <button class="nova-btn" data-starter-install>Verkaufswebsite einrichten</button>
                </form>
            @endif
        </div>
    </section>
@endsection
