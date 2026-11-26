/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// The admin SPA, served by Laravel at the root of its origin (docs/ARCHITECTURE.md §2).
// `@shared` holds code with no admin-specific knowledge: API client, UI kit.
export default defineConfig({
    plugins: [
        // laravel-vite-plugin refuses to start in CI; Vitest needs only React.
        ...(process.env.VITEST ? [] : [laravel({ input: ['resources/js/admin/main.tsx'], refresh: true })]),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@shared': fileURLToPath(new URL('./resources/js/shared', import.meta.url)),
            '@admin': fileURLToPath(new URL('./resources/js/admin', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: false,
        setupFiles: ['resources/js/test/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        css: false,
    },
});
