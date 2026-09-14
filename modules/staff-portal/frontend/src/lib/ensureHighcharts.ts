import { loadExternalScript, loadExternalScripts } from '@/lib/loadExternalScript'

declare global {
  interface Window {
    Highcharts?: {
      chart: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
      mapChart?: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
      maps?: Record<string, unknown>
      setOptions?: (opts: Record<string, unknown>) => void
    }
  }
}

const HIGHCHARTS_SCRIPTS = [
  'https://code.highcharts.com/highcharts.js',
  'https://code.highcharts.com/highcharts-more.js',
  'https://code.highcharts.com/modules/solid-gauge.js',
  'https://code.highcharts.com/modules/exporting.js',
  'https://code.highcharts.com/modules/export-data.js',
  'https://code.highcharts.com/modules/accessibility.js',
]

const MAP_MODULE = 'https://code.highcharts.com/modules/map.js'
const AFRICA_MAP_CDN = 'https://code.highcharts.com/mapdata/custom/africa.js'

let highchartsReady: Promise<void> | null = null
let highchartsMapReady: Promise<void> | null = null

function africaMapSrc(): string {
  const base = import.meta.env.BASE_URL || '/'
  const prefix = base.endsWith('/') ? base : `${base}/`
  return `${prefix}maps/africa.js`
}

function africaMapLoaded(): boolean {
  return Boolean(window.Highcharts?.mapChart && window.Highcharts.maps?.['custom/africa'])
}

/** Lazily load Highcharts (dashboard / performance only — not on login). */
export function ensureHighcharts(): Promise<void> {
  if (typeof window !== 'undefined' && window.Highcharts) {
    return Promise.resolve()
  }
  if (!highchartsReady) {
    highchartsReady = loadExternalScripts(HIGHCHARTS_SCRIPTS)
  }
  return highchartsReady
}

/** Highcharts + map module + Africa GeoJSON (same map as the prioritisation dashboard). */
export async function ensureHighchartsMap(): Promise<void> {
  if (africaMapLoaded()) {
    return
  }
  if (!highchartsMapReady) {
    highchartsMapReady = (async () => {
      await ensureHighcharts()
      await loadExternalScript(MAP_MODULE)
      if (!window.Highcharts?.maps?.['custom/africa']) {
        try {
          await loadExternalScript(africaMapSrc())
        } catch {
          /* local map may 404 before publish-spa */
        }
      }
      if (!window.Highcharts?.maps?.['custom/africa']) {
        await loadExternalScript(AFRICA_MAP_CDN)
      }
    })().catch((err) => {
      highchartsMapReady = null
      throw err
    })
  }
  await highchartsMapReady
  if (!africaMapLoaded()) {
    throw new Error('Africa map failed to load')
  }
}
