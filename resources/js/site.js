// Kleine Helfer für die öffentliche Website: Slider und datenschutzfreundliche Videos.

document.querySelectorAll('[data-slider]').forEach((slider) => {
    const track = slider.querySelector('.nova-slider-track');
    const slides = [...track.children];
    const dots = [...slider.querySelectorAll('[data-dot]')];
    let index = 0;
    let timer = null;

    const go = (i) => {
        index = (i + slides.length) % slides.length;
        track.scrollTo({ left: slides[index].offsetLeft, behavior: 'smooth' });
    };
    const update = () => {
        index = Math.round(track.scrollLeft / track.clientWidth);
        dots.forEach((d, i) => d.setAttribute('aria-current', i === index ? 'true' : 'false'));
    };

    slider.querySelector('[data-prev]')?.addEventListener('click', () => go(index - 1));
    slider.querySelector('[data-next]')?.addEventListener('click', () => go(index + 1));
    dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
    track.addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (slider.dataset.autoplay === '1' && slides.length > 1 && !reduce) {
        const start = () => (timer = setInterval(() => go(index + 1), 5000));
        const stop = () => clearInterval(timer);
        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);
        slider.addEventListener('focusin', stop);
        start();
    }
    update();
});

document.querySelectorAll('[data-video-consent]').forEach((box) => {
    box.querySelector('button').addEventListener('click', () => {
        const iframe = document.createElement('iframe');
        iframe.src = box.dataset.src;
        iframe.title = box.dataset.title || 'Video';
        iframe.allow = 'autoplay; fullscreen; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.className = 'absolute inset-0 size-full';
        box.replaceChildren(iframe);
    });
});

// Modul-Übersicht: Filter (Alle / Im Kern / Kostenlos / Kostenpflichtig) und Baukasten mit Summe
const euro = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });
document.querySelectorAll('[data-modules]').forEach((box) => {
    const chips = [...box.querySelectorAll('[data-filter]')];
    const cards = [...box.querySelectorAll('[data-kind]')];
    chips.forEach((chip) =>
        chip.addEventListener('click', () => {
            const f = chip.dataset.filter;
            chips.forEach((c) => {
                c.classList.toggle('is-on', c === chip);
                c.setAttribute('aria-pressed', String(c === chip));
            });
            cards.forEach((card) => (card.hidden = f !== 'all' && card.dataset.kind !== f));
        }),
    );

    const form = box.querySelector('[data-builder]');
    const bar = box.querySelector('[data-builder-bar]');
    if (!form || !bar) return;
    const update = () => {
        const picked = [...form.querySelectorAll('input[name="products[]"]:checked')];
        const sum = picked.reduce((s, i) => s + Number(i.closest('[data-price]')?.dataset.price || 0), 0);
        bar.hidden = picked.length === 0;
        bar.querySelector('[data-builder-count]').textContent = picked.length;
        bar.querySelector('[data-builder-sum]').textContent = euro.format(sum / 100);
    };
    form.addEventListener('change', update);
    update();
});

// Einblend-Effekte: Abschnitte erscheinen, sobald sie ins Bild scrollen
(() => {
    const body = document.body;
    if (!body.matches('[data-anim]:not([data-anim="none"])') || !('IntersectionObserver' in window)) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const items = [...document.querySelectorAll('[data-reveal]')];
    const io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    io.unobserve(e.target);
                }
            }),
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );
    // Was beim Laden schon sichtbar ist, bleibt sofort sichtbar
    items.forEach((el) => {
        if (el.getBoundingClientRect().top < window.innerHeight * 0.92) el.classList.add('is-visible');
        else io.observe(el);
    });
    body.classList.add('js-reveal');
})();

// Hintergrundvideos: bei „weniger Bewegung“ anhalten
document.querySelectorAll('video[data-bg-video]').forEach((v) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        v.removeAttribute('autoplay');
        v.pause();
    }
});

// Menü auf Handy und Tablet: öffnet über die ganze Breite, schließt mit Esc, Unterpunkte zum Aufklappen
(() => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    if (!toggle || !menu) return;
    const label = toggle.querySelector('[data-menu-label]');
    const setOpen = (open, focusBack = false) => {
        toggle.setAttribute('aria-expanded', String(open));
        label.textContent = open ? 'Menü schließen' : 'Menü öffnen';
        menu.hidden = !open;
        document.documentElement.classList.toggle('nova-menu-open', open);
        if (open) menu.querySelector('ul a')?.focus();
        else if (focusBack) toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') setOpen(false, true);
    });
    // Wird das Fenster breit genug für das normale Menü, schließt das Handy-Menü
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => e.matches && setOpen(false));
    menu.querySelectorAll('[data-submenu-toggle]').forEach((btn) =>
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') !== 'true';
            btn.setAttribute('aria-expanded', String(open));
            document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
        }),
    );
})();

// Untermenüs am Computer: Esc schließt das geöffnete Untermenü
document.querySelectorAll('[data-has-submenu]').forEach((box) =>
    box.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') document.activeElement?.blur();
    }),
);

// Formularfehler: Felder als ungültig markieren, Meldung verknüpfen und das erste Feld fokussieren
(() => {
    const data = document.getElementById('nova-form-errors');
    if (!data) return;
    let errors = {};
    try { errors = JSON.parse(data.textContent); } catch { return; }
    let first = null;
    Object.entries(errors).forEach(([name, messages]) => {
        document.querySelectorAll(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`).forEach((field) => {
            if (field.type === 'hidden') return;
            field.setAttribute('aria-invalid', 'true');
            const id = `${field.id || name}-fehler`;
            if (!document.getElementById(id)) {
                const p = document.createElement('p');
                p.id = id;
                p.className = 'mt-1 text-sm font-medium text-red-700';
                p.textContent = messages[0];
                (field.closest('label') || field).insertAdjacentElement('afterend', p);
            }
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
            first ??= field;
        });
    });
    first?.focus();
})();
