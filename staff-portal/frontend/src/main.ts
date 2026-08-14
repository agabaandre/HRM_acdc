import { createApp } from 'vue'
import { createPinia } from 'pinia'
import vuetify from './plugins/vuetify'
import { registerUiComponents } from '@cbp/ui/register'
import '@cbp/helpdesk-lib/styles/app-preloader.css'
import '@cbp/helpdesk-lib/styles/vuetify-overrides.css'
import '@cbp/helpdesk-lib/styles/helpdesk-forms.css'
import '@cbp/helpdesk-lib/style.css'
import '@cbp/helpdesk-lib/styles/cbp-finance-layout.css'
import './styles/portal-shell.css'
import './styles/portal-fields.css'
import './styles/portal-datatable.css'
import './styles/toast.css'
import '@cbp/helpdesk-lib/styles/helpdesk-dark-mode.css'
import './styles/portal-dark-mode.css'
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

  // Never block first paint on /me. Session cache hydrates instantly; otherwise
  // start the request and let the router guard await the shared in-flight promise.
  // Do not invalidate the session on background failure — the router handles 401s.
  if (getStoredToken()) {
    if (auth.me) {
      auth.refreshMeInBackground()
    } else {
      void auth.fetchMe().catch(() => {
        /* router guard / API interceptor clear invalid sessions */
      })
    }
  }

  app.use(router)
  await router.isReady()
  app.mount('#app')
  dismissBootLoader()
}

void bootstrap()
