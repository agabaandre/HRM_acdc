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

function portalHomePath(): string {
  const fromEnv = (import.meta.env.VITE_STAFF_PORTAL_HOME_URL as string | undefined)?.trim()
  if (fromEnv?.startsWith('/')) {
    return fromEnv.endsWith('/') ? fromEnv : `${fromEnv}/`
  }
  if (typeof window !== 'undefined') {
    const stripped = window.location.pathname.replace(
      /\/(helpdesk|finance|apm|risk-register)(\/.*)?$/,
      '',
    )
    if (stripped && stripped !== '/') {
      return stripped.endsWith('/') ? stripped : `${stripped}/`
    }
  }
  return '/staff/'
}

export function staffPortalHomeUrl(): string {
  const fromEnv = (import.meta.env.VITE_STAFF_PORTAL_HOME_URL as string | undefined)?.trim()
  if (fromEnv && /^https?:\/\//i.test(fromEnv)) {
    return fromEnv.endsWith('/') ? fromEnv : `${fromEnv}/`
  }
  if (typeof window !== 'undefined') {
    const { protocol, host } = window.location
    return `${protocol}//${host}${portalHomePath()}`
  }
  if (fromEnv) {
    return fromEnv
  }
  return 'http://localhost/staff/'
}

let redirectingToStaffPortal = false

/** Staff portal sign-out. It clears the portal session and opens the portal login. */
export function staffPortalLogoutUrl(): string {
  return `${staffPortalBaseUrl()}/backend/logout`
}

/** Send the user back to Staff portal sign-in (session expired / invalid token). */
export function redirectToStaffPortalHome(): void {
  if (redirectingToStaffPortal) {
    return
  }
  redirectingToStaffPortal = true
  window.location.href = staffPortalLogoutUrl()
}

/** Base Staff portal URL (no trailing home path) for shared assets, e.g. logo — same pattern as Finance. */
export function staffPortalBaseUrl(): string {
  const explicit = (import.meta.env.VITE_STAFF_BASE_URL as string | undefined)?.trim()
  if (explicit && /^https?:\/\//i.test(explicit)) {
    return explicit.replace(/\/$/, '')
  }
  if (typeof window !== 'undefined') {
    const { protocol, host } = window.location
    if (explicit?.startsWith('/')) {
      return `${protocol}//${host}${explicit.replace(/\/$/, '')}`
    }
    return `${protocol}//${host}${portalHomePath().replace(/\/$/, '')}`
  }
  if (explicit) {
    return explicit.replace(/\/$/, '')
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
