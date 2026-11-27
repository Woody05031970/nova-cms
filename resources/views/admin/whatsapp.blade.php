@extends('layouts.admin')
@section('title', 'WhatsApp')
@section('width', 'max-w-4xl')

@section('content')
    <div class="flex items-center gap-4">
        <span class="grid size-12 place-items-center rounded-2xl bg-[#25d366] text-white shadow-lg shadow-[#25d366]/30">
            <svg class="size-7" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.7a10.7 10.7 0 0 1-5.5-1.5l-.4-.2-3.9 1 1-3.8-.2-.4A10.7 10.7 0 1 1 16 26.7z"/></svg>
        </span>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">WhatsApp</h1>
            <p class="text-slate-600">Kunden schreiben Ihnen mit einem Klick. Ohne fremde Skripte: Erst beim Klick öffnet sich WhatsApp.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.whatsapp.update') }}" class="mt-6 space-y-6">
        @csrf @method('PUT')
        <section class="nova-card space-y-5">
            <label class="flex items-center gap-3 text-base font-medium"><input type="checkbox" name="whatsapp_enabled" value="1" class="size-5 accent-[#25d366]" @checked(old('whatsapp_enabled', $v['whatsapp_enabled']) === '1')> WhatsApp auf der Website anbieten</label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nova-label" for="whatsapp_number">Ihre WhatsApp-Nummer</label>
                    <input class="nova-input" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $v['whatsapp_number']) }}" placeholder="0171 1234567" inputmode="tel">
                    @error('whatsapp_number')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    <p class="nova-help">Handynummer oder WhatsApp-Business-Nummer. Deutsche Nummern ohne +49 werden automatisch ergänzt.</p>
                </div>
                <div>
                    <label class="nova-label" for="whatsapp_message">Vorausgefüllte Nachricht (optional)</label>
                    <input class="nova-input" id="whatsapp_message" name="whatsapp_message" value="{{ old('whatsapp_message', $v['whatsapp_message']) }}" maxlength="300" placeholder="Hallo, ich habe eine Frage …">
                </div>
            </div>
        </section>
        <section class="nova-card space-y-4">
            <h2 class="font-semibold">Wo soll WhatsApp erscheinen?</h2>
            <label class="flex items-start gap-3"><input type="checkbox" name="whatsapp_floating" value="1" class="mt-1 accent-nova-600" @checked(($v['whatsapp_floating'] ?? '1') === '1')>
                <span><strong>Chat-Knopf</strong> in der Ecke jeder Seite</span></label>
            <div class="grid gap-4 pl-7 sm:grid-cols-2">
                <div><label class="nova-label" for="whatsapp_label">Text neben dem Knopf</label><input class="nova-input" id="whatsapp_label" name="whatsapp_label" maxlength="40" value="{{ old('whatsapp_label', $v['whatsapp_label'] ?? 'Fragen? Schreiben Sie uns') }}"><p class="nova-help">Leer lassen für nur das Symbol. Auf dem Handy wird nur das Symbol gezeigt.</p></div>
                <div><label class="nova-label" for="whatsapp_position">Position</label>
                    <select class="nova-input" id="whatsapp_position" name="whatsapp_position"><option value="right" @selected(($v['whatsapp_position'] ?? 'right') === 'right')>Unten rechts</option><option value="left" @selected($v['whatsapp_position'] === 'left')>Unten links</option></select></div>
            </div>
            <label class="flex items-start gap-3"><input type="checkbox" name="whatsapp_products" value="1" class="mt-1 accent-nova-600" @checked(($v['whatsapp_products'] ?? '1') === '1')>
                <span><strong>„Frage per WhatsApp stellen“</strong> auf jeder Produktseite im Shop, mit Produktname und Link in der Nachricht</span></label>
        </section>
        @if ($link)
            <section class="nova-card space-y-2">
                <h2 class="font-semibold">Ihr WhatsApp-Link</h2>
                <p class="text-sm text-slate-600">Für Knöpfe in Ihren Seiten, für Visitenkarten oder Social Media. Im Baustein „Knopf“ oder „Aufruf“ einfach als Ziel einfügen.</p>
                <div class="flex gap-2"><input class="nova-input font-mono text-sm" readonly value="{{ $link }}" onclick="this.select()"><a href="{{ $link }}" target="_blank" rel="noopener" class="nova-btn-secondary shrink-0">Testen</a></div>
            </section>
        @endif
        <div class="flex justify-end"><button class="nova-btn">Speichern</button></div>
    </form>
@endsection
