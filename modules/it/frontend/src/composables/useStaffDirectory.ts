import { ref } from 'vue'
import { api } from '@/lib/api'

export type StaffDirectoryOpt = {
  label: string
  value: number
  subtitle?: string | null
}

const CACHE_KEY = 'rr_staff_directory_v1'
const CACHE_TTL_MS = 5 * 60 * 1000

const cached = ref<StaffDirectoryOpt[] | null>(null)
let inflight: Promise<StaffDirectoryOpt[]> | null = null

function readSession(): StaffDirectoryOpt[] | null {
  try {
    const raw = sessionStorage.getItem(CACHE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { at: number; data: StaffDirectoryOpt[] }
    if (!parsed?.data || Date.now() - parsed.at > CACHE_TTL_MS) return null
    return parsed.data
  } catch {
    return null
  }
}

function writeSession(data: StaffDirectoryOpt[]): void {
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    /* ignore */
  }
}

export function useStaffDirectory() {
  async function loadStaffDirectory(force = false): Promise<StaffDirectoryOpt[]> {
    if (!force && cached.value) return cached.value
    if (!force) {
      const fromSession = readSession()
      if (fromSession) {
        cached.value = fromSession
        return fromSession
      }
    }
    if (!force && inflight) return inflight

    inflight = api
      .get<{ data: Array<{ staff_id: number; name: string; email: string | null }> }>('/api/v1/org/staff')
      .then(({ data }) => {
        const opts = (data.data || []).map((s) => ({
          label: s.name,
          value: s.staff_id,
          subtitle: s.email,
        }))
        cached.value = opts
        writeSession(opts)
        return opts
      })
      .finally(() => {
        inflight = null
      })

    return inflight
  }

  return { staffOptions: cached, loadStaffDirectory }
}
