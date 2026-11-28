import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

/**
 * „Backoffice-Menü anpassen“: Kategorien und Punkte per Ziehen ordnen, Punkte in andere Kategorien
 * verschieben, ausblenden, Kategorien umbenennen. Nach jedem Ziehen wird die Reihenfolge aus dem DOM
 * gelesen und neu gezeichnet, damit Alpine und SortableJS sich nicht in die Quere kommen.
 */
Alpine.data('navEditor', (cfg) => {
    let root = null;
    let sortables = [];
    return {
        groups: cfg.groups,
        single: cfg.single,
        version: 0,
        dirty: false,

        init() {
            root = this.$el;
            this.$nextTick(() => this.mount());
            window.addEventListener('beforeunload', (e) => { if (this.dirty) e.preventDefault(); });
        },
        get layout() {
            return JSON.stringify(this.groups.map((g) => ({ key: g.key, label: g.label, items: g.items.map((i) => ({ key: i.key, hidden: i.hidden })) })));
        },
        changed() { this.dirty = true; },
        redraw() {
            sortables.forEach((s) => s.destroy());
            sortables = [];
            this.version++;
            this.$nextTick(() => this.mount());
        },
        mount() {
            const opts = { animation: 160, ghostClass: 'nova-drag-ghost', chosenClass: 'nova-drag-chosen', onStart: () => root.classList.add('nova-dragging'), onEnd: () => { root.classList.remove('nova-dragging'); this.readDom(); } };
            const list = root.querySelector('[data-groups]');
            if (list) sortables.push(Sortable.create(list, { ...opts, handle: '[data-group-handle]', draggable: '[data-group]' }));
            root.querySelectorAll('[data-items]').forEach((el) => sortables.push(Sortable.create(el, { ...opts, group: 'nav-items', handle: '[data-item-handle]', draggable: '[data-item]' })));
        },
        readDom() {
            const items = new Map(this.groups.flatMap((g) => g.items.map((i) => [i.key, i])));
            const groups = new Map(this.groups.map((g) => [g.key, g]));
            this.groups = [...root.querySelectorAll('[data-group]')].map((el) => {
                const g = groups.get(el.dataset.group);
                g.items = [...el.querySelectorAll('[data-item]')].map((i) => items.get(i.dataset.item));
                return g;
            });
            this.changed();
            this.redraw();
        },
        // Ohne Maus: Pfeile und Auswahlfeld
        moveGroup(g, dir) {
            const i = this.groups.indexOf(g);
            const j = i + dir;
            if (j < 0 || j >= this.groups.length) return;
            this.groups.splice(i, 1);
            this.groups.splice(j, 0, g);
            this.changed();
            this.redraw();
        },
        moveItem(g, item, dir) {
            const i = g.items.indexOf(item);
            const j = i + dir;
            if (j < 0 || j >= g.items.length) return;
            g.items.splice(i, 1);
            g.items.splice(j, 0, item);
            this.changed();
            this.redraw();
        },
        toGroup(from, item, key) {
            const to = this.groups.find((g) => g.key === key);
            if (!to || to === from) return;
            from.items.splice(from.items.indexOf(item), 1);
            to.items.push(item);
            this.changed();
            this.redraw();
        },
        toggle(item) {
            item.hidden = !item.hidden;
            this.changed();
        },
        visibleCount(g) {
            return g.items.filter((i) => !i.hidden).length;
        },
    };
});

window.Alpine = Alpine;
Alpine.start();
