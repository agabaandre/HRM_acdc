/** Laravel login + Microsoft SSO entry (web routes on the API host). */

export function apiPublicBaseUrl(): string {
  const fromEnv = import.meta.env.VITE_STAFF_PORTAL_API_BASE_URL as string | undefined
  if (fromEnv && fromEnv.trim() !== '') {
    const base = fromEnv.trim().replace(/\/$/, '')
    if (base.startsWith('http://') || base.startsWith('https://')) {
      return base
    }
    if (typeof window !== 'undefined') {
      return `${window.location.origin}${base.startsWith('/') ? base : `/${base}`}`
    }
    return base
  }
  if (typeof window !== 'undefined') {
    const { protocol, host } = window.location
    return `${protocol}//${host}/staff/staff-portal/backend`
  }
  return '/staff/staff-portal/backend'
}

function resolveApiPublicBase(): string {
  return apiPublicBaseUrl()
}

/** Public Swagger UI for the Share / staff API. */
export function apiDocsUrl(): string {
  return `${apiPublicBaseUrl()}/share/docs`
}


/** SPA login URL (never bounce through Laravel Livewire /login). */
export function loginUrl(): string {
  if (typeof window !== 'undefined') {
    const base = (import.meta.env.BASE_URL || '/staff/staff-portal/').replace(/\/?$/, '/')
    return `${window.location.origin}${base}login`
  }
  return '/staff/staff-portal/login'
}

export function microsoftLoginUrl(): string {
  const base = resolveApiPublicBase()
  return `${base}/auth/microsoft`
}

export function logoutUrl(): string {
  const base = resolveApiPublicBase()
  return `${base}/logout`
}

/** End the Laravel web session, then land on SPA login. */
export function navigateToLogout(): void {
  window.location.href = logoutUrl()
}
