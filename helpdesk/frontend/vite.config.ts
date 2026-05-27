import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const isProd = mode === 'production'

  // Where Vite's dev /api proxy forwards to. Defaults to the Apache-served
  // Laravel backend (see helpdesk/backend/.htaccess) so no `php artisan
  // serve` is required for local development.
  const proxyTarget = env.VITE_HELPDESK_API_PROXY_TARGET || 'http://localhost/staff/helpdesk/backend'

  // Base public path for production builds. The built SPA is served by
  // Apache from <host>/staff/helpdesk/ (mirroring how /staff/apm/ is
  // served), so assets and Vue Router need that prefix.
  const base = isProd
    ? (env.VITE_HELPDESK_BASE_PATH || '/staff/helpdesk/')
    : '/'

  return {
    base,
    plugins: [vue()],
    server: {
      port: 5174,
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
