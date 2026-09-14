/** Client-side CSV / Excel download + printable PDF table (staff-toolbar parity when no API export). */

export type TableExportCell = string | number | null | undefined

function escapeCsvCell(value: TableExportCell): string {
  const s = value == null ? '' : String(value)
  if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`
  return s
}

function triggerDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 30_000)
}

export function downloadClientCsv(
  filename: string,
  headers: string[],
  rows: TableExportCell[][],
): void {
  const lines = [
    headers.map(escapeCsvCell).join(','),
    ...rows.map((row) => row.map(escapeCsvCell).join(',')),
  ]
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
  triggerDownload(blob, filename.endsWith('.csv') ? filename : `${filename}.csv`)
}

function escapeXml(value: TableExportCell): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/**
 * Excel-compatible SpreadsheetML (.xls) — opens in Excel / LibreOffice without extra deps.
 */
export function downloadClientExcel(
  filename: string,
  headers: string[],
  rows: TableExportCell[][],
  sheetName = 'Sheet1',
): void {
  const safeSheet = escapeXml(sheetName).slice(0, 31) || 'Sheet1'
  const headerRow = `<Row>${headers
    .map((h) => `<Cell><Data ss:Type="String">${escapeXml(h)}</Data></Cell>`)
    .join('')}</Row>`
  const dataRows = rows
    .map((row) => {
      const cells = row
        .map((c) => {
          const isNum = typeof c === 'number' && Number.isFinite(c)
          return `<Cell><Data ss:Type="${isNum ? 'Number' : 'String'}">${escapeXml(c)}</Data></Cell>`
        })
        .join('')
      return `<Row>${cells}</Row>`
    })
    .join('')
  const xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="${safeSheet}">
  <Table>${headerRow}${dataRows}</Table>
 </Worksheet>
</Workbook>`
  const blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8' })
  const name = filename.replace(/\.(csv|xlsx|xls)$/i, '')
  triggerDownload(blob, `${name}.xls`)
}

function escapeHtml(value: TableExportCell): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** Open a printable HTML table in a new window (browser Print → Save as PDF). */
export function openClientPdfTable(
  title: string,
  headers: string[],
  rows: TableExportCell[][],
  options?: { subtitle?: string },
): void {
  const head = headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('')
  const body = rows
    .map((row) => `<tr>${row.map((c) => `<td>${escapeHtml(c)}</td>`).join('')}</tr>`)
    .join('')
  const subtitle = options?.subtitle
    ? `<p style="margin:0 0 0.85rem;color:#555;font-size:0.85rem">${escapeHtml(options.subtitle)}</p>`
    : ''
  const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>${escapeHtml(title)}</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 1.25rem; color: #222; }
  h1 { font-size: 1.1rem; margin: 0 0 0.35rem; }
  table { border-collapse: collapse; width: 100%; font-size: 0.85rem; }
  th, td { border: 1px solid #c5cdd6; padding: 0.35rem 0.5rem; text-align: left; vertical-align: top; }
  th { background: #f3f6f9; }
  @media print { body { margin: 0.5rem; } }
</style>
</head>
<body>
<h1>${escapeHtml(title)}</h1>
${subtitle}
<table>
<thead><tr>${head}</tr></thead>
<tbody>${body || `<tr><td colspan="${headers.length}">No rows</td></tr>`}</tbody>
</table>
<script>window.onload = function () { window.print(); };<\/script>
</body>
</html>`
  const win = window.open('', '_blank', 'noopener,noreferrer')
  if (!win) return
  win.document.open()
  win.document.write(html)
  win.document.close()
}

/** Fetch all pages from a paginated list API for export. */
export async function fetchAllPages<T>(
  fetchPage: (page: number, perPage: number) => Promise<{ data: T[]; meta: { last_page: number } }>,
  perPage = 100,
  maxPages = 50,
): Promise<T[]> {
  const first = await fetchPage(1, perPage)
  const out = [...first.data]
  const last = Math.min(Math.max(1, first.meta.last_page), maxPages)
  for (let p = 2; p <= last; p++) {
    const res = await fetchPage(p, perPage)
    out.push(...res.data)
  }
  return out
}
