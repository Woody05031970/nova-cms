import Alpine from 'alpinejs';
import { registerAi } from './ai.js';
import { registerMedia } from './media.js';
import { registerSeo } from './seo.js';

registerAi(Alpine);
registerMedia(Alpine);
registerSeo(Alpine);

Alpine.data('busyForm', () => ({
    busy: false,
    seconds: 0,
    start() {
        this.busy = true;
        setInterval(() => this.seconds++, 1000);
    },
}));

window.Alpine = Alpine;
Alpine.start();
