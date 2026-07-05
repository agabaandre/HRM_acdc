import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const isProd = mode === 'production'

  const proxyTarget = env.VITE_HELPDESK_API_PROXY_TARGET || 'http://localhost/staff/helpdesk/backend'

  const base = isProd
    ? (env.VITE_HELPDESK_BASE_PATH || '/staff/helpdesk/')
    : '/'

  return {
    base,
    build: {
      outDir: 'dist-build',
      emptyOutDir: false,
      target: 'es2020',
      cssCodeSplit: true,
      sourcemap: false,
      modulePreload: {
        polyfill: true,
      },
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (!id.includes('node_modules')) {
              return undefined
            }
            if (id.includes('@vueup/vue-quill') || id.includes('quill')) {
              return 'editor'
            }
            if (id.includes('vuetify')) {
              return 'vuetify'
            }
            if (
              id.includes('/vue/') ||
              id.includes('vue-router') ||
              id.includes('pinia') ||
              id.includes('@vue/')
            ) {
              return 'vue-vendor'
            }
            if (id.includes('axios')) {
              return 'http'
            }
            return undefined
          },
        },
      },
      chunkSizeWarningLimit: 700,
    },
    plugins: [
      vue(),
      vuetify({
        autoImport: true,
      }),
    ],
    optimizeDeps: {
      include: ['vue', 'vue-router', 'pinia', 'vuetify', 'axios'],
    },
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
