/** Public site origin for this build, e.g. http://localhost:8888/ACDC/2026/cbp */
export function staffWebRoot(): string {
  const path = String(import.meta.env.BASE_URL || '/staff/').replace(/\/$/, '')
  if (typeof window === 'undefined') {
    return path || '/staff'
  }
  return `${window.location.origin}${path.startsWith('/') ? path : `/${path}`}`
}
