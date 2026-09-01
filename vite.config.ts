import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});
