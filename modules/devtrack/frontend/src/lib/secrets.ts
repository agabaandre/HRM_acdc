const SECRET_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*-_'

export function generateClientSecret(length = 32): string {
  const bytes = new Uint8Array(length)
  crypto.getRandomValues(bytes)

  return Array.from(bytes, (byte) => SECRET_CHARSET[byte % SECRET_CHARSET.length]).join('')
}
