/** Query param used by Staff portal (same as Finance / APM). */
export const STAFF_SSO_QUERY = 'token'

export function getStaffSsoTokenFromUrl(): string | null {
  const raw = new URLSearchParams(window.location.search).get(STAFF_SSO_QUERY)
  if (!raw || raw.trim() === '') {
    return null
  }
  try {
    return decodeURIComponent(raw.trim())
  } catch {
    return raw.trim()
  }
}

/** Remove SSO token from the address bar without reloading. */
export function stripStaffSsoTokenFromUrl(): void {
  const u = new URL(window.location.href)
  if (!u.searchParams.has(STAFF_SSO_QUERY)) {
    return
  }
  u.searchParams.delete(STAFF_SSO_QUERY)
  const q = u.searchParams.toString()
  const path = u.pathname + (q ? `?${q}` : '') + u.hash
  window.history.replaceState({}, document.title, path)
}

export function staffPortalHomeUrl(): string {
  if (typeof window !== 'undefined') {
    const { protocol, hostname, host } = window.location
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return `${protocol}//${host}/staff/home/index`
    }
    const fromEnv = import.meta.env.VITE_STAFF_PORTAL_HOME_URL as string | undefined
    if (fromEnv?.startsWith('/')) {
      return `${protocol}//${host}${fromEnv}`
    }
  }
  const fromEnv = import.meta.env.VITE_STAFF_PORTAL_HOME_URL as string | undefined
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim()
  }
  return 'http://localhost/staff/home/index'
}

let redirectingToStaffPortal = false

/** Send the user back to Staff portal sign-in (session expired / invalid token). */
export function redirectToStaffPortalHome(): void {
  if (redirectingToStaffPortal) {
    return
  }
  redirectingToStaffPortal = true
  window.location.href = staffPortalHomeUrl()
}

/** Base Staff portal URL (no `/home/index`) for shared assets, e.g. logo — same pattern as Finance. */
export function staffPortalBaseUrl(): string {
  if (typeof window !== 'undefined') {
    const { protocol, hostname, host } = window.location
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return `${protocol}//${host}/staff`
    }
    const explicit = import.meta.env.VITE_STAFF_BASE_URL as string | undefined
    if (explicit?.startsWith('/')) {
      return `${protocol}//${host}${explicit.replace(/\/$/, '')}`
    }
  }
  const explicit = import.meta.env.VITE_STAFF_BASE_URL as string | undefined
  if (explicit?.trim()) {
    return explicit.trim().replace(/\/$/, '')
  }
  const home = staffPortalHomeUrl()
  const stripped = home.replace(/\/home\/index\/?$/i, '').replace(/\/$/, '')
  return stripped !== '' ? stripped : home.replace(/\/$/, '')
}

/** Staff portal (CodeIgniter) user profile — same path as Finance header. */
export function staffPortalProfileUrl(): string {
  const fromEnv = import.meta.env.VITE_STAFF_PROFILE_URL as string | undefined
  if (fromEnv?.trim()) {
    return fromEnv.trim()
  }
  return `${staffPortalBaseUrl()}/auth/profile`
}
