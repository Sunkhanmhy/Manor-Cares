import { defineConfig } from 'vite';

// Manor Cares is a static HTML/CSS/JS site with a thin PHP backend (contact
// form + one-time payment iframe). No JS bundling/build step is required —
// Vite is only wired up for teams that want a local dev server with HMR.
export default defineConfig({
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
