import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'
import { dirname, resolve } from 'node:path'

const frontendDir = dirname(fileURLToPath(import.meta.url))
const appBase = readFileSync(resolve(frontendDir, '../app-base'), 'utf8').trim().replace(/\/$/, '')
const uiBase = `${appBase}/ui/`

export default defineConfig({
  base: uiBase,
  plugins: [
    vue({
      template: {
        compilerOptions: {
          isCustomElement: () => false,
        },
      },
    }),
    vuetify({ autoImport: true }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      vue: 'vue/dist/vue.runtime.esm-bundler.js',
    },
  },
  build: {
    outDir: resolve(frontendDir, '../ui'),
    emptyOutDir: true,
    sourcemap: false,
    minify: 'esbuild',
  },
})
