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
      // Keep false while some dist-build/assets files remain root-owned
      // (`sudo rm -rf dist-build/assets`, then flip to true for clean builds).
      emptyOutDir: false,
      target: 'es2020',
      cssCodeSplit: true,
      sourcemap: false,
      modulePreload: {
        polyfill: true,
        resolveDependencies(_filename, deps) {
          // Quill / editor chunk is route-specific — never preload on boot.
          return deps.filter(
            (dep) =>
              !/(^|\/)editor[-.]/.test(dep) &&
              !dep.includes('vue-quill') &&
              !/\/quill/.test(dep),
          )
        },
      },
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (!id.includes('node_modules')) {
              return undefined
            }
            // Keep Vue runtime out of any feature chunk (esp. Quill).
            if (
              /[/\\](?:vue|vue-router|pinia)[/\\]/.test(id) ||
              id.includes('@vue/')
            ) {
              return 'vue-vendor'
            }
            if (id.includes('vuetify')) {
              return 'vuetify'
            }
            if (id.includes('axios')) {
              return 'http'
            }
            // Do NOT force Quill into a shared chunk — dynamic import only.
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
