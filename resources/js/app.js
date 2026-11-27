import Alpine from 'alpinejs';
import { registerAi } from './ai.js';

registerAi(Alpine);

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
