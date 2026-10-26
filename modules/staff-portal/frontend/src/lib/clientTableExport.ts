/** Client-side CSV / Excel download + printable PDF table (staff-toolbar parity when no API export). */

export type TableExportCell = string | number | null | undefined

export type ExcelExportOptions = {
  sheetName?: string
  title?: string
  subtitle?: string
  /** Body/header font size in points (default 13). */
  fontSize?: number
}

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

function excelColumnWidths(headers: string[], rows: TableExportCell[][]): number[] {
  return headers.map((header, col) => {
    let maxLen = String(header ?? '').length
    for (const row of rows) {
      const len = String(row[col] ?? '').length
      if (len > maxLen) maxLen = len
    }
    // SpreadsheetML Width is roughly character units; keep readable bounds.
    return Math.min(48, Math.max(12, maxLen + 4))
  })
}

/**
 * Excel-compatible SpreadsheetML (.xls) — opens in Excel / LibreOffice without extra deps.
 * Uses Calibri 13 by default with bold header row and light borders.
 */
export function downloadClientExcel(
  filename: string,
  headers: string[],
  rows: TableExportCell[][],
  sheetNameOrOptions: string | ExcelExportOptions = 'Sheet1',
): void {
  const options: ExcelExportOptions =
    typeof sheetNameOrOptions === 'string'
      ? { sheetName: sheetNameOrOptions }
      : sheetNameOrOptions || {}

  const fontSize = options.fontSize && options.fontSize > 0 ? options.fontSize : 13
  const safeSheet = escapeXml(options.sheetName || 'Sheet1').slice(0, 31) || 'Sheet1'
  const widths = excelColumnWidths(headers, rows)
  const colXml = widths.map((w) => `<Column ss:AutoFitWidth="0" ss:Width="${w * 6}"/>`).join('')

  const titleRows: string[] = []
  let mergeAcross = Math.max(0, headers.length - 1)
  if (options.title) {
    titleRows.push(
      `<Row ss:Height="${fontSize + 10}"><Cell ss:StyleID="Title" ss:MergeAcross="${mergeAcross}"><Data ss:Type="String">${escapeXml(options.title)}</Data></Cell></Row>`,
    )
  }
  if (options.subtitle) {
    titleRows.push(
      `<Row ss:Height="${fontSize + 4}"><Cell ss:StyleID="Subtitle" ss:MergeAcross="${mergeAcross}"><Data ss:Type="String">${escapeXml(options.subtitle)}</Data></Cell></Row>`,
    )
  }
  if (titleRows.length) {
    titleRows.push('<Row/>')
  }

  const headerRow = `<Row ss:Height="${fontSize + 6}">${headers
    .map((h) => `<Cell ss:StyleID="Header"><Data ss:Type="String">${escapeXml(h)}</Data></Cell>`)
    .join('')}</Row>`

  const dataRows = rows
    .map((row) => {
      const cells = headers
        .map((_, i) => {
          const c = row[i]
          const isNum = typeof c === 'number' && Number.isFinite(c)
          return `<Cell ss:StyleID="Cell"><Data ss:Type="${isNum ? 'Number' : 'String'}">${escapeXml(c)}</Data></Cell>`
        })
        .join('')
      return `<Row ss:Height="${fontSize + 4}">${cells}</Row>`
    })
    .join('')

  const xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="${fontSize}" ss:Color="#1F2933"/>
  </Style>
  <Style ss:ID="Title">
   <Alignment ss:Vertical="Center" ss:WrapText="1"/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="${fontSize + 3}" ss:Bold="1" ss:Color="#911C39"/>
  </Style>
  <Style ss:ID="Subtitle">
   <Alignment ss:Vertical="Center" ss:WrapText="1"/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="${fontSize}" ss:Color="#52606D"/>
  </Style>
  <Style ss:ID="Header">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9AA5B1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9AA5B1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9AA5B1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9AA5B1"/>
   </Borders>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="${fontSize}" ss:Bold="1" ss:Color="#1F2933"/>
   <Interior ss:Color="#E4E7EB" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Cell">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EC"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EC"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EC"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EC"/>
   </Borders>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="${fontSize}" ss:Color="#1F2933"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="${safeSheet}">
  <Table ss:DefaultRowHeight="${fontSize + 4}">
   ${colXml}
   ${titleRows.join('')}
   ${headerRow}
   ${dataRows}
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Layout x:Orientation="Landscape"/>
   </PageSetup>
   <FitToPage/>
   <Print>
    <ValidPrinterInfo/>
    <PaperSizeIndex>9</PaperSizeIndex>
    <FitHeight>0</FitHeight>
   </Print>
   <Selected/>
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>${titleRows.length + 1}</SplitHorizontal>
   <TopRowBottomPane>${titleRows.length + 1}</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
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
