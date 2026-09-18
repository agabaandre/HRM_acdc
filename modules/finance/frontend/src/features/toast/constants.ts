import type { ToastType } from './types'

export const TOAST_DEFAULT_DURATION = 5000

export const TOAST_DEFAULT_TITLES: Record<ToastType, string> = {
  success: 'Success',
  error: 'Error',
  info: 'Info',
  warning: 'Warning',
}

export const TOAST_TYPE_COLORS: Record<ToastType, string> = {
  success: '#47D764',
  error: '#ff355b',
  info: '#2F86EB',
  warning: '#FFC021',
}
