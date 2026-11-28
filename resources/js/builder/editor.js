// Visueller Editor (Seiten): Daten, Auswahl, Ziehen und Ablegen, Rückgängig.
// Die Leinwand (canvas.js) läuft im iframe und spricht über window.novaBuilder mit diesem Teil.
import Alpine from 'alpinejs';
import { LAYOUTS } from './render.js';
import { TEMPLATES, section, widget } from './templates.js';

const uid = () => Math.random().toString(36).slice(2, 12);
const csrf = () => document.querySelector('meta[name=csrf-token]').content;
export const strip = (o) => JSON.parse(JSON.stringify(o, (k, v) => (k.startsWith('_') ? undefined : v)));

/** Neue IDs für eine Kopie (Bereich, Spalten, Elemente) */
function freshIds(node) {
    const c = strip(node);
    c.id = uid();
    if (c.type === 'section') {
        c.data.cols.forEach((col) => {
            col.id = uid();
            col.widgets.forEach((w) => (w.id = uid()));
        });
    }
    return c;
}

const e = (t) => String(t ?? '').replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c]);

/** Texte aus Bereichen als einfache Textblöcke (für Beschreibung und KI-Vorschläge) */
export function flatBlocks(blocks) {
    return blocks.flatMap((b) => {
        if (b.type !== 'section') return [b];
        return b.data.cols.flatMap((c) => c.widgets.map((w) => {
            const d = w.data;
            const html = {
                heading: `<h2>${e(d.text)}</h2>`, text: d.html || '', icon: `<h3>${e(d.title)}</h3><p>${e(d.text)}</p>`,
                list: `<ul>${(d.items || []).map((i) => `<li>${e(i)}</li>`).join('')}</ul>`, quote: `<p>${e(d.text)}</p>`,
                counter: `<p>${e(d.number)} ${e(d.label)}</p>`, button: `<p>${e(d.label)}</p>`,
            }[w.type];
            return html ? { type: 'text', data: { html } } : null;
        }).filter(Boolean));
    });
}

const SIMPLE = ['text', 'image', 'button', 'video'];

/** Alte Seiten: aufeinanderfolgende Text-, Bild-, Knopf- und Videoblöcke werden zu Bereichen. */
export function convertLegacy(blocks) {
    const out = [];
    let run = null;
    let changed = false;
    const flush = () => {
        if (run) {
            run.data.cols[0].widgets.at(-1).style.mb = 0;
            out.push(run);
        }
        run = null;
    };
    for (const b of blocks) {
        if (!SIMPLE.includes(b.type)) {
            flush();
            out.push(b);
            continue;
        }
        changed = true;
        const d = b.data || {};
        let w;
        if (b.type === 'text') w = widget('text', d.html || '<p></p>');
        else if (b.type === 'button') w = widget('button', d.label || '', d.url || '', { variant: d.style === 'secondary' ? 'outline' : 'primary' });
        else if (b.type === 'video') w = { ...widget('video'), data: { url: d.url || '', file: d.file || null, poster: d.poster || null, caption: d.caption || '', provider: d.provider, video_id: d.video_id } };
        else {
            w = widget('image');
            Object.assign(w.data, { url: d.url || null, alt: d.alt || '', caption: d.caption || '' });
            if (d.size === 'wide' || d.size === 'full') {
                flush();
                w.style.mb = 0;
                out.push(section('1', [[w]], { width: d.size === 'full' ? 'full' : 'boxed', pt: 32, pb: 0 }));
                continue;
            }
        }
        if (b.type === 'button') w.style.align = d.align === 'left' ? '' : d.align;
        w.style.mb = 32;
        if (!run) run = section('1', [[]], { width: 'narrow', pt: 32, pb: 0 });
        run.data.cols[0].widgets.push(w);
    }
    flush();
    return { blocks: out, changed };
}

export function builderMixin(cfg) {
    return {
        sel: null,
        panel: 'add',
        addTab: 'widgets',
        tab: 'content',
        addTarget: null,
        addAt: null,
        showTitle: cfg.showTitle,
        widgetTypes: cfg.widgets,
        ownTemplates: cfg.templates || [],
        templates: TEMPLATES,
        layouts: Object.entries(LAYOUTS),
        converted: false,
        canvasOk: false,
        undoStack: [],
        redoStack: [],
        lastSnap: '',
        snapTimer: null,
        restoring: false,
        toast: '',
        toastUndo: false,
        toastTimer: null,
        templateTarget: null,

        initBuilder() {
            const conv = convertLegacy(this.blocks);
            this.blocks = conv.blocks;
            this.converted = conv.changed;
            this.lastSnap = JSON.stringify(strip(this.blocks));
            const c = this;
            window.novaBuilder = {
                dragging: null,
                canvasReady: () => {
                    c.canvasOk = true;
                    c.pushCanvas();
                },
                select: (kind, id) => c.select(kind, id),
                blockHtml: (b, first) => c.blockHtml(b, first),
                inlineEdit: (id, field, value) => {
                    const w = c.find(id)?.widget;
                    if (w) w.data[field] = value;
                },
                setTitle: (t) => (c.title = t),
                duplicate: (kind, id) => c.duplicate(kind, id),
                remove: (kind, id) => c.remove(kind, id),
                openAdd: (i) => {
                    c.addAt = i;
                    if (i !== null) c.sel = null;
                },
                showTemplates: (i) => {
                    c.templateTarget = i;
                    c.sel = null;
                    c.panel = 'add';
                    c.addTab = 'templates';
                },
                addSection: (layout, i) => c.addSection(layout, i),
                addInto: (colId) => {
                    c.select('col', colId);
                    c.panel = 'add';
                    c.addTab = 'widgets';
                    c.addTarget = { col: colId };
                },
                drop: (drag, target) => c.drop(drag, target),
                key: (k, shift) => c.key(k, shift),
            };
            this.$watch('blocks', () => this.record(), { deep: true });
            Alpine.effect(() => this.pushCanvas());
            window.addEventListener('keydown', (e) => {
                const typing = e.target.closest('input, textarea, select, [contenteditable="true"], .cm-editor');
                const mod = e.ctrlKey || e.metaKey;
                if (mod && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    return this.key('s');
                }
                if (typing) return;
                if (mod && ['z', 'y'].includes(e.key.toLowerCase())) {
                    e.preventDefault();
                    this.key(e.key.toLowerCase(), e.shiftKey);
                } else if (['Delete', 'Escape'].includes(e.key)) this.key(e.key);
            });
        },

        // --- Leinwand -------------------------------------------------------------------
        get frame() {
            return this.$refs.canvas;
        },
        get startsWithHero() {
            const b = this.blocks[0];
            if (b?.type === 'section') return b.data.cols.some((c) => c.widgets.some((w) => w.type === 'heading' && w.style.tag === 'h1'));
            return b?.type === 'hero' || (['features', 'pricing', 'modules', 'compare', 'faq', 'contact'].includes(b?.type) && !!b.data?.title);
        },
        pushCanvas() {
            const state = {
                blocks: strip(this.blocks),
                sel: this.sel ? { ...this.sel } : null,
                title: this.title,
                showTitle: this.showTitle && !this.startsWithHero,
                addAt: this.addAt,
                design: cfg.design,
                icons: cfg.iconPaths,
                labels: { ...this.widgetTypes, ...Object.fromEntries(Object.entries(this.types).map(([k, t]) => [k, t.label])) },
            };
            if (this.canvasOk) this.frame?.contentWindow?.novaCanvas?.render(state);
        },
        async blockHtml(b, first = false) {
            try {
                const res = await fetch(cfg.blockPreviewUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ page: this.pageId, first, block: JSON.stringify(strip(b)) }),
                });
                return res.ok ? await res.text() : '<div class="nbx-loading">Vorschau nicht möglich</div>';
            } catch {
                return '<div class="nbx-loading">Keine Verbindung</div>';
            }
        },
        get frameWidth() {
            return { desktop: '100%', tablet: '820px', phone: '390px' }[this.device];
        },

        // --- Suchen und Auswählen ---------------------------------------------------------
        find(id) {
            for (let bi = 0; bi < this.blocks.length; bi++) {
                const block = this.blocks[bi];
                if (block.id === id) return { block, bi };
                if (block.type !== 'section') continue;
                for (let ci = 0; ci < block.data.cols.length; ci++) {
                    const col = block.data.cols[ci];
                    if (col.id === id) return { block, bi, col, ci };
                    const wi = col.widgets.findIndex((w) => w.id === id);
                    if (wi > -1) return { block, bi, col, ci, widget: col.widgets[wi], wi };
                }
            }
            return null;
        },
        select(kind, id) {
            this.addAt = null;
            this.addTarget = null;
            this.templateTarget = null;
            if (!kind) {
                this.sel = null;
                this.panel = 'add';
                this.addTab = 'widgets';
                return;
            }
            if (this.sel?.kind !== kind) this.tab = 'content';
            this.sel = { kind, id };
            this.panel = 'edit';
            this.$nextTick(() => id && this.frame?.contentWindow?.novaCanvas?.reveal(id));
        },
        get hit() {
            return this.sel?.id ? this.find(this.sel.id) : null;
        },
        get selWidget() {
            return this.sel?.kind === 'widget' ? this.hit?.widget ?? null : null;
        },
        get selCol() {
            return this.sel?.kind === 'col' ? this.hit?.col ?? null : null;
        },
        get selSection() {
            return ['section', 'col', 'widget'].includes(this.sel?.kind) && this.hit?.block?.type === 'section' ? this.hit.block : null;
        },
        get selBlock() {
            return this.sel?.kind === 'block' ? this.hit?.block ?? null : null;
        },
        // Für x-for, damit die alten Formulare „block“ als Variable bekommen
        get selBlockList() {
            return this.selBlock ? [this.selBlock] : [];
        },
        get selWidgetList() {
            return this.selWidget ? [this.selWidget] : [];
        },
        get crumbs() {
            const h = this.hit;
            if (!h || h.block.type !== 'section') return [];
            const out = [{ kind: 'section', id: h.block.id, label: 'Bereich ' + (h.bi + 1) }];
            if (h.col) out.push({ kind: 'col', id: h.col.id, label: 'Spalte ' + (h.ci + 1) });
            if (h.widget) out.push({ kind: 'widget', id: h.widget.id, label: this.widgetTypes[h.widget.type] });
            return out;
        },
        get tree() {
            const out = [];
            this.blocks.forEach((b, i) => {
                if (b.type !== 'section') return out.push({ kind: 'block', id: b.id, depth: 0, label: this.label(b.type), hint: 'Fertiger Baustein' });
                out.push({ kind: 'section', id: b.id, depth: 0, label: 'Bereich ' + (i + 1), hint: b.data.cols.length + (b.data.cols.length === 1 ? ' Spalte' : ' Spalten') });
                b.data.cols.forEach((c, ci) => {
                    if (b.data.cols.length > 1) out.push({ kind: 'col', id: c.id, depth: 1, label: 'Spalte ' + (ci + 1), hint: '' });
                    c.widgets.forEach((w) => out.push({ kind: 'widget', id: w.id, depth: b.data.cols.length > 1 ? 2 : 1, label: this.widgetTypes[w.type], hint: this.widgetHint(w) }));
                });
            });
            return out;
        },
        widgetHint(w) {
            const d = w.data;
            const t = d.text || d.title || d.label || (d.html || '').replace(/<[^>]+>/g, ' ') || d.number || d.alt || '';
            return String(t).replace(/\s+/g, ' ').trim().slice(0, 40);
        },

        // --- Hinzufügen -------------------------------------------------------------------
        addSection(layout, index = this.blocks.length) {
            const s = section(layout);
            this.blocks.splice(index, 0, s);
            this.addAt = null;
            this.select('col', s.data.cols[0].id);
            this.panel = 'add';
            this.addTab = 'widgets';
            this.addTarget = { col: s.data.cols[0].id };
            this.notify('Bereich eingefügt. Wählen Sie jetzt links ein Element oder ziehen Sie es in eine Spalte.');
        },
        /** Klick auf ein Element in der Leiste: dorthin, wo gerade gearbeitet wird */
        addWidget(type) {
            const w = widget(type);
            const target = this.addTarget?.col ? this.find(this.addTarget.col) : this.hit;
            if (target?.col) {
                const at = target.widget ? target.wi + 1 : target.col.widgets.length;
                target.col.widgets.splice(at, 0, w);
            } else if (target?.block?.type === 'section') {
                target.block.data.cols[0].widgets.push(w);
            } else {
                const at = this.addAt ?? (target ? target.bi + 1 : this.blocks.length);
                this.blocks.splice(at, 0, section('1', [[w]]));
            }
            this.afterAdd(w);
        },
        afterAdd(w) {
            this.select('widget', w.id);
            if (['heading', 'text'].includes(w.type)) {
                setTimeout(() => this.frame?.contentWindow?.novaCanvas?.focusField(w.id, w.type === 'text' ? 'html' : 'text'), 120);
            }
        },
        addLegacy(type) {
            const at = this.addAt ?? (this.hit ? this.hit.bi + 1 : this.blocks.length);
            const b = { id: uid(), type, data: this.defaults[type]() };
            this.blocks.splice(at, 0, b);
            this.select('block', b.id);
        },
        insertTemplate(t, own = false) {
            const at = this.templateTarget ?? this.addAt ?? (this.hit ? this.hit.bi + 1 : this.blocks.length);
            const list = own ? [freshIds(t.block)] : t.make();
            this.blocks.splice(at, 0, ...list);
            this.templateTarget = null;
            this.select(list[0].type === 'section' ? 'section' : 'block', list[0].id);
            this.notify(`Vorlage „${t.name}“ eingefügt. Klicken Sie auf Texte, um sie zu ändern.`);
        },
        async saveTemplate(block) {
            const name = window.prompt('Name der Vorlage, z. B. „Angebot mit Bild“:', '');
            if (!name) return;
            try {
                const res = await fetch(cfg.templatesUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ name, block: JSON.stringify(strip(block)) }),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Speichern fehlgeschlagen.');
                this.ownTemplates = json.templates;
                this.notify(`Als Vorlage „${name}“ gespeichert. Sie finden sie unter „Vorlagen“.`);
            } catch (e) {
                this.error = e.message;
            }
        },
        async deleteTemplate(t) {
            if (!confirm(`Vorlage „${t.name}“ löschen?`)) return;
            const res = await fetch(cfg.templatesUrl + '/' + t.id, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            if (res.ok) this.ownTemplates = (await res.json()).templates;
        },

        // --- Ziehen aus der Leiste --------------------------------------------------------
        startDrag(e, drag) {
            window.novaBuilder.dragging = drag;
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', 'nova');
        },
        endDrag() {
            setTimeout(() => (window.novaBuilder.dragging = null), 50);
        },
        drop(drag, t) {
            if (drag.kind === 'widget' || drag.kind === 'move-widget') {
                let w;
                if (drag.kind === 'widget') w = widget(drag.type);
                else {
                    const from = this.find(drag.id);
                    if (!from?.widget) return;
                    if (t.kind === 'col' && t.col === from.col.id && (t.index === from.wi || t.index === from.wi + 1)) return;
                    w = from.col.widgets.splice(from.wi, 1)[0];
                    if (t.kind === 'col' && t.col === from.col.id && t.index > from.wi) t.index--;
                }
                if (t.kind === 'col') this.find(t.col)?.col.widgets.splice(t.index, 0, w);
                else this.blocks.splice(t.index, 0, section('1', [[w]]));
                return drag.kind === 'widget' ? this.afterAdd(w) : this.select('widget', w.id);
            }
            if (t.kind !== 'top') return;
            if (drag.kind === 'move-top') {
                const from = this.blocks.findIndex((b) => b.id === drag.id);
                if (from < 0 || t.index === from || t.index === from + 1) return;
                const [b] = this.blocks.splice(from, 1);
                this.blocks.splice(t.index > from ? t.index - 1 : t.index, 0, b);
            } else if (drag.kind === 'layout') this.addSection(drag.layout, t.index);
            else if (drag.kind === 'block') {
                this.addAt = t.index;
                this.addLegacy(drag.type);
            } else if (drag.kind === 'template') {
                this.templateTarget = t.index;
                this.insertTemplate(TEMPLATES.find((x) => x.key === drag.key));
            }
        },

        // --- Bearbeiten -------------------------------------------------------------------
        duplicate(kind, id) {
            const h = this.find(id);
            if (!h) return;
            if (kind === 'widget' && h.widget) {
                const copy = strip(h.widget);
                copy.id = uid();
                h.col.widgets.splice(h.wi + 1, 0, copy);
                this.select('widget', copy.id);
            } else {
                const copy = freshIds(h.block);
                this.blocks.splice(h.bi + 1, 0, copy);
                this.select(copy.type === 'section' ? 'section' : 'block', copy.id);
            }
        },
        remove(kind, id) {
            const h = this.find(id);
            if (!h) return;
            if (kind === 'widget' && h.widget) h.col.widgets.splice(h.wi, 1);
            else this.blocks.splice(h.bi, 1);
            if (this.sel?.id === id || !this.find(this.sel?.id)) this.select(null);
            this.notify(kind === 'widget' ? 'Element gelöscht.' : 'Bereich gelöscht.', true);
        },
        moveBy(dir) {
            const h = this.hit;
            if (!h) return;
            if (h.widget) {
                const j = h.wi + dir;
                if (j >= 0 && j < h.col.widgets.length) h.col.widgets.splice(j, 0, h.col.widgets.splice(h.wi, 1)[0]);
                else {
                    // über die Spaltengrenze hinweg
                    const cols = h.block.data.cols;
                    const target = cols[h.ci + dir];
                    if (!target) return;
                    const [w] = h.col.widgets.splice(h.wi, 1);
                    dir < 0 ? target.widgets.push(w) : target.widgets.unshift(w);
                }
            } else {
                const j = h.bi + dir;
                if (j < 0 || j >= this.blocks.length) return;
                this.blocks.splice(j, 0, this.blocks.splice(h.bi, 1)[0]);
            }
            this.$nextTick(() => this.frame?.contentWindow?.novaCanvas?.reveal(this.sel.id));
        },
        setLayout(s, layout) {
            const n = LAYOUTS[layout].length;
            const cols = s.data.cols;
            while (cols.length < n) cols.push({ id: uid(), style: { ...cols[0].style, bg: null, pad: 0, radius: 0, shadow: false, border: false }, widgets: [] });
            while (cols.length > n) cols[n - 1].widgets.push(...cols.pop().widgets);
            s.data.layout = layout;
        },
        toggleIn(list, value) {
            const i = list.indexOf(value);
            i > -1 ? list.splice(i, 1) : list.push(value);
        },
        async addGallery(w, event) {
            const files = [...(event.target.files || event.dataTransfer?.files || [])];
            this.error = '';
            w.data._uploading = true;
            try {
                for (const f of files) w.data.items.push({ url: await this.upload(f), alt: '' });
            } catch (e) {
                this.error = e.message;
            } finally {
                delete w.data._uploading;
                if (event.target.value !== undefined) event.target.value = '';
            }
        },
        pickGallery(w) {
            this.openLibrary('image', true, (list) => list.forEach((m) => w.data.items.push({ url: m.url, alt: m.alt || '' })));
        },
        listText(w) {
            return (w.data.items || []).join('\n');
        },
        setList(w, v) {
            w.data.items = v.split('\n');
        },

        // --- Rückgängig -------------------------------------------------------------------
        record() {
            this.dirty = true;
            if (this.restoring) return;
            clearTimeout(this.snapTimer);
            this.snapTimer = setTimeout(() => this.snapshot(), 400);
        },
        snapshot() {
            const now = JSON.stringify(strip(this.blocks));
            if (now === this.lastSnap) return;
            this.undoStack.push(this.lastSnap);
            if (this.undoStack.length > 100) this.undoStack.shift();
            this.redoStack = [];
            this.lastSnap = now;
        },
        restore(json) {
            this.restoring = true;
            this.blocks = JSON.parse(json);
            this.lastSnap = json;
            if (this.sel && !this.find(this.sel.id)) this.select(null);
            this.$nextTick(() => (this.restoring = false));
        },
        undo() {
            clearTimeout(this.snapTimer);
            this.snapshot();
            if (!this.undoStack.length) return;
            this.redoStack.push(this.lastSnap);
            this.restore(this.undoStack.pop());
        },
        redo() {
            if (!this.redoStack.length) return;
            this.undoStack.push(this.lastSnap);
            this.restore(this.redoStack.pop());
        },
        key(k, shift = false) {
            if (k === 's') return this.submit('save');
            if (k === 'z') return shift ? this.redo() : this.undo();
            if (k === 'y') return this.redo();
            if (k === 'Escape') return this.select(null);
            if (k === 'Delete' && this.sel && this.sel.kind !== 'col' && this.sel.kind !== 'title') this.remove(this.sel.kind === 'widget' ? 'widget' : 'top', this.sel.id);
        },
        notify(text, undo = false) {
            this.toast = text;
            this.toastUndo = undo;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = ''), 5000);
        },
        serialized() {
            return JSON.stringify(strip(this.blocks).map(({ id, type, data }) => ({ id, type, data })));
        },
    };
}
