/** Secure CBP module launch via Staff Portal API (JWT never in the URL). */

import { api } from './api'

function staffMountBaseUrl(): string {
  if (typeof window === 'undefined') {
    return '/staff'
  }
  const path = window.location.pathname || ''
  const idx = path.indexOf('/staff/')
  if (idx >= 0) {
    return `${window.location.origin}${path.substring(0, idx + 6)}`
  }
  return `${window.location.origin}/staff`
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

/** Launch a CBP module (APM / Finance / Helpdesk) via authenticated API hand-off. */
export async function launchCbpModule(moduleKey: string, openInNewTab = false): Promise<void> {
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

    if (data.redirect_url) {
      if (openInNewTab) {
        window.open(data.redirect_url, '_blank', 'noopener,noreferrer')
      } else {
        window.location.assign(data.redirect_url)
      }
      return
    }

    const acceptUrl = (data.accept_url || '').trim()
    const jwt = (data.staff_sso_jwt || '').trim()
    if (!acceptUrl || !jwt) {
      window.alert('Could not obtain a security token. Open CBP Home and try again.')
      return
    }

    postHiddenForm(acceptUrl, { staff_sso_jwt: jwt }, openInNewTab)
  } catch {
    // Fallback for environments that still expose the legacy CI-shaped routes.
    const base = staffMountBaseUrl()
    try {
      const res = await fetch(`${base}/auth/refreshCSRF`, { credentials: 'same-origin' })
      const csrfPayload = (await res.json()) as { csrf_token?: string }
      const csrf = csrfPayload.csrf_token?.trim()
      if (!csrf) {
        window.alert('Could not obtain a security token. Open CBP Home and try again.')
        return
      }
      postHiddenForm(
        `${base}/home/launch_module`,
        { module_key: key, africacdc_csrf_token: csrf },
        openInNewTab,
      )
    } catch {
      window.alert('Could not obtain a security token. Open CBP Home and try again.')
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
