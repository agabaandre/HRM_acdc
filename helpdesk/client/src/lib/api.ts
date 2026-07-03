import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { redirectToStaffPortalHome } from './sso'

const TOKEN_KEY = 'helpdesk_api_token'

/** Allow slow cold starts / gateway wake-up before surfacing an error. */
const API_TIMEOUT_MS = 60_000
const MAX_TRANSIENT_RETRIES = 2

type RetryableConfig = InternalAxiosRequestConfig & { __retryCount?: number }

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setStoredToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

/**
 * Resolve the Helpdesk API base URL.
 *
 * - In `npm run dev`, leaving this blank routes `/api/*` through Vite's proxy
 *   (see vite.config.ts), which forwards to the Apache-served Laravel backend
 *   at `http://localhost/staff/helpdesk/backend` by default. No
 *   `php artisan serve` required.
 * - For production builds (`npm run build`), set
 *   `VITE_HELPDESK_API_BASE_URL=/staff/helpdesk/backend` in
 *   `helpdesk/frontend/.env.production` so the SPA targets the
 *   Apache-served Laravel API on the same host.
 */
function resolveApiBaseUrl(): string {
  const fromEnv = import.meta.env.VITE_HELPDESK_API_BASE_URL as string | undefined
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim().replace(/\/$/, '')
  }
  return ''
}

/** Prefix API-relative avatar paths so <img src> hits the Laravel backend in production. */
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
  if (!url) {
    return false
  }
  return url.includes('/auth/')
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
})

api.interceptors.request.use((config) => {
  const t = getStoredToken()
  if (t) {
    config.headers.Authorization = `Bearer ${t}`
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
      redirectToStaffPortalHome()
      return Promise.reject(error)
    }

    if (config && isRetryableError(error)) {
      config.__retryCount = (config.__retryCount ?? 0) + 1
      if (config.__retryCount <= MAX_TRANSIENT_RETRIES) {
        await sleep(800 * config.__retryCount)
        return api.request(config)
      }
    }

    return Promise.reject(error)
  },
)
