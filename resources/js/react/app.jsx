import '../../css/motion.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { reveal } from '../reveal';
import { setMoneySymbol } from './ui';
import Shell from './Shell';

// New screens and pages of results: reveal their below-the-fold sections once rendered.
router.on('navigate', () => requestAnimationFrame(() => reveal()));
// Keep money() on the current business's symbol (set before the new page renders).
router.on('success', (e) => setMoneySymbol(e.detail.page.props.moneySymbol));

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
        setMoneySymbol(props.initialPage.props.moneySymbol);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#0e6461', delay: 150 },
});
