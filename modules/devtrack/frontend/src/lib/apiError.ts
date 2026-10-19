import axios from 'axios'

/** Human-readable message from an Axios / Laravel validation error. */
export function apiErrorMessage(err: unknown, fallback = 'Request failed'): string {
  if (!axios.isAxiosError(err)) {
    return err instanceof Error ? err.message : fallback
  }
  const data = err.response?.data as
    | { message?: string; error?: string; errors?: Record<string, string[] | string> }
    | undefined
  if (!data) {
    return err.message || fallback
  }
  if (data.errors && typeof data.errors === 'object') {
    const parts = Object.values(data.errors).flatMap((v) => (Array.isArray(v) ? v : [v]))
    if (parts.length) return parts.join(' ')
  }
  return data.message || data.error || err.message || fallback
}
