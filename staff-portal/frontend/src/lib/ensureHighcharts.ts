import { loadExternalScripts } from '@/lib/loadExternalScript'

declare global {
  interface Window {
    Highcharts?: {
      chart: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
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

let highchartsReady: Promise<void> | null = null

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
