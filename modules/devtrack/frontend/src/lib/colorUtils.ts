export function normalizeHex(color: string): string {
  const value = color.trim()
  return value.startsWith('#') ? value : `#${value}`
}

function hexToRgb(hex: string): [number, number, number] | null {
  const normalized = normalizeHex(hex).slice(1)
  const full = normalized.length === 3
    ? normalized.split('').map((c) => c + c).join('')
    : normalized

  if (!/^[0-9a-fA-F]{6}$/.test(full)) {
    return null
  }

  return [
    Number.parseInt(full.slice(0, 2), 16),
    Number.parseInt(full.slice(2, 4), 16),
    Number.parseInt(full.slice(4, 6), 16),
  ]
}

function rgbToHex(r: number, g: number, b: number): string {
  return `#${[r, g, b].map((channel) => channel.toString(16).padStart(2, '0')).join('')}`
}

export function mixWithWhite(hex: string, whiteWeight: number): string {
  const rgb = hexToRgb(hex)
  if (!rgb) {
    return hex
  }

  const weight = Math.min(Math.max(whiteWeight, 0), 1)
  return rgbToHex(
    ...rgb.map((channel) => Math.round(channel + (255 - channel) * weight)) as [number, number, number],
  )
}
