/** Honorifics stripped before computing initials (Mr Dennis Kibiye → DK, not MK). */
const NAME_TITLES = new Set([
  'mr',
  'mrs',
  'ms',
  'miss',
  'dr',
  'prof',
  'professor',
  'rev',
  'sir',
  'lady',
  'hon',
  'eng',
  'engr',
])

function namePartsWithoutTitles(name: string): string[] {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .filter((part) => {
      const normalized = part.replace(/\.+$/, '').toLowerCase()
      return normalized !== '' && !NAME_TITLES.has(normalized)
    })
}

/** Initials for avatar fallback (first + last real name, or first two letters). */
export function avatarInitials(name: string): string {
  const parts = namePartsWithoutTitles(name)
  if (parts.length >= 2) {
    const a = parts[0][0] ?? ''
    const b = parts[parts.length - 1][0] ?? ''
    return (a + b).toUpperCase()
  }
  if (parts.length === 1 && parts[0].length >= 2) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  const fallback = name.trim().split(/\s+/).filter(Boolean)
  if (fallback.length >= 2) {
    return ((fallback[0][0] ?? '') + (fallback[fallback.length - 1][0] ?? '')).toUpperCase()
  }
  return (fallback[0]?.[0] ?? '?').toUpperCase()
}

const AVATAR_COLORS = ['#119a48', '#1bb85a', '#0d7a3a', '#2c3e50', '#0d47a1', '#6a1b9a', '#9f2240']

/** Deterministic background from display name (same palette idea as APM). */
export function avatarBackground(seed: string): string {
  let h = 0
  for (let i = 0; i < seed.length; i++) {
    h = (h * 31 + seed.charCodeAt(i)) >>> 0
  }
  return AVATAR_COLORS[h % AVATAR_COLORS.length]
}
