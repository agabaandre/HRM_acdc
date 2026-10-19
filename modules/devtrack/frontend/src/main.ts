import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { applyBrandingTheme } from './lib/brandingTheme'
import { recoverFromChunkLoadError } from './lib/chunkLoadRecovery'
import { initThemePreference } from './lib/themePreference'
import { useAuthStore } from './stores/auth'
import { useBrandingStore } from './stores/branding'
import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/inter/700.css'
import './assets/styles/main.scss'

window.addEventListener('error', (event) => {
  recoverFromChunkLoadError(event.error ?? event.message)
})

window.addEventListener('unhandledrejection', (event) => {
  if (recoverFromChunkLoadError(event.reason)) {
    event.preventDefault()
  }
})

async function bootstrap() {
  const pinia = createPinia()
  const branding = useBrandingStore(pinia)

  try {
    await branding.fetch()
  } catch {
    applyBrandingTheme(branding.branding)
  }

  const app = createApp(App)
  app.use(pinia)
  app.use(vuetify)
  initThemePreference()
  app.use(router)

  const auth = useAuthStore(pinia)
  await auth.bootstrap()

  app.mount('#app')
}

void bootstrap()
