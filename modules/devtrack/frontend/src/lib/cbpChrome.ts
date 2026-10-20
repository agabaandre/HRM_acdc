export interface CbpModuleLink {
  id?: string
  label: string
  href: string
  icon?: string
  is_active?: boolean
  opens_in_new_tab?: boolean
  module_key?: string | null
  sso_launch?: boolean
}

export interface CbpNavPayload {
  home: CbpModuleLink
  modules: CbpModuleLink[]
}

export interface PortalLanguage {
  code: string
  name: string
  flag: string
}

const FALLBACK_LANGUAGES: PortalLanguage[] = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'fr', name: 'Français', flag: '🇫🇷' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦' },
  { code: 'es', name: 'Español', flag: '🇪🇸' },
  { code: 'pt', name: 'Português', flag: '🇵🇹' },
  { code: 'sw', name: 'Kiswahili', flag: '🇰🇪' },
]

const LOCALE_KEY = 'staff_portal_locale'

export function staffPortalBase(): string {
  if (typeof window === 'undefined') return '/staff'
  const path = window.location.pathname
  const mark = path.indexOf('/devtrack')
  if (mark > 0) return path.slice(0, mark)
  return '/staff'
}

/** Staff portal sign-out. It clears the portal session and opens the portal login. */
export function staffPortalLogoutUrl(): string {
  const base = staffPortalBase().replace(/\/$/, '')
  const path = `${base}/backend/logout`
  if (path.startsWith('http') || typeof window === 'undefined') return path
  return `${window.location.origin}${path}`
}

function staffToken(): string | null {
  try {
    return sessionStorage.getItem('staff_portal_api_token') || localStorage.getItem('staff_portal_api_token')
  } catch {
    return null
  }
}

async function staffFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const token = staffToken()
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  if (init.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json')
  return fetch(`${staffPortalBase()}/backend${path}`, { ...init, headers })
}

export async function fetchCbpModules(): Promise<CbpNavPayload> {
  const res = await staffFetch('/api/v1/cbp-modules?active=devtrack&path=devtrack')
  if (!res.ok) throw new Error('Could not load CBP modules')
  const body = (await res.json()) as { data?: CbpNavPayload }
  if (!body.data?.home || !Array.isArray(body.data.modules)) throw new Error('Invalid CBP modules payload')
  return body.data
}

export async function launchCbpModule(moduleKey: string): Promise<void> {
  const res = await staffFetch('/api/v1/cbp-modules/launch', {
    method: 'POST',
    body: JSON.stringify({ module_key: moduleKey }),
  })
  if (!res.ok) throw new Error('Could not open that module')
  const data = (await res.json()) as { accept_url?: string; staff_sso_jwt?: string; redirect_url?: string }
  if (data.redirect_url) {
    window.location.assign(data.redirect_url)
    return
  }
  const acceptUrl = (data.accept_url || '').trim()
  const jwt = (data.staff_sso_jwt || '').trim()
  if (!acceptUrl || !jwt) throw new Error('Could not obtain a security token')
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = acceptUrl
  form.style.display = 'none'
  const input = document.createElement('input')
  input.type = 'hidden'
  input.name = 'staff_sso_jwt'
  input.value = jwt
  form.appendChild(input)
  document.body.appendChild(form)
  form.submit()
}

export function readLocale(): string {
  try {
    return localStorage.getItem(LOCALE_KEY) || 'en'
  } catch {
    return 'en'
  }
}

export function fallbackLanguages(): PortalLanguage[] {
  return FALLBACK_LANGUAGES
}

export async function fetchLanguages(): Promise<{ locale: string; languages: PortalLanguage[] }> {
  const res = await staffFetch('/api/v1/languages')
  if (!res.ok) throw new Error('Could not load languages')
  const body = (await res.json()) as { data?: { locale?: string; languages?: PortalLanguage[] } }
  const languages = body.data?.languages?.length ? body.data.languages : FALLBACK_LANGUAGES
  return { locale: body.data?.locale || readLocale(), languages }
}

export async function applyLocale(code: string): Promise<void> {
  try {
    localStorage.setItem(LOCALE_KEY, code)
  } catch {
    /* ignore */
  }
  document.cookie = `${LOCALE_KEY}=${encodeURIComponent(code)};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
  const res = await staffFetch('/api/v1/locale', {
    method: 'POST',
    body: JSON.stringify({ locale: code }),
  })
  if (!res.ok) throw new Error('Could not change language')
}
