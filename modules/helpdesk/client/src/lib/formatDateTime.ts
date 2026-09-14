const DEFAULT_OPTIONS: Intl.DateTimeFormatOptions = {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
}

/**
 * Format API timestamps (ISO 8601) for display in the UI.
 */
export function formatDateTime(
  value: string | null | undefined,
  options: Intl.DateTimeFormatOptions = DEFAULT_OPTIONS,
): string {
  if (!value || value.trim() === '') {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(undefined, options).format(date)
}

/**
 * Longer label for tooltips / screen readers.
 */
export function formatDateTimeLong(value: string | null | undefined): string {
  return formatDateTime(value, {
    weekday: 'short',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
  })
}
