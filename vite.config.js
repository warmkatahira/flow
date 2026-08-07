import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Kosugi Maru', {
                    weights: [400],
                    subsets: ['latin', 'japanese'],
                }),
                // ロゴの「Flow」だけに使う。日本語グリフは持っていない
                bunny('Righteous', {
                    weights: [400],
                    subsets: ['latin'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
