/** Initials for avatar fallback (APM-style: first + last, or first two letters). */
export function avatarInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) {
    const a = parts[0][0] ?? ''
    const b = parts[parts.length - 1][0] ?? ''
    return (a + b).toUpperCase()
  }
  if (parts.length === 1 && parts[0].length >= 2) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return (parts[0]?.[0] ?? '?').toUpperCase()
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
