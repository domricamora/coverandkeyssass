import '../../css/motion.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { reveal } from '../reveal';
import Shell from './Shell';

// New screens and pages of results: reveal their below-the-fold sections once rendered.
router.on('navigate', () => requestAnimationFrame(() => reveal()));

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
