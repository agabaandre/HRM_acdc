import { clearApiCache, cachedGet } from './apiCache'

export interface CbpModuleLink {
  id?: string
  label: string
  href: string
  description?: string
  icon?: string
  is_active?: boolean
  opens_in_new_tab?: boolean
  module_key?: string | null
  sso_launch?: boolean
  launch_url?: string | null
}

export interface CbpNavPayload {
  home: CbpModuleLink
  modules: CbpModuleLink[]
}

type CbpModulesApiResponse =
  | CbpNavPayload
  | { data: CbpNavPayload; meta?: Record<string, unknown> }

function unwrapPayload(raw: CbpModulesApiResponse): CbpNavPayload {
  if (raw && typeof raw === 'object' && 'data' in raw && raw.data?.home && Array.isArray(raw.data.modules)) {
    return raw.data
  }
  if (raw && typeof raw === 'object' && 'home' in raw && Array.isArray((raw as CbpNavPayload).modules)) {
    return raw as CbpNavPayload
  }
  throw new Error('Invalid CBP modules payload')
}

export type FetchCbpModulesOptions = {
  /** Exclude a module_key (e.g. staff_portal in the top-bar Systems list). */
  exclude?: string
  /** Force active module highlight. */
  active?: string
  /** Current SPA path for home/active heuristics. */
  path?: string
  /** Bypass client cache (e.g. after login). */
  fresh?: boolean
}

/** Shared by top header + Home — one network call per session window (Service Desk pattern). */
export async function fetchCbpModules(options: FetchCbpModulesOptions = {}): Promise<CbpNavPayload> {
  const params: Record<string, string> = {}
  if (options.exclude) params.exclude = options.exclude
  if (options.active) params.active = options.active
  if (options.path) params.path = options.path

  const cacheKey = `cbp:modules:${options.exclude || ''}:${options.active || ''}:${options.path || ''}`
  if (options.fresh) {
    clearApiCache('cbp:modules')
  }

  const raw = await cachedGet<CbpModulesApiResponse>(
    cacheKey,
    '/api/v1/cbp-modules',
    10 * 60_000,
    Object.keys(params).length ? params : undefined,
  )
  return unwrapPayload(raw)
}
