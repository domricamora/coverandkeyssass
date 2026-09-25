import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Modules/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Geist"', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                mono: ['"Geist Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
                display: ['"Playfair Display"', 'Georgia', '"Times New Roman"', 'serif'],
            },
            colors: {
                // Marketing palette (ink/bone neutrals + gold/brown accents)
                ink: {
                    900: '#0a0a0b',
                    800: '#121213',
                    700: '#1a1a1c',
                    600: '#232326',
                    500: '#33333a',
                },
                bone: '#f7f5f2',
                mist: '#d8d5d0',
                smoke: '#9a958d',
                ash: '#848076',
                gold: {
                    DEFAULT: '#c9a227',
                    soft: '#d9bd6a',
                    deep: '#a8861d',
                },
                brown: {
                    DEFAULT: '#6b4f3a',
                    soft: '#a9866a',
                },
                // App UI palette (slate surfaces + sky focus rings)
                slate: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    900: '#0f172a',
                },
                sky: {
                    600: '#0284c7',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 23, 42, 0.06), 0 8px 24px -12px rgba(15, 23, 42, 0.18)',
            },
        },
    },

    plugins: [forms],
};
