import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isProd = mode === 'production';

    // Apache serves the app at /staff/finance/ (same idea as helpdesk's base path).
    const base = isProd
        ? (env.VITE_APP_BASE_PATH || '/staff/finance/')
        : '/staff/finance/';

    return {
        base,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.jsx'],
                refresh: true,
            }),
            react(),
        ],
        server: {
            host: 'localhost',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5173',
            cors: true,
            hmr: {
                host: 'localhost',
            },
        },
    };
});
