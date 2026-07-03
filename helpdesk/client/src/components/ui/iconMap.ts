const LUCIDE_TO_MDI: Record<string, string> = {
  'i-lucide-search': 'mdi-magnify',
  'i-lucide-trash-2': 'mdi-delete',
  'i-lucide-send': 'mdi-send',
  'i-lucide-refresh-cw': 'mdi-refresh',
  'i-lucide-plus': 'mdi-plus',
}

export function mapLucideIcon(icon?: string): string | undefined {
  if (!icon) return undefined
  if (icon.startsWith('mdi-')) return icon
  return LUCIDE_TO_MDI[icon] ?? 'mdi-circle-small'
}
