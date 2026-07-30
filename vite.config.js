import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/entity-builder.js', 'resources/js/entity-record-form.js', 'resources/js/calendar.js', 'resources/js/importer-wizard.js', 'resources/js/workflow-builder.js', 'resources/js/workflow-instance-viewer.js', 'resources/js/menu-builder.js', 'resources/js/install-wizard.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Montserrat', {
                    weights: [500, 800],
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
