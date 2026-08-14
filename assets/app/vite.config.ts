import path from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
    plugins: [react(), tailwindcss(), symfonyPlugin()],
    build: {
        outDir: '../../public/build',
        rollupOptions: {
            input: {
                app: './src/entries/app.css',
                nav: './src/entries/nav.tsx',
                convertHero: './src/entries/convertHero.tsx',
                extensionConnect: './src/entries/extensionConnect.tsx',
                kmlToGpx: './src/entries/kmlToGpx.tsx',
                gpxToKml: './src/entries/gpxToKml.tsx',
                gpxSimplify: './src/entries/gpxSimplify.tsx',
                gpxMerge: './src/entries/gpxMerge.tsx',
                gpxToGoogleMaps: './src/entries/gpxToGoogleMaps.tsx',
                gpxViewer: './src/entries/gpxViewer.tsx',
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './src'),
        },
    },
});
