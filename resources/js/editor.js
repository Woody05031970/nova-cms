import Alpine from 'alpinejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { EditorView, basicSetup } from 'codemirror';
import { html as htmlLang } from '@codemirror/lang-html';
import { aiPost, registerAi } from './ai.js';
import { pickerMixin, registerMedia, uploadFile } from './media.js';
import { seoMixin } from './seo.js';

const uid = () => Math.random().toString(36).slice(2, 12);

const DEFAULTS = {
    text: () => ({ html: '<p></p>' }),
    hero: () => ({ title: '', text: '', image: null, button_label: '', button_url: '' }),
    image: () => ({ url: null, alt: '', caption: '', size: 'normal' }),
    video: () => ({ url: '', file: null, caption: '' }),
    slider: () => ({ slides: [], autoplay: true }),
    button: () => ({ label: 'Jetzt anfragen', url: '/kontakt', style: 'primary', align: 'left' }),
    html: () => ({ code: '' }),
};

const csrf = () => document.querySelector('meta[name=csrf-token]').content;
let AI = { enabled: false };

async function upload(file, kind = 'image') {
    return (await uploadFile(file, kind)).url;
}

Alpine.data('pageEditor', (cfg) => ({
    ...pickerMixin(),
    ...seoMixin(cfg),
    ai: (AI = cfg.ai),
    aiBusy: false,
    aiNotice: '',
    blocks: cfg.blocks.length ? cfg.blocks : [{ id: uid(), type: 'text', data: DEFAULTS.text() }],
    title: cfg.title,
    slug: cfg.slug,
    metaTitle: cfg.metaTitle || '',
    metaDescription: cfg.metaDescription || '',
    showInMenu: cfg.showInMenu,
    isHome: cfg.isHome,
    siteName: cfg.siteName,
    host: cfg.host,
    types: cfg.types,
    picker: null,
    dirty: false,
    error: '',

    init() {
        this.$nextTick(() => {
            this.$watch('blocks', () => (this.dirty = true), { deep: true });
            ['title', 'slug', 'metaTitle', 'metaDescription', 'focusKeyword', 'showInMenu', 'isHome'].forEach((k) => this.$watch(k, () => (this.dirty = true)));
            this.initSeo();
        });
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty) e.preventDefault();
        });
    },

    add(type, index) {
        const block = { id: uid(), type, data: DEFAULTS[type]() };
        this.blocks.splice(index, 0, block);
        this.picker = null;
        this.$nextTick(() => document.getElementById('block-' + block.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    },
    remove(i) {
        if (confirm('Diesen Block entfernen?')) this.blocks.splice(i, 1);
    },
    move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= this.blocks.length) return;
        const [b] = this.blocks.splice(i, 1);
        this.blocks.splice(j, 0, b);
    },
    duplicate(i) {
        const copy = JSON.parse(JSON.stringify(this.blocks[i]));
        copy.id = uid();
        this.blocks.splice(i + 1, 0, copy);
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
        div.innerHTML = this.blocks.filter((b) => b.type === 'text').map((b) => b.data.html.replace(/<\/(p|h\d|li|blockquote)>/g, '$& ')).join(' ');
        const text = (this.blocks.find((b) => b.type === 'hero')?.data.text || '') + ' ' + div.textContent;
        const clean = text.replace(/\s+/g, ' ').trim();
        this.metaDescription = clean.length > 155 ? clean.slice(0, 152).replace(/\s+\S*$/, '') + ' …' : clean;
    },
    pageText() {
        const div = document.createElement('div');
        div.innerHTML = this.blocks.map((b) => {
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

Alpine.data('richText', (block) => {
    let editor = null;
    return {
        tick: 0,
        init() {
            editor = new Editor({
                element: this.$refs.ed,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
                    }),
                ],
                content: block.data.html,
                editorProps: { attributes: { class: 'nova-prose min-h-24 focus:outline-none' } },
                onUpdate: ({ editor }) => (block.data.html = editor.getHTML()),
                onTransaction: () => this.tick++,
            });
        },
        destroy() {
            editor?.destroy();
        },
        active(name, attrs = {}) {
            this.tick;
            return editor?.isActive(name, attrs);
        },
        run(cmd, ...args) {
            editor.chain().focus()[cmd](...args).run();
        },
        aiOpen: false,
        aiBusy: false,
        async aiRun(action) {
            this.aiOpen = false;
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
