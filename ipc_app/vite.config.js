import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        // Older tablet browsers (Chrome 106, UC, Huawei Browser).
        target: ['es2019', 'chrome80', 'safari13'],
        // Tailwind v4 emits oklch() palette colors, which Chrome < 111 treats as
        // invalid (transparent backgrounds that still swallow taps). Lightning CSS
        // rewrites them to hex with an @supports upgrade for modern browsers.
        cssMinify: 'lightningcss',
    },
    css: {
        lightningcss: {
            targets: { chrome: 80 << 16, android: 80 << 16, safari: 13 << 16 },
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
});
