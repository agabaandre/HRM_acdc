import { withAppBase } from '@/lib/basePath'

const RELOAD_KEY = 'devtrack.chunk_reload_at'

/** Stale Vite chunks after deploy (hashed filenames no longer on the server). */
export function isChunkLoadError(error: unknown): boolean {
  const msg =
    error instanceof Error
      ? `${error.name} ${error.message}`
      : typeof error === 'string'
        ? error
        : String(error ?? '')

  return /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Unable to preload CSS|Loading chunk [\w-]+ failed/i.test(
    msg,
  )
}

function recentlyReloaded(): boolean {
  try {
    const raw = sessionStorage.getItem(RELOAD_KEY)
    if (!raw) return false
    return Date.now() - Number(raw) < 15_000
  } catch {
    return false
  }
}

function markReloaded(): void {
  try {
    sessionStorage.setItem(RELOAD_KEY, String(Date.now()))
  } catch {
    // ignore
  }
}

/** Full page reload once to pick up the new index.html + asset hashes. */
export function recoverFromChunkLoadError(error: unknown): boolean {
  if (!isChunkLoadError(error) || recentlyReloaded()) return false
  markReloaded()
  window.location.reload()
  return true
}

/**
 * After auth succeeds, navigation can still fail on a stale lazy chunk.
 * Prefer a hard navigation to the app root (token already in sessionStorage).
 */
export function hardNavigateAppRoot(): void {
  markReloaded()
  window.location.assign(withAppBase('/') || '/')
}
