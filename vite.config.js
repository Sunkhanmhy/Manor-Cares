import { defineConfig } from 'vite';
import { resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));

// Manor Cares dashboards are plain native ES modules (src/dashboard/*.js),
// so no build step is required for local development or a first Railway
// deploy — `php -S` / Railway's PHP runtime can serve them as-is. Vite is
// wired up for teams that want dev-server HMR (`npm run dev`) or a minified
// production bundle (`npm run build` -> assets/dist) as the project grows.
export default defineConfig({
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        dashboard: resolve(__dirname, 'src/dashboard/main.js'),
        admin: resolve(__dirname, 'src/dashboard/admin.js'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: false,
    // Lets `npm run dev` serve the site while PHP endpoints are proxied to
    // `php -S localhost:8000` (run `npm run php:serve` in another terminal).
    proxy: {
      '/php': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
});
