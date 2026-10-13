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

/** Core chart engine — enough for pie / column / bar / area. */
const HIGHCHARTS_CORE = 'https://code.highcharts.com/highcharts.js'

const HIGHCHARTS_EXPORTING = [
  'https://code.highcharts.com/modules/exporting.js',
  'https://code.highcharts.com/modules/export-data.js',
  'https://code.highcharts.com/modules/accessibility.js',
]

const HIGHCHARTS_EXTRA = [
  'https://code.highcharts.com/highcharts-more.js',
  'https://code.highcharts.com/modules/heatmap.js',
  'https://code.highcharts.com/modules/solid-gauge.js',
]

const MAP_MODULE = 'https://code.highcharts.com/modules/map.js'
const AFRICA_MAP_CDN = 'https://code.highcharts.com/mapdata/custom/africa.js'

let highchartsCoreReady: Promise<void> | null = null
let highchartsExportReady: Promise<void> | null = null
let highchartsExtraReady: Promise<void> | null = null
let highchartsMapReady: Promise<void> | null = null

function africaMapSrc(): string {
  const base = import.meta.env.BASE_URL || '/'
  const prefix = base.endsWith('/') ? base : `${base}/`
  return `${prefix}maps/africa.js`
}

function africaMapLoaded(): boolean {
  return Boolean(window.Highcharts?.mapChart && window.Highcharts.maps?.['custom/africa'])
}

async function loadCore(): Promise<void> {
  if (typeof window !== 'undefined' && window.Highcharts) {
    return
  }
  if (!highchartsCoreReady) {
    highchartsCoreReady = loadExternalScript(HIGHCHARTS_CORE).catch((err) => {
      highchartsCoreReady = null
      throw err
    })
  }
  await highchartsCoreReady
}

async function loadExporting(): Promise<void> {
  await loadCore()
  if (!highchartsExportReady) {
    // Exporting modules are independent of each other once core is present.
    highchartsExportReady = loadExternalScripts(HIGHCHARTS_EXPORTING, true).catch((err) => {
      highchartsExportReady = null
      throw err
    })
  }
  await highchartsExportReady
}

/** Lazily load Highcharts core + exporting (dashboard charts). */
export function ensureHighcharts(): Promise<void> {
  return loadExporting()
}

/** Heatmap / solidgauge extras — only when those chart types are used. */
export async function ensureHighchartsExtras(): Promise<void> {
  await loadCore()
  if (!highchartsExtraReady) {
    // more.js must load before heatmap/solid-gauge.
    highchartsExtraReady = loadExternalScripts(HIGHCHARTS_EXTRA, false).catch((err) => {
      highchartsExtraReady = null
      throw err
    })
  }
  await highchartsExtraReady
}

/** Prefetch on idle so the first dashboard chart paints faster. */
export function prefetchHighcharts(): void {
  const run = () => {
    void ensureHighcharts().catch(() => {
      /* ignore */
    })
  }
  if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
    ;(window as Window & { requestIdleCallback: (cb: () => void) => number }).requestIdleCallback(run)
  } else {
    window.setTimeout(run, 1200)
  }
}

/** Highcharts + map module + Africa GeoJSON (same map as the prioritisation dashboard). */
export async function ensureHighchartsMap(): Promise<void> {
  if (africaMapLoaded()) {
    return
  }
  if (!highchartsMapReady) {
    highchartsMapReady = (async () => {
      await ensureHighcharts()
      await ensureHighchartsExtras()
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
