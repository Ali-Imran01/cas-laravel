import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

// Static demo: same Pages/Components as the real app, dummy data, no Laravel.
export default defineConfig({
    root: 'resources/js/demo',
    publicDir: 'public', // relative to root: holds _redirects for SPA refresh
    plugins: [react(), tailwindcss()],
    build: { outDir: resolve(import.meta.dirname, 'dist-demo'), emptyOutDir: true },
});
