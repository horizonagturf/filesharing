import Alpine from 'alpinejs';
import axios, { isAuthFailure, redirectToLogin } from './lib/http.js';
import dayjs from './lib/dayjs-config.js';

window.Alpine = Alpine;
window.axios = axios;
window.isAuthFailure = isAuthFailure;
window.redirectToLogin = redirectToLogin;
window.dayjs = dayjs;

const pageModules = import.meta.glob('./pages/*.js', { eager: true });

document.addEventListener('alpine:init', () => {
    for (const module of Object.values(pageModules)) {
        if (typeof module.registerAlpineComponents === 'function') {
            module.registerAlpineComponents();
        }
    }
});

Alpine.start();
