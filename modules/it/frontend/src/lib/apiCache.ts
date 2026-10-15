import { api } from './api'

interface CacheEntry<T> {
  expiresAt: number
  data: T
}

const store = new Map<string, CacheEntry<unknown>>()
const inflight = new Map<string, Promise<unknown>>()

export function clearApiCache(prefix?: string): void {
  if (!prefix) {
    store.clear()
    inflight.clear()
    return
  }
  for (const key of store.keys()) {
    if (key.startsWith(prefix)) {
      store.delete(key)
    }
  }
  for (const key of inflight.keys()) {
    if (key.startsWith(prefix)) {
      inflight.delete(key)
    }
  }
}

/**
 * Short-lived GET dedupe for read-heavy endpoints (form lookups, leave types, etc.).
 * Same pattern as helpdesk/frontend/src/lib/apiCache.ts.
 */
export async function cachedGet<T>(
  key: string,
  url: string,
  ttlMs: number,
  params?: Record<string, unknown>,
): Promise<T> {
  const cacheKey = params ? `${key}?${JSON.stringify(params)}` : key
  const hit = store.get(cacheKey)
  if (hit && hit.expiresAt > Date.now()) {
    return hit.data as T
  }

  const pending = inflight.get(cacheKey)
  if (pending) {
    return pending as Promise<T>
  }

  const request = api
    .get<T>(url, { params })
    .then((response) => {
      store.set(cacheKey, { expiresAt: Date.now() + ttlMs, data: response.data })
      inflight.delete(cacheKey)
      return response.data
    })
    .catch((error) => {
      inflight.delete(cacheKey)
      throw error
    })

  inflight.set(cacheKey, request)
  return request
}
