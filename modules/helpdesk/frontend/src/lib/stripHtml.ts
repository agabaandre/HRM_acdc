/** Plain-text preview from HTML (for table cells). */
export function stripHtml(html: string, maxLength = 120): string {
  const text = html
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()
  if (maxLength > 0 && text.length > maxLength) {
    return `${text.slice(0, maxLength)}…`
  }
  return text
}
