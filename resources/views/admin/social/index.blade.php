@extends('layouts.admin')
@section('title', 'Social Media')
@section('width', 'max-w-6xl')
@push('head') @vite('resources/js/app.js') @endpush

@php
    $statusLabel = ['pending' => ['Geplant', 'bg-sky-50 text-sky-800'], 'sent' => ['Veröffentlicht', 'bg-emerald-50 text-emerald-700'], 'failed' => ['Fehlgeschlagen', 'bg-red-50 text-red-700']];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Social Media</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Schreiben Sie Beiträge zu Ihren Seiten und Produkten und veröffentlichen Sie sie direkt auf Ihrer Facebook-Seite und bei Instagram. Beim Veröffentlichen einer Seite können Sie den Beitrag auch gleich im Seiten-Editor mitschicken.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-5">
        {{-- Beitrag schreiben --}}
        <section class="nova-card lg:col-span-3" aria-labelledby="compose" x-data="socialComposer(@js(['suggestUrl' => route('admin.social.suggest'), 'old' => old('text', '')]))">
            <h2 id="compose" class="font-semibold">Neuer Beitrag</h2>
            <form method="POST" action="{{ route('admin.social.publish') }}" class="mt-4 space-y-4" data-social-form>
                @csrf
                <div>
                    <label class="nova-label" for="source">Worum geht es?</label>
                    <select class="nova-input" id="source" name="source" x-model="source">
                        <option value="">Eigenes Thema</option>
                        @if ($pages->isNotEmpty())
                            <optgroup label="Seiten">@foreach ($pages as $p)<option value="page:{{ $p->id }}" @selected(old('source') === 'page:'.$p->id)>{{ $p->title }}</option>@endforeach</optgroup>
                        @endif
                        @if ($products->isNotEmpty())
                            <optgroup label="Produkte">@foreach ($products as $p)<option value="product:{{ $p->id }}" @selected(old('source') === 'product:'.$p->id)>{{ $p->name }}</option>@endforeach</optgroup>
                        @endif
                    </select>
                </div>
                <div x-show="source === ''">
                    <label class="nova-label" for="topic">Ihr Thema in Stichworten</label>
                    <textarea class="nova-input" id="topic" x-model="topic" rows="2" placeholder="z. B. Neue Herbstkollektion ab Samstag im Laden, 10 % für Newsletter-Abonnenten"></textarea>
                </div>
                <button type="button" class="nova-btn-secondary inline-flex items-center gap-1.5" @click="suggest()" :disabled="busy" data-social-suggest>
                    <x-icon name="sparkles" class="size-4" /><span x-text="busy ? 'Schreibe …' : 'Text vorschlagen'"></span>
                </button>
                <p x-show="error" x-text="error" class="text-sm font-medium text-red-700" role="alert"></p>

                <div>
                    <div class="flex justify-between">
                        <label class="nova-label" for="text">Text des Beitrags</label>
                        <span class="text-xs" :class="text.length > 2200 ? 'text-red-700' : 'text-slate-500'" x-text="text.length + ' / 2200'"></span>
                    </div>
                    <textarea class="nova-input" id="text" name="text" x-model="text" rows="9" maxlength="2200" required @error('text') aria-invalid="true" @enderror></textarea>
                    <p class="mt-1 text-xs text-slate-500" x-show="hint" x-text="hint"></p>
                </div>
                <template x-if="image">
                    <figure class="flex items-center gap-3 rounded-lg border border-slate-200 p-2">
                        <img :src="image" alt="" class="size-16 rounded object-cover">
                        <figcaption class="text-sm text-slate-600">Dieses Bild wird mitgeschickt.</figcaption>
                    </figure>
                </template>

                <fieldset>
                    <legend class="nova-label">Veröffentlichen bei</legend>
                    <div class="mt-1 flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" name="networks[]" value="facebook" class="accent-nova-600" @checked($fb['connected']) @disabled(! $fb['connected'])> Facebook @unless($fb['connected'])<span class="text-slate-500">(nicht verbunden)</span>@endunless</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="networks[]" value="instagram" class="accent-nova-600" @checked($ig['connected']) @disabled(! $ig['connected'])> Instagram @unless($ig['connected'])<span class="text-slate-500">(nicht verbunden)</span>@endunless</label>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Instagram braucht ein Bild. Wählen Sie dafür eine Seite oder ein Produkt mit Bild.</p>
                    @error('networks')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </fieldset>

                <div class="flex flex-wrap gap-2">
                    <button class="nova-btn" @disabled(! $fb['connected'] && ! $ig['connected']) data-social-publish>Jetzt veröffentlichen</button>
                    <button type="button" class="nova-btn-secondary inline-flex items-center gap-1.5" @click="copy()"><x-icon name="copy" class="size-4" /><span x-text="copied ? 'Kopiert' : 'Text kopieren'"></span></button>
                </div>
                <p class="sr-only" role="status" x-text="copied ? 'Text in die Zwischenablage kopiert' : ''"></p>
            </form>
        </section>

        {{-- Verbindung --}}
        <section class="nova-card lg:col-span-2" aria-labelledby="connection" id="verbindung">
            <h2 id="connection" class="font-semibold">Verbindung</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex items-center gap-2" data-social-status="facebook"><span class="size-2.5 rounded-full {{ $fb['connected'] ? 'bg-emerald-500' : 'bg-slate-300' }}" aria-hidden="true"></span>Facebook: {{ $fb['connected'] ? '„'.$fb['name'].'“' : 'nicht verbunden' }}</li>
                <li class="flex items-center gap-2" data-social-status="instagram"><span class="size-2.5 rounded-full {{ $ig['connected'] ? 'bg-emerald-500' : 'bg-slate-300' }}" aria-hidden="true"></span>Instagram: {{ $ig['connected'] ? '@'.$ig['name'] : 'nicht verbunden' }}</li>
            </ul>

            <details class="mt-4 rounded-lg bg-slate-50 p-3 text-sm" @if(! $fb['connected']) open @endif>
                <summary class="cursor-pointer font-medium">So verbinden Sie Ihre Konten (einmalig, ca. 15 Minuten)</summary>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-slate-600">
                    <li>Sie brauchen eine <strong>Facebook-Seite</strong> für Ihr Unternehmen. Auf private Profile lässt Meta keine Programme posten.</li>
                    <li>Für Instagram stellen Sie Ihr Konto in der Instagram-App auf <strong>Business- oder Creator-Konto</strong> um und verknüpfen es mit der Facebook-Seite.</li>
                    <li>Melden Sie sich unter <span class="font-mono text-xs">developers.facebook.com</span> an und legen Sie eine App vom Typ „Business“ an.</li>
                    <li>Öffnen Sie dort den „Graph API Explorer“, wählen Sie Ihre App und Ihre Seite und geben Sie diese Berechtigungen frei: <span class="font-mono text-xs">pages_manage_posts, pages_read_engagement, instagram_basic, instagram_content_publish</span>.</li>
                    <li>Erzeugen Sie einen langlebigen <strong>Seiten-Zugriffsschlüssel</strong> und fügen Sie ihn unten mit der Seiten-ID ein. Nova findet das verknüpfte Instagram-Konto selbst.</li>
                </ol>
            </details>

            <form method="POST" action="{{ route('admin.social.connect') }}" class="mt-4 space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="nova-label" for="page_id">Facebook-Seiten-ID</label>
                    <input class="nova-input font-mono text-sm" id="page_id" name="page_id" inputmode="numeric" value="{{ old('page_id', $fb['page_id']) }}" required @error('page_id') aria-invalid="true" aria-describedby="page-id-error" @enderror>
                    @error('page_id')<p id="page-id-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="nova-label" for="token">Seiten-Zugriffsschlüssel</label>
                    <input class="nova-input font-mono text-sm" type="password" id="token" name="token" autocomplete="off" placeholder="{{ $fb['connected'] ? 'gespeichert, nur zum Ändern ausfüllen' : '' }}" @error('token') aria-invalid="true" aria-describedby="token-error" @enderror>
                    @error('token')<p id="token-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Wird verschlüsselt gespeichert.</p>
                </div>
                <button class="nova-btn w-full sm:w-auto" data-social-connect>Verbinden und prüfen</button>
            </form>
            @if ($fb['connected'])
                <form method="POST" action="{{ route('admin.social.disconnect') }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Verbindung trennen</button></form>
            @endif
        </section>
    </div>

    <section class="nova-card mt-6 p-0" aria-labelledby="history">
        <h2 id="history" class="px-6 pt-5 font-semibold">Beiträge</h2>
        @if ($posts->isEmpty())
            <p class="px-6 pt-2 pb-6 text-sm text-slate-600">Noch keine Beiträge.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100" data-social-posts>
                @foreach ($posts as $post)
                    <li class="flex flex-col gap-2 px-6 py-3 sm:flex-row sm:items-start">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm"><strong>{{ $post->networkName() }}</strong>
                                <span class="ml-1 rounded px-1.5 py-0.5 text-xs {{ $statusLabel[$post->status][1] }}">{{ $statusLabel[$post->status][0] }}</span>
                                <span class="ml-1 text-xs text-slate-500">{{ ($post->sent_at ?? $post->scheduled_at ?? $post->created_at)->format('d.m.Y, H:i') }} Uhr</span>
                                @if ($post->page)<span class="text-xs text-slate-500">· Seite „{{ $post->page->title }}“</span>@endif
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-sm text-slate-600">{{ $post->text }}</p>
                            @if ($post->status === 'failed')<p class="mt-0.5 text-sm text-red-700">{{ $post->error }}</p>@endif
                        </div>
                        @if ($post->status === 'failed')
                            <form method="POST" action="{{ route('admin.social.retry', $post) }}">@csrf<button class="nova-btn-secondary py-1.5 text-sm">Erneut senden</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <script>
        function socialComposer(cfg) {
            return {
                source: @js(old('source', '')), topic: '', text: cfg.old, image: null, busy: false, error: '', hint: '', copied: false,
                async suggest() {
                    this.busy = true; this.error = '';
                    try {
                        const r = await fetch(cfg.suggestUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ source: this.source, topic: this.topic }) });
                        const d = await r.json();
                        if (!r.ok) { this.error = d.message || 'Das hat nicht geklappt.'; return; }
                        this.text = d.text; this.image = d.image;
                        this.hint = d.ai ? 'Von der KI vorgeschlagen. Bitte prüfen und anpassen.' : 'Aus einer Vorlage erstellt. Mit einem KI-Anbieter (KI-Assistent) werden die Vorschläge persönlicher.';
                    } catch (e) { this.error = 'Keine Verbindung. Bitte erneut versuchen.'; } finally { this.busy = false; }
                },
                async copy() {
                    try { await navigator.clipboard.writeText(this.text); this.copied = true; setTimeout(() => this.copied = false, 2500); } catch (e) {}
                },
            };
        }
    </script>
@endsection
