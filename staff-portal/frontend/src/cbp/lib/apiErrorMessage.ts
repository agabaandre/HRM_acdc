import axios from 'axios'

function isDevHelpdesk(): boolean {
  return import.meta.env.DEV
}

export function apiErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const raw = error.response?.data
    if (raw && typeof raw === 'object' && 'message' in raw) {
      const m = (raw as { message: unknown }).message
      if (typeof m === 'string' && m.trim() !== '') {
        return m
      }
    }
    const status = error.response?.status
    if (status === 403) {
      return 'You do not have permission for this action (admin role required on the helpdesk profile).'
    }
    if (status === 401) {
      return 'Session expired or invalid. Sign in again from the Staff portal.'
    }
    if (status === 422 && raw && typeof raw === 'object' && 'message' in raw) {
      const m = (raw as { message: unknown }).message
      if (typeof m === 'string') {
        return m
      }
    }
    if (error.code === 'ECONNABORTED') {
      return 'The helpdesk API is taking longer than usual. Please wait a moment and try again.'
    }
    if (error.code === 'ERR_NETWORK' || (!error.response && error.request)) {
      if (isDevHelpdesk()) {
        return 'Could not reach the helpdesk API. Is the backend running (e.g. :8000) and Vite proxying /api?'
      }
      return 'Could not reach the helpdesk API. The server may be waking up — try again in a few seconds.'
    }
    if (typeof error.message === 'string' && error.message !== 'Request failed with status code 403') {
      return error.message
    }
    return fallback
  }
  if (error instanceof Error && error.message) {
    return error.message
  }
  return fallback
}
