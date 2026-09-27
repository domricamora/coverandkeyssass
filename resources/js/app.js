

import '../css/motion.css';
import Alpine from 'alpinejs';
import { accordions, reveal } from './reveal';

document.addEventListener('DOMContentLoaded', () => reveal());
accordions();

// Preloader: a top bar while the next page loads (plain links and form posts on Blade pages).
const loading = (on) => document.body?.classList.toggle('is-loading', on);
document.addEventListener('click', (e) => {
    const a = e.target.closest?.('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target && a.target !== '_self') return;
    if (a.hasAttribute('download') || a.origin !== location.origin || (a.hash && a.pathname === location.pathname)) return;
    loading(true);
});
document.addEventListener('submit', (e) => { if (!e.defaultPrevented && !e.target.target) loading(true); });
window.addEventListener('pageshow', () => loading(false)); // back/forward cache

window.Alpine = Alpine;

Alpine.start();
