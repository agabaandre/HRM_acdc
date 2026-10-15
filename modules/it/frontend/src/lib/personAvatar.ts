import { avatarBackground, avatarInitials } from '@cbp/helpdesk-lib/lib/avatar'

/** Name parts used for top-nav style avatars (firstname + other/surname + lastname). */
export type PersonAvatarNameParts = {
  fname?: string | null
  oname?: string | null
  lname?: string | null
  /** Fallback display name when structured parts are missing (e.g. auth users). */
  name?: string | null
}

/**
 * Build the display name for avatar initials — same source as the top-bar profile:
 * firstname, other/surname, lastname (then free-text name).
 */
export function personAvatarName(parts: PersonAvatarNameParts): string {
  const fromParts = [parts.fname, parts.oname, parts.lname]
    .map((part) => (typeof part === 'string' ? part.trim() : ''))
    .filter(Boolean)
  if (fromParts.length > 0) {
    return fromParts.join(' ')
  }
  const name = typeof parts.name === 'string' ? parts.name.trim() : ''
  return name || '?'
}

export function personAvatarInitials(parts: PersonAvatarNameParts): string {
  return avatarInitials(personAvatarName(parts))
}

export function personAvatarBackground(parts: PersonAvatarNameParts): string {
  return avatarBackground(personAvatarName(parts))
}

/** Absolute URL so shared CbpAvatar does not re-prefix with the helpdesk API base. */
export function toAbsoluteMediaUrl(url: string | null | undefined): string | null {
  if (!url || url.trim() === '') {
    return null
  }
  const trimmed = url.trim()
  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed
  }
  if (trimmed.startsWith('/') && typeof window !== 'undefined') {
    return `${window.location.origin}${trimmed}`
  }
  return trimmed
}

export { avatarBackground, avatarInitials }
