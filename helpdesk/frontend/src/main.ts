import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import './styles/cbp-finance-layout.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { getStaffSsoTokenFromUrl, stripStaffSsoTokenFromUrl, staffPortalHomeUrl } from './lib/sso'

async function bootstrap() {
  const app = createApp(App)
  const pinia = createPinia()
  app.use(pinia)

  const urlToken = getStaffSsoTokenFromUrl()
  if (urlToken) {
    const auth = useAuthStore(pinia)
    try {
      await auth.exchangeStaffSso(urlToken)
      stripStaffSsoTokenFromUrl()
    } catch {
      stripStaffSsoTokenFromUrl()
      window.location.href = `${staffPortalHomeUrl()}?helpdesk_error=sso`
      return
    }
  }

  app.use(router)
  await router.isReady()
  app.mount('#app')
}

void bootstrap()
