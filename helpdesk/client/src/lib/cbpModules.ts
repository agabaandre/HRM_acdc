import { api } from './api'
import { fallbackCbpNavPayload, type CbpNavPayload } from './cbpSystems'

export type { CbpNavHome, CbpNavModule, CbpNavPayload } from './cbpSystems'

export async function fetchCbpModules(): Promise<CbpNavPayload> {
  try {
    const { data } = await api.get<{ data: CbpNavPayload; meta?: { degraded?: boolean } }>(
      '/api/v1/cbp-modules',
    )
    if (data.data?.home && Array.isArray(data.data.modules)) {
      return data.data
    }
  } catch {
    /* use client fallback below */
  }
  return fallbackCbpNavPayload()
}
