// Mediathek und Bildauswahl
import { aiPost, csrf } from './ai.js';

export async function uploadFile(file, kind = 'image') {
    const body = new FormData();
    body.append('file', file);
    body.append('kind', kind);
    let res;
    try {
        res = await fetch('/admin/uploads', { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
    } catch {
        throw new Error('Keine Verbindung zum Server.');
    }
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        if (res.status === 413) throw new Error(`„${file.name}“ ist zu groß für Ihren Webspace.`);
        throw new Error(json.errors?.file?.[0] || json.message || 'Hochladen fehlgeschlagen.');
    }
    return json;
}

async function send(method, url, payload) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: payload ? JSON.stringify(payload) : undefined,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(json.errors ? Object.values(json.errors)[0][0] : json.message || 'Das hat nicht geklappt.');
        err.status = res.status;
        throw err;
    }
    return json;
}

const kindOf = (file) => (file.type.startsWith('video/') ? 'video' : 'image');

export function registerMedia(Alpine) {
    Alpine.data('mediaLibrary', (cfg) => ({
        items: cfg.items,
        ai: cfg.ai,
        filter: 'all',
        search: '',
        selected: null,
        detail: null,
        uploads: [],
        error: '',
        saved: false,
        busyAlt: false,
        bulk: null,
        dragging: false,

        get visible() {
            const q = this.search.trim().toLowerCase();
            return this.items.filter((m) => (this.filter === 'all' || (this.filter === 'noalt' ? m.kind === 'image' && !m.alt : m.kind === this.filter))
                && (!q || (m.name + ' ' + (m.alt || '')).toLowerCase().includes(q)));
        },
        get stats() {
            const bytes = this.items.reduce((sum, m) => sum + (m.bytes || 0), 0);
            const size = bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
            return `${this.items.length} ${this.items.length === 1 ? 'Datei' : 'Dateien'}, zusammen ${size}`;
        },
        get missingAlt() {
            return this.items.filter((m) => m.kind === 'image' && !m.alt).length;
        },

        async upload(event) {
            this.dragging = false;
            const files = [...(event.target.files || event.dataTransfer?.files || [])];
            if (event.target.value !== undefined) event.target.value = '';
            this.error = '';
            for (const file of files) {
                const row = { name: file.name, done: false };
                this.uploads.push(row);
                try {
                    const media = await uploadFile(file, kindOf(file));
                    this.items.unshift(media);
                    row.done = true;
                } catch (e) {
                    this.error = e.message;
                    row.failed = true;
                }
            }
            setTimeout(() => (this.uploads = this.uploads.filter((u) => !u.done)), 1500);
        },

        async open(item) {
            this.selected = item;
            this.detail = null;
            this.saved = false;
            this.detail = await send('GET', `/admin/medien/${item.id}`);
        },
        close() {
            this.selected = null;
            this.detail = null;
        },
        async save() {
            const json = await send('PATCH', `/admin/medien/${this.selected.id}`, { alt: this.detail.alt, title: this.detail.title });
            Object.assign(this.selected, json);
            this.saved = true;
            setTimeout(() => (this.saved = false), 2000);
        },
        async describe(item = this.selected) {
            this.error = '';
            this.busyAlt = true;
            try {
                const json = await aiPost(`/admin/medien/${item.id}/beschreibung`, {});
                item.alt = json.alt;
                if (this.detail && this.selected === item) this.detail.alt = json.alt;
                return true;
            } catch (e) {
                this.error = e.message;
                return false;
            } finally {
                this.busyAlt = false;
            }
        },
        async describeAll() {
            const todo = this.items.filter((m) => m.kind === 'image' && !m.alt);
            this.bulk = { done: 0, total: todo.length };
            for (const item of todo) {
                if (!(await this.describe(item))) break;
                this.bulk.done++;
            }
            setTimeout(() => (this.bulk = null), 2500);
        },
        async remove() {
            if (!confirm('Diese Datei endgültig löschen?')) return;
            try {
                await send('DELETE', `/admin/medien/${this.selected.id}`);
            } catch (e) {
                if (e.status !== 409 || !confirm(e.message + '\n\nTrotzdem löschen?')) {
                    this.error = e.status === 409 ? '' : e.message;
                    return;
                }
                await send('DELETE', `/admin/medien/${this.selected.id}?trotzdem=1`);
            }
            this.items = this.items.filter((m) => m !== this.selected);
            this.close();
        },
        copy() {
            navigator.clipboard?.writeText(location.origin + this.selected.url);
        },
    }));
}

/** Bildauswahl im Editor: öffnet die Mediathek als Dialog. */
export function pickerMixin() {
    return {
        picker2: { open: false, kind: 'image', multiple: false, items: [], loading: false, chosen: [], search: '', apply: null },

        async openLibrary(kind, multiple, apply) {
            Object.assign(this.picker2, { open: true, kind, multiple, chosen: [], search: '', apply, loading: true });
            this.$nextTick(() => this.$refs.mediaDialog?.showModal());
            try {
                const res = await fetch(`/admin/medien/liste?art=${kind}`, { headers: { Accept: 'application/json' } });
                this.picker2.items = await res.json();
            } finally {
                this.picker2.loading = false;
            }
        },
        pickerItems() {
            const q = this.picker2.search.trim().toLowerCase();
            return this.picker2.items.filter((m) => !q || (m.name + ' ' + (m.alt || '')).toLowerCase().includes(q));
        },
        togglePick(item) {
            if (!this.picker2.multiple) {
                this.picker2.chosen = [item];
                this.confirmPick();
                return;
            }
            const i = this.picker2.chosen.indexOf(item);
            i >= 0 ? this.picker2.chosen.splice(i, 1) : this.picker2.chosen.push(item);
        },
        confirmPick() {
            if (this.picker2.chosen.length) this.picker2.apply(this.picker2.chosen);
            this.closeLibrary();
        },
        closeLibrary() {
            this.$refs.mediaDialog?.close();
            this.picker2.open = false;
        },
        // Für Blöcke: setzt URL und, falls leer, die Bildbeschreibung
        pickInto(target, key, kind = 'image', altKey = null) {
            this.openLibrary(kind, false, ([m]) => {
                target[key] = m.url;
                if (altKey && !target[altKey] && m.alt) target[altKey] = m.alt;
            });
        },
        pickSlides(block) {
            this.openLibrary('image', true, (list) => list.forEach((m) => block.data.slides.push({ url: m.url, alt: m.alt || '', caption: '' })));
        },
    };
}
