import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                display: ['"Playfair Display"', 'Georgia', 'Times New Roman', 'serif'],
            },
            colors: {
                ink: {
                    900: '#0a0a0b',
                    800: '#121213',
                    700: '#1a1a1c',
                    600: '#232326',
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
                paper: '#faf9f6',
                surface: '#ffffff',
                sand: {
                    50: '#faf8f5',
                    100: '#f3efe8',
                    200: '#e5ddcf',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(28, 58, 43, 0.06), 0 8px 24px -12px rgba(28, 58, 43, 0.18)',
            },
        },
    },

    plugins: [forms],
};
