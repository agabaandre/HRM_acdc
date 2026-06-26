import { ref } from 'vue'

export const routePreloaderVisible = ref(false)

/** Minimum time the preloader stays visible, even on fast loads. */
export const PRELOADER_MIN_MS = 3000

const bootShownAt = Date.now()
let routePending = 0
let routeShownAt = 0
let routeHideTimer: number | null = null

function scheduleAfterMinDisplay(shownAt: number, fn: () => void): void {
  const wait = Math.max(0, PRELOADER_MIN_MS - (Date.now() - shownAt))
  routeHideTimer = window.setTimeout(fn, wait)
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

export function startRoutePreloader(shownAt?: number): void {
  routePending += 1
  if (routeHideTimer) {
    clearTimeout(routeHideTimer)
    routeHideTimer = null
  }
  if (!routePreloaderVisible.value) {
    routeShownAt = shownAt ?? Date.now()
    routePreloaderVisible.value = true
  }
}

export function finishRoutePreloader(): void {
  routePending = Math.max(0, routePending - 1)
  if (routePending > 0) {
    return
  }

  scheduleAfterMinDisplay(routeShownAt, () => {
    routePreloaderVisible.value = false
    routeHideTimer = null
  })
}

/** First paint after boot: show content-frame loader for any remaining minimum time. */
export function showInitialContentPreloader(): void {
  startRoutePreloader(bootShownAt)
  finishRoutePreloader()
}
