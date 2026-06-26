import { ref } from 'vue'

export const routePreloaderVisible = ref(false)

const ROUTE_MIN_MS = 180
let routePending = 0
let routeShownAt = 0
let routeHideTimer: ReturnType<typeof setTimeout> | null = null

export function dismissBootPreloader(): void {
  const el = document.getElementById('helpdesk-boot-loader')
  if (!el) {
    document.body.classList.remove('hd-app-loading')
    return
  }

  el.classList.add('hd-boot-loader--out')
  const remove = () => {
    el.remove()
    document.body.classList.remove('hd-app-loading')
  }
  el.addEventListener('transitionend', remove, { once: true })
  window.setTimeout(remove, 450)
}

export function startRoutePreloader(): void {
  routePending += 1
  if (routeHideTimer) {
    clearTimeout(routeHideTimer)
    routeHideTimer = null
  }
  if (!routePreloaderVisible.value) {
    routeShownAt = Date.now()
    routePreloaderVisible.value = true
    document.body.classList.add('hd-app-loading')
  }
}

export function finishRoutePreloader(): void {
  routePending = Math.max(0, routePending - 1)
  if (routePending > 0) {
    return
  }

  const elapsed = Date.now() - routeShownAt
  const wait = Math.max(0, ROUTE_MIN_MS - elapsed)

  routeHideTimer = setTimeout(() => {
    routePreloaderVisible.value = false
    document.body.classList.remove('hd-app-loading')
    routeHideTimer = null
  }, wait)
}
