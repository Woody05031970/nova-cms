// SEO: Wettbewerber-Vergleich und Live-Prüfung im Editor
import { aiPost } from './ai.js';

export const scoreClass = (s) => (s >= 80 ? 'text-emerald-700 bg-emerald-50' : s >= 50 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50');

export function registerSeo(Alpine) {
    Alpine.data('seoCompare', (cfg) => ({
        pageId: cfg.pageId,
        url: '',
        keyword: cfg.keywords[cfg.pageId] || '',
        keywords: cfg.keywords,
        busy: false,
        error: '',
        result: null,
        seconds: 0,
        timer: null,
        pageChanged() {
            this.keyword = this.keywords[this.pageId] || '';
            this.result = null;
        },
        async run() {
            this.error = '';
            this.result = null;
            this.busy = true;
            this.seconds = 0;
            this.timer = setInterval(() => this.seconds++, 1000);
            try {
                this.result = await aiPost(cfg.url, { page_id: this.pageId, url: this.url, keyword: this.keyword });
            } catch (e) {
                this.error = e.message;
            } finally {
                clearInterval(this.timer);
                this.busy = false;
            }
        },
        winnerClass(w) {
            return w === 'own' ? 'text-emerald-700' : w === 'competitor' ? 'text-red-700' : 'text-slate-500';
        },
        winnerText(w) {
            return w === 'own' ? 'Sie vorn' : w === 'competitor' ? 'Wettbewerber vorn' : 'gleichauf';
        },
    }));
}

/** Für den Seiten-Editor: Live-Prüfung und Suchbegriff-Vorschläge. */
export function seoMixin(cfg) {
    return {
        focusKeyword: cfg.focusKeyword || '',
        purpose: cfg.purpose || null,
        seo: { score: null, checks: [], words: 0 },
        seoTimer: null,
        keywordIdeas: [],
        keywordBusy: false,
        scoreClass,

        initSeo() {
            const run = () => {
                clearTimeout(this.seoTimer);
                this.seoTimer = setTimeout(() => this.runSeo(), 700);
            };
            ['blocks', 'title', 'slug', 'metaTitle', 'metaDescription', 'focusKeyword', 'isHome'].forEach((k) => this.$watch(k, run, { deep: k === 'blocks' }));
            this.runSeo();
        },
        async runSeo() {
            try {
                this.seo = await aiPost(cfg.seoUrl, {
                    title: this.title, slug: this.slug, meta_title: this.metaTitle, meta_description: this.metaDescription,
                    focus_keyword: this.focusKeyword, is_home: !!this.isHome, purpose: this.purpose,
                    blocks: this.blocks.map(({ type, data }) => ({ type, data })),
                });
            } catch {
                /* Prüfung ist nur ein Hinweis; Fehler stören das Bearbeiten nicht */
            }
        },
        async suggestKeywords() {
            this.error = '';
            this.keywordBusy = true;
            try {
                this.keywordIdeas = (await aiPost(cfg.keywordsUrl, { title: this.title, text: this.pageText() })).keywords;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.keywordBusy = false;
            }
        },
    };
}
