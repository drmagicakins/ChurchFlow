import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    // Bind the dev server to IPv4 explicitly. Left to its default, Vite listens
    // on `localhost`, which this machine resolves to the IPv6 loopback (::1) —
    // so the generated asset URLs were http://[::1]:5173/... and the browser
    // refused them under the app's Content-Security-Policy, because a bracketed
    // IPv6 literal is not a valid CSP source. Pinning the host to 127.0.0.1
    // makes the URLs match the origin SecurityHeaders allows in `local`.
    // Dev only: `npm run build` is unaffected and production assets are served
    // from the app's own origin.
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
