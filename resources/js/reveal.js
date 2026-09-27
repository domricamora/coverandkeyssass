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
        el.querySelectorAll('.grid').forEach((grid) => [...grid.children].forEach((child, i) => child.style.setProperty('--i', Math.min(i, 9))));
        observer.observe(el);
    });
}

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = 'cubic-bezier(0.23, 1, 0.32, 1)';

/**
 * <details> accordions (FAQ): slide open / shut instead of snapping. Animates the
 * details element's height between its closed and open size, fades the answer in,
 * and survives a second click mid-animation. Opt out with data-no-slide.
 */
export function accordions() {
    document.addEventListener('click', (e) => {
        const summary = e.target.closest('details > summary');
        const details = summary?.parentElement;
        if (!details || details.hasAttribute('data-no-slide') || reduced()) return;
        e.preventDefault();

        const closing = details.open && !details.dataset.closing;
        const from = details.getBoundingClientRect().height;
        details._slide?.cancel();

        details.open = true;
        const openHeight = details.getBoundingClientRect().height;
        details.open = false;
        const closedHeight = details.getBoundingClientRect().height;
        details.open = true;

        details.dataset.closing = closing ? '1' : '';
        details.style.overflow = 'hidden';
        const body = [...details.children].filter((el) => el !== summary);
        if (!closing) body.forEach((el) => el.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: EASE, delay: 40 }));

        const slide = details.animate({ height: [`${from}px`, `${closing ? closedHeight : openHeight}px`] }, { duration: closing ? 220 : 320, easing: EASE });
        details._slide = slide;
        slide.onfinish = () => {
            details.open = !closing;
            details.dataset.closing = '';
            details.style.overflow = '';
            details._slide = null;
        };
    });
}
