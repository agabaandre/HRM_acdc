/** Laravel login + Microsoft SSO entry (web routes on the API host). */
export function loginUrl(): string {
  const base = resolveApiPublicBase()
  return `${base}/login`
}

export function microsoftLoginUrl(): string {
  const base = resolveApiPublicBase()
  return `${base}/auth/microsoft`
}

export function logoutUrl(): string {
  const base = resolveApiPublicBase()
  return `${base}/logout`
}

function resolveApiPublicBase(): string {
  const fromEnv = import.meta.env.VITE_STAFF_PORTAL_API_BASE_URL as string | undefined
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim().replace(/\/$/, '')
  }
  if (typeof window !== 'undefined') {
    const { protocol, host } = window.location
    return `${protocol}//${host}/staff/staff-portal/public`
  }
  return '/staff/staff-portal/public'
}
