import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // React + Inertia dashboard (2026-09-26 rebuild)
                'resources/css/react.css',
                'resources/js/react/app.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
