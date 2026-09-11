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
                sans: ['"Work Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'Georgia', 'serif'],
            },
            colors: {
                brand: {
                    50: '#f2f8f4',
                    100: '#e0efe5',
                    200: '#c2dfcc',
                    300: '#95c7a8',
                    400: '#63a97f',
                    500: '#418b61',
                    600: '#2f6e4c',
                    700: '#27583e',
                    800: '#214633',
                    900: '#1c3a2b',
                    950: '#0d2018',
                },
                brass: {
                    50: '#fbf9eb',
                    100: '#f6efcb',
                    200: '#eedd9b',
                    300: '#e4c55f',
                    400: '#dbab36',
                    500: '#c99027',
                    600: '#ad6f1f',
                    700: '#8b521c',
                    800: '#73421d',
                    900: '#63381d',
                    950: '#391c0c',
                },
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
