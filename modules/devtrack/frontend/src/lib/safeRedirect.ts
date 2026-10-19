/**
 * Same-origin relative paths only — blocks open redirects used in phishing.
 */
export function safeInternalRedirect(candidate: string | null | undefined): string | null {
  if (!candidate) return null
  const value = candidate.trim()
  if (!value.startsWith('/')) return null
  if (value.startsWith('//') || value.startsWith('/\\')) return null
  if (/[\u0000-\u001F\u007F]/.test(value)) return null
  if (!/^\/[A-Za-z0-9._~!$&'()*+,;=:@%/\-?#]*$/.test(value)) return null
  return value
}
