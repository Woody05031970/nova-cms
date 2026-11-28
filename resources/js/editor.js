import Alpine from 'alpinejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TextAlign } from '@tiptap/extension-text-align';
import { Highlight } from '@tiptap/extension-highlight';
import { TableKit } from '@tiptap/extension-table';
import { Image } from '@tiptap/extension-image';
import Sortable from 'sortablejs';
import { EditorView, basicSetup } from 'codemirror';
import { html as htmlLang } from '@codemirror/lang-html';
import { aiPost, registerAi } from './ai.js';
import { pickerMixin, registerMedia, uploadFile } from './media.js';
import { seoMixin } from './seo.js';
import { builderMixin, flatBlocks } from './builder/editor.js';

const uid = () => Math.random().toString(36).slice(2, 12);

const DEFAULTS = {
    text: () => ({ html: '<p></p>' }),
    hero: () => ({ title: '', text: '', image: null, button_label: '', button_url: '' }),
    image: () => ({ url: null, alt: '', caption: '', size: 'normal' }),
    video: () => ({ url: '', file: null, caption: '', poster: null }),
    slider: () => ({ slides: [], autoplay: true }),
    button: () => ({ label: 'Jetzt anfragen', url: '/kontakt', style: 'primary', align: 'left' }),
    features: () => ({
        eyebrow: '', title: 'Ihre Vorteile', intro: '', style: 'grid',
        items: [
            { icon: 'bolt', title: 'Schnell', text: 'Beschreiben Sie hier einen Vorteil in ein, zwei Sätzen.' },
            { icon: 'heart', title: 'Persönlich', text: 'Was schätzen Ihre Kunden besonders?' },
            { icon: 'shield', title: 'Zuverlässig', text: 'Worauf kann man sich bei Ihnen verlassen?' },
        ],
    }),
    pricing: () => ({
        eyebrow: '', title: 'Preise', intro: '', note: '',
        plans: [
            { name: 'Basis', price: '49 €', period: 'einmalig', text: '', features: ['Leistung 1', 'Leistung 2'], product: null, button_label: 'Anfragen', button_url: '/kontakt', highlight: false },
            { name: 'Komfort', price: '99 €', period: 'einmalig', text: '', features: ['Alles aus Basis', 'Leistung 3'], product: null, button_label: 'Anfragen', button_url: '/kontakt', highlight: true },
        ],
    }),
    modules: () => ({ eyebrow: '', title: 'Module', intro: '', builder: false, items: [{ name: 'Neues Modul', text: '', kind: 'free', category: '', price: '', product: null, soon: false }] }),
    compare: () => ({ eyebrow: '', title: 'Im Vergleich', intro: '', ours: 'Wir', theirs: 'Andere', note: '', rows: [{ label: 'Merkmal', ours: 'yes', theirs: 'partly' }] }),
    faq: () => ({ eyebrow: '', title: 'Häufige Fragen', intro: '', items: [{ q: 'Ihre Frage?', a: 'Ihre Antwort.' }] }),
    cta: () => ({ title: 'Bereit für den nächsten Schritt?', text: '', button_label: 'Kontakt aufnehmen', button_url: '/kontakt', button2_label: '', button2_url: '', style: 'dark' }),
    contact: () => ({ eyebrow: '', title: 'Schreiben Sie uns', intro: 'Wir antworten in der Regel innerhalb eines Werktags.', button_label: 'Nachricht senden', success: '', subjects: [] }),
    reviews: () => ({ eyebrow: 'Das sagen unsere Kunden', title: 'Bewertungen bei Google', intro: '', limit: 3, show_button: true }),
    booking: () => ({ eyebrow: 'Online-Termin', title: 'Termin buchen', intro: 'Wählen Sie eine Leistung und einen freien Termin. Sie erhalten sofort eine Bestätigung per E-Mail.', button_label: 'Termin wählen', service: 0 }),
    newsletter: () => ({ eyebrow: 'Newsletter', title: 'Nichts mehr verpassen', intro: 'Neuigkeiten, Aktionen und Tipps, etwa einmal im Monat. Abmeldung jederzeit.', button_label: 'Abonnieren', ask_name: false, style: 'dark' }),
    html: () => ({ code: '' }),
};

const slugify = (t) => t.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const csrf = () => document.querySelector('meta[name=csrf-token]').content;
let AI = { enabled: false };

async function upload(file, kind = 'image') {
    return (await uploadFile(file, kind)).url;
}

// Mischt Teile zusammen, ohne Getter auszuwerten (Spread würde sie sofort aufrufen)
const mix = (...parts) => parts.reduce((o, p) => Object.defineProperties(o, Object.getOwnPropertyDescriptors(p)), {});

Alpine.data('pageEditor', (cfg) => mix(pickerMixin(), seoMixin(cfg), builderMixin(cfg), {
    ai: (AI = cfg.ai),
    aiBusy: false,
    aiNotice: '',
    blocks: cfg.blocks,
    defaults: DEFAULTS,
    title: cfg.title,
    slug: cfg.slug,
    metaTitle: cfg.metaTitle || '',
    metaDescription: cfg.metaDescription || '',
    showInMenu: cfg.showInMenu,
    isHome: cfg.isHome,
    siteName: cfg.siteName,
    pageId: cfg.pageId,
    host: cfg.host,
    types: cfg.types,
    products: cfg.products || [],
    services: cfg.services || [],
    icons: cfg.icons || [],
    features: cfg.features || {},
    picker: null,
    dirty: false,
    error: '',
    device: 'desktop',

    init() {
        this.initBuilder();
        this.$nextTick(() => {
            this.$watch('title', (now, before) => {
                // Solange die Seite nicht online ist, folgt die Adresse dem Titel (außer sie wurde von Hand geändert)
                const auto = !this.slug || this.slug === slugify(before || '') || /^neue-seite(-\d+)?$/.test(this.slug);
                if (!cfg.published && !this.isHome && auto) this.slug = slugify(now);
            });
            this.dirty = false;
            ['title', 'slug', 'metaTitle', 'metaDescription', 'focusKeyword', 'showInMenu', 'isHome', 'showTitle'].forEach((k) => this.$watch(k, () => (this.dirty = true)));
            this.initSeo();
        });
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty) e.preventDefault();
        });
    },

    upload,

    // Listen in Bausteinen (Vorteile, Pakete, Fragen …)
    listAdd(list, item) {
        list.push(item);
    },
    listMove(list, i, dir) {
        const j = i + dir;
        if (j < 0 || j >= list.length) return;
        list.splice(j, 0, list.splice(i, 1)[0]);
    },
    listRemove(list, i) {
        list.splice(i, 1);
    },
    typesIn(group) {
        return Object.entries(this.types).filter(([, t]) => t.group === group);
    },

    label(type) {
        return this.types[type]?.label ?? type;
    },

    async uploadTo(target, key, event, kind = 'image') {
        const file = event.target.files?.[0] || event.dataTransfer?.files?.[0];
        if (!file) return;
        this.error = '';
        target['_uploading'] = true;
        try {
            target[key] = await upload(file, kind);
        } catch (e) {
            this.error = e.message;
        } finally {
            delete target['_uploading'];
            if (event.target.value !== undefined) event.target.value = '';
        }
    },
    async addSlides(block, event) {
        const files = [...(event.target.files || event.dataTransfer?.files || [])];
        this.error = '';
        block.data._uploading = true;
        try {
            for (const f of files) block.data.slides.push({ url: await upload(f), alt: '', caption: '' });
        } catch (e) {
            this.error = e.message;
        } finally {
            delete block.data._uploading;
            if (event.target.value !== undefined) event.target.value = '';
        }
    },

    // Google-Vorschau
    get previewTitle() {
        return (this.metaTitle || this.title || 'Seitentitel') + ' – ' + this.siteName;
    },
    get previewUrl() {
        return this.host + (this.isHome ? '' : ' › ' + (this.slug || 'seite'));
    },
    get previewDescription() {
        return this.metaDescription || 'Noch keine Beschreibung. Google nimmt sonst irgendeinen Text von der Seite.';
    },
    descriptionFromText() {
        const div = document.createElement('div');
        div.innerHTML = flatBlocks(this.blocks).filter((b) => b.type === 'text').map((b) => b.data.html.replace(/<\/(p|h\d|li|blockquote)>/g, '$& ')).join(' ');
        const text = (this.blocks.find((b) => b.type === 'hero')?.data.text || '') + ' ' + div.textContent;
        const clean = text.replace(/\s+/g, ' ').trim();
        this.metaDescription = clean.length > 155 ? clean.slice(0, 152).replace(/\s+\S*$/, '') + ' …' : clean;
    },
    pageText() {
        const div = document.createElement('div');
        div.innerHTML = flatBlocks(this.blocks).map((b) => {
            if (b.type === 'text') return b.data.html.replace(/<\/(p|h\d|li|blockquote)>/g, '$& ');
            if (b.type === 'hero') return `<p>${b.data.title || ''}. ${b.data.text || ''}</p>`;
            return '';
        }).join(' ');
        return div.textContent.replace(/\s+/g, ' ').trim();
    },
    async descriptionWithAi() {
        this.error = '';
        this.aiBusy = true;
        try {
            const json = await aiPost(this.ai.descriptionUrl, { title: this.metaTitle || this.title, text: this.pageText() });
            this.metaDescription = json.description;
        } catch (e) {
            this.error = e.message;
        } finally {
            this.aiBusy = false;
        }
    },
    counterClass(len, min, max) {
        if (len === 0) return 'text-slate-400';
        return len < min || len > max ? 'text-amber-600' : 'text-emerald-600';
    },

    submit(action) {
        this.dirty = false;
        this.$refs.action.value = action;
        this.$refs.form.requestSubmit();
    },
}));

/** Formatiert HTML für die Quelltext-Ansicht lesbar (ein Block-Element pro Zeile). */
function prettyHtml(html) {
    return html
        .replace(/>\s*</g, '><')
        .replace(/<(\/(p|h[1-6]|ul|ol|li|blockquote|table|thead|tbody|tr|pre)|hr|br|img[^>]*|(table|thead|tbody|tr|ul|ol)(\s[^>]*)?)>/g, '<$1>\n')
        .replace(/\n{2,}/g, '\n')
        .trim();
}

Alpine.data('richText', (block) => {
    let editor = null;
    let source = null;
    return {
        tick: 0,
        mode: 'visual',
        styleOpen: false,
        tableOpen: false,
        init() {
            editor = new Editor({
                element: this.$refs.ed,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
                    }),
                    TextAlign.configure({ types: ['heading', 'paragraph'] }),
                    Highlight,
                    TableKit.configure({ table: { resizable: false } }),
                    Image.configure({ inline: false }),
                ],
                content: block.data.html,
                editorProps: { attributes: { class: 'nova-prose min-h-32 focus:outline-none' } },
                onUpdate: ({ editor }) => (block.data.html = editor.getHTML()),
                onTransaction: () => this.tick++,
            });
            // Wird der Text direkt auf der Leinwand geändert, zieht der Editor hier nach
            Alpine.effect(() => {
                const html = block.data.html;
                if (editor && !editor.isDestroyed && !editor.isFocused && this.mode === 'visual' && html !== editor.getHTML()) {
                    editor.commands.setContent(html, { emitUpdate: false });
                }
            });
        },
        destroy() {
            editor?.destroy();
            source?.destroy();
        },
        active(name, attrs = {}) {
            this.tick;
            return editor?.isActive(name, attrs);
        },
        aligned(value) {
            this.tick;
            return editor?.isActive({ textAlign: value });
        },
        get styleLabel() {
            this.tick;
            if (!editor) return 'Absatz';
            for (const [lvl, label] of [[2, 'Überschrift 1'], [3, 'Überschrift 2'], [4, 'Überschrift 3']]) {
                if (editor.isActive('heading', { level: lvl })) return label;
            }
            return editor.isActive('blockquote') ? 'Zitat' : 'Absatz';
        },
        setStyle(level) {
            this.styleOpen = false;
            const c = editor.chain().focus();
            level ? c.setHeading({ level }).run() : c.setParagraph().run();
        },
        run(cmd, ...args) {
            editor.chain().focus()[cmd](...args).run();
        },
        can(cmd) {
            this.tick;
            return editor?.can()[cmd]?.() ?? false;
        },
        inTable() {
            this.tick;
            return editor?.isActive('table');
        },
        table(cmd) {
            this.tableOpen = false;
            if (cmd === 'insert') editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
            else editor.chain().focus()[cmd]().run();
        },
        image() {
            this.$dispatch('pick-image', (m) => editor.chain().focus().setImage({ src: m.url, alt: m.alt || '' }).run());
        },
        clearFormat() {
            editor.chain().focus().unsetAllMarks().clearNodes().run();
        },
        // Umschalten zwischen Editor und HTML-Quelltext
        toggleSource() {
            if (this.mode === 'visual') {
                this.mode = 'source';
                this.$nextTick(() => {
                    source = new EditorView({
                        doc: prettyHtml(editor.getHTML()),
                        extensions: [basicSetup, htmlLang(), EditorView.lineWrapping, EditorView.updateListener.of((u) => {
                            if (u.docChanged) block.data.html = u.state.doc.toString();
                        })],
                        parent: this.$refs.src,
                    });
                    source.focus();
                });
            } else {
                const html = source.state.doc.toString();
                source.destroy();
                source = null;
                editor.commands.setContent(html, { emitUpdate: true });
                this.mode = 'visual';
            }
        },
        aiOpen: false,
        aiBusy: false,
        async aiRun(action) {
            this.aiOpen = false;
            if (this.mode === 'source') this.toggleSource();
            const { from, to, empty } = editor.state.selection;
            // Mit Markierung wird nur der markierte Text bearbeitet, sonst der ganze Block.
            const input = empty ? editor.getHTML() : editor.state.doc.textBetween(from, to, '\n');
            if (!input.replace(/<[^>]*>/g, '').trim()) {
                this.$dispatch('ai-error', 'Dieser Block ist noch leer. Schreiben Sie zuerst ein paar Worte.');
                return;
            }
            this.aiBusy = true;
            editor.setEditable(false);
            try {
                const json = await aiPost(AI.rewriteUrl, { action, html: input });
                editor.setEditable(true);
                if (empty) editor.chain().focus().setContent(json.html, { emitUpdate: true }).run();
                else editor.chain().focus().insertContentAt({ from, to }, json.html).run();
                this.$dispatch('ai-done');
            } catch (e) {
                this.$dispatch('ai-error', e.message);
            } finally {
                editor.setEditable(true);
                this.aiBusy = false;
            }
        },
        link() {
            const prev = editor.getAttributes('link').href || '';
            const url = window.prompt('Wohin soll der Link führen? (z. B. /kontakt oder https://…)', prev);
            if (url === null) return;
            if (url === '') editor.chain().focus().unsetLink().run();
            else editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },
    };
});

Alpine.data('codeEditor', (block) => {
    let view = null;
    return {
        init() {
            view = new EditorView({
                doc: block.data.code,
                extensions: [basicSetup, htmlLang(), EditorView.updateListener.of((u) => {
                    if (u.docChanged) block.data.code = u.state.doc.toString();
                })],
                parent: this.$refs.cm,
            });
        },
        destroy() {
            view?.destroy();
        },
    };
});

registerAi(Alpine);
registerMedia(Alpine);
window.Alpine = Alpine;
Alpine.start();
