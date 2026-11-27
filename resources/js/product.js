// Produkt bearbeiten: Bilder aus der Mediathek, Beschreibung mit Textformat, Texte per KI
import Alpine from 'alpinejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { aiPost, registerAi } from './ai.js';
import { pickerMixin, registerMedia, uploadFile } from './media.js';

let editor = null;

Alpine.data('productForm', (cfg) => ({
    ...pickerMixin(),
    images: cfg.images || [],
    description: cfg.description || '',
    short: cfg.short || '',
    name: cfg.name || '',
    tick: 0,
    uploading: false,
    aiBusy: false,
    aiError: '',

    init() {
        editor = new Editor({
            element: this.$refs.ed,
            extensions: [StarterKit.configure({ heading: { levels: [3, 4] }, link: { openOnClick: false, defaultProtocol: 'https' } })],
            content: this.description || '<p></p>',
            editorProps: { attributes: { class: 'nova-prose min-h-40 focus:outline-none' } },
            onUpdate: ({ editor }) => (this.description = editor.getHTML()),
            onTransaction: () => this.tick++,
        });
    },
    active(name, attrs = {}) {
        this.tick;
        return editor?.isActive(name, attrs);
    },
    run(cmd, ...args) {
        editor.chain().focus()[cmd](...args).run();
    },
    pickImages() {
        this.openLibrary('image', true, (list) => list.forEach((m) => this.images.includes(m.url) || this.images.push(m.url)));
    },
    async uploadImages(files) {
        this.uploading = true;
        try {
            for (const f of files) this.images.push((await uploadFile(f, 'image')).url);
        } catch (e) {
            alert(e.message);
        } finally {
            this.uploading = false;
        }
    },
    move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= this.images.length) return;
        [this.images[i], this.images[j]] = [this.images[j], this.images[i]];
    },
    async writeWithAi() {
        this.aiError = '';
        if (!this.name.trim()) {
            this.aiError = 'Geben Sie zuerst einen Produktnamen ein.';
            return;
        }
        const notes = (this.short + '\n' + (editor?.getText() || '')).trim();
        this.aiBusy = true;
        try {
            const json = await aiPost(cfg.aiUrl, { name: this.name, notes });
            this.short = json.short;
            editor.commands.setContent(json.html, { emitUpdate: true });
        } catch (e) {
            this.aiError = e.message;
        } finally {
            this.aiBusy = false;
        }
    },
}));

registerAi(Alpine);
registerMedia(Alpine);
window.Alpine = Alpine;
Alpine.start();
