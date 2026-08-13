import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Matches the published location (public_path('vendor/crm'),
            // see CrmServiceProvider's crm-assets tag) and the
            // @vite([...], 'vendor/crm') calls in every package/host view
            // that load these assets — must match exactly, or paths baked
            // into the compiled manifest/font CSS (e.g. @font-face src
            // URLs) point at the wrong directory.
            buildDirectory: 'vendor/crm',
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/entity-builder.js',
                'resources/js/entity-record-form.js',
                'resources/js/entity-relations.js',
                'resources/js/entity-condition-builder.js',
                'resources/js/entity-field-conditions.js',
                'resources/js/calendar.js',
                'resources/js/documents.js',
                'resources/js/importer-wizard.js',
                'resources/js/workflow-builder.js',
                'resources/js/workflow-instance-viewer.js',
                'resources/js/menu-builder.js',
                'resources/js/ticket-timer.js',
                'resources/js/mail.js',
                'resources/js/mail-signature-form.js',
                'resources/js/install-wizard.js',
            ],
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
});
