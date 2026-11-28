// Fertige Vorlagen für den visuellen Editor. Jede liefert einen oder mehrere Bereiche.

const uid = () => Math.random().toString(36).slice(2, 12);
const base = { mb: 20, align: '', hide: [] };
export const W = {
    heading: (text = 'Ihre Überschrift', st = {}) => ({ type: 'heading', data: { text, link: null }, style: { tag: 'h2', size: 'l', weight: 'semibold', color: null, gradient: false, ...st } }),
    text: (html = '<p>Schreiben Sie hier Ihren Text. Klicken Sie einfach hinein und tippen Sie los.</p>', st = {}) => ({ type: 'text', data: { html }, style: { size: 'm', color: null, ...st } }),
    image: (st = {}) => ({ type: 'image', data: { url: null, alt: '', caption: '', link: null }, style: { width: 100, radius: 'm', shadow: false, ratio: 'auto', ...st } }),
    button: (label = 'Jetzt anfragen', url = '/kontakt', st = {}, d = {}) => ({ type: 'button', data: { label, url, label2: '', url2: null, ...d }, style: { variant: 'primary', variant2: 'outline', size: 'm', full: false, ...st } }),
    icon: (icon = 'sparkles', title = 'Ihr Vorteil', text = 'Beschreiben Sie den Vorteil in ein, zwei Sätzen.', st = {}) => ({ type: 'icon', data: { icon, title, text, link: null }, style: { layout: 'top', shape: 'circle', color: null, size: 'm', ...st } }),
    list: (items = ['Erster Punkt', 'Zweiter Punkt', 'Dritter Punkt'], st = {}) => ({ type: 'list', data: { items }, style: { icon: 'check', color: null, size: 'm', ...st } }),
    counter: (number = '250', suffix = '+', label = 'zufriedene Kunden', st = {}) => ({ type: 'counter', data: { number, prefix: '', suffix, label }, style: { color: null, size: 'l', ...st } }),
    quote: (text = 'Sehr freundlich, schnell und zuverlässig. Wir kommen gerne wieder!', name = 'Maria Beispiel', role = 'Kundin aus Köln', st = {}) => ({ type: 'quote', data: { text, name, role, image: null }, style: { variant: 'card', stars: true, ...st } }),
    video: () => ({ type: 'video', data: { url: '', file: null, poster: null, caption: '' }, style: {} }),
    gallery: () => ({ type: 'gallery', data: { items: [] }, style: { cols: 3, ratio: '1:1', gap: 's', radius: 's' } }),
    spacer: () => ({ type: 'spacer', data: {}, style: { height: 48 } }),
    divider: () => ({ type: 'divider', data: {}, style: { line: 'solid', width: 100, weight: 1, color: null } }),
    html: () => ({ type: 'html', data: { code: '' }, style: {} }),
};

export function widget(type, ...args) {
    const w = W[type](...args);
    return { id: uid(), type: w.type, data: w.data, style: { ...base, ...w.style } };
}
const wd = (w, extra = {}) => ({ id: uid(), type: w.type, data: w.data, style: { ...base, ...w.style, ...extra } });

export const SECTION_STYLE = {
    width: 'boxed', height: 'auto', valign: 'top', gap: 'm', pt: 64, pb: 64,
    bg: 'none', bg_color: null, bg_color2: null, bg_angle: 135, bg_image: null, bg_pos: 'center', bg_fixed: false,
    overlay: 'dark', overlay_opacity: 50, text: 'auto', card: false, anchor: '', hide: [], reverse: false,
};
const COL_STYLE = { bg: null, pad: 0, radius: 0, shadow: false, border: false, valign: 'top', align: '' };

export function section(layout = '1', cols = null, style = {}, colStyle = {}) {
    const n = { 1: 1, '1-1': 2, '1-1-1': 3, '1-1-1-1': 4, '2-1': 2, '1-2': 2, '1-2-1': 3 }[layout] || 1;
    const list = cols || Array.from({ length: n }, () => []);
    return {
        id: uid(),
        type: 'section',
        data: {
            layout,
            style: { ...SECTION_STYLE, ...style },
            cols: list.map((widgets) => ({ id: uid(), style: { ...COL_STYLE, ...colStyle }, widgets })),
        },
    };
}

export const TEMPLATES = [
    {
        key: 'intro', name: 'Großer Einstieg', desc: 'Farbfläche mit großer Überschrift und zwei Knöpfen', scheme: 'hero',
        make: () => [section('1', [[
            wd(W.heading('Willkommen bei Ihrem Unternehmen', { tag: 'h1', size: 'xxl', weight: 'bold' })),
            wd(W.text('<p>Ein, zwei Sätze, die sagen, was Sie besonders macht und warum man sich bei Ihnen melden sollte.</p>', { size: 'l' }), { mb: 32 }),
            wd(W.button('Jetzt anfragen', '/kontakt', { variant: 'light', variant2: 'outline', size: 'l' }, { label2: 'Mehr erfahren', url2: '#mehr' })),
        ]], { bg: 'gradient', bg_color: 'brand', bg_color2: 'brand-dark', height: 'm', valign: 'center', width: 'narrow', pt: 96, pb: 96 }, { align: 'center' })],
    },
    {
        key: 'image-text', name: 'Bild und Text', desc: 'Foto links, daneben Überschrift, Text und Knopf', scheme: 'split',
        make: () => [section('1-1', [
            [wd(W.image({ ratio: '4:3', radius: 'l', shadow: true }))],
            [wd(W.heading('Was wir für Sie tun', { size: 'l' })), wd(W.text()), wd(W.button('Kontakt aufnehmen'))],
        ], { valign: 'center', gap: 'l' })],
    },
    {
        key: 'text-image', name: 'Text und Bild', desc: 'Text links, Foto rechts', scheme: 'split-r',
        make: () => [section('1-1', [
            [wd(W.heading('Unsere Geschichte', { size: 'l' })), wd(W.text()), wd(W.list(['Familienbetrieb seit 1998', 'Persönliche Beratung', 'Faire Preise']))],
            [wd(W.image({ ratio: '4:3', radius: 'l', shadow: true }))],
        ], { valign: 'center', gap: 'l', reverse: true })],
    },
    {
        key: 'features', name: 'Drei Vorteile', desc: 'Überschrift und drei Kacheln mit Symbolen', scheme: 'three',
        make: () => [
            section('1', [[wd(W.heading('Darum zu uns', { size: 'l' })), wd(W.text('<p>Drei gute Gründe, die unsere Kunden besonders schätzen.</p>'))]], { bg: 'color', bg_color: 'brand-light', width: 'narrow', pb: 8 }, { align: 'center' }),
            section('1-1-1', [
                [wd(W.icon('bolt', 'Schnell', 'Termine meist schon in derselben Woche.'))],
                [wd(W.icon('heart', 'Persönlich', 'Sie haben immer denselben Ansprechpartner.'))],
                [wd(W.icon('shield', 'Zuverlässig', 'Feste Preise und Termine, auf die Sie sich verlassen können.'))],
            ], { bg: 'color', bg_color: 'brand-light', pt: 24 }, { bg: 'white', pad: 28, radius: 16, shadow: true, align: 'center' }),
        ],
    },
    {
        key: 'stats', name: 'Kennzahlen', desc: 'Vier große Zahlen auf dunklem Grund', scheme: 'four',
        make: () => [section('1-1-1-1', [
            [wd(W.counter('25', '', 'Jahre Erfahrung'))], [wd(W.counter('1.200', '+', 'zufriedene Kunden'))],
            [wd(W.counter('4,9', '', 'Sterne bei Google'))], [wd(W.counter('24', ' h', 'bis zur Antwort'))],
        ], { bg: 'color', bg_color: 'brand-dark', pt: 72, pb: 72 }, { align: 'center' })],
    },
    {
        key: 'quotes', name: 'Kundenstimmen', desc: 'Überschrift und drei Bewertungen', scheme: 'three',
        make: () => [
            section('1', [[wd(W.heading('Das sagen unsere Kunden', { size: 'l' }))]], { pb: 8, width: 'narrow' }, { align: 'center' }),
            section('1-1-1', [
                [wd(W.quote())],
                [wd(W.quote('Top Beratung, ehrliche Preise. Klare Empfehlung!', 'Thomas K.', 'Bonn'))],
                [wd(W.quote('Alles hat wunderbar geklappt, vom ersten Anruf bis zum Schluss.', 'Familie Weber', 'Leverkusen'))],
            ], { pt: 24 }),
        ],
    },
    {
        key: 'intro3d', name: '3D-Einstieg', desc: 'Bewegter 3D-Hintergrund mit großer Überschrift', scheme: 'hero3d', three: true,
        make: () => [section('1', [[
            wd(W.heading('Willkommen in der Zukunft Ihres Unternehmens', { tag: 'h1', size: 'xxl', weight: 'bold' })),
            wd(W.text('<p>Ein, zwei Sätze, die sagen, was Sie besonders macht und warum man sich bei Ihnen melden sollte.</p>', { size: 'l' }), { mb: 32 }),
            wd(W.button('Jetzt anfragen', '/kontakt', { variant: 'light', variant2: 'outline', size: 'l' }, { label2: 'Mehr erfahren', url2: '#mehr' })),
        ]], { bg: '3d', bg_3d: 'wellen', bg_color: 'dark', bg_color2: 'accent', height: 'screen', valign: 'center', width: 'narrow', pt: 120, pb: 120 }, { align: 'center' })],
    },
    {
        key: 'split3d', name: '3D-Kugel mit Text', desc: 'Text links, leuchtende 3D-Kugel rechts', scheme: 'split3d', three: true,
        make: () => [section('1-1', [[
            wd(W.heading('Wir bringen Sie weltweit nach vorn', { size: 'xl', weight: 'bold' })),
            wd(W.text('<p>Beschreiben Sie hier Ihr Angebot in zwei, drei Sätzen. Die Kugel rechts dreht sich langsam und folgt der Maus.</p>', { size: 'l' }), { mb: 28 }),
            wd(W.button('Mehr erfahren', '#mehr', { variant: 'light', size: 'l' })),
        ], []], { bg: '3d', bg_3d: 'kugel', bg_color: 'brand-dark', bg_color2: 'accent', height: 'm', valign: 'center', pt: 96, pb: 96 })],
    },
    {
        key: 'cta', name: 'Aufruf', desc: 'Auffällige Karte mit Überschrift und Knöpfen', scheme: 'card',
        make: () => [section('1', [[
            wd(W.heading('Bereit für den nächsten Schritt?', { size: 'xl', weight: 'bold' })),
            wd(W.text('<p>Rufen Sie uns an oder schreiben Sie uns. Wir melden uns innerhalb eines Werktags.</p>', { size: 'l' }), { mb: 28 }),
            wd(W.button('Kontakt aufnehmen', '/kontakt', { variant: 'light', variant2: 'outline', size: 'l' }, { label2: 'Anrufen', url2: 'tel:+49' })),
        ]], { bg: 'gradient', bg_color: 'brand', bg_color2: 'brand-dark', card: true, pt: 72, pb: 72 }, { align: 'center' })],
    },
    {
        key: 'gallery', name: 'Bildergalerie', desc: 'Überschrift und Bilder im Raster', scheme: 'grid',
        make: () => [section('1', [[wd(W.heading('Eindrücke', { size: 'l' }), { align: 'center' }), wd(W.gallery())]])],
    },
    {
        key: 'team', name: 'Team', desc: 'Drei Personen mit rundem Foto', scheme: 'three',
        make: () => [
            section('1', [[wd(W.heading('Unser Team', { size: 'l' }))]], { pb: 8, width: 'narrow' }, { align: 'center' }),
            section('1-1-1', [0, 1, 2].map((i) => [
                wd(W.image({ radius: 'full', width: 60 })),
                wd(W.heading(['Anna Muster', 'Jonas Beispiel', 'Lea Probe'][i], { tag: 'h3', size: 's' }), { mb: 6 }),
                wd(W.text(`<p>${['Inhaberin', 'Beratung', 'Büro und Termine'][i]}</p>`, { size: 's', color: 'gray' })),
            ]), { pt: 24 }, { align: 'center' }),
        ],
    },
    {
        key: 'services', name: 'Leistungen', desc: 'Text links, Liste mit Häkchen rechts', scheme: 'split',
        make: () => [section('2-1', [
            [wd(W.heading('Unsere Leistungen', { size: 'l' })), wd(W.text()), wd(W.button('Angebot anfordern'))],
            [wd(W.list(['Beratung vor Ort', 'Planung und Umsetzung', 'Wartung und Service', 'Notdienst'], { size: 'l' }))],
        ], { valign: 'center', gap: 'l' }, {})],
    },
];
