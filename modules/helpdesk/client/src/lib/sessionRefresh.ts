import { staffPortalBaseUrl } from './sso'
import { persistStaffSsoToken } from './cbpSystems'
import { useAuthStore } from '../stores/auth'

declare global {
  interface Window {
    CBP_STAFF_BASE_URL?: string
    CBP_SSO_REFRESH_HANDLERS?: Array<(detail: { sso_token?: string }) => void>
    cbpRegisterSsoRefreshHandler?: (fn: (detail: { sso_token?: string }) => void) => void
    cbpStartSsoSessionRefresh?: () => void
    cbpRefreshSsoSession?: () => void
  }
}

const SCRIPT_ID = 'cbp-session-refresh-script'

function ensureStaffBaseUrl(): void {
  if (!window.CBP_STAFF_BASE_URL) {
    window.CBP_STAFF_BASE_URL = staffPortalBaseUrl()
  }
}

function loadRefreshScript(): Promise<void> {
  if (document.getElementById(SCRIPT_ID)) {
    return Promise.resolve()
  }
  ensureStaffBaseUrl()
  const base = (window.CBP_STAFF_BASE_URL || staffPortalBaseUrl()).replace(/\/$/, '')
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.id = SCRIPT_ID
    script.src = `${base}/assets/js/cbp-session-refresh.js?v=1`
    script.defer = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Failed to load cbp-session-refresh.js'))
    document.head.appendChild(script)
  })
}

/** Keep Helpdesk API token in sync with Staff portal SSO JWT refresh. */
export async function startHelpdeskSsoSessionRefresh(): Promise<void> {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) {
    return
  }

  const handler = async (detail: { sso_token?: string }) => {
    if (!detail?.sso_token || !auth.isAuthenticated) {
      return
    }
    try {
      await auth.exchangeStaffSso(detail.sso_token)
      persistStaffSsoToken(detail.sso_token)
    } catch (err) {
      console.warn('[helpdesk] SSO session refresh failed', err)
    }
  }

  if (typeof window.cbpRegisterSsoRefreshHandler === 'function') {
    window.cbpRegisterSsoRefreshHandler(handler)
  } else {
    window.CBP_SSO_REFRESH_HANDLERS = window.CBP_SSO_REFRESH_HANDLERS || []
    window.CBP_SSO_REFRESH_HANDLERS.push(handler)
  }

  try {
    await loadRefreshScript()
    window.cbpStartSsoSessionRefresh?.()
  } catch (err) {
    console.warn('[helpdesk] Could not start Staff SSO refresh', err)
  }
}
