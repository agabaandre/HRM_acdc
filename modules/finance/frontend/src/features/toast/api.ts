import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useToastStore } from './store'
import type { ToastInput, ToastType } from './types'

function show(input: ToastInput) {
  return useToastStore().show(input)
}

function showTyped(type: ToastType, message: string, title?: string, duration?: number) {
  return show({ type, message, title, duration })
}

/** Imperative toast API — same types as MoH PMS out-of-station notifications. */
export const toast = {
  show,
  success: (message: string, title = 'Success', duration?: number) =>
    showTyped('success', message, title, duration),
  error: (message: string, title = 'Error', duration?: number) =>
    showTyped('error', message, title, duration),
  info: (message: string, title = 'Info', duration?: number) =>
    showTyped('info', message, title, duration),
  warning: (message: string, title = 'Warning', duration?: number) =>
    showTyped('warning', message, title, duration),
  dismiss: (id: string) => useToastStore().dismiss(id),
}

export function notifyApiError(error: unknown, fallback = 'Request failed', title = 'Error') {
  toast.error(apiErrorMessage(error, fallback), title)
}
