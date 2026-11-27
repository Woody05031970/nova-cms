{{-- Auswahl aus der Mediathek (wird vom pickerMixin gesteuert) --}}
<dialog x-ref="mediaDialog" @close="picker2.open = false" @click.self="closeLibrary()"
        class="m-auto w-[min(56rem,calc(100vw-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40">
    <div class="flex max-h-[85vh] flex-col">
        <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold" x-text="picker2.kind === 'video' ? 'Video aus der Mediathek' : (picker2.multiple ? 'Bilder aus der Mediathek' : 'Bild aus der Mediathek')"></h2>
            <input type="search" x-model="picker2.search" placeholder="Suchen …" class="nova-input ml-auto w-48 py-1.5 text-sm" aria-label="Mediathek durchsuchen">
            <button type="button" @click="closeLibrary()" class="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Schließen">✕</button>
        </header>
        <div class="flex-1 overflow-y-auto p-5">
            <p x-show="picker2.loading" class="text-sm text-slate-500">Lädt …</p>
            <p x-show="!picker2.loading && !pickerItems().length" class="text-sm text-slate-500">Noch keine passenden Dateien. Laden Sie zuerst etwas hoch.</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <template x-for="m in pickerItems()" :key="m.id">
                    <button type="button" @click="togglePick(m)" class="group relative overflow-hidden rounded-lg border-2 text-left"
                            :class="picker2.chosen.includes(m) ? 'border-nova-500 ring-2 ring-nova-200' : 'border-transparent hover:border-slate-300'">
                        <template x-if="m.kind === 'image'"><img :src="m.thumb" :alt="m.alt || ''" loading="lazy" class="aspect-square w-full bg-slate-100 object-cover"></template>
                        <template x-if="m.kind === 'video'"><video :src="m.url" muted preload="metadata" class="aspect-square w-full bg-slate-900 object-cover"></video></template>
                        <span class="block truncate px-1.5 py-1 text-xs text-slate-600" x-text="m.name"></span>
                        <span x-show="picker2.chosen.includes(m)" class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-nova-600 text-xs text-white">✓</span>
                    </button>
                </template>
            </div>
        </div>
        <footer x-show="picker2.multiple" class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
            <button type="button" class="nova-btn-secondary" @click="closeLibrary()">Abbrechen</button>
            <button type="button" class="nova-btn" :disabled="!picker2.chosen.length" @click="confirmPick()" x-text="picker2.chosen.length + ' übernehmen'"></button>
        </footer>
    </div>
</dialog>
