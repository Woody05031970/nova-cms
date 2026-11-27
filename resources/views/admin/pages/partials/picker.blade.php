<div class="grid gap-2 rounded-xl border border-nova-200 bg-nova-50/60 p-3 sm:grid-cols-2 lg:grid-cols-3">
    <template x-for="(t, key) in types" :key="key">
        <button type="button" @click="add(key, {{ $at ?? 'i' }})" class="flex items-start gap-2 rounded-lg bg-white p-3 text-left shadow-xs hover:ring-2 hover:ring-nova-200">
            <span><span class="block text-sm font-medium" x-text="t.label"></span><span class="block text-xs text-slate-500" x-text="t.description"></span></span>
        </button>
    </template>
</div>
