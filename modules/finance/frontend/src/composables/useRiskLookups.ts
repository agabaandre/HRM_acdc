import { ref } from 'vue'
import { fetchLookups, type RiskLookups } from '@/lib/riskApi'

const CACHE_KEY = 'rr_lookups_v2'
const CACHE_TTL_MS = 5 * 60 * 1000

const cached = ref<RiskLookups | null>(null)
let inflight: Promise<RiskLookups> | null = null

function readSession(): RiskLookups | null {
  try {
    const raw = sessionStorage.getItem(CACHE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { at: number; data: RiskLookups }
    if (!parsed?.data || Date.now() - parsed.at > CACHE_TTL_MS) return null
    return parsed.data
  } catch {
    return null
  }
}

function writeSession(data: RiskLookups): void {
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    /* ignore quota */
  }
}

export function scoreOptionLabel(row: { label?: string; name?: string; score: number }): string {
  const name = (row.label || row.name || '').trim()
  return name ? `${row.score} — ${name}` : String(row.score)
}

export function useRiskLookups() {
  async function loadLookups(force = false): Promise<RiskLookups> {
    if (!force && cached.value) return cached.value
    if (!force) {
      const fromSession = readSession()
      if (fromSession) {
        cached.value = fromSession
        return fromSession
      }
    }
    if (!force && inflight) return inflight

    inflight = fetchLookups()
      .then((data) => {
        cached.value = data
        writeSession(data)
        return data
      })
      .finally(() => {
        inflight = null
      })

    return inflight
  }

  return { lookups: cached, loadLookups, scoreOptionLabel }
}
