/** Render a typed signature (CI3 / profile blue script style) to a PNG data URL. */
export function renderTypedSignatureDataUrl(text: string, width = 520, height = 160): string {
  const canvas = document.createElement('canvas')
  const ratio = window.devicePixelRatio || 1
  canvas.width = Math.floor(width * ratio)
  canvas.height = Math.floor(height * ratio)
  const ctx = canvas.getContext('2d')
  if (!ctx) return ''
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, width, height)
  const value = text.trim()
  if (!value) return canvas.toDataURL('image/png')
  ctx.fillStyle = '#1a237e'
  ctx.font = '48px "Segoe Script", "Brush Script MT", cursive'
  ctx.textBaseline = 'middle'
  ctx.fillText(value, 24, height / 2)
  return canvas.toDataURL('image/png')
}
