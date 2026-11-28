// Leinwand des visuellen Editors. Läuft im iframe mit dem echten Website-Design;
// die Daten und alle Änderungen liegen im Editor (window.parent.novaBuilder).
import morphdom from 'morphdom';
import { esc, renderSection } from './builder/render.js';

// Der Editor kann einen Moment nach der Leinwand fertig sein, daher immer frisch nachschlagen
const P = new Proxy({}, {
    get: (_, k) => window.parent.novaBuilder?.[k],
    set: (_, k, v) => {
        if (window.parent.novaBuilder) window.parent.novaBuilder[k] = v;
        return true;
    },
});
const root = document.getElementById('nbx-root');
const doc = document;
let state = null;
const legacy = new Map(); // Vorschau-HTML der fertigen Bausteine
const pending = new Set();

const svg = (name, cls = 'nbx-i') => `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${(state?.icons || {})[name] || (state?.icons || {}).puzzle || ''}"/></svg>`;
const btn = (act, icon, title, extra = '') => `<button type="button" class="nbx-b" data-act="${act}" title="${title}" aria-label="${title}" ${extra}>${svg(icon)}</button>`;

const ctx = {
    edit: true,
    get design() {
        return state.design;
    },
    get paths() {
        return state.icons;
    },
    icon: (n, cls = 'nb-svg') => svg(n, cls),
    widgetTools: (w) => `<div class="nbx-wtools" contenteditable="false"><span class="nbx-wlabel">${esc(state.labels[w.type] || w.type)}</span>`
        + `<span class="nbx-b nbx-grip" draggable="true" data-drag="widget" data-id="${esc(w.id)}" title="Ziehen zum Verschieben">${svg('grip')}</span>`
        + btn('w-dup', 'copy', 'Doppeln') + btn('w-del', 'trash', 'Löschen') + '</div>',
    sectionTools: (b) => `<div class="nbx-stools" contenteditable="false">`
        + btn('sec-add', 'plus', 'Bereich darüber einfügen')
        + `<span class="nbx-b nbx-grip" draggable="true" data-drag="section" data-id="${esc(b.id)}" title="Ziehen zum Verschieben">${svg('grip')}</span>`
        + `<button type="button" class="nbx-b nbx-name" data-act="sec-edit">${svg('columns')}<span>Bereich</span></button>`
        + btn('sec-dup', 'copy', 'Bereich doppeln') + btn('sec-del', 'trash', 'Bereich löschen') + '</div>',
    emptyColumn: (c) => `<button type="button" class="nbx-empty" data-act="col-add" data-id="${esc(c.id)}" contenteditable="false">${svg('plus')}<span>Element hierher ziehen<br>oder klicken</span></button>`,
};

function legacyHtml(b) {
    const first = state.blocks[0]?.id === b.id;
    const key = b.type + (first ? '1' : '0') + JSON.stringify(b.data);
    if (legacy.has(key)) return legacy.get(key);
    if (!pending.has(key)) {
        pending.add(key);
        P.blockHtml(b, first).then((html) => {
            legacy.set(key, html);
            pending.delete(key);
            if (legacy.size > 120) legacy.delete(legacy.keys().next().value);
            draw();
        });
    }
    // Während des Ladens den letzten Stand dieses Bausteins zeigen, damit nichts springt
    const old = root.querySelector(`[data-nb-block="${CSS.escape(b.id)}"] .nbx-legacy-body`);
    return old ? old.innerHTML : '<div class="nbx-loading">Wird geladen …</div>';
}

function addZone(index, open) {
    const layouts = [['1', [1]], ['1-1', [1, 1]], ['1-1-1', [1, 1, 1]], ['1-1-1-1', [1, 1, 1, 1]], ['2-1', [2, 1]], ['1-2', [1, 2]], ['1-2-1', [1, 2, 1]]];
    const picker = open
        ? `<div class="nbx-layouts"><p>Wie viele Spalten?</p><div>${layouts.map(([k, parts]) => `<button type="button" data-act="layout" data-layout="${k}" title="${parts.length} ${parts.length === 1 ? 'Spalte' : 'Spalten'}">${parts.map((f) => `<i style="flex:${f}"></i>`).join('')}</button>`).join('')}</div>`
          + `<button type="button" class="nbx-link" data-act="close-add">Abbrechen</button></div>`
        : `<div class="nbx-addbtns"><button type="button" class="nbx-round" data-act="open-add" title="Bereich hinzufügen">${svg('plus')}</button><button type="button" class="nbx-round nbx-alt" data-act="templates" title="Vorlage einfügen">${svg('template')}</button></div><p>Bereich hinzufügen oder Vorlage wählen. Sie können auch Elemente von links hierher ziehen.</p>`;
    return `<div class="nbx-addzone${open ? ' is-open' : ''}" data-insert-at="${index}" contenteditable="false">${picker}</div>`;
}

function html() {
    const blocks = state.blocks;
    let out = '';
    if (state.showTitle) {
        out += `<div class="mx-auto max-w-3xl px-4 pt-12 sm:px-6 nbx-title" data-nb-title><h1 class="text-4xl font-semibold tracking-tight sm:text-5xl" data-nb-edit="title" contenteditable="true" data-ph="Titel der Seite">${esc(state.title)}</h1></div>`;
    }
    out += '<div class="pb-16">';
    blocks.forEach((b, i) => {
        if (state.addAt === i) out += addZone(i, true);
        out += `<div class="nbx-insert" data-insert-at="${i}" contenteditable="false"><button type="button" data-act="insert" data-at="${i}" title="Hier einen Bereich einfügen">${svg('plus')}</button></div>`;
        if (b.type === 'section') out += renderSection(b, ctx);
        else {
            out += `<div class="nbx-legacy" data-nb-block="${esc(b.id)}"><div class="nbx-legacy-body">${legacyHtml(b)}</div>`
                + `<div class="nbx-stools" contenteditable="false">${btn('sec-add', 'plus', 'Bereich darüber einfügen')}<span class="nbx-b nbx-grip" draggable="true" data-drag="section" data-id="${esc(b.id)}" title="Ziehen zum Verschieben">${svg('grip')}</span>`
                + `<button type="button" class="nbx-b nbx-name" data-act="sec-edit">${svg('layers')}<span>${esc(state.labels[b.type] || b.type)}</span></button>${btn('sec-dup', 'copy', 'Doppeln')}${btn('sec-del', 'trash', 'Löschen')}</div></div>`;
        }
    });
    out += addZone(blocks.length, state.addAt === blocks.length);
    return out + '</div>';
}

let raf = 0;
function draw() {
    if (!state || raf) return;
    raf = requestAnimationFrame(() => {
        raf = 0;
        const active = doc.activeElement?.isContentEditable ? doc.activeElement.closest('[data-nb-edit]') : null;
        morphdom(root, `<div id="nbx-root">${html()}</div>`, {
            onBeforeElUpdated(from, to) {
                if (from === active) return false; // gerade getippter Text bleibt unangetastet
                // Laufende 3D-Fläche behalten, solange Szene und Farben gleich sind
                if (from.hasAttribute('data-nova-3d') && ['data-nova-3d', 'data-c1', 'data-c2'].every((a) => from.getAttribute(a) === to.getAttribute(a))) return false;
                if (from.isEqualNode(to)) return false;
                return true;
            },
        });
        markSelection();
        if (root.querySelector('[data-nova-3d]') || window.Nova3D) import('./three/nova3d.js').then((m) => m.scan(root));
    });
}

function markSelection() {
    root.querySelectorAll('.nbx-sel').forEach((el) => el.classList.remove('nbx-sel'));
    const s = state.sel;
    if (!s) return;
    const el = s.kind === 'title' ? root.querySelector('[data-nb-title]') : root.querySelector(`[data-nb-${s.kind === 'block' ? 'block' : s.kind === 'col' ? 'col' : s.kind === 'widget' ? 'widget' : 'section'}="${CSS.escape(s.id)}"]`);
    el?.classList.add('nbx-sel');
}

// --- Schnittstelle für den Editor ---------------------------------------------------
window.novaCanvas = {
    render(next) {
        state = next;
        draw();
    },
    reveal(id) {
        const el = root.querySelector(`[data-nb-widget="${CSS.escape(id)}"], [data-nb-section="${CSS.escape(id)}"], [data-nb-block="${CSS.escape(id)}"], [data-nb-col="${CSS.escape(id)}"]`);
        if (!el) return;
        const r = el.getBoundingClientRect();
        if (r.top < 60 || r.bottom > innerHeight) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
    focusField(id, field) {
        const el = root.querySelector(`[data-nb-widget="${CSS.escape(id)}"] [data-nb-edit="${field}"]`);
        if (!el) return;
        window.focus();
        el.focus();
        const r = doc.createRange();
        r.selectNodeContents(el);
        r.collapse(false);
        getSelection().removeAllRanges();
        getSelection().addRange(r);
    },
};

// --- Klicks: auswählen und Werkzeuge -----------------------------------------------
doc.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (a) e.preventDefault(); // Links führen in der Leinwand nirgendwohin
    const act = e.target.closest('[data-act]');
    const holder = (el) => el.closest('[data-nb-widget]') || el.closest('[data-nb-col]') || el.closest('[data-nb-section]') || el.closest('[data-nb-block]');
    if (act) {
        e.preventDefault();
        e.stopPropagation();
        const kind = act.dataset.act;
        const zone = act.closest('[data-insert-at]');
        const top = act.closest('[data-nb-section], [data-nb-block]');
        const topId = top?.dataset.nbSection || top?.dataset.nbBlock;
        const w = act.closest('[data-nb-widget]')?.dataset.nbWidget;
        switch (kind) {
            case 'w-dup': return P.duplicate('widget', w);
            case 'w-del': return P.remove('widget', w);
            case 'sec-dup': return P.duplicate('top', topId);
            case 'sec-del': return P.remove('top', topId);
            case 'sec-edit': return P.select(top.dataset.nbSection ? 'section' : 'block', topId);
            case 'sec-add': return P.openAdd(state.blocks.findIndex((b) => b.id === topId));
            case 'insert': return P.openAdd(+act.dataset.at);
            case 'open-add': return P.openAdd(+zone.dataset.insertAt);
            case 'close-add': return P.openAdd(null);
            case 'templates': return P.showTemplates(+zone.dataset.insertAt);
            case 'layout': return P.addSection(act.dataset.layout, +zone.dataset.insertAt);
            case 'col-add': return P.addInto(act.dataset.id);
        }
        return;
    }
    if (e.target.closest('[data-nb-title]')) return P.select('title', null);
    const h = holder(e.target);
    if (!h) return P.select(null, null);
    if (h.dataset.nbWidget) P.select('widget', h.dataset.nbWidget);
    else if (h.dataset.nbCol) P.select('col', h.dataset.nbCol);
    else if (h.dataset.nbSection) P.select('section', h.dataset.nbSection);
    else P.select('block', h.dataset.nbBlock);
});
doc.addEventListener('submit', (e) => e.preventDefault());

// --- Direkt im Text tippen ----------------------------------------------------------
doc.addEventListener('input', (e) => {
    const el = e.target.closest?.('[data-nb-edit]');
    if (!el) return;
    const field = el.dataset.nbEdit;
    if (field === 'title') return P.setTitle(el.innerText.replace(/\s+/g, ' ').trim());
    const id = el.closest('[data-nb-widget]')?.dataset.nbWidget;
    const value = field === 'html' ? el.innerHTML : el.innerText.replace(/\n+/g, ' ');
    P.inlineEdit(id, field, value);
});
doc.addEventListener('keydown', (e) => {
    const el = e.target.closest?.('[data-nb-edit]');
    if (el && el.dataset.nbEdit !== 'html' && e.key === 'Enter') {
        e.preventDefault();
        el.blur();
    }
    const mod = e.ctrlKey || e.metaKey;
    if (mod && ['s', 'z', 'y'].includes(e.key.toLowerCase()) && !(el && e.key.toLowerCase() !== 's')) {
        e.preventDefault();
        P.key(e.key.toLowerCase(), e.shiftKey);
    } else if (!el && ['Delete', 'Escape'].includes(e.key)) P.key(e.key, false);
});
doc.addEventListener('paste', (e) => {
    const el = e.target.closest?.('[data-nb-edit]');
    if (!el) return;
    e.preventDefault(); // nur reinen Text übernehmen, keine fremden Formate
    const text = e.clipboardData.getData('text/plain');
    doc.execCommand('insertText', false, el.dataset.nbEdit === 'html' ? text : text.replace(/\s*\n\s*/g, ' '));
});

// --- Ziehen und Ablegen -------------------------------------------------------------
const marker = doc.createElement('div');
marker.className = 'nbx-marker';
doc.body.appendChild(marker);
let target = null;

doc.addEventListener('dragstart', (e) => {
    const g = e.target.closest?.('[data-drag]');
    if (!g) return;
    P.dragging = { kind: g.dataset.drag === 'widget' ? 'move-widget' : 'move-top', id: g.dataset.id };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', 'nova');
    const ghost = g.closest('[data-nb-widget], [data-nb-section], [data-nb-block]');
    if (ghost) e.dataTransfer.setDragImage(ghost, 20, 20);
});

function topTarget(y) {
    const items = [...root.querySelectorAll(':scope > .pb-16 > [data-nb-section], :scope > .pb-16 > [data-nb-block]')];
    let index = items.length;
    for (let i = 0; i < items.length; i++) {
        const r = items[i].getBoundingClientRect();
        if (y < r.top + r.height / 2) {
            index = i;
            break;
        }
    }
    const ref = items[index] || items[items.length - 1];
    const r = ref ? ref.getBoundingClientRect() : root.getBoundingClientRect();
    return { kind: 'top', index, rect: { left: r.left, width: r.width, top: items[index] ? r.top : r.bottom } };
}

function colTarget(col, y) {
    const ws = [...col.querySelectorAll(':scope > [data-nb-widget]')];
    let index = ws.length;
    for (let i = 0; i < ws.length; i++) {
        const r = ws[i].getBoundingClientRect();
        if (y < r.top + r.height / 2) {
            index = i;
            break;
        }
    }
    const cr = col.getBoundingClientRect();
    let top;
    if (!ws.length) top = cr.top + cr.height / 2;
    else if (ws[index]) top = ws[index].getBoundingClientRect().top - 4;
    else top = ws[ws.length - 1].getBoundingClientRect().bottom + 4;
    return { kind: 'col', col: col.dataset.nbCol, index, rect: { left: cr.left + 6, width: cr.width - 12, top } };
}

doc.addEventListener('dragover', (e) => {
    const d = P.dragging;
    if (!d) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = d.kind.startsWith('move') ? 'move' : 'copy';
    const wantsColumn = d.kind === 'widget' || d.kind === 'move-widget';
    const col = wantsColumn ? doc.elementFromPoint(e.clientX, e.clientY)?.closest('[data-nb-col]') : null;
    target = col ? colTarget(col, e.clientY) : topTarget(e.clientY);
    marker.style.cssText = `display:block;left:${target.rect.left}px;width:${target.rect.width}px;top:${target.rect.top + scrollY - 2}px`;
    marker.classList.toggle('is-top', target.kind === 'top');
});
const clear = () => {
    marker.style.display = 'none';
    target = null;
};
doc.addEventListener('dragleave', (e) => {
    if (!e.relatedTarget) clear();
});
doc.addEventListener('drop', (e) => {
    e.preventDefault();
    if (P.dragging && target) P.drop(P.dragging, target);
    P.dragging = null;
    clear();
});
doc.addEventListener('dragend', () => {
    P.dragging = null;
    clear();
});

(function ready() {
    if (window.parent.novaBuilder) window.parent.novaBuilder.canvasReady();
    else setTimeout(ready, 40);
})();
