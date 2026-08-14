import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './src'),
        },
    },
    test: {
        // jsdom, pas 'node' (contrairement à chrome-extension/vitest.config.ts) : les parseurs
        // GPX/KML testés ici s'appuient sur DOMParser/XMLSerializer, indisponibles sous 'node'.
        environment: 'jsdom',
    },
});
