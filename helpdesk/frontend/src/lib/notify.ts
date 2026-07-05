/** Lobibox toasts — same defaults as APM `show_notification()` in layouts/partials/footer.blade.php */

export type NotifyType = 'success' | 'error' | 'warning' | 'info' | 'default'

type LobiboxNotify = (
  type: string,
  options: {
    msg: string
    pauseDelayOnHover?: boolean
    continueDelayOnInactiveTab?: boolean
    position?: string
    icon?: string
    sound?: boolean
  },
) => void

declare global {
  interface Window {
    Lobibox?: { notify: LobiboxNotify }
  }
}

function iconFor(type: NotifyType): string {
  switch (type) {
    case 'success':
      return 'bx bx-check-circle'
    case 'error':
      return 'bx bx-error-circle'
    case 'warning':
      return 'bx bx-error'
    default:
      return 'bx bx-info-circle'
  }
}

function showFallbackToast(message: string, type: NotifyType): void {
  const el = document.createElement('div')
  el.className = `hd-fallback-toast hd-fallback-toast--${type}`
  el.setAttribute('role', 'alert')
  el.textContent = message
  document.body.appendChild(el)
  requestAnimationFrame(() => {
    el.classList.add('hd-fallback-toast--visible')
  })
  window.setTimeout(() => {
    el.classList.remove('hd-fallback-toast--visible')
    window.setTimeout(() => el.remove(), 300)
  }, 6000)
}

export function notify(message: string, type: NotifyType = 'info'): void {
  const text = message.trim()
  if (!text) {
    return
  }
  const lobibox = window.Lobibox
  if (!lobibox?.notify) {
    console.warn('[helpdesk] Lobibox not loaded:', text)
    showFallbackToast(text, type)
    return
  }
  lobibox.notify(type === 'default' ? 'default' : type, {
    pauseDelayOnHover: true,
    continueDelayOnInactiveTab: false,
    position: 'center top',
    icon: iconFor(type),
    sound: false,
    msg: text,
  })
}

export function notifySuccess(message: string): void {
  notify(message, 'success')
}

export function notifyError(message: string): void {
  notify(message, 'error')
}

export function notifyWarning(message: string): void {
  notify(message, 'warning')
}

export function notifyInfo(message: string): void {
  notify(message, 'info')
}

export async function loadLobiboxAssets(): Promise<void> {
  if (window.Lobibox?.notify) {
    return
  }

  const base = import.meta.env.BASE_URL.replace(/\/$/, '')

  if (!document.querySelector('link[data-helpdesk-lobibox]')) {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = `${base}/vendor/lobibox/lobibox.min.css`
    link.setAttribute('data-helpdesk-lobibox', '1')
    document.head.appendChild(link)
  }

  await loadScript('https://code.jquery.com/jquery-3.6.0.min.js')
  await loadScript(`${base}/vendor/lobibox/lobibox.min.js`)
}

function loadScript(src: string): Promise<void> {
  if (document.querySelector(`script[src="${src}"]`)) {
    return Promise.resolve()
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error(`Failed to load ${src}`))
    document.head.appendChild(script)
  })
}
