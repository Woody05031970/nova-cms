@extends('layouts.admin')
@section('title', 'Design')
@section('width', 'max-w-7xl')
@push('head') @vite('resources/js/app.js') @endpush

@php
    use App\Support\Design\SiteDesign;
    $themes = collect(SiteDesign::THEMES)->map(fn ($t, $k) => $t + ['palette' => SiteDesign::palette($t['brand'])]);
    $state = collect($design)->only(['theme', 'brand', 'accent', 'font', 'heading_font', 'radius', 'header', 'footer', 'animation', 'logo_height', 'show_name'])->all();
    $pill = 'rounded-lg border px-3 py-1.5 text-sm transition';
@endphp

@section('content')
<form method="POST" action="{{ route('admin.design.update') }}" enctype="multipart/form-data"
      x-data="designEditor(@js(['state' => $state, 'themes' => $themes, 'pro' => $pro, 'home' => route('home'), 'logo' => $design['logo']]))" x-cloak>
    @csrf @method('PUT')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Design</h1>
            <p class="mt-1 text-slate-600">Wählen Sie eine Vorlage und passen Sie Farben, Schrift und Logo an. Rechts sehen Sie sofort das Ergebnis.</p>
        </div>
        <button class="nova-btn" data-save-design>Design speichern</button>
    </div>
    @error('theme')<p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
        <div class="space-y-6">
            {{-- Vorlagen --}}
            <section class="nova-card">
                <h2 class="font-semibold">Vorlage</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <template x-for="(t, key) in themes" :key="key">
                        <button type="button" @click="pickTheme(key)" :data-theme-card="key"
                                class="group relative overflow-hidden rounded-xl border text-left transition"
                                :class="s.theme === key ? 'border-nova-600 ring-2 ring-nova-200' : 'border-slate-200 hover:border-slate-300'">
                            <div class="h-24 p-2" :style="'background:' + t.bg">
                                <div class="flex h-4 items-center gap-1 rounded px-1.5" :style="t.header === 'dark' ? 'background:#0f172a' : (t.header === 'glass' ? 'background:rgba(255,255,255,.7);border:1px solid #e2e8f0' : 'background:#fff;border:1px solid #f1f5f9')">
                                    <span class="h-1.5 w-6 rounded-full" :style="'background:' + t.palette[600]"></span><span class="ml-auto h-1 w-10 rounded-full bg-slate-300"></span>
                                </div>
                                <div class="mt-1.5 h-10 p-2" :style="'background:linear-gradient(120deg,#0b0a1f,' + t.palette[700] + ');border-radius:' + (t.radius === 'sharp' ? '2px' : (t.radius === 'round' ? '12px' : '7px'))">
                                    <span class="block h-1.5 w-16 rounded-full bg-white/80" :style="t.font === 'serif' && 'height:7px'"></span>
                                    <span class="mt-1.5 block h-2.5 w-10 rounded" :style="'background:' + t.accent"></span>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 bg-white px-3 py-2">
                                <p class="flex items-center gap-2 text-sm font-semibold"><span x-text="t.label"></span>
                                    <span x-show="!t.free && !pro" class="rounded bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800">Pro</span></p>
                                <p class="text-xs text-slate-500" x-text="t.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
                <input type="hidden" name="theme" :value="s.theme">
            </section>

            {{-- Farben --}}
            <section class="nova-card space-y-4">
                <h2 class="font-semibold">Farben</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="nova-label">Hauptfarbe</label>
                        <div class="flex items-center gap-2"><input type="color" name="brand" x-model="s.brand" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"><input class="nova-input font-mono" x-model="s.brand" maxlength="7"></div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="c in ['#5a37f5','#0369a1','#15803d','#b91c1c','#c2410c','#be185d','#0f766e','#111827']"><button type="button" class="size-6 rounded-full ring-2 ring-white shadow" :style="'background:' + c" @click="s.brand = c" :title="c"></button></template>
                        </div>
                    </div>
                    <div :class="!pro && 'opacity-60'">
                        <label class="nova-label">Akzentfarbe für Verläufe <span x-show="!pro" class="rounded bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800">Pro</span></label>
                        <div class="flex items-center gap-2"><input type="color" name="accent" x-model="s.accent" :disabled="!pro" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"><input class="nova-input font-mono" x-model="s.accent" :disabled="!pro" maxlength="7"></div>
                    </div>
                </div>
            </section>

            {{-- Schrift, Ecken, Kopf- und Fußzeile --}}
            <section class="nova-card space-y-5">
                <h2 class="font-semibold">Schrift und Form</h2>
                <div :class="!pro && 'opacity-60'">
                    <label class="nova-label">Schrift <span x-show="!pro" class="rounded bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800">Pro</span></label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach (SiteDesign::FONTS as $k => [$label, $stack])
                            <button type="button" class="rounded-xl border px-3 py-2 text-left" :class="s.font === '{{ $k }}' ? 'border-nova-600 bg-nova-50' : 'border-slate-200 hover:bg-slate-50'" @click="pro && (s.font = '{{ $k }}')" :disabled="!pro">
                                <span class="block text-xl" style="font-family: {{ $stack }}">Aa</span><span class="text-xs text-slate-500">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="font" :value="s.font">
                    <label class="mt-3 block text-sm">Überschriften in
                        <select name="heading_font" x-model="s.heading_font" class="nova-input mt-1 py-1.5 text-sm" :disabled="!pro">
                            <option value="">derselben Schrift</option>
                            @foreach (SiteDesign::FONTS as $k => [$label]) <option value="{{ $k }}">{{ $label }}</option> @endforeach
                        </select></label>
                </div>
                <div>
                    <label class="nova-label">Ecken</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (SiteDesign::RADII as $k => [$label, $r])
                            <button type="button" class="{{ $pill }}" :class="s.radius === '{{ $k }}' ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200'" @click="s.radius = '{{ $k }}'" style="border-radius: {{ $r['xl'] }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="radius" :value="s.radius">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="nova-label" for="header">Kopfzeile</label>
                        <select class="nova-input" id="header" name="header" x-model="s.header">@foreach (SiteDesign::HEADERS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label class="nova-label" for="footer">Fußzeile</label>
                        <select class="nova-input" id="footer" name="footer" x-model="s.footer"><option value="light">Hell</option><option value="dark">Dunkel</option></select></div>
                </div>
            </section>

            {{-- Effekte --}}
            <section class="nova-card space-y-3" :class="!pro && 'opacity-60'">
                <h2 class="font-semibold">Effekte <span x-show="!pro" class="rounded bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800">Pro</span></h2>
                <p class="text-sm text-slate-600">Abschnitte erscheinen beim Scrollen mit einer sanften Bewegung. Wer am Gerät „Bewegung reduzieren“ eingestellt hat, sieht keine Effekte.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (SiteDesign::ANIMATIONS as $k => $l)
                        <button type="button" class="{{ $pill }}" :class="s.animation === '{{ $k }}' ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200'" @click="pro && (s.animation = '{{ $k }}')" :disabled="!pro">{{ $l }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="animation" :value="s.animation">
                <p class="text-sm text-slate-600">Hintergrundvideos mit Farbschleier stellen Sie im Baustein „Titelbild“ unter „Video“ ein.</p>
            </section>

            {{-- Logo --}}
            <section class="nova-card space-y-4">
                <h2 class="font-semibold">Logo</h2>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="grid h-20 min-w-40 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4">
                        <template x-if="logoPreview"><img :src="logoPreview" alt="Logo" :style="'height:' + s.logo_height + 'px'" class="w-auto"></template>
                        <span x-show="!logoPreview" class="text-sm text-slate-400">Noch kein Logo</span>
                    </div>
                    <div class="space-y-2">
                        <label class="nova-btn-secondary cursor-pointer">Logo hochladen<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="pickLogo($event)"></label>
                        <label x-show="logoPreview" class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_logo" value="1" x-model="removeLogo" class="accent-nova-600"> Logo entfernen</label>
                    </div>
                </div>
                <p class="nova-help">PNG mit transparentem Hintergrund wirkt am besten, auch auf dunklen Kopfzeilen.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="nova-label">Höhe: <span x-text="s.logo_height + ' Pixel'"></span></label><input type="range" min="20" max="96" step="2" name="logo_height" x-model.number="s.logo_height" class="w-full accent-nova-600"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_name" value="1" x-model="s.show_name" class="accent-nova-600"> Namen der Website neben dem Logo zeigen</label>
                </div>
            </section>
        </div>

        {{-- Live-Vorschau --}}
        <section class="sticky top-20 h-fit overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Vorschau">
            <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
                <span class="flex gap-1"><span class="size-2.5 rounded-full bg-red-300"></span><span class="size-2.5 rounded-full bg-amber-300"></span><span class="size-2.5 rounded-full bg-emerald-300"></span></span>
                <span class="ml-2 flex-1 truncate font-mono text-[11px] text-slate-500">Vorschau mit ungespeicherten Änderungen</span>
                <span x-show="loading" class="size-2 animate-pulse rounded-full bg-nova-500"></span>
            </div>
            <div x-ref="box" class="relative h-[70vh] overflow-hidden bg-slate-100">
                <iframe x-ref="frame" title="Vorschau der Website" class="absolute top-0 left-0 origin-top-left border-0 bg-white" :style="frameStyle" @load="loading = false" data-design-preview></iframe>
            </div>
        </section>
    </div>
</form>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('designEditor', (cfg) => ({
            s: { ...cfg.state, show_name: !!cfg.state.show_name, heading_font: cfg.state.heading_font || '' },
            themes: cfg.themes, pro: cfg.pro, logoPreview: cfg.logo, removeLogo: false, loading: true, scale: 0.5, timer: null,
            init() {
                const fit = () => (this.scale = Math.min(1, this.$refs.box.clientWidth / 1280));
                new ResizeObserver(fit).observe(this.$refs.box); fit();
                this.$watch('s', () => this.refresh(), { deep: true });
                this.refresh(0);
            },
            get frameStyle() { return `width:1280px;height:${Math.round(this.$refs.box?.clientHeight / this.scale) || 900}px;transform:scale(${this.scale})`; },
            pickTheme(key) {
                const t = this.themes[key];
                if (!t.free && !this.pro) return alert('Diese Vorlage gehört zu „Design Pro“. Sie können das Modul unter „Lizenz“ freischalten.');
                Object.assign(this.s, { theme: key, brand: t.brand, accent: t.accent, font: t.font, radius: t.radius, header: t.header, footer: t.footer });
            },
            pickLogo(e) { const f = e.target.files[0]; if (f) { this.logoPreview = URL.createObjectURL(f); this.removeLogo = false; } },
            refresh(delay = 350) {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    this.loading = true;
                    const v = { ...this.s, show_name: this.s.show_name ? '1' : '0' };
                    this.$refs.frame.src = cfg.home + '?design_vorschau=' + encodeURIComponent(btoa(JSON.stringify(v)));
                }, delay);
            },
        }));
    });
</script>
@endsection
