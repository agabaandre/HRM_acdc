import { Quill } from '@vueup/vue-quill'

export type RichTextVariant = 'standard' | 'full'

/** Default minimum visible editor rows (~line height 1.6 × 15px font). */
export const DEFAULT_RICH_TEXT_MIN_ROWS = 5

/** Pixel height for one editor row (used for min-height + auto-grow). */
export const RICH_TEXT_ROW_PX = 28

export function editorMinHeightPx(minRows: number = DEFAULT_RICH_TEXT_MIN_ROWS): number {
  return minRows * RICH_TEXT_ROW_PX + 20
}

/** True when the string looks like HTML from Quill or another editor. */
export function isHtmlContent(content: string | null | undefined): boolean {
  return !!content && /<[a-z][\s\S]*>/i.test(content)
}

/** Quill empty states and whitespace-only HTML count as blank. */
export function hasRichTextContent(html: string): boolean {
  const stripped = html.replace(/\s+/g, '')
  if (stripped === '' || stripped === '<p><br></p>' || stripped === '<p><br/></p>') {
    return false
  }
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent!.trim() !== '' || !!tmp.querySelector('img')
}

let linkBlotPatched = false

/** Open http(s) links from Quill in a new tab. */
export function patchQuillExternalLinks(): void {
  if (linkBlotPatched) {
    return
  }
  const LinkBlot = Quill.import('formats/link') as {
    create?: (value: string) => HTMLAnchorElement
    __cbpPatched?: boolean
  }
  if (!LinkBlot?.create) {
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
}

/** Shared rows for standard + full editors (helpdesk tickets, KB, resolutions). */
const RICH_TEXT_TOOLBAR_CORE = [
  [{ header: [1, 2, 3, 4, 5, 6, false] }],
  [{ size: ['small', false, 'large', 'huge'] }],
  ['bold', 'italic', 'underline', 'strike'],
  [{ color: [] }, { background: [] }],
  [{ script: 'sub' }, { script: 'super' }],
  [{ list: 'ordered' }, { list: 'bullet' }],
  [{ indent: '-1' }, { indent: '+1' }],
  [{ align: ['', 'center', 'right', 'justify'] }],
  [{ direction: 'rtl' }],
  ['blockquote', 'code-block'],
] as const

function buildToolbar(variant: RichTextVariant): readonly (readonly unknown[])[] {
  const media = variant === 'full' ? (['link', 'image', 'video'] as const) : (['link', 'image'] as const)
  return [...RICH_TEXT_TOOLBAR_CORE, media, ['clean'] as const]
}

export interface BuildQuillOptionsParams {
  variant?: RichTextVariant
  placeholder?: string
  onImagePick?: () => void
}

export function buildQuillOptions(params: BuildQuillOptionsParams = {}): Record<string, unknown> {
  const variant = params.variant ?? 'standard'
  const placeholder = params.placeholder ?? 'Enter text…'
  const toolbar = buildToolbar(variant)
  const withImages = !!params.onImagePick

  const modules: Record<string, unknown> = {}

  if (withImages) {
    modules.toolbar = {
      container: [...toolbar],
      handlers: { image: params.onImagePick },
    }
    modules.clipboard = { matchVisual: false }
  } else {
    modules.toolbar = [...toolbar]
  }

  return {
    modules,
    placeholder,
    theme: 'snow',
  }
}

/** Grow the Quill editor height with content (minimum `minPx`). */
export function setupQuillAutoGrow(quill: { root: HTMLElement; on: (e: string, fn: () => void) => void }, minPx: number): void {
  const editor = quill.root
  const grow = () => {
    editor.style.height = 'auto'
    const next = Math.max(minPx, editor.scrollHeight + 2)
    editor.style.height = `${next}px`
    const container = editor.closest('.ql-container') as HTMLElement | null
    if (container) {
      container.style.height = 'auto'
    }
  }
  quill.on('text-change', grow)
  grow()
}
