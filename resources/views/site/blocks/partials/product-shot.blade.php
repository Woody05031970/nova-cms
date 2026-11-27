{{-- Gezeichnete Ansicht der Nova-Verwaltung, ganz ohne Bilddatei --}}
<div class="relative mx-auto w-full max-w-xl lg:mr-0" aria-hidden="true">
    <div class="absolute -inset-8 -z-10 rounded-[2.5rem] bg-gradient-to-tr from-nova-500/30 via-fuchsia-500/20 to-transparent blur-3xl"></div>
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/80 shadow-2xl shadow-black/40 backdrop-blur">
        <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-3">
            <span class="size-2.5 rounded-full bg-white/20"></span><span class="size-2.5 rounded-full bg-white/20"></span><span class="size-2.5 rounded-full bg-white/20"></span>
            <span class="ml-3 flex-1 truncate rounded-md bg-white/5 px-3 py-1 font-mono text-[11px] text-white/50">ihre-website.de/admin/seiten/startseite</span>
        </div>
        <div class="grid grid-cols-[88px_1fr] sm:grid-cols-[120px_1fr]">
            <div class="space-y-1.5 border-r border-white/10 p-3">
                <div class="mb-3 h-5 w-14 rounded bg-gradient-to-r from-nova-400 to-fuchsia-400"></div>
                @foreach (['Übersicht', 'Seiten', 'Menü', 'Medien', 'Shop', 'SEO'] as $n)
                    <div @class(['rounded-md px-2 py-1 text-[10px] sm:text-[11px]', 'bg-white/10 text-white' => $n === 'Seiten', 'text-white/45' => $n !== 'Seiten'])>{{ $n }}</div>
                @endforeach
            </div>
            <div class="space-y-3 p-4">
                <div class="flex items-center justify-between">
                    <div class="h-3 w-32 rounded bg-white/70"></div>
                    <div class="rounded-md bg-emerald-400/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-950">Gespeichert</div>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="mb-2 flex gap-1">@foreach (range(1, 7) as $x)<span class="h-4 w-4 rounded bg-white/10"></span>@endforeach<span class="ml-auto h-4 w-9 rounded bg-gradient-to-r from-nova-500 to-fuchsia-500"></span></div>
                    <div class="h-2.5 w-3/4 rounded bg-white/50"></div>
                    <div class="mt-2 h-2 w-full rounded bg-white/15"></div>
                    <div class="mt-1.5 h-2 w-5/6 rounded bg-white/15"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (range(1, 3) as $x)<div class="h-14 rounded-lg border border-white/10 bg-gradient-to-br from-white/10 to-white/0"></div>@endforeach
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-dashed border-white/15 px-3 py-2 text-[10px] text-white/40">+ Block hinzufügen</div>
            </div>
        </div>
    </div>
    <div class="absolute -bottom-6 -left-4 w-56 rounded-xl border border-white/10 bg-slate-950/90 p-2 shadow-2xl backdrop-blur sm:-left-10">
        <div class="flex items-center gap-2 border-b border-white/10 px-2 pb-2 text-[11px] text-white/60"><x-icon name="search" class="size-3.5" />Preise<span class="ml-auto rounded border border-white/15 px-1 font-mono text-[9px]">Strg K</span></div>
        <div class="mt-1.5 rounded-md bg-nova-600 px-2 py-1.5 text-[11px] text-white">Seite „Preise“ bearbeiten</div>
        <div class="px-2 py-1.5 text-[11px] text-white/50">Produkt „Nova Business“</div>
    </div>
    <div class="absolute -top-5 -right-3 hidden rounded-xl border border-white/10 bg-white px-3 py-2 text-slate-900 shadow-2xl sm:block">
        <p class="font-mono text-[10px] text-slate-500 uppercase">Live-Vorschau</p>
        <p class="text-sm font-semibold">Änderung sofort sichtbar</p>
    </div>
</div>
