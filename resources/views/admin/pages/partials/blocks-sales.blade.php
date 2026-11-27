@php
    $heading = '<div class="grid gap-3 sm:grid-cols-[1fr_2fr]">
        <div><label class="nova-label">Kleine Zeile darüber</label><input class="nova-input" x-model="block.data.eyebrow" maxlength="80" placeholder="optional"></div>
        <div><label class="nova-label">Überschrift</label><input class="nova-input" x-model="block.data.title" maxlength="160"></div>
        <div class="sm:col-span-2"><label class="nova-label">Einleitung (optional)</label><textarea class="nova-input" rows="2" x-model="block.data.intro" maxlength="600"></textarea></div>
    </div>';
    $itemBar = fn (string $list) => '<div class="flex items-center gap-1 text-xs text-slate-500">
        <button type="button" class="rounded px-1.5 py-0.5 hover:bg-slate-100 disabled:opacity-30" :disabled="n === 0" @click="listMove('.$list.', n, -1)" title="Nach oben">↑</button>
        <button type="button" class="rounded px-1.5 py-0.5 hover:bg-slate-100 disabled:opacity-30" :disabled="n === '.$list.'.length - 1" @click="listMove('.$list.', n, 1)" title="Nach unten">↓</button>
        <button type="button" class="rounded px-1.5 py-0.5 text-red-700 hover:bg-red-50" @click="listRemove('.$list.', n)">Entfernen</button>
    </div>';
    $addBtn = 'mt-3 inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:border-nova-500 hover:text-nova-700';
    $item = 'rounded-lg border border-slate-200 bg-slate-50/60 p-3';
@endphp

{{-- VORTEILE --}}
<template x-if="block.type === 'features'">
    <div class="space-y-4">
        {!! $heading !!}
        <div>
            <label class="nova-label">Darstellung</label>
            <div class="flex flex-wrap gap-2">
                <template x-for="[v, l] in [['grid', 'Kacheln mit Symbol'], ['steps', 'Nummerierte Schritte'], ['stats', 'Große Zahlen']]" :key="v">
                    <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="block.data.style === v ? 'border-nova-600 bg-nova-50 text-nova-800' : 'border-slate-200 hover:bg-slate-50'" @click="block.data.style = v" x-text="l"></button>
                </template>
            </div>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <template x-for="(it, n) in block.data.items" :key="n">
                <div class="{{ $item }}">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <select class="nova-input w-auto py-1 text-xs" x-model="it.icon" x-show="block.data.style === 'grid'" title="Symbol">
                            <template x-for="ic in icons" :key="ic"><option :value="ic" x-text="ic" :selected="it.icon === ic"></option></template>
                        </select>
                        <span class="font-mono text-xs text-slate-400" x-show="block.data.style !== 'grid'" x-text="String(n + 1).padStart(2, '0')"></span>
                        {!! $itemBar('block.data.items') !!}
                    </div>
                    <input class="nova-input py-1.5 text-sm font-medium" x-model="it.title" maxlength="120" :placeholder="block.data.style === 'stats' ? 'Zahl, z. B. 5 Min.' : 'Überschrift'">
                    <textarea class="nova-input mt-2 py-1.5 text-sm" rows="2" x-model="it.text" maxlength="500" :placeholder="block.data.style === 'stats' ? 'Was die Zahl bedeutet' : 'Kurzer Text'"></textarea>
                </div>
            </template>
        </div>
        <button type="button" class="{{ $addBtn }}" @click="listAdd(block.data.items, { icon: 'sparkles', title: '', text: '' })">+ Eintrag hinzufügen</button>
    </div>
</template>

{{-- PREISE --}}
<template x-if="block.type === 'pricing'">
    <div class="space-y-4">
        {!! $heading !!}
        <div class="grid gap-3 lg:grid-cols-2">
            <template x-for="(plan, n) in block.data.plans" :key="n">
                <div class="{{ $item }}" :class="plan.highlight && 'ring-2 ring-nova-300'">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-700"><input type="checkbox" x-model="plan.highlight" class="accent-nova-600"> Hervorheben</label>
                        {!! $itemBar('block.data.plans') !!}
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input class="nova-input col-span-2 py-1.5 text-sm font-medium" x-model="plan.name" maxlength="60" placeholder="Name des Pakets">
                        <select class="nova-input col-span-2 py-1.5 text-sm" x-model="plan.product" title="Mit Shop-Produkt verknüpfen">
                            <option value="">Kein Shop-Produkt (Preis von Hand, Knopf mit Link)</option>
                            <template x-for="p in products" :key="p.slug"><option :value="p.slug" x-text="'Shop: ' + p.label" :selected="plan.product === p.slug"></option></template>
                        </select>
                        <input class="nova-input py-1.5 text-sm" x-model="plan.price" maxlength="30" placeholder="Preis, z. B. 149 €" x-show="!plan.product">
                        <input class="nova-input py-1.5 text-sm" x-model="plan.period" maxlength="60" placeholder="z. B. einmalig" :class="plan.product && 'col-span-2'">
                        <input class="nova-input col-span-2 py-1.5 text-sm" x-model="plan.text" maxlength="300" placeholder="Für wen ist das Paket?">
                        <textarea class="nova-input col-span-2 py-1.5 text-sm" rows="4" :value="(plan.features || []).join('\n')" @input="plan.features = $event.target.value.split('\n')" placeholder="Leistungen, eine pro Zeile"></textarea>
                        <input class="nova-input py-1.5 text-sm" x-model="plan.button_label" maxlength="60" :placeholder="plan.product ? 'In den Warenkorb' : 'Knopf-Text'">
                        <input class="nova-input py-1.5 text-sm" x-model="plan.button_url" placeholder="/kontakt" x-show="!plan.product">
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" x-show="plan.product">Preis und Steuerhinweis kommen aus dem Shop. Der Knopf legt das Produkt in den Warenkorb.</p>
                </div>
            </template>
        </div>
        <button type="button" class="{{ $addBtn }}" @click="listAdd(block.data.plans, { name: '', price: '', period: '', text: '', features: [], product: null, button_label: '', button_url: '', highlight: false })" x-show="block.data.plans.length < 6">+ Paket hinzufügen</button>
        <div><label class="nova-label">Hinweis unter den Paketen (optional)</label><input class="nova-input" x-model="block.data.note" maxlength="400"></div>
    </div>
</template>

{{-- MODULE --}}
<template x-if="block.type === 'modules'">
    <div class="space-y-4">
        {!! $heading !!}
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="block.data.builder" class="mt-1 accent-nova-600">
            <span><strong>Baukasten</strong>: Besucher wählen mehrere kaufbare Module aus und sehen sofort die Summe. Funktioniert mit Modulen, die mit einem Shop-Produkt verknüpft sind.</span></label>
        <div class="space-y-2">
            <template x-for="(it, n) in block.data.items" :key="n">
                <div class="{{ $item }} grid gap-2 sm:grid-cols-[1.2fr_1fr_1fr_auto]">
                    <input class="nova-input py-1.5 text-sm font-medium" x-model="it.name" maxlength="80" placeholder="Name">
                    <select class="nova-input py-1.5 text-sm" x-model="it.kind">
                        <option value="core">Im Kern enthalten</option><option value="free">Kostenloses Modul</option><option value="paid">Kostenpflichtig</option>
                    </select>
                    <input class="nova-input py-1.5 text-sm" x-model="it.category" maxlength="40" placeholder="Bereich, z. B. Shop">
                    {!! $itemBar('block.data.items') !!}
                    <textarea class="nova-input py-1.5 text-sm sm:col-span-4" rows="2" x-model="it.text" maxlength="400" placeholder="Was kann das Modul?"></textarea>
                    <template x-if="it.kind === 'paid'">
                        <div class="grid gap-2 sm:col-span-4 sm:grid-cols-[2fr_1fr_auto]">
                            <select class="nova-input py-1.5 text-sm" x-model="it.product">
                                <option value="">Kein Shop-Produkt</option>
                                <template x-for="p in products" :key="p.slug"><option :value="p.slug" x-text="'Shop: ' + p.label" :selected="it.product === p.slug"></option></template>
                            </select>
                            <input class="nova-input py-1.5 text-sm" x-model="it.price" maxlength="30" placeholder="Preis-Text, z. B. ab 49 €">
                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" x-model="it.soon" class="accent-nova-600"> Bald verfügbar</label>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <button type="button" class="{{ $addBtn }}" @click="listAdd(block.data.items, { name: '', text: '', kind: 'free', category: '', price: '', product: null, soon: false })">+ Modul hinzufügen</button>
        <p class="text-xs text-slate-500">Ist ein Shop-Produkt ausgeblendet oder ausverkauft, zeigt die Website „Bald verfügbar“ bzw. den Preis-Text statt des Kaufknopfs.</p>
    </div>
</template>

{{-- VERGLEICH --}}
<template x-if="block.type === 'compare'">
    <div class="space-y-4">
        {!! $heading !!}
        <div class="grid grid-cols-2 gap-3">
            <div><label class="nova-label">Spalte 1 (Sie)</label><input class="nova-input" x-model="block.data.ours" maxlength="60"></div>
            <div><label class="nova-label">Spalte 2 (Vergleich)</label><input class="nova-input" x-model="block.data.theirs" maxlength="60"></div>
        </div>
        <div class="space-y-2">
            <template x-for="(r, n) in block.data.rows" :key="n">
                <div class="{{ $item }} grid gap-2 sm:grid-cols-[2fr_1fr_1fr_auto]">
                    <input class="nova-input py-1.5 text-sm" x-model="r.label" maxlength="160" placeholder="Merkmal">
                    @foreach (['ours', 'theirs'] as $col)
                        <div class="flex gap-1">
                            <select class="nova-input w-auto py-1.5 text-sm" :value="['yes','no','partly'].includes(r.{{ $col }}) ? r.{{ $col }} : 'text'" @change="r.{{ $col }} = $event.target.value === 'text' ? '' : $event.target.value">
                                <option value="yes">Ja</option><option value="no">Nein</option><option value="partly">teilweise</option><option value="text">Text …</option>
                            </select>
                            <input class="nova-input min-w-0 py-1.5 text-sm" x-show="!['yes','no','partly'].includes(r.{{ $col }})" x-model="r.{{ $col }}" maxlength="120">
                        </div>
                    @endforeach
                    {!! $itemBar('block.data.rows') !!}
                </div>
            </template>
        </div>
        <button type="button" class="{{ $addBtn }}" @click="listAdd(block.data.rows, { label: '', ours: 'yes', theirs: 'partly' })">+ Zeile hinzufügen</button>
        <div><label class="nova-label">Hinweis unter der Tabelle</label><input class="nova-input" x-model="block.data.note" maxlength="500" placeholder="z. B. Stand und Quelle des Vergleichs"></div>
        <p class="text-xs text-amber-800">Tipp: Vergleiche müssen sachlich und nachprüfbar sein. Nennen Sie Stand und Grundlage und prüfen Sie die Angaben vor dem Veröffentlichen.</p>
    </div>
</template>

{{-- HÄUFIGE FRAGEN --}}
<template x-if="block.type === 'faq'">
    <div class="space-y-4">
        {!! $heading !!}
        <div class="space-y-2">
            <template x-for="(it, n) in block.data.items" :key="n">
                <div class="{{ $item }}">
                    <div class="mb-2 flex items-center justify-between"><span class="font-mono text-xs text-slate-400" x-text="'Frage ' + (n + 1)"></span>{!! $itemBar('block.data.items') !!}</div>
                    <input class="nova-input py-1.5 text-sm font-medium" x-model="it.q" maxlength="200" placeholder="Frage">
                    <textarea class="nova-input mt-2 py-1.5 text-sm" rows="3" x-model="it.a" maxlength="2000" placeholder="Antwort"></textarea>
                </div>
            </template>
        </div>
        <button type="button" class="{{ $addBtn }}" @click="listAdd(block.data.items, { q: '', a: '' })">+ Frage hinzufügen</button>
        <p class="text-xs text-slate-500">Google kann die Fragen direkt in den Suchergebnissen anzeigen.</p>
    </div>
</template>

{{-- AUFRUF --}}
<template x-if="block.type === 'cta'">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="nova-label">Überschrift</label><input class="nova-input" x-model="block.data.title" maxlength="160"></div>
        <div class="sm:col-span-2"><label class="nova-label">Text (optional)</label><textarea class="nova-input" rows="2" x-model="block.data.text" maxlength="400"></textarea></div>
        <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="60"></div>
        <div><label class="nova-label">Knopf-Ziel</label><input class="nova-input" x-model="block.data.button_url" placeholder="/kontakt"></div>
        <div><label class="nova-label">Zweiter Knopf (optional)</label><input class="nova-input" x-model="block.data.button2_label" maxlength="60"></div>
        <div><label class="nova-label">Ziel</label><input class="nova-input" x-model="block.data.button2_url"></div>
        <div><label class="nova-label">Aussehen</label>
            <select class="nova-input" x-model="block.data.style"><option value="dark">Dunkel (Technik)</option><option value="light">Hell</option></select></div>
    </div>
</template>

{{-- KONTAKTFORMULAR --}}
<template x-if="block.type === 'contact'">
    <div class="space-y-4">
        {!! $heading !!}
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="60"></div>
            <div><label class="nova-label">Dankeschön-Text</label><input class="nova-input" x-model="block.data.success" maxlength="300" placeholder="Vielen Dank! Wir melden uns …"></div>
            <div class="sm:col-span-2"><label class="nova-label">Auswahl „Worum geht es?“ (optional, ein Thema pro Zeile)</label>
                <textarea class="nova-input" rows="3" :value="(block.data.subjects || []).join('\n')" @input="block.data.subjects = $event.target.value.split('\n')" placeholder="Allgemeine Frage&#10;Angebot anfragen"></textarea></div>
        </div>
        <p class="text-xs text-slate-500">Anfragen finden Sie in der Verwaltung unter „Anfragen“. Eine Kopie geht an die Kontakt-E-Mail aus Ihrem Firmenprofil. Mit Spam-Schutz, ganz ohne Rätselbilder.</p>
    </div>
</template>

{{-- TERMINBUCHUNG --}}
<template x-if="block.type === 'booking'">
    <div class="space-y-4">
        <p x-show="!features.terminbuchung" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Das Modul „Terminbuchung“ ist in Ihrer Lizenz nicht enthalten. Der Baustein wird auf der Website erst angezeigt, wenn es freigeschaltet ist.</p>
        {!! $heading !!}
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="nova-label">Leistungen</label>
                <select class="nova-input" x-model.number="block.data.service"><option :value="0">Alle Leistungen zeigen</option>
                    <template x-for="s in services" :key="s.id"><option :value="s.id" x-text="'Nur: ' + s.name" :selected="block.data.service == s.id"></option></template></select></div>
            <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="40"></div>
        </div>
        <p class="text-xs text-slate-500">Leistungen, Öffnungszeiten und gebuchte Termine verwalten Sie unter „Termine“.</p>
    </div>
</template>

{{-- GOOGLE-BEWERTUNGEN --}}
<template x-if="block.type === 'reviews'">
    <div class="space-y-4">
        <p x-show="!features['google-bewertungen']" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Das Modul „Google-Bewertungen“ ist in Ihrer Lizenz nicht enthalten. Der Baustein wird auf der Website erst angezeigt, wenn es freigeschaltet ist.</p>
        {!! $heading !!}
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="nova-label">Wie viele Bewertungen?</label>
                <select class="nova-input" x-model.number="block.data.limit"><template x-for="n in [1,2,3,4,5]" :key="n"><option :value="n" x-text="n" :selected="block.data.limit == n"></option></template></select></div>
            <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" x-model="block.data.show_button" class="accent-nova-600"> Knopf „Bewertung schreiben“ zeigen</label>
        </div>
        <p class="text-xs text-slate-500">Verbindung zu Google und Mindest-Sterne stellen Sie unter „Bewertungen“ ein.</p>
    </div>
</template>

{{-- NEWSLETTER --}}
<template x-if="block.type === 'newsletter'">
    <div class="space-y-4">
        <p x-show="!features.newsletter" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Das Modul „Newsletter“ ist in Ihrer Lizenz nicht enthalten. Der Baustein wird auf der Website erst angezeigt, wenn es freigeschaltet ist.</p>
        {!! $heading !!}
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="nova-label">Knopf-Text</label><input class="nova-input" x-model="block.data.button_label" maxlength="40"></div>
            <div><label class="nova-label">Aussehen</label>
                <select class="nova-input" x-model="block.data.style"><option value="dark">Dunkel (Technik)</option><option value="light">Hell</option></select></div>
            <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" x-model="block.data.ask_name" class="accent-nova-600"> Vorname abfragen</label>
        </div>
        <p class="text-xs text-slate-500">Neue Abonnenten bekommen eine E-Mail mit Bestätigungslink (Double-Opt-in). Erst danach erhalten sie Newsletter. Verwaltung und Versand unter „Newsletter“.</p>
    </div>
</template>
