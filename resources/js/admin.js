// Gemeinsames Verhalten aller Verwaltungsseiten: mobile Seitenleiste und Schnellsuche (Strg+K).
// Bewusst ohne Alpine, damit es neben den Seiten-Skripten unabhängig läuft.

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

// --- Seitenleiste auf dem Handy -------------------------------------------------
function sidebar(open) {
    $('#sidebar')?.classList.toggle('-translate-x-full', !open);
    $('[data-sidebar-backdrop]')?.classList.toggle('hidden', !open);
    $('[data-sidebar-open]')?.setAttribute('aria-expanded', String(open));
}
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-open]')) sidebar(true);
    if (e.target.closest('[data-sidebar-close], [data-sidebar-backdrop]')) sidebar(false);
});

// --- Schnellsuche --------------------------------------------------------------
const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss');
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
let data = { items: [], searchUrl: '' };
let results = [];
let active = 0;
let timer = null;
let seq = 0;

function render() {
    const list = $('[data-palette-results]');
    if (!list) return;
    if (!results.length) {
        list.innerHTML = '<li class="px-3 py-6 text-center text-sm text-slate-500">Nichts gefunden.</li>';
        return;
    }
    let html = '';
    let group = null;
    results.forEach((r, i) => {
        if (r.group !== group) {
            group = r.group;
            html += `<li class="px-3 pt-2 pb-1 font-mono text-[10px] font-semibold tracking-[0.14em] text-slate-400 uppercase" role="presentation">${esc(group)}</li>`;
        }
        const on = i === active;
        html += `<li role="option" id="pal-${i}" aria-selected="${on}" data-index="${i}"
            class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm ${on ? 'bg-nova-600 text-white' : 'text-slate-700 hover:bg-slate-50'}">
            <span class="min-w-0 flex-1 truncate">${esc(r.label)}</span>
            ${r.hint ? `<span class="truncate font-mono text-[11px] ${on ? 'text-white/70' : 'text-slate-400'}">${esc(r.hint)}</span>` : ''}
        </li>`;
    });
    list.innerHTML = html;
    $(`#pal-${active}`)?.scrollIntoView({ block: 'nearest' });
}

function filterLocal(q) {
    const n = norm(q.trim());
    return data.items.filter((i) => !n || norm(i.label).includes(n));
}

function search(q) {
    results = filterLocal(q);
    active = 0;
    render();
    clearTimeout(timer);
    if (q.trim().length < 2) return;
    const mine = ++seq;
    timer = setTimeout(async () => {
        try {
            const res = await fetch(`${data.searchUrl}?q=${encodeURIComponent(q.trim())}`, { headers: { Accept: 'application/json' } });
            if (!res.ok || mine !== seq) return;
            const remote = await res.json();
            results = [...filterLocal(q), ...remote];
            render();
        } catch {
            // Ohne Verbindung bleiben die lokalen Treffer stehen
        }
    }, 160);
}

function go(r) {
    if (!r) return;
    $('#palette')?.close();
    if (r.external) window.open(r.url, '_blank');
    else window.location.href = r.url;
}

function openPalette() {
    const dlg = $('#palette');
    if (!dlg || dlg.open) return;
    const input = $('[data-palette-input]');
    input.value = '';
    search('');
    dlg.showModal();
    input.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        data = JSON.parse($('#palette-data')?.textContent || '{}');
    } catch {
        // keine Daten: Suche bleibt leer
    }
    const input = $('[data-palette-input]');
    input?.addEventListener('input', () => search(input.value));
    input?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            active = Math.min(active + 1, results.length - 1);
            render();
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            active = Math.max(active - 1, 0);
            render();
            e.preventDefault();
        } else if (e.key === 'Enter') {
            go(results[active]);
            e.preventDefault();
        }
    });
    $('[data-palette-results]')?.addEventListener('click', (e) => {
        const li = e.target.closest('[data-index]');
        if (li) go(results[+li.dataset.index]);
    });
    $('#palette')?.addEventListener('click', (e) => {
        if (e.target.id === 'palette') e.target.close(); // Klick auf den abgedunkelten Hintergrund
    });
    $$('[data-palette-open]').forEach((b) => b.addEventListener('click', openPalette));
});

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openPalette();
    }
    if (e.key === 'Escape') sidebar(false);
});
