import { staffPortalBaseUrl } from './sso'

const CSRF_TOKEN_NAME = 'africacdc_csrf_token'

async function fetchStaffCsrfToken(base: string): Promise<string | null> {
  try {
    const res = await fetch(`${base}/auth/refreshCSRF`, { credentials: 'same-origin' })
    const data = (await res.json()) as { csrf_token?: string }
    return data.csrf_token?.trim() ? data.csrf_token.trim() : null
  } catch {
    return null
  }
}

/** POST to Staff portal home/launch_module (JWT never in URL). */
export async function launchCbpModule(moduleKey: string, openInNewTab = false): Promise<void> {
  const key = moduleKey.trim()
  if (!key) {
    return
  }
  const base = staffPortalBaseUrl()
  const csrf = await fetchStaffCsrfToken(base)
  if (!csrf) {
    window.alert('Could not obtain a security token. Open CBP Home and try again.')
    return
  }
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = `${base}/home/launch_module`
  form.style.display = 'none'
  if (openInNewTab) {
    form.target = '_blank'
  }
  const mk = document.createElement('input')
  mk.type = 'hidden'
  mk.name = 'module_key'
  mk.value = key
  form.appendChild(mk)
  const csrfInput = document.createElement('input')
  csrfInput.type = 'hidden'
  csrfInput.name = CSRF_TOKEN_NAME
  csrfInput.value = csrf
  form.appendChild(csrfInput)
  document.body.appendChild(form)
  form.submit()
  window.setTimeout(() => form.remove(), 1000)
}

export function moduleLaunchKey(mod: { sso_launch?: boolean; module_key?: string; id?: string }): string {
  if (mod.module_key?.trim()) {
    return mod.module_key.trim()
  }
  return mod.id?.trim() ?? ''
}
