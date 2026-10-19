/** Secure CBP module launch via Staff Portal API (JWT never in the URL). */

import { api } from './api'

function staffMountBaseUrl(): string {
  const path = String(import.meta.env.BASE_URL || '/staff/').replace(/\/$/, '')
  if (typeof window === 'undefined') {
    return path || '/staff'
  }
  return `${window.location.origin}${path.startsWith('/') ? path : `/${path}`}`
}

function postHiddenForm(action: string, fields: Record<string, string>, openInNewTab: boolean): void {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = action
  form.style.display = 'none'
  if (openInNewTab) {
    form.target = '_blank'
  }
  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
  window.setTimeout(() => form.remove(), 1000)
}

/** Relative path inside the target module. Rejects absolute and parent paths. */
export function sanitizeModuleNext(path: string): string {
  const next = path.trim().replace(/^\/+/, '')
  if (!next || next.includes('..') || next.includes('://') || next.includes('\\')) {
    return ''
  }
  if (!/^[A-Za-z0-9_./?=&%-]+$/.test(next)) {
    return ''
  }
  return next
}

/** Launch a CBP module (APM / Finance / Helpdesk) via authenticated API hand-off. */
export async function launchCbpModule(moduleKey: string, openInNewTab = false, nextPath = ''): Promise<void> {
  const key = moduleKey.trim()
  if (!key) {
    return
  }

  try {
    const { data } = await api.post<{
      accept_url?: string
      staff_sso_jwt?: string
      redirect_url?: string
      label?: string
    }>('/api/v1/cbp-modules/launch', { module_key: key })

    const next = sanitizeModuleNext(nextPath)
    if (data.redirect_url) {
      const url = next ? `${data.redirect_url.replace(/\/$/, '')}/${next}` : data.redirect_url
      if (openInNewTab) {
        window.open(url, '_blank', 'noopener,noreferrer')
      } else {
        window.location.assign(url)
      }
      return
    }

    const acceptUrl = (data.accept_url || '').trim()
    const jwt = (data.staff_sso_jwt || '').trim()
    if (!acceptUrl || !jwt) {
      window.alert('Could not obtain a security token. Open CBP Home and try again.')
      return
    }

    const fields: Record<string, string> = { staff_sso_jwt: jwt }
    if (next) {
      fields.next = next
    }
    postHiddenForm(acceptUrl, fields, openInNewTab)
  } catch (err: unknown) {
    const axiosErr = err as { response?: { status?: number; data?: { message?: string } }; message?: string }
    const apiMessage = axiosErr.response?.data?.message?.trim()
    const status = axiosErr.response?.status
    // Do not fall back to cookie launch_module on auth/permission failures —
    // SPA auth is Bearer-only (withCredentials: false), so the legacy POST usually 302/500s.
    if (status === 401 || status === 403 || status === 422) {
      window.alert(apiMessage || 'You do not have access to open this module.')
      return
    }
    if (apiMessage) {
      window.alert(apiMessage)
      return
    }
    // Network / unexpected: one legacy fallback attempt for older hosts.
    const base = staffMountBaseUrl()
    try {
      const res = await fetch(`${base}/auth/refreshCSRF`, { credentials: 'same-origin' })
      const csrfPayload = (await res.json()) as { csrf_token?: string }
      const csrf = csrfPayload.csrf_token?.trim()
      if (!csrf) {
        window.alert('Could not open module. Sign in again and retry.')
        return
      }
      postHiddenForm(
        `${base}/home/launch_module`,
        { module_key: key, africacdc_csrf_token: csrf },
        openInNewTab,
      )
    } catch {
      window.alert('Could not open module. Sign in again and retry.')
    }
  }
}

export function moduleLaunchKey(mod: {
  sso_launch?: boolean
  module_key?: string | null
  id?: string
}): string {
  if (mod.module_key?.trim()) {
    return mod.module_key.trim()
  }
  return mod.id?.trim() ?? ''
}
