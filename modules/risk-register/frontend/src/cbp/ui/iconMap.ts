const LUCIDE_TO_MDI: Record<string, string> = {
  'i-lucide-search': 'mdi-magnify',
  'i-lucide-trash-2': 'mdi-delete',
  'i-lucide-send': 'mdi-send',
  'i-lucide-refresh-cw': 'mdi-refresh',
  'i-lucide-plus': 'mdi-plus',
  'i-lucide-file-spreadsheet': 'mdi-file-excel',
  'i-lucide-file-text': 'mdi-file-pdf-box',
  'i-lucide-download': 'mdi-download',
}

export function mapLucideIcon(icon?: string): string | undefined {
  if (!icon) return undefined
  if (icon.startsWith('mdi-')) return icon
  if (icon.startsWith('bx ')) return undefined
  return LUCIDE_TO_MDI[icon] ?? undefined
}
