import { ref } from 'vue'

export const routePreloaderVisible = ref(false)

/** Brief floor so the spinner does not flash; keep low so nav feels instant. */
export const PRELOADER_MIN_MS = 200

/** Hard safety: never leave the content frame clipped if a finish is missed. */
const PRELOADER_MAX_MS = 8000

const bootShownAt = Date.now()
let routeShownAt = 0
let routeHideTimer: number | null = null
let routeMaxTimer: number | null = null
let generation = 0

function clearTimers(): void {
  if (routeHideTimer) {
    clearTimeout(routeHideTimer)
    routeHideTimer = null
  }
  if (routeMaxTimer) {
    clearTimeout(routeMaxTimer)
    routeMaxTimer = null
  }
}

function hidePreloader(): void {
  clearTimers()
  routePreloaderVisible.value = false
}

/**
 * Show the in-app content preloader. Call only from beforeResolve (after guards
 * succeed) so aborted/redirected navigations never leave it stuck.
 */
export function startRoutePreloader(shownAt?: number): void {
  clearTimers()
  generation += 1
  const gen = generation
  routeShownAt = shownAt ?? Date.now()
  routePreloaderVisible.value = true
  routeMaxTimer = window.setTimeout(() => {
    if (gen === generation) {
      hidePreloader()
    }
  }, PRELOADER_MAX_MS)
}

/** Hide after the minimum display time for the current navigation generation. */
export function finishRoutePreloader(): void {
  const gen = generation
  const shownAt = routeShownAt || Date.now()
  if (routeHideTimer) {
    clearTimeout(routeHideTimer)
    routeHideTimer = null
  }
  const wait = Math.max(0, PRELOADER_MIN_MS - (Date.now() - shownAt))
  routeHideTimer = window.setTimeout(() => {
    if (gen !== generation) {
      return
    }
    hidePreloader()
  }, wait)
}

export function dismissBootPreloader(_options?: { immediate?: boolean }): void {
  const el = document.getElementById('helpdesk-boot-loader')
  if (el) {
    el.classList.add('hd-boot-loader--out')
    const remove = () => el.remove()
    el.addEventListener('transitionend', remove, { once: true })
    window.setTimeout(remove, 450)
  }
  document.body.classList.remove('hd-app-loading')
}

/** First paint after boot: show content-frame loader for any remaining minimum time. */
export function showInitialContentPreloader(): void {
  startRoutePreloader(bootShownAt)
  finishRoutePreloader()
}
