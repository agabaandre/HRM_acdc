/** Public URL prefix when the app is served from a MAMP/htdocs subfolder. */
export function appBasePath(): string {
  const meta = document.querySelector('meta[name="app-base"]')?.getAttribute('content')
  if (meta && meta !== '/') {
    return meta.replace(/\/$/, '')
  }

  if (typeof window !== 'undefined') {
    const match = window.location.pathname.match(/^(.*?\/(?:AfricaCDCDevTrack|devtrack))(?:\/|$)/)
    if (match) return match[1]
  }

  return ''
}

export function withAppBase(path: string): string {
  const base = appBasePath()
  const normalized = path.startsWith('/') ? path : `/${path}`
  return `${base}${normalized}`
}
