import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/portal.css', 'resources/js/portal.js'],
            refresh: true,
            fonts: [
                bunny('Work Sans', {
                    weights: [300, 400, 500, 600, 700, 800],
                    styles: ['normal', 'italic'],
                    preload: [{ weight: 400 }, { weight: 700 }],
                    optimizedFallbacks: false,
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
