import { createApp } from 'vue'
import { createPinia } from 'pinia'
import vuetify from '@cbp/helpdesk-lib/plugins/vuetify'
import { registerUiComponents } from '@cbp/ui/register'
import '@cbp/helpdesk-lib/styles/app-preloader.css'
import '@cbp/helpdesk-lib/styles/vuetify-overrides.css'
import '@cbp/helpdesk-lib/styles/helpdesk-forms.css'
import '@cbp/helpdesk-lib/style.css'
import '@cbp/helpdesk-lib/styles/cbp-finance-layout.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { getStoredToken } from './lib/api'

function dismissBootLoader(): void {
  const el = document.getElementById('portal-boot-loader')
  if (el) {
    el.remove()
  }
}

async function bootstrap() {
  const app = createApp(App)
  const pinia = createPinia()
  app.use(pinia)
  app.use(vuetify)
  registerUiComponents(app)

  const auth = useAuthStore(pinia)

  if (getStoredToken() && !auth.me) {
    try {
      await auth.fetchMe()
    } catch {
      auth.invalidateSession()
    }
  }

  app.use(router)
  await router.isReady()
  app.mount('#app')
  dismissBootLoader()
}

void bootstrap()
