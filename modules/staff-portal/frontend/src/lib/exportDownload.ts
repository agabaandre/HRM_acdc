import { api, getStoredToken } from './api'

export type ExportParamValue = string | number | Array<string | number> | undefined | null
export type ExportParams = Record<string, ExportParamValue>

function cleanParams(params: ExportParams): Record<string, string | number | Array<string | number>> {
  const out: Record<string, string | number | Array<string | number>> = {}
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      if (value.length === 0) continue
      out[key] = value
      continue
    }
    out[key] = value
  }
  return out
}

/** Download authenticated API export (PDF/CSV) via Sanctum bearer token. */
export async function downloadApiExport(
  path: string,
  filename: string,
  params: ExportParams = {},
): Promise<void> {
  const { data } = await api.get(path, {
    params: cleanParams(params),
    paramsSerializer: { indexes: null },
    responseType: 'blob',
    headers: {
      Authorization: `Bearer ${getStoredToken() || ''}`,
      Accept: '*/*',
    },
  })
  const blob = data instanceof Blob ? data : new Blob([data])
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.target = filename.endsWith('.pdf') ? '_blank' : '_self'
  document.body.appendChild(a)
  a.click()
  a.remove()
  // Keep PDF object URL briefly so new tab can load
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

/** Open PDF inline in a new tab with auth. */
export async function openApiPdf(path: string, params: ExportParams = {}): Promise<void> {
  const { data } = await api.get(path, {
    params: cleanParams(params),
    paramsSerializer: { indexes: null },
    responseType: 'blob',
  })
  const blob = data instanceof Blob ? data : new Blob([data], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(url), 120_000)
}
