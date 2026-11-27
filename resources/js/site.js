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
