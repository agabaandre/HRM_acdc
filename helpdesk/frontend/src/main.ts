import axios from 'axios'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import './styles/cbp-finance-layout.css'
import './styles/rich-text-display.css'
import './styles/ticket-table.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { persistStaffSsoToken } from './lib/cbpSystems'
import { getStaffSsoTokenFromUrl, stripStaffSsoTokenFromUrl, staffPortalHomeUrl } from './lib/sso'

type SsoFailure = {
  code: 'network' | 'forbidden' | 'unauthorized' | 'invalid' | 'config' | 'unknown'
  message: string
  status?: number
}

function syncFaviconWithApm(): void {
  const link = document.getElementById('app-favicon') as HTMLLinkElement | null
  if (!link) return

  const protocol = window.location.protocol
  const hostNoPort = window.location.hostname
  // Match APM's favicon source: /staff/apm/assets/images/au_emblem.png
  link.href = `${protocol}//${hostNoPort}/staff/apm/assets/images/au_emblem.png`
  link.type = 'image/png'
}

function classifyExchangeError(err: unknown): SsoFailure {
  if (axios.isAxiosError(err)) {
    const status = err.response?.status
    const body = err.response?.data as Record<string, unknown> | undefined
    const apiMessage = typeof body?.message === 'string' && body.message.trim() !== ''
      ? body.message.trim()
      : ''
    if (err.code === 'ERR_NETWORK' || (!err.response && err.request)) {
      return {
        code: 'network',
        message: 'Could not reach the Helpdesk API. Make sure the Laravel backend is running (php artisan serve on :8000 in dev, or the production API host is reachable).',
      }
    }
    if (status === 403) {
      return {
        code: 'forbidden',
        status,
        message: apiMessage !== ''
          ? apiMessage
          : 'Your Staff portal profile does not include permission to open the Helpdesk.',
      }
    }
    if (status === 401) {
      return {
        code: 'unauthorized',
        status,
        message: apiMessage !== '' ? apiMessage : 'Staff session token rejected. Sign in to the Staff portal again and retry.',
      }
    }
    if (status === 422) {
      return {
        code: 'invalid',
        status,
        message: apiMessage !== '' ? apiMessage : 'Staff session token is missing required fields (staff_id or email).',
      }
    }
    if (status === 503) {
      return {
        code: 'config',
        status,
        message: apiMessage !== '' ? apiMessage : 'Helpdesk API is missing JWT_SECRET (must match the Staff portal root .env).',
      }
    }
    return {
      code: 'unknown',
      status,
      message: apiMessage !== '' ? apiMessage : (err.message || 'Helpdesk SSO failed.'),
    }
  }
  if (err instanceof Error && err.message) {
    return { code: 'unknown', message: err.message }
  }
  return { code: 'unknown', message: 'Helpdesk SSO failed for an unknown reason.' }
}

function renderSsoErrorScreen(failure: SsoFailure): void {
  const root = document.getElementById('app')
  if (!root) {
    return
  }
  const portal = staffPortalHomeUrl()
  const safeMessage = failure.message
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  root.innerHTML = `
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#f4f5f7;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
      <section style="max-width:560px;width:100%;background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);padding:28px;">
        <p style="margin:0 0 8px;font-size:0.85rem;letter-spacing:0.04em;text-transform:uppercase;color:#0d7a3a;font-weight:600;">IT Service Desk</p>
        <h1 style="margin:0 0 12px;font-size:1.4rem;color:#1f2933;">We couldn’t open the Helpdesk</h1>
        <p style="margin:0 0 16px;color:#475569;line-height:1.55;">${safeMessage}</p>
        <p style="margin:0 0 20px;color:#64748b;font-size:0.9rem;line-height:1.55;">
          Reason code: <code style="background:#eef2f7;padding:2px 6px;border-radius:4px;">${failure.code}${failure.status ? ' / ' + failure.status : ''}</code>
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="${portal}" style="background:#0d7a3a;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;">Return to Staff portal</a>
          <button id="cbp-help-retry" type="button" style="background:#fff;color:#0d7a3a;border:1px solid #0d7a3a;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;">Retry from Staff portal</button>
        </div>
      </section>
    </main>
  `
  const retry = document.getElementById('cbp-help-retry')
  if (retry) {
    retry.addEventListener('click', () => {
      window.location.href = portal
    })
  }
}

function redirectToStaffPortalWithError(failure: SsoFailure): void {
  const target = new URL(staffPortalHomeUrl())
  target.searchParams.set('helpdesk_error', 'sso')
  target.searchParams.set('helpdesk_error_reason', failure.code)
  window.location.href = target.toString()
}

async function bootstrap() {
  syncFaviconWithApm()

  const app = createApp(App)
  const pinia = createPinia()
  app.use(pinia)

  const urlToken = getStaffSsoTokenFromUrl()
  if (urlToken) {
    persistStaffSsoToken(urlToken)
    const auth = useAuthStore(pinia)
    try {
      await auth.exchangeStaffSso(urlToken)
      stripStaffSsoTokenFromUrl()
    } catch (err) {
      stripStaffSsoTokenFromUrl()
      const failure = classifyExchangeError(err)
      console.error('[helpdesk] SSO exchange failed:', failure, err)
      if (failure.code === 'network' || failure.code === 'config') {
        renderSsoErrorScreen(failure)
        return
      }
      redirectToStaffPortalWithError(failure)
      return
    }
  }

  app.use(router)
  await router.isReady()
  app.mount('#app')
}

void bootstrap()
