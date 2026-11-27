{{-- Ein Menüpunkt in der Menüverwaltung ($level 0 = Hauptpunkt, 1 = Unterpunkt) --}}
<div class="rounded-xl border bg-white shadow-xs transition" :class="open === item.key ? 'border-nova-300 ring-2 ring-nova-100' : 'border-slate-200 hover:border-slate-300'">
    <div class="flex items-center gap-2 px-2 py-2 sm:px-3">
        <button type="button" data-handle class="cursor-grab touch-none rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing" title="Ziehen zum Verschieben" aria-label="Verschieben">
            <svg class="size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="3.5" r="1.3"/><circle cx="10.5" cy="3.5" r="1.3"/><circle cx="5.5" cy="8" r="1.3"/><circle cx="10.5" cy="8" r="1.3"/><circle cx="5.5" cy="12.5" r="1.3"/><circle cx="10.5" cy="12.5" r="1.3"/></svg>
        </button>
        <button type="button" class="min-w-0 flex-1 text-left" @click="open = open === item.key ? null : item.key" :aria-expanded="open === item.key">
            <span class="block truncate font-medium" :class="hidden(item) && 'text-slate-400 line-through decoration-slate-300'" x-text="title(item)"></span>
            <span class="block truncate font-mono text-[11px] text-slate-500"><span x-text="kind(item)"></span> · <span x-text="target(item)"></span></span>
        </button>
        <span x-show="!item.is_visible" class="hidden rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500 sm:inline">ausgeblendet</span>
        <div class="flex items-center text-slate-400">
            <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-25" @click="move(item, -1)" :disabled="listOf(item).indexOf(item) === 0" title="Nach oben" aria-label="Nach oben">↑</button>
            <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-25" @click="move(item, 1)" :disabled="listOf(item).indexOf(item) === listOf(item).length - 1" title="Nach unten" aria-label="Nach unten">↓</button>
            @if ($level === 0)
                <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-25" @click="indent(item)" :disabled="!canIndent(item)" title="Zum Unterpunkt machen (einrücken)" aria-label="Einrücken">→</button>
            @else
                <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-700" @click="outdent(item)" title="Zum Hauptpunkt machen (ausrücken)" aria-label="Ausrücken">←</button>
            @endif
        </div>
    </div>
    <div x-show="open === item.key" x-transition class="grid gap-3 border-t border-slate-100 px-4 py-3 sm:grid-cols-2">
        <div>
            <label class="nova-label text-xs">Text im Menü</label>
            <input class="nova-input py-1.5 text-sm" x-model="item.label" @input="changed()" maxlength="80" :placeholder="item.type === 'page' ? (page(item.page_id)?.title ?? '') : (item.type === 'shop' ? 'Shop' : '')">
        </div>
        <div x-show="item.type === 'link'">
            <label class="nova-label text-xs">Ziel</label>
            <input class="nova-input py-1.5 text-sm" x-model="item.url" @input="changed()" placeholder="/kontakt oder https://…">
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm sm:col-span-2">
            <label class="flex items-center gap-2"><input type="checkbox" class="size-4 accent-nova-600" x-model="item.is_visible" @change="changed()"> Auf der Website zeigen</label>
            <label class="flex items-center gap-2"><input type="checkbox" class="size-4 accent-nova-600" x-model="item.new_tab" @change="changed()"> In neuem Tab öffnen</label>
            <button type="button" class="ml-auto inline-flex items-center gap-1 rounded-md px-2 py-1 text-red-700 hover:bg-red-50" @click="remove(item)"><x-icon name="trash" class="size-4" /> Aus dem Menü nehmen</button>
        </div>
    </div>
</div>
