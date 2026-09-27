/**
 * Section reveal: sections that start below the fold fade up as they scroll
 * into view, once. Anything already on screen is left alone (the page-enter
 * animation covers it), so nothing flashes and no-JS pages stay fully visible.
 * Children of [data-stagger] cascade 60 ms apart.
 */
const SELECTOR = 'main section, main [data-reveal], main [data-stagger] > *';

let observer;

export function reveal(root = document) {
    observer ??= new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        }
    }, { rootMargin: '0px 0px -8% 0px' });

    const fold = window.innerHeight * 0.92;
    root.querySelectorAll(SELECTOR).forEach((el) => {
        if (el.dataset.revealed || el.closest('.reveal')) return;
        el.dataset.revealed = '1';
        if (el.getBoundingClientRect().top < fold) return;

        const group = el.parentElement?.hasAttribute('data-stagger') ? el.parentElement : null;
        if (group) el.style.setProperty('--reveal-delay', `${Math.min([...group.children].indexOf(el), 8) * 60}ms`);
        el.classList.add('reveal');
        observer.observe(el);
    });
}
