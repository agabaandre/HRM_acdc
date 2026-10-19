import axios from 'axios'
import { withAppBase } from '@/lib/basePath'

const TOKEN_KEY = 'email_server.token'

/**
 * Prefer sessionStorage so the admin bearer token does not survive browser restarts.
 * Migrate any legacy localStorage token once, then clear it.
 */
function tokenStore(): Storage {
  return window.sessionStorage
}

function migrateLegacyToken(): void {
  try {
    const legacy = window.localStorage.getItem(TOKEN_KEY)
    if (legacy && !window.sessionStorage.getItem(TOKEN_KEY)) {
      window.sessionStorage.setItem(TOKEN_KEY, legacy)
    }
    window.localStorage.removeItem(TOKEN_KEY)
  } catch {
    // ignore storage access errors
  }
}

migrateLegacyToken()

export const api = axios.create({
  baseURL: withAppBase('/api/v1'),
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export function setToken(token: string | null) {
  try {
    if (token) tokenStore().setItem(TOKEN_KEY, token)
    else tokenStore().removeItem(TOKEN_KEY)
    window.localStorage.removeItem(TOKEN_KEY)
  } catch {
    // ignore
  }
}

export function getToken(): string | null {
  try {
    return tokenStore().getItem(TOKEN_KEY)
  } catch {
    return null
  }
}
