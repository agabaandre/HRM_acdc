import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { loginUrl } from './auth'

const TOKEN_KEY = 'staff_portal_api_token'
const API_TIMEOUT_MS = 30_000
const MAX_TRANSIENT_RETRIES = 1

type RetryableConfig = InternalAxiosRequestConfig & { __retryCount?: number }

export function getStoredToken(): string | null {
  return sessionStorage.getItem(TOKEN_KEY) ?? localStorage.getItem(TOKEN_KEY)
}

export function setStoredToken(token: string | null): void {
  if (token) {
    sessionStorage.setItem(TOKEN_KEY, token)
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    sessionStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(TOKEN_KEY)
  }
}

function resolveApiBaseUrl(): string {
  const fromEnv = import.meta.env.VITE_STAFF_PORTAL_API_BASE_URL as string | undefined
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim().replace(/\/$/, '')
  }
  return ''
}

export function resolveAvatarUrl(url: string | null | undefined): string | null {
  if (!url || url.trim() === '') {
    return null
  }
  const trimmed = url.trim()
  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed
  }
  const base = resolveApiBaseUrl()
  if (base && trimmed.startsWith('/')) {
    return `${base}${trimmed}`
  }
  return trimmed
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms)
  })
}

function isAuthEndpoint(url: string | undefined): boolean {
  return !!url && url.includes('/auth/')
}

function isRetryableError(error: AxiosError): boolean {
  const status = error.response?.status
  if (status === 502 || status === 503 || status === 504) {
    return true
  }
  if (error.code === 'ECONNABORTED') {
    return true
  }
  if (!error.response && error.request) {
    return true
  }
  return false
}

export const api = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: { Accept: 'application/json' },
  timeout: API_TIMEOUT_MS,
  // Bearer tokens are the SPA auth source. Sending cookies into Sanctum's
  // stateful pipeline can fight the token and bounce the user back to login.
  withCredentials: false,
})

api.interceptors.request.use((config) => {
  const t = getStoredToken()
  if (t) {
    config.headers.Authorization = `Bearer ${t}`
  }
  // Never force multipart Content-Type — the browser must set the boundary.
  // A header without a boundary makes PHP skip the body and Laravel 419s CSRF.
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
    const headers = config.headers as { delete?: (name: string) => void } & Record<string, unknown>
    if (typeof headers.delete === 'function') {
      headers.delete('Content-Type')
    } else {
      delete headers['Content-Type']
      delete headers['content-type']
    }
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as RetryableConfig | undefined
    const status = error.response?.status

    if (status === 401 && config && !isAuthEndpoint(config.url)) {
      setStoredToken(null)
      window.location.href = loginUrl()
      return Promise.reject(error)
    }

    if (config && isRetryableError(error)) {
      config.__retryCount = (config.__retryCount ?? 0) + 1
      if (config.__retryCount <= MAX_TRANSIENT_RETRIES) {
        await sleep(200 * config.__retryCount)
        return api.request(config)
      }
    }

    return Promise.reject(error)
  },
)
