<div class="space-y-3 rounded-xl border border-nova-200 bg-nova-50/60 p-3">
    @foreach (['basis' => 'Grundbausteine', 'verkauf' => 'Überzeugen und verkaufen', 'profi' => 'Für Profis'] as $g => $gLabel)
        <div>
            <p class="mb-1.5 px-1 font-mono text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase">{{ $gLabel }}</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="[key, t] in typesIn('{{ $g }}')" :key="key">
                    <button type="button" @click="add(key, {{ $at ?? 'i' }})" class="flex items-start gap-2 rounded-lg bg-white p-3 text-left shadow-xs hover:ring-2 hover:ring-nova-200" :data-add-block="key">
                        <span><span class="block text-sm font-medium" x-text="t.label"></span><span class="block text-xs text-slate-500" x-text="t.description"></span></span>
                    </button>
                </template>
            </div>
        </div>
    @endforeach
</div>
