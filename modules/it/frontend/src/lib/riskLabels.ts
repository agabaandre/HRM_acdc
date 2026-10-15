/** Display helpers for Risk Register labels. */

/** Strip leading "1. " style prefixes from enterprise theme names (ids remain the link). */
export function themeDisplayName(name: string | null | undefined): string {
  if (!name) return '—'
  const cleaned = name.replace(/^\s*\d+\.\s*/, '').trim()
  return cleaned || name
}
