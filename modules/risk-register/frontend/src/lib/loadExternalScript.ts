/** Load a third-party script once (Highcharts, Mermaid, etc.). */

const inflight = new Map<string, Promise<void>>()

export function loadExternalScript(src: string): Promise<void> {
  const existing = inflight.get(src)
  if (existing) return existing

  const promise = new Promise<void>((resolve, reject) => {
    const found = document.querySelector<HTMLScriptElement>(`script[src="${src}"]`)
    if (found) {
      if (found.dataset.loaded === '1') {
        resolve()
        return
      }
      found.addEventListener('load', () => resolve(), { once: true })
      found.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.dataset.loaded = '0'
    script.onload = () => {
      script.dataset.loaded = '1'
      resolve()
    }
    script.onerror = () => reject(new Error(`Failed to load ${src}`))
    document.head.appendChild(script)
  })

  inflight.set(src, promise)
  return promise
}

export async function loadExternalScripts(sources: string[]): Promise<void> {
  for (const src of sources) {
    await loadExternalScript(src)
  }
}
