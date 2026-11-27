import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

const key = () => 'n' + Math.random().toString(36).slice(2, 10);
const csrf = () => document.querySelector('meta[name=csrf-token]').content;

/**
 * Menüverwaltung: Punkte per Ziehen anordnen und zu Unterpunkten machen (zwei Ebenen).
 * Nach jedem Ziehen wird die Reihenfolge aus dem DOM gelesen und die Liste neu gezeichnet,
 * damit Alpine und SortableJS sich nicht in die Quere kommen.
 */
Alpine.data('menuEditor', (cfg) => {
    let root = null;
    return {
    tree: cfg.tree,
    pages: cfg.pages,
    shopEnabled: cfg.shopEnabled,
    url: cfg.url,
    version: 0,
    open: null,
    dirty: false,
    saving: false,
    message: '',
    error: '',
    linkLabel: '',
    linkUrl: '',
    sortables: [],

    init() {
        root = this.$el; // $root ist nach dem Neuzeichnen in verschachtelten Aufrufen nicht mehr erreichbar
        this.$nextTick(() => this.mountSortables());
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty) e.preventDefault();
        });
    },

    // --- Anzeige ---------------------------------------------------------
    page(id) {
        return this.pages.find((p) => p.id === id);
    },
    title(item) {
        if (item.label) return item.label;
        if (item.type === 'page') return this.page(item.page_id)?.title ?? 'Gelöschte Seite';
        if (item.type === 'shop') return 'Shop';
        return item.url || 'Neuer Link';
    },
    kind(item) {
        if (item.type === 'page') return this.page(item.page_id)?.published === false ? 'Seite · Entwurf' : 'Seite';
        return item.type === 'shop' ? 'Shop' : 'Link';
    },
    target(item) {
        if (item.type === 'page') return this.page(item.page_id)?.url ?? '';
        if (item.type === 'shop') return '/shop';
        return item.url;
    },
    hidden(item) {
        return !item.is_visible || (item.type === 'page' && this.page(item.page_id)?.published === false) || (item.type === 'shop' && !this.shopEnabled);
    },
    get unusedPages() {
        const used = new Set();
        this.walk((i) => i.type === 'page' && used.add(i.page_id));
        return this.pages.filter((p) => !used.has(p.id));
    },
    get hasShop() {
        let found = false;
        this.walk((i) => (found ||= i.type === 'shop'));
        return found;
    },
    get preview() {
        return this.tree.filter((i) => !this.hidden(i)).map((i) => ({ label: this.title(i), children: i.children.filter((c) => !this.hidden(c)).map((c) => this.title(c)) }));
    },
    walk(fn) {
        this.tree.forEach((i) => {
            fn(i);
            i.children.forEach(fn);
        });
    },

    // --- Ändern ------------------------------------------------------------
    changed() {
        this.dirty = true;
        this.message = '';
    },
    add(item) {
        this.tree.push({ key: key(), id: null, label: '', url: '', new_tab: false, is_visible: true, page_id: null, children: [], ...item });
        this.changed();
        this.redraw();
    },
    addPage(p) {
        this.add({ type: 'page', page_id: p.id });
    },
    addLink() {
        if (!this.linkUrl.trim()) {
            this.error = 'Bitte geben Sie an, wohin der Link führen soll, z. B. /kontakt oder https://…';
            return;
        }
        this.error = '';
        this.add({ type: 'link', label: this.linkLabel.trim(), url: this.linkUrl.trim(), new_tab: /^https?:/i.test(this.linkUrl.trim()) });
        this.linkLabel = this.linkUrl = '';
    },
    remove(item) {
        const list = this.listOf(item);
        const i = list.indexOf(item);
        list.splice(i, 1, ...item.children); // Unterpunkte bleiben erhalten
        this.changed();
        this.redraw();
    },
    listOf(item) {
        if (this.tree.includes(item)) return this.tree;
        return this.tree.find((p) => p.children.includes(item)).children;
    },
    parentOf(item) {
        return this.tree.find((p) => p.children.includes(item)) || null;
    },
    move(item, dir) {
        const list = this.listOf(item);
        const i = list.indexOf(item);
        const j = i + dir;
        if (j < 0 || j >= list.length) return;
        list.splice(i, 1);
        list.splice(j, 0, item);
        this.changed();
        this.redraw();
    },
    canIndent(item) {
        const i = this.tree.indexOf(item);
        return i > 0 && item.children.length === 0;
    },
    indent(item) {
        if (!this.canIndent(item)) return;
        const i = this.tree.indexOf(item);
        this.tree.splice(i, 1);
        this.tree[i - 1].children.push(item);
        this.changed();
        this.redraw();
    },
    outdent(item) {
        const parent = this.parentOf(item);
        if (!parent) return;
        parent.children.splice(parent.children.indexOf(item), 1);
        this.tree.splice(this.tree.indexOf(parent) + 1, 0, item);
        this.changed();
        this.redraw();
    },

    // --- Ziehen und Ablegen ------------------------------------------------
    redraw() {
        this.sortables.forEach((s) => s.destroy());
        this.sortables = [];
        this.version++;
        this.$nextTick(() => this.mountSortables());
    },
    mountSortables() {
        const lists = root.querySelectorAll('[data-sortable]');
        lists.forEach((el) => {
            this.sortables.push(Sortable.create(el, {
                group: 'menu',
                handle: '[data-handle]',
                draggable: '[data-item]',
                animation: 160,
                fallbackOnBody: true,
                swapThreshold: 0.6,
                ghostClass: 'nova-drag-ghost',
                chosenClass: 'nova-drag-chosen',
                // Nur zwei Ebenen: Ein Punkt mit Unterpunkten kann selbst kein Unterpunkt werden
                onStart: () => root.classList.add('nova-dragging'),
                onMove: (evt) => !(evt.to.dataset.level === '1' && evt.dragged.querySelector('[data-item]')),
                onEnd: () => {
                    root.classList.remove('nova-dragging');
                    this.readDom();
                },
            }));
        });
    },
    readDom() {
        const all = new Map();
        this.walk((i) => all.set(i.key, i));
        const read = (list, level) => [...list.children].filter((el) => el.matches('[data-item]')).map((el) => {
            const item = all.get(el.dataset.key);
            const sub = el.querySelector(':scope [data-sortable]');
            item.children = level === 0 && sub ? read(sub, 1) : [];
            return item;
        });
        this.tree = read(root.querySelector('[data-sortable][data-level="0"]'), 0);
        this.changed();
        this.redraw();
    },

    // --- Speichern ---------------------------------------------------------
    async save() {
        this.saving = true;
        this.error = '';
        try {
            const strip = (i) => ({ id: i.id, type: i.type, page_id: i.page_id, label: i.label, url: i.url, new_tab: i.new_tab, is_visible: i.is_visible });
            const res = await fetch(this.url, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ tree: JSON.stringify(this.tree.map((i) => ({ ...strip(i), children: i.children.map(strip) }))) }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.errors?.tree?.[0] || json.message || 'Speichern hat nicht geklappt.');
            this.dirty = false;
            this.message = json.message;
            // Neue Punkte bekommen ihre Nummer erst beim nächsten Laden; ein Neuladen hält alles konsistent
            if (this.tree.some((i) => !i.id || i.children.some((c) => !c.id))) setTimeout(() => window.location.reload(), 900);
        } catch (e) {
            this.error = e.message;
        } finally {
            this.saving = false;
        }
    },
};
});

window.Alpine = Alpine;
Alpine.start();
