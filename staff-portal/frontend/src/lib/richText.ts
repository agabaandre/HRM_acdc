export const DEFAULT_RICH_TEXT_MIN_ROWS = 3
export const RICH_TEXT_ROW_PX = 28

export function editorMinHeightPx(minRows: number = DEFAULT_RICH_TEXT_MIN_ROWS): number {
  return minRows * RICH_TEXT_ROW_PX + 20
}

/** True when the string looks like HTML from Quill or another editor. */
export function isHtmlContent(content: string | null | undefined): boolean {
  return !!content && /<[a-z][\s\S]*>/i.test(content)
}

/** Quill empty states and whitespace-only HTML count as blank. */
export function hasRichTextContent(html: string | null | undefined): boolean {
  if (!html) {
    return false
  }
  const stripped = html.replace(/\s+/g, '')
  if (stripped === '' || stripped === '<p><br></p>' || stripped === '<p><br/></p>') {
    return false
  }
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return (tmp.textContent || '').trim() !== ''
}

export function escapePlainTextAsHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/\n/g, '<br>')
}

export function richTextToHtml(value: string | null | undefined): string {
  if (!value) {
    return ''
  }
  return isHtmlContent(value) ? value : escapePlainTextAsHtml(value)
}

/** Compact PPA toolbar — same as the CI3 performance Quill editors. */
export const PERFORMANCE_QUILL_TOOLBAR = [
  ['bold', 'italic', 'underline'],
  [{ list: 'ordered' }, { list: 'bullet' }],
  ['link'],
  ['clean'],
] as const

export function buildPerformanceQuillOptions(params: {
  placeholder?: string
  readOnly?: boolean
} = {}): Record<string, unknown> {
  if (params.readOnly) {
    return {
      theme: 'snow',
      readOnly: true,
      modules: { toolbar: false },
      placeholder: params.placeholder ?? '',
    }
  }

  return {
    theme: 'snow',
    readOnly: false,
    placeholder: params.placeholder ?? 'Enter text…',
    modules: {
      toolbar: [...PERFORMANCE_QUILL_TOOLBAR],
      clipboard: { matchVisual: false },
    },
  }
}

/** Grow the Quill editor height with content (minimum `minPx`). */
export function setupQuillAutoGrow(
  quill: { root: HTMLElement; on: (e: string, fn: () => void) => void },
  minPx: number,
): void {
  const editor = quill.root
  const grow = () => {
    const isBlank = editor.classList.contains('ql-blank')
    editor.style.height = 'auto'
    const next = isBlank ? minPx : Math.max(minPx, editor.scrollHeight + 2)
    editor.style.height = `${next}px`
    const container = editor.closest('.ql-container') as HTMLElement | null
    if (container) {
      container.style.height = 'auto'
    }
  }
  quill.on('text-change', grow)
  grow()
}

let linkBlotPatched = false
let linkBlotPatchPromise: Promise<void> | null = null

type QuillLike = {
  import: (path: string) => unknown
}

type VueQuillModule = {
  Quill: QuillLike
  loadQuill?: () => Promise<QuillLike>
}

/** Open http(s) links from Quill in a new tab. */
export function patchQuillExternalLinks(): Promise<void> {
  if (linkBlotPatched) {
    return Promise.resolve()
  }
  if (linkBlotPatchPromise) {
    return linkBlotPatchPromise
  }

  linkBlotPatchPromise = (async () => {
    const mod = (await import('@vueup/vue-quill')) as unknown as VueQuillModule
    const Quill = typeof mod.loadQuill === 'function' ? await mod.loadQuill() : mod.Quill
    const LinkBlot = Quill.import('formats/link') as {
      create?: (value: string) => HTMLAnchorElement
      __cbpPatched?: boolean
    }
    if (!LinkBlot?.create || LinkBlot.__cbpPatched) {
      linkBlotPatched = true
      return
    }
    const origCreate = LinkBlot.create
    LinkBlot.create = function (value: string) {
      const node: HTMLAnchorElement = origCreate.call(this, value)
      if (/^https?:/i.test(value)) {
        node.setAttribute('target', '_blank')
        node.setAttribute('rel', 'noopener noreferrer')
      }
      return node
    }
    LinkBlot.__cbpPatched = true
    linkBlotPatched = true
  })()

  return linkBlotPatchPromise
}
