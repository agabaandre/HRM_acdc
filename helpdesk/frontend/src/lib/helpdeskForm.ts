import type { FormError } from '@nuxt/ui'

export type { FormError }

export function fieldError(
  name: string,
  value: unknown,
  message = 'This field is required',
): FormError | null {
  if (value === undefined || value === null) {
    return { name, message }
  }
  if (typeof value === 'string' && value.trim() === '') {
    return { name, message }
  }
  if (typeof value === 'number' && value < 1) {
    return { name, message }
  }
  return null
}

export function minLengthError(
  name: string,
  value: string,
  min: number,
  message?: string,
): FormError | null {
  if (value.trim().length < min) {
    return { name, message: message ?? `Must be at least ${min} characters` }
  }
  return null
}

export const PRIORITY_ITEMS = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Critical', value: 'critical' },
] as const

export const PER_PAGE_ITEMS = [
  { label: '10 per page', value: 10 },
  { label: '20 per page', value: 20 },
  { label: '50 per page', value: 50 },
  { label: '100 per page', value: 100 },
] as const
