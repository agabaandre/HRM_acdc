import path from 'node:path'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

const rootDir = import.meta.dirname
const cbpSrc = path.resolve(rootDir, 'src/cbp')

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const isProd = mode === 'production'

  const proxyTarget =
    env.VITE_STAFF_PORTAL_API_PROXY_TARGET || 'http://localhost/staff/staff-portal/backend'

  const base = isProd
    ? (env.VITE_STAFF_PORTAL_BASE_PATH || '/staff/staff-portal/')
    : '/'

  return {
    base,
    resolve: {
      alias: {
        '@': path.resolve(rootDir, 'src'),
        '@cbp/ui': path.join(cbpSrc, 'ui'),
        '@cbp/common': path.join(cbpSrc, 'common'),
        '@cbp/layout': path.join(cbpSrc, 'layout'),
        '@cbp/helpdesk-lib': cbpSrc,
      },
    },
    build: {
      outDir: 'dist-build',
      emptyOutDir: true,
      target: 'es2020',
      cssCodeSplit: true,
      sourcemap: false,
      chunkSizeWarningLimit: 600,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (!id.includes('node_modules')) {
              return undefined
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
    },
    plugins: [
      vue(),
      vuetify({
        autoImport: true,
      }),
    ],
    server: {
      port: 5175,
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
        '/auth': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
