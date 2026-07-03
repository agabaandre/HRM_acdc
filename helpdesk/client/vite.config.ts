import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const isProd = mode === 'production'

  const proxyTarget = env.VITE_HELPDESK_API_PROXY_TARGET || 'http://localhost/staff/helpdesk/backend'

  const base = isProd
    ? (env.VITE_HELPDESK_BASE_PATH || '/staff/helpdesk2/')
    : '/'

  return {
    base,
    plugins: [
      vue(),
      vuetify({ autoImport: true }),
    ],
    server: {
      port: Number(env.VITE_HELPDESK_CLIENT_DEV_PORT || 5175),
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
