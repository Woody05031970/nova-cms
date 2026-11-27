@extends('layouts.admin')
@section('title', $campaign->exists ? $campaign->subject : 'Neuer Newsletter')
@section('width', 'max-w-6xl')
@push('head') @vite('resources/js/app.js') @endpush

@section('content')
    @php $locked = $campaign->exists && $campaign->status !== 'draft'; @endphp
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.newsletter.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Newsletter</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $campaign->exists ? $campaign->subject : 'Neuer Newsletter' }}</h1>
        </div>
        @if ($campaign->exists)<span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ $campaign->statusLabel() }}</span>@endif
    </div>
    @error('mail')<p class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" x-data="{ subject: @js(old('subject', $campaign->subject)), heading: @js(old('heading', $campaign->heading)), body: @js(old('body', $campaign->body)), image: @js(old('image', $campaign->image)), button_label: @js(old('button_label', $campaign->button_label)), button_url: @js(old('button_url', $campaign->button_url)) }">
        <form method="POST" action="{{ $campaign->exists ? route('admin.newsletter.update', $campaign) : route('admin.newsletter.store') }}" class="nova-card space-y-4">
            @csrf @if ($campaign->exists) @method('PUT') @endif
            <fieldset @disabled($locked) class="space-y-4">
                <div><label class="nova-label" for="subject">Betreff</label><input class="nova-input" id="subject" name="subject" x-model="subject" maxlength="150" required placeholder="z. B. Neu im Sortiment: unsere Herbstaktion"></div>
                <div><label class="nova-label" for="preheader">Vorschautext (optional)</label><input class="nova-input" id="preheader" name="preheader" value="{{ old('preheader', $campaign->preheader) }}" maxlength="150"><p class="nova-help">Erscheint im Posteingang neben dem Betreff.</p></div>
                <div><label class="nova-label" for="heading">Überschrift</label><input class="nova-input" id="heading" name="heading" x-model="heading" maxlength="150"></div>
                <div><label class="nova-label" for="body">Text</label><textarea class="nova-input" id="body" name="body" rows="10" x-model="body" required placeholder="Schreiben Sie wie in einer E-Mail. Eine Leerzeile beginnt einen neuen Absatz."></textarea></div>
                <div><label class="nova-label" for="image">Bild (optional, Adresse aus der Mediathek)</label><input class="nova-input" id="image" name="image" x-model="image" placeholder="/uploads/…"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="nova-label" for="button_label">Knopf-Text (optional)</label><input class="nova-input" id="button_label" name="button_label" x-model="button_label" maxlength="60" placeholder="Jetzt ansehen"></div>
                    <div><label class="nova-label" for="button_url">Knopf-Ziel</label><input class="nova-input" id="button_url" name="button_url" x-model="button_url" placeholder="/shop"></div>
                </div>
                @unless ($locked)<div class="flex justify-end"><button class="nova-btn">Entwurf speichern</button></div>@endunless
            </fieldset>
        </form>

        <div class="space-y-4">
            <section class="nova-card p-4">
                <p class="mb-2 font-mono text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase">Vorschau</p>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <div class="h-1.5 bg-nova-600"></div>
                    <template x-if="image"><img :src="image" alt="" class="w-full"></template>
                    <div class="space-y-2 bg-white p-4 text-sm">
                        <p class="text-lg font-semibold" x-text="heading || subject || 'Betreff'"></p>
                        <template x-for="p in (body || '').split(/\n\s*\n/).filter(x => x.trim())"><p class="whitespace-pre-line text-slate-700" x-text="p"></p></template>
                        <template x-if="button_label"><span class="mt-2 inline-block rounded-lg bg-nova-600 px-3 py-2 font-semibold text-white" x-text="button_label"></span></template>
                    </div>
                </div>
            </section>
            @if ($campaign->exists)
                <section class="nova-card space-y-3 p-5" x-data="newsletterSend(@js(['url' => route('admin.newsletter.send', $campaign), 'total' => $recipients, 'status' => $campaign->status, 'sent' => $campaign->sent_count]))">
                    <h2 class="font-semibold">Versand</h2>
                    <form method="POST" action="{{ route('admin.newsletter.test', $campaign) }}">@csrf<button class="nova-btn-secondary w-full">Test-E-Mail an mich senden</button></form>
                    <template x-if="status !== 'sent'">
                        <div class="space-y-2">
                            <p class="text-sm text-slate-600">Geht an <strong>{{ $recipients }}</strong> bestätigte {{ $recipients === 1 ? 'Abonnentin bzw. Abonnenten' : 'Abonnenten' }}.</p>
                            <button type="button" class="nova-btn w-full" @click="start()" :disabled="busy || {{ $recipients }} === 0" data-send-newsletter><span x-text="busy ? 'Wird versendet …' : 'Jetzt an alle senden'"></span></button>
                        </div>
                    </template>
                    <div x-show="busy || status === 'sent'" class="space-y-1">
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-nova-600 transition-all" :style="'width:' + pct + '%'"></div></div>
                        <p class="text-sm text-slate-600" x-text="sent + ' von ' + total + ' versendet' + (failed ? ', ' + failed + ' fehlgeschlagen' : '')" data-send-progress></p>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-red-700"></p>
                    @if ($campaign->status === 'draft')
                        <form method="POST" action="{{ route('admin.newsletter.destroy', $campaign) }}" onsubmit="return confirm('Entwurf löschen?')">@csrf @method('DELETE')<button class="text-sm text-red-700 hover:underline">Entwurf löschen</button></form>
                    @endif
                </section>
            @endif
        </div>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('newsletterSend', (cfg) => ({
                busy: false, sent: cfg.sent, total: cfg.total, failed: 0, status: cfg.status, error: '',
                get pct() { return this.total ? Math.round(this.sent / this.total * 100) : 100; },
                async start() {
                    if (!confirm('Newsletter jetzt an ' + this.total + ' Abonnenten senden?')) return;
                    this.busy = true; this.error = '';
                    try {
                        for (;;) {
                            const r = await fetch(cfg.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } });
                            if (!r.ok) throw new Error();
                            const d = await r.json();
                            Object.assign(this, { sent: d.sent, total: d.total, failed: d.failed || 0 });
                            if (d.done) { this.status = 'sent'; break; }
                        }
                    } catch { this.error = 'Der Versand wurde unterbrochen. Klicken Sie erneut, es geht an der gleichen Stelle weiter.'; }
                    this.busy = false;
                },
            }));
        });
    </script>
@endsection
