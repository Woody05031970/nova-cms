// KI-Funktionen der Verwaltung, die Editor und Einstellungen gemeinsam nutzen.

export const csrf = () => document.querySelector('meta[name=csrf-token]').content;

export async function aiPost(url, payload) {
    let res;
    try {
        res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(payload),
        });
    } catch {
        throw new Error('Keine Verbindung zum Server. Bitte prüfen Sie Ihre Internetverbindung.');
    }
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        if (res.status === 429) throw new Error('Bitte einen Moment warten: Es wurden gerade sehr viele KI-Anfragen gestellt.');
        const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;
        const err = new Error(first || json.message || 'Die KI-Anfrage ist fehlgeschlagen.');
        err.setup = json.setup;
        throw err;
    }
    return json;
}

export function registerAi(Alpine) {
    Alpine.data('aiSettings', (cfg) => ({
        providers: cfg.providers,
        provider: cfg.provider,
        model: cfg.model || cfg.providers[cfg.provider]?.model || '',
        baseUrl: cfg.baseUrl || cfg.providers[cfg.provider]?.base_url || '',
        apiKey: '',
        testing: false,
        result: null,

        get p() {
            return this.providers[this.provider] || {};
        },
        choose(key) {
            if (key === this.provider) return;
            this.provider = key;
            this.model = this.providers[key].model;
            this.baseUrl = this.providers[key].base_url || '';
            this.result = null;
        },
        async test() {
            this.testing = true;
            this.result = null;
            try {
                const json = await aiPost(cfg.testUrl, { provider: this.provider, model: this.model, api_key: this.apiKey, base_url: this.p.editable_url ? this.baseUrl : null });
                this.result = { ok: true, message: json.message };
            } catch (e) {
                this.result = { ok: false, message: e.message };
            } finally {
                this.testing = false;
            }
        },
    }));
}
