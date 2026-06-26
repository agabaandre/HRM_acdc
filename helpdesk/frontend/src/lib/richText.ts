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
    const Quill =
      typeof mod.loadQuill === 'function' ? await mod.loadQuill() : mod.Quill
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

type QuillEditorLike = {
  root: HTMLElement
  getSelection: (focus?: boolean) => { index: number } | null
  getLength: () => number
  getLeaf: (index: number) => [{ domNode?: Node } | null, number]
  insertEmbed: (index: number, type: string, value: string, source?: string) => void
  setSelection: (index: number, length?: number, source?: string) => void
  on: (event: string, fn: () => void) => void
}

/** Default width for newly inserted inline images (matches APM memo editor). */
export function prepareQuillInsertedImage(quill: QuillEditorLike, index?: number): void {
  const img = findQuillImageAtIndex(quill, index)
  if (!img) {
    return
  }
  img.style.maxWidth = '100%'
  img.style.height = 'auto'
  if (!img.style.width) {
    img.style.width = '50%'
  }
  img.classList.add('cbp-quill-image')
}

function findQuillImageAtIndex(quill: QuillEditorLike, index?: number): HTMLImageElement | null {
  if (index !== undefined) {
    const leaf = quill.getLeaf(index)[0]
    const node = leaf?.domNode
    if (node instanceof HTMLImageElement) {
      return node
    }
  }
  const imgs = quill.root.querySelectorAll('img')
  const last = imgs[imgs.length - 1]
  return last instanceof HTMLImageElement ? last : null
}

/**
 * Click-to-select image sizing (25–100% presets + drag handle), like APM special memo editor.
 */
export function setupQuillImageResize(quill: QuillEditorLike, wrap: HTMLElement): void {
  if (wrap.dataset.cbpImageResizeBound === '1') {
    return
  }
  wrap.dataset.cbpImageResizeBound = '1'

  const root = quill.root
  const editorShell = wrap.querySelector('.cbp-rich-text__editor') as HTMLElement | null
  const container = editorShell ?? wrap
  container.style.position = 'relative'

  const overlay = document.createElement('div')
  overlay.className = 'cbp-quill-image-overlay cbp-quill-image-overlay--hidden'
  overlay.innerHTML =
    '<div class="cbp-quill-image-toolbar" role="toolbar" aria-label="Image size">'
    + '<button type="button" data-cbp-img-size="25">25%</button>'
    + '<button type="button" data-cbp-img-size="50">50%</button>'
    + '<button type="button" data-cbp-img-size="75">75%</button>'
    + '<button type="button" data-cbp-img-size="100">100%</button>'
    + '</div>'
    + '<div class="cbp-quill-image-frame"></div>'
    + '<span class="cbp-quill-image-handle" title="Drag to resize"></span>'
  container.appendChild(overlay)

  const frame = overlay.querySelector('.cbp-quill-image-frame') as HTMLElement
  const handle = overlay.querySelector('.cbp-quill-image-handle') as HTMLElement
  const toolbar = overlay.querySelector('.cbp-quill-image-toolbar') as HTMLElement
  let activeImg: HTMLImageElement | null = null
  let drag: { startX: number; startWidth: number } | null = null

  function clearSelection(): void {
    activeImg = null
    overlay.classList.add('cbp-quill-image-overlay--hidden')
    drag = null
  }

  function editorWidth(): number {
    return root.clientWidth || container.clientWidth || 1
  }

  function setImageWidthPercent(img: HTMLImageElement, percent: number): void {
    const pct = Math.max(10, Math.min(100, percent))
    img.style.width = `${pct}%`
    img.style.maxWidth = '100%'
    img.style.height = 'auto'
    positionOverlay(img)
  }

  function positionOverlay(img: HTMLImageElement): void {
    const imgRect = img.getBoundingClientRect()
    const boxRect = container.getBoundingClientRect()
    const top = imgRect.top - boxRect.top + container.scrollTop
    const left = imgRect.left - boxRect.left + container.scrollLeft
    overlay.classList.remove('cbp-quill-image-overlay--hidden')
    overlay.style.top = `${top}px`
    overlay.style.left = `${left}px`
    overlay.style.width = `${imgRect.width}px`
    overlay.style.height = `${imgRect.height}px`
    frame.style.width = '100%'
    frame.style.height = '100%'
  }

  function selectImage(img: HTMLImageElement): void {
    activeImg = img
    img.classList.add('cbp-quill-image')
    positionOverlay(img)
  }

  toolbar.addEventListener('mousedown', (e) => {
    e.preventDefault()
    e.stopPropagation()
  })

  toolbar.addEventListener('click', (e) => {
    const btn = (e.target as HTMLElement).closest('[data-cbp-img-size]') as HTMLElement | null
    if (!btn || !activeImg) {
      return
    }
    e.preventDefault()
    e.stopPropagation()
    setImageWidthPercent(activeImg, Number.parseInt(btn.getAttribute('data-cbp-img-size') ?? '50', 10))
  })

  handle.addEventListener('mousedown', (e) => {
    if (!activeImg) {
      return
    }
    e.preventDefault()
    e.stopPropagation()
    drag = {
      startX: e.clientX,
      startWidth: activeImg.getBoundingClientRect().width,
    }
  })

  const onMouseMove = (e: MouseEvent): void => {
    if (!drag || !activeImg) {
      return
    }
    const delta = e.clientX - drag.startX
    const nextWidth = Math.max(40, drag.startWidth + delta)
    const percent = Math.round((nextWidth / editorWidth()) * 100)
    setImageWidthPercent(activeImg, percent)
  }

  const onMouseUp = (): void => {
    drag = null
  }

  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)

  root.addEventListener('click', (e) => {
    const target = e.target
    if (target instanceof HTMLImageElement && root.contains(target)) {
      e.preventDefault()
      selectImage(target)
      return
    }
    if (!overlay.contains(target as Node)) {
      clearSelection()
    }
  })

  quill.on('text-change', () => {
    if (activeImg && !root.contains(activeImg)) {
      clearSelection()
    } else if (activeImg) {
      positionOverlay(activeImg)
    }
  })

  window.addEventListener('resize', () => {
    if (activeImg) {
      positionOverlay(activeImg)
    }
  })

  root.querySelectorAll('img').forEach((node) => {
    if (node instanceof HTMLImageElement) {
      node.classList.add('cbp-quill-image')
      if (!node.style.maxWidth) {
        node.style.maxWidth = '100%'
      }
      if (!node.style.height) {
        node.style.height = 'auto'
      }
    }
  })
}

const ATTACHMENT_IMAGE_RE = /\/api\/v1\/attachments\/(\d+)\/file/i
const RICH_TEXT_IMAGE_RE = /\/storage\/helpdesk\/rich-text\/|helpdesk\/rich-text\//i

/** img src URLs embedded in Quill HTML. */
export function extractImageUrlsFromHtml(html: string): string[] {
  if (!html || !isHtmlContent(html)) {
    return []
  }
  const doc = new DOMParser().parseFromString(html, 'text/html')
  return Array.from(doc.querySelectorAll('img[src]'))
    .map((img) => img.getAttribute('src') ?? '')
    .filter((url) => url.trim() !== '')
}

/** Compare image URLs ignoring signed attachment query params. */
export function normalizeImageUrlForCompare(url: string): string {
  try {
    const parsed = new URL(url, window.location.origin)
    if (ATTACHMENT_IMAGE_RE.test(parsed.pathname)) {
      return parsed.pathname
    }
    return parsed.pathname + parsed.search
  } catch {
    return url
  }
}

/** URLs removed from the editor since the previous HTML snapshot. */
export function diffRemovedImageUrls(previousHtml: string, nextHtml: string): string[] {
  const nextKeys = new Set(
    extractImageUrlsFromHtml(nextHtml).map((url) => normalizeImageUrlForCompare(url)),
  )
  return extractImageUrlsFromHtml(previousHtml).filter(
    (url) => !nextKeys.has(normalizeImageUrlForCompare(url)),
  )
}

/** True when the URL points at helpdesk-managed inline upload storage. */
export function isManagedInlineImageUrl(url: string): boolean {
  return ATTACHMENT_IMAGE_RE.test(url) || RICH_TEXT_IMAGE_RE.test(url)
}

export function attachmentIdFromImageUrl(url: string): number | null {
  try {
    const parsed = new URL(url, window.location.origin)
    const match = parsed.pathname.match(ATTACHMENT_IMAGE_RE)
    if (!match?.[1]) {
      return null
    }
    const id = Number(match[1])
    return Number.isFinite(id) && id > 0 ? id : null
  } catch {
    const match = url.match(ATTACHMENT_IMAGE_RE)
    if (!match?.[1]) {
      return null
    }
    const id = Number(match[1])
    return Number.isFinite(id) && id > 0 ? id : null
  }
}
