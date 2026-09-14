import { staffPortalBaseUrl, staffPortalHomeUrl } from './sso'

const STAFF_SSO_TOKEN_KEY = 'helpdesk.staff_sso_token'

export interface CbpSystemLink {
  id: string
  label: string
  href: string
  description?: string
}

/** Keep Staff portal JWT for hand-off to APM / Finance (same `?token=` as home dashboard). */
export function persistStaffSsoToken(token: string): void {
  try {
    sessionStorage.setItem(STAFF_SSO_TOKEN_KEY, token)
  } catch {
    /* private mode */
  }
}

export function getPersistedStaffSsoToken(): string | null {
  try {
    return sessionStorage.getItem(STAFF_SSO_TOKEN_KEY)
  } catch {
    return null
  }
}

function withSsoToken(url: string): string {
  const token = getPersistedStaffSsoToken()
  if (!token?.trim()) {
    return url
  }
  try {
    const u = new URL(url, window.location.origin)
    u.searchParams.set('token', token.trim())
    return u.toString()
  } catch {
    return url
  }
}

function resolveFinanceUrl(): string {
  const fromEnv = import.meta.env.VITE_FINANCE_APP_URL as string | undefined
  if (fromEnv?.trim()) {
    return fromEnv.trim().replace(/\/$/, '')
  }
  const host = window.location.hostname
  if (host === 'localhost' || host === '127.0.0.1') {
    return 'http://localhost:3002'
  }
  const scheme = window.location.protocol === 'https:' ? 'https' : 'http'
  return `${scheme}://${window.location.host}/staff/finance`
}

function resolveApmUrl(staffBase: string): string {
  const fromEnv = import.meta.env.VITE_APM_BASE_URL as string | undefined
  if (fromEnv?.trim()) {
    return fromEnv.trim().replace(/\/$/, '')
  }
  return `${staffBase}/apm`
}

/** Other CBP apps (Helpdesk is current). Staff portal is returned separately as the primary link. */
export function cbpSystemLinks(): CbpSystemLink[] {
  const staffBase = staffPortalBaseUrl()
  return [
    {
      id: 'apm',
      label: 'APM',
      description: 'Approvals & memos',
      href: withSsoToken(resolveApmUrl(staffBase)),
    },
    {
      id: 'finance',
      label: 'Finance',
      description: 'Budgets & transactions',
      href: withSsoToken(resolveFinanceUrl()),
    },
  ]
}

export function staffPortalPrimaryLink(): string {
  return staffPortalHomeUrl()
}

export interface CbpNavHome {
  id: string
  label: string
  description: string
  href: string
  is_active?: boolean
}

export interface CbpNavModule {
  id: string
  label: string
  description: string
  href: string
  icon?: string
  opens_in_new_tab?: boolean
  is_active?: boolean
}

export interface CbpNavPayload {
  home: CbpNavHome
  modules: CbpNavModule[]
}

/** Client-side fallback when /api/v1/cbp-modules is unavailable. */
export function fallbackCbpNavPayload(): CbpNavPayload {
  return {
    home: {
      id: 'cbp_home',
      label: 'CBP Home',
      description: '',
      href: staffPortalHomeUrl(),
      is_active: false,
    },
    modules: cbpSystemLinks().map((link) => ({
      id: link.id,
      label: link.label,
      description: link.description ?? '',
      href: link.href,
      icon: link.id === 'apm' ? 'fa fa-sitemap' : 'fa fa-wallet',
      opens_in_new_tab: false,
      is_active: false,
    })),
  }
}
