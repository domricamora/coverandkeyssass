import { createRoot } from 'react-dom/client';

/**
 * React islands on the server-rendered public pages. A page renders
 * `<div data-widget="StayPanel" data-props='{…}'>` (with a no-JS fallback
 * inside) and pushes this entry; each widget module loads only when used.
 */
const modules = import.meta.glob('./widgets/*.jsx');

document.querySelectorAll('[data-widget]').forEach(async (el) => {
    const load = modules[`./widgets/${el.dataset.widget}.jsx`];
    if (!load) return;
    const { default: Widget } = await load();
    createRoot(el).render(<Widget {...JSON.parse(el.dataset.props || '{}')} />);
});
