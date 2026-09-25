import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Shell from './Shell';

const pages = import.meta.glob('../Pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? `${title} | Cover & Keys` : 'Cover & Keys'),
    resolve: async (name) => {
        const page = await pages[`../Pages/${name}.jsx`]();
        // Every dashboard screen sits in the full-screen shell unless it opts out.
        page.default.layout ??= (children) => <Shell>{children}</Shell>;
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#0e6461', delay: 150 },
});
