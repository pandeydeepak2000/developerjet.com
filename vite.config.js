import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/css/docs.css', 'resources/css/admin.css', 'resources/css/admin-theme.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
