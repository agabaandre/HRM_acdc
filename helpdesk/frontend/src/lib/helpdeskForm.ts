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

export type CheckboxValue = boolean | 'indeterminate'

/** UCheckbox can emit `indeterminate`; treat that as unchecked. */
export function isCheckboxChecked(value: CheckboxValue): boolean {
  return value === true
}

export type TicketPriority = 'low' | 'medium' | 'high' | 'critical'
export type PageSize = 10 | 20 | 50 | 100

export type SelectNumberItem = { label: string; value: number }
export type SelectStringItem = { label: string; value: string }

export const PRIORITY_ITEMS: { label: string; value: TicketPriority }[] = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Critical', value: 'critical' },
]

export const PER_PAGE_ITEMS: { label: string; value: PageSize }[] = [
  { label: '10 per page', value: 10 },
  { label: '20 per page', value: 20 },
  { label: '50 per page', value: 50 },
  { label: '100 per page', value: 100 },
]

const PAGE_SIZE_SET = new Set<number>(PER_PAGE_ITEMS.map((i) => i.value))

/** Coerce API pagination to a supported page size for USelect. */
export function normalizePageSize(n: number, fallback: PageSize = 20): PageSize {
  return PAGE_SIZE_SET.has(n) ? (n as PageSize) : fallback
}
