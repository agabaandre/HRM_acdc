/** Run work after first paint / when the browser is idle. */
export function scheduleIdle(work: () => void, timeoutMs = 1200): void {
  if (typeof window.requestIdleCallback === 'function') {
    window.requestIdleCallback(() => work(), { timeout: timeoutMs })
    return
  }
  window.setTimeout(work, 0)
}
