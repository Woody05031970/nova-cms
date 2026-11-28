// Zeichnet Bereiche des visuellen Editors als HTML – genau wie site/blocks/section.blade.php.
// Mit edit = true kommen Markierungen für die Leinwand dazu (Auswahl, Ziehen, Tippen direkt im Text).

export const LAYOUTS = { 1: [1], '1-1': [1, 1], '1-1-1': [1, 1, 1], '1-1-1-1': [1, 1, 1, 1], '2-1': [2, 1], '1-2': [1, 2], '1-2-1': [1, 2, 1] };

export const COLORS = {
    brand: ['Hauptfarbe', 'var(--color-nova-600)'],
    'brand-dark': ['Hauptfarbe dunkel', 'var(--color-nova-900)'],
    'brand-light': ['Hauptfarbe hell', 'var(--color-nova-50)'],
    accent: ['Akzentfarbe', 'var(--color-fuchsia-600)'],
    dark: ['Fast schwarz', '#0f172a'],
    gray: ['Grau', '#64748b'],
    light: ['Hellgrau', '#f1f5f9'],
    white: ['Weiß', '#ffffff'],
};

const LIST_ICONS = { check: 'M5 12l5 5L20 7', arrow: 'M5 12h14M13 6l6 6-6 6', dot: 'M12 12h.01' };

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);

export function color(v) {
    if (!v) return null;
    if (COLORS[v]) return COLORS[v][1];
    return /^#[0-9a-f]{6}$/i.test(v) ? v : null;
}

const isLightHex = (hex) => {
    const n = parseInt(hex.slice(1), 16);
    return 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255) > 170;
};

export function isDark(v, design) {
    if (!v) return false;
    const hex = { brand: design.brand, accent: design.accent, 'brand-dark': '#0f172a', dark: '#0f172a', gray: '#64748b', 'brand-light': '#ffffff', light: '#ffffff', white: '#ffffff' }[v] ?? v;
    return /^#[0-9a-f]{6}$/i.test(hex) && !isLightHex(hex);
}

export function sectionParts(d, design) {
    const s = d.style || {};
    const css = [`--nb-pt:${+(s.pt ?? 64)}px`, `--nb-pb:${+(s.pb ?? 64)}px`];
    const bg = [];
    let dark = false;
    if (s.bg === 'color' && color(s.bg_color)) {
        bg.push('background-color:' + color(s.bg_color));
        dark = isDark(s.bg_color, design);
    } else if (s.bg === 'gradient') {
        bg.push(`background-image:linear-gradient(${+(s.bg_angle ?? 135)}deg,${color(s.bg_color) ?? COLORS.brand[1]},${color(s.bg_color2) ?? COLORS.accent[1]})`);
        dark = isDark(s.bg_color || 'brand', design);
    } else if (s.bg === '3d') {
        bg.push('background-color:' + (color(s.bg_color) ?? COLORS.dark[1]), `background-image:radial-gradient(120% 90% at 80% 10%,color-mix(in srgb,${color(s.bg_color2) ?? COLORS.accent[1]} 22%,transparent),transparent 60%)`);
        dark = isDark(s.bg_color || 'dark', design);
    } else if (s.bg === 'image' && s.bg_image) {
        bg.push(`background-image:url('${esc(s.bg_image)}')`, 'background-position:' + (s.bg_pos || 'center'));
        dark = s.overlay ? isDark(s.overlay, design) && (s.overlay_opacity ?? 50) >= 30 : true;
    }
    const text = s.text || 'auto';
    dark = text === 'light' || (text === 'auto' && dark);
    const card = !!s.card && (s.width || 'boxed') !== 'full';
    const overlay = s.bg === 'image' && s.bg_image && s.overlay && (s.overlay_opacity ?? 0) > 0
        ? `background-color:${color(s.overlay)};opacity:${Math.round(s.overlay_opacity) / 100}` : null;
    const cls = ['nb-section', 'nb-w-' + (s.width || 'boxed'), 'nb-gap-' + (s.gap || 'm'), 'nb-va-' + (s.valign || 'top')];
    if ((s.height || 'auto') !== 'auto') cls.push('nb-h-' + s.height);
    cls.push(dark ? 'nb-dark' : 'nb-light');
    if (card) cls.push('nb-card');
    if (s.bg_fixed && s.bg === 'image') cls.push('nb-fixed');
    if (s.bg === 'image') cls.push('nb-bg-image');
    if (s.reverse) cls.push('nb-reverse');
    let three = null;
    if (s.bg === '3d') {
        cls.push('nb-3d');
        if (design?.three) three = { scene: s.bg_3d || 'wellen', c1: color(s.bg_color) ?? COLORS.dark[1], c2: color(s.bg_color2) ?? COLORS.accent[1] };
    }
    (s.hide || []).forEach((h) => cls.push('nb-hide-' + h));
    return { cls: cls.join(' '), style: (card ? css : [...css, ...bg]).join(';'), inner: card ? bg.join(';') : '', overlay, three, card };
}

export const gridColumns = (layout) => (LAYOUTS[layout] || [1]).map((f) => `minmax(0,${f}fr)`).join(' ');

function columnParts(c, design) {
    const s = c.style || {};
    const cls = ['nb-col', 'nb-cva-' + (s.valign || 'top')];
    if (s.align) cls.push('nb-al-' + s.align);
    if (s.shadow) cls.push('nb-shadow');
    if (s.border) cls.push('nb-border');
    const css = [];
    if (color(s.bg)) {
        css.push('background-color:' + color(s.bg));
        cls.push(isDark(s.bg, design) ? 'nb-dark' : 'nb-light');
    }
    if (+s.pad) css.push(`padding:${+s.pad}px`);
    if (+s.radius) css.push(`border-radius:${+s.radius}px`);
    return { cls: cls.join(' '), style: css.join(';') };
}

const ratio = (r) => (!r || r === 'auto' ? '' : r.replace(':', ' / '));
const ext = (u) => typeof u === 'string' && u.startsWith('http');
const attr = (name, v) => (v ? ` ${name}="${esc(v)}"` : '');
const styleColor = (c) => (color(c) ? ` style="color:${color(c)}"` : '');

/** Ein Element. edit: Leinwand-Modus (Platzhalter, direkte Textbearbeitung). */
export function renderWidget(w, ctx) {
    const d = w.data || {};
    const s = w.style || {};
    const edit = ctx.edit;
    const ed = (field) => (edit ? ` data-nb-edit="${field}" contenteditable="true" spellcheck="true"` : '');
    const ph = (text) => (edit ? `<div class="nbx-ph">${text}</div>` : '');
    let html = '';
    switch (w.type) {
        case 'heading': {
            const tag = ['h1', 'h2', 'h3', 'h4', 'p'].includes(s.tag) ? s.tag : 'h2';
            const cls = `nb-heading nb-hs-${s.size || 'l'} nb-fw-${s.weight || 'semibold'}${s.gradient ? ' nb-grad' : ''}`;
            if (!edit && !d.text) return '';
            const inner = edit ? esc(d.text) : d.link ? `<a href="${esc(d.link)}"${ext(d.link) ? ' target="_blank" rel="noopener"' : ''}>${esc(d.text)}</a>` : esc(d.text);
            html = `<${tag} class="${cls}"${styleColor(s.color)}${ed('text')} data-ph="Überschrift eingeben">${inner}</${tag}>`;
            break;
        }
        case 'text':
            html = `<div class="nova-prose nb-text nb-ts-${s.size || 'm'}"${styleColor(s.color)}${ed('html')} data-ph="Text eingeben …">${d.html || ''}</div>`;
            break;
        case 'image': {
            if (!d.url) return edit ? box(w, ph(`${ICON('image')}<span>Bild wählen</span>`), true) : '';
            const img = `<img src="${esc(d.url)}" alt="${esc(d.alt)}" loading="lazy" class="nb-img nb-r-${s.radius || 'm'}${s.shadow ? ' nb-shadow' : ''}"${s.ratio && s.ratio !== 'auto' ? ` style="aspect-ratio:${ratio(s.ratio)}"` : ''}>`;
            html = `<figure class="nb-figure" style="width:${+(s.width ?? 100)}%">${d.link && !edit ? `<a href="${esc(d.link)}">${img}</a>` : img}${d.caption ? `<figcaption class="nb-caption">${esc(d.caption)}</figcaption>` : ''}</figure>`;
            break;
        }
        case 'button': {
            const one = (l, u, v, def, f) => (edit ? d[l] || f === 'label' : d[l] && d[u])
                ? `<a href="${esc(edit ? '#' : d[u])}" class="nb-btn nb-btn-${s[v] || def} nb-bs-${s.size || 'm'}"${!edit && ext(d[u]) ? ' target="_blank" rel="noopener"' : ''}${ed(f)} data-ph="Knopf">${esc(d[l])}</a>` : '';
            const inner = one('label', 'url', 'variant', 'primary', 'label') + one('label2', 'url2', 'variant2', 'outline', 'label2');
            if (!inner) return '';
            html = `<div class="nb-btns${s.full ? ' nb-btns-full' : ''}">${inner}</div>`;
            break;
        }
        case 'icon':
            html = `<div class="nb-ib nb-ib-${s.layout || 'top'}"><span class="nb-ib-icon nb-ib-${s.shape || 'circle'} nb-is-${s.size || 'm'}"${styleColor(s.color)}>${ctx.icon(d.icon || 'sparkles')}</span><div class="nb-ib-body">`
                + (d.title || edit ? `<h3 class="nb-ib-title"${ed('title')} data-ph="Titel">${!edit && d.link ? `<a href="${esc(d.link)}">${esc(d.title)}</a>` : esc(d.title)}</h3>` : '')
                + (d.text || edit ? `<p class="nb-ib-text"${ed('text')} data-ph="Kurzer Text">${esc(d.text)}</p>` : '')
                + '</div></div>';
            break;
        case 'list': {
            const items = (d.items || []).filter((i) => String(i).trim() !== '');
            if (!items.length) return edit ? box(w, ph(`${ICON('list')}<span>Listenpunkte eingeben</span>`), true) : '';
            const icon = s.icon || 'check';
            const path = icon === 'star' ? ctx.paths.star : LIST_ICONS[icon];
            const svg = `<svg class="nb-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${icon === 'dot' ? 6 : 2.2}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${path}"/></svg>`;
            html = `<ul class="nb-list nb-ls-${s.size || 'm'}">${items.map((i) => `<li><span class="nb-li-icon"${styleColor(s.color)}>${svg}</span><span>${esc(i)}</span></li>`).join('')}</ul>`;
            break;
        }
        case 'counter':
            html = `<div class="nb-counter nb-cs-${s.size || 'l'}"><div class="nb-counter-num"${styleColor(s.color)}>${edit ? `${esc(d.prefix)}<span${ed('number')} data-ph="100">${esc(d.number)}</span>${esc(d.suffix)}` : esc((d.prefix || '') + (d.number || '') + (d.suffix || ''))}</div>`
                + (d.label || edit ? `<div class="nb-counter-label"${ed('label')} data-ph="Beschriftung">${esc(d.label)}</div>` : '') + '</div>';
            break;
        case 'quote':
            html = `<figure class="nb-quote nb-quote-${s.variant || 'card'}">${s.stars ? '<div class="nb-stars" aria-label="5 von 5 Sternen">★★★★★</div>' : ''}<blockquote${ed('text')} data-ph="Was sagt Ihr Kunde?">${esc(d.text)}</blockquote>`
                + (d.name || d.image || edit ? `<figcaption>${d.image ? `<img src="${esc(d.image)}" alt="" loading="lazy">` : ''}<span><strong${ed('name')} data-ph="Name">${esc(d.name)}</strong>${d.role || edit ? `<span${ed('role')} data-ph="Firma oder Ort">${esc(d.role)}</span>` : ''}</span></figcaption>` : '')
                + '</figure>';
            break;
        case 'video':
            if (!d.video_id && !d.file && !(edit && d.url)) return edit ? box(w, ph(`${ICON('play')}<span>Video-Link eingeben</span>`), true) : '';
            html = `<figure class="nb-video"><div class="relative grid aspect-video place-items-center overflow-hidden rounded-xl bg-slate-900 bg-cover bg-center"${d.poster ? ` style="background-image:url('${esc(d.poster)}')"` : ''}><span class="grid size-16 place-items-center rounded-full bg-white/95 text-slate-900 shadow-lg"><svg viewBox="0 0 24 24" class="ml-1 size-7" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></div>${d.caption ? `<figcaption class="nb-caption">${esc(d.caption)}</figcaption>` : ''}</figure>`;
            break;
        case 'gallery': {
            const items = d.items || [];
            if (!items.length) return edit ? box(w, ph(`${ICON('image')}<span>Bilder für die Galerie wählen</span>`), true) : '';
            html = `<div class="nb-gallery nb-gg-${s.gap || 's'}" style="--nb-gcols:${+(s.cols ?? 3)}">${items.map((i) => `<img src="${esc(i.url)}" alt="${esc(i.alt)}" loading="lazy" class="nb-r-${s.radius || 's'}" style="aspect-ratio:${ratio(s.ratio || '1:1')}">`).join('')}</div>`;
            break;
        }
        case 'spacer':
            html = `<div class="nb-spacer" style="height:${+(s.height ?? 48)}px"></div>`;
            break;
        case 'divider':
            html = `<hr class="nb-divider" style="border-top:${+(s.weight ?? 1)}px ${s.line || 'solid'} ${color(s.color) ?? '#cbd5e1'};width:${+(s.width ?? 100)}%">`;
            break;
        case 'html':
            html = edit ? `<div class="nbx-code">${ICON('code')}<span>Eigener HTML-Code</span><small>${esc((d.code || '').slice(0, 80))}</small></div>` : d.code || '';
            break;
        default:
            return '';
    }
    return box(w, html);

    function box(widget, inner, empty = false) {
        const st = widget.style || {};
        const cls = ['nb-w', 'nb-w-' + widget.type];
        if (st.align) cls.push('nb-al-' + st.align);
        (st.hide || []).forEach((h) => cls.push('nb-hide-' + h));
        if (empty) cls.push('nbx-empty-w');
        const data = edit ? ` data-nb-widget="${esc(widget.id)}"` : '';
        return `<div class="${cls.join(' ')}" style="--nb-mb:${+(st.mb ?? 20)}px"${data}>${inner}${edit ? ctx.widgetTools(widget) : ''}</div>`;
    }
    function ICON(n) {
        return ctx.icon(n, 'nbx-ph-icon');
    }
}

export function renderSection(block, ctx) {
    const d = block.data || {};
    const p = sectionParts(d, ctx.design);
    const cols = d.cols || [];
    const edit = ctx.edit;
    let html = `<section${attr('id', d.style?.anchor)} class="${p.cls}" style="${p.style}"${edit ? ` data-nb-section="${esc(block.id)}"` : ''}>`;
    if (p.overlay) html += `<div class="nb-overlay" style="${p.overlay}"></div>`;
    const three = p.three ? `<div class="nb-3d-canvas" data-nova-3d="${esc(p.three.scene)}" data-c1="${esc(p.three.c1)}" data-c2="${esc(p.three.c2)}"></div>` : '';
    if (!p.card) html += three;
    html += `<div class="nb-inner"${attr('style', p.inner)}>${p.card ? three : ''}<div class="nb-row" data-n="${cols.length}" style="--nb-cols:${gridColumns(d.layout || '1')}">`;
    for (const c of cols) {
        const cp = columnParts(c, ctx.design);
        html += `<div class="${cp.cls}"${attr('style', cp.style)}${edit ? ` data-nb-col="${esc(c.id)}"` : ''}>`;
        html += (c.widgets || []).map((w) => renderWidget(w, ctx)).join('');
        if (edit && !(c.widgets || []).length) html += ctx.emptyColumn(c);
        html += '</div>';
    }
    html += '</div></div>';
    if (edit) html += ctx.sectionTools(block);
    return html + '</section>';
}
