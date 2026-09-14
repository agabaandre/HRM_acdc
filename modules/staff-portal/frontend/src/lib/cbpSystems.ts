const SSO_TOKEN_KEY = 'staff_portal.sso_token'

export function persistStaffSsoToken(token: string): void {
  try {
    sessionStorage.setItem(SSO_TOKEN_KEY, token)
  } catch {
    // ignore
  }
}

export function readStaffSsoToken(): string | null {
  try {
    return sessionStorage.getItem(SSO_TOKEN_KEY)
  } catch {
    return null
  }
}

export function appendSsoTokenToUrl(url: string, token: string | null): string {
  if (!token || /[?&]token=/.test(url)) {
    return url
  }
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}
