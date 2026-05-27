import axios from 'axios'

const TOKEN_KEY = 'helpdesk_api_token'

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

export const api = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const t = getStoredToken()
  if (t) {
    config.headers.Authorization = `Bearer ${t}`
  }
  return config
})
