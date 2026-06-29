export type RichTextVariant = 'standard' | 'full'

/** Default minimum visible editor rows (~line height 1.6 × 15px font). */
export const DEFAULT_RICH_TEXT_MIN_ROWS = 3

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

/** Default width for newly inserted inline images (matches APM memo editor). */
export const DEFAULT_QUILL_IMAGE_WIDTH_PERCENT = 25

type QuillEditorLike = {
  root: HTMLElement
  container?: HTMLElement
  getSelection: (focus?: boolean) => { index: number } | null
  getLength: () => number
  getLeaf: (index: number) => [{ domNode?: Node } | null, number]
  insertEmbed: (index: number, type: string, value: string, source?: string) => void
  setSelection: (index: number, length?: number, source?: string) => void
  on: (event: string, fn: () => void) => void
}

export function prepareQuillInsertedImage(
  quill: QuillEditorLike,
  index?: number,
  widthPercent: number = DEFAULT_QUILL_IMAGE_WIDTH_PERCENT,
): HTMLImageElement | null {
  const img = findQuillImageAtIndex(quill, index)
  if (!img) {
    return null
  }
  img.style.maxWidth = '100%'
  img.style.height = 'auto'
  img.style.display = 'block'
  img.removeAttribute('width')
  img.removeAttribute('height')
  img.style.width = `${widthPercent}%`
  img.classList.add('cbp-quill-image')
  return img
}

/** Select an image for resize once it has layout dimensions (after load). */
export function selectQuillImageWhenReady(img: HTMLImageElement, onSelect: (img: HTMLImageElement) => void): void {
  const run = () => {
    window.requestAnimationFrame(() => {
      onSelect(img)
    })
  }
  if (img.complete && img.naturalWidth > 0) {
    run()
    return
  }
  img.addEventListener('load', run, { once: true })
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

export interface QuillImageResizeOptions {
  /** Called after width changes so v-model receives inline styles. */
  onHtmlChange?: () => void
}

export interface QuillImageResizeHandle {
  selectImage: (img: HTMLImageElement) => void
}

type Corner = 'nw' | 'ne' | 'sw' | 'se'

type CornerDrag = {
  corner: Corner
  startX: number
  startY: number
  startWidth: number
}

function imageOffsetInRoot(img: HTMLImageElement, root: HTMLElement): { top: number; left: number } {
  const imgRect = img.getBoundingClientRect()
  const rootRect = root.getBoundingClientRect()
  return {
    top: imgRect.top - rootRect.top + root.scrollTop,
    left: imgRect.left - rootRect.left + root.scrollLeft,
  }
}

/**
 * Click-to-select image sizing with corner drag handles (aspect ratio preserved).
 */
export function setupQuillImageResize(
  quill: QuillEditorLike,
  wrap: HTMLElement,
  options: QuillImageResizeOptions = {},
): QuillImageResizeHandle | null {
  if (!quill || !wrap || wrap.dataset.cbpImageResizeBound === '1') {
    return null
  }

  const root = quill.root
  if (!(root instanceof HTMLElement)) {
    return null
  }

  wrap.dataset.cbpImageResizeBound = '1'
  root.style.position = 'relative'

  const overlay = document.createElement('div')
  overlay.className = 'cbp-quill-image-overlay is-hidden'
  overlay.innerHTML =
    '<div class="cbp-quill-image-frame"></div>'
    + '<span class="cbp-quill-image-handle cbp-quill-image-handle--nw" data-corner="nw" title="Resize"></span>'
    + '<span class="cbp-quill-image-handle cbp-quill-image-handle--ne" data-corner="ne" title="Resize"></span>'
    + '<span class="cbp-quill-image-handle cbp-quill-image-handle--sw" data-corner="sw" title="Resize"></span>'
    + '<span class="cbp-quill-image-handle cbp-quill-image-handle--se" data-corner="se" title="Resize"></span>'
  root.appendChild(overlay)

  const frame = overlay.querySelector('.cbp-quill-image-frame') as HTMLElement
  let activeImg: HTMLImageElement | null = null
  let drag: CornerDrag | null = null
  let resizeObserver: ResizeObserver | null = null

  function notifyChange(): void {
    options.onHtmlChange?.()
  }

  function clearSelection(): void {
    activeImg = null
    overlay.classList.add('is-hidden')
    drag = null
    resizeObserver?.disconnect()
    resizeObserver = null
  }

  function editorWidth(): number {
    return root.clientWidth || 1
  }

  function setImageWidthPercent(img: HTMLImageElement, percent: number): void {
    const pct = Math.max(10, Math.min(100, percent))
    img.removeAttribute('width')
    img.removeAttribute('height')
    img.style.width = `${pct}%`
    img.style.maxWidth = '100%'
    img.style.height = 'auto'
    img.style.display = 'block'
    positionOverlay(img)
    notifyChange()
  }

  function positionOverlay(img: HTMLImageElement): void {
    if (!frame) {
      return
    }
    const { top, left } = imageOffsetInRoot(img, root)
    const width = img.offsetWidth
    const height = img.offsetHeight
    if (width <= 0 || height <= 0) {
      return
    }
    overlay.classList.remove('is-hidden')
    overlay.style.top = `${top}px`
    overlay.style.left = `${left}px`
    overlay.style.width = `${width}px`
    overlay.style.height = `${height}px`
    frame.style.width = '100%'
    frame.style.height = '100%'
  }

  function selectImage(img: HTMLImageElement): void {
    resizeObserver?.disconnect()
    activeImg = img
    img.classList.add('cbp-quill-image')
    img.removeAttribute('width')
    img.removeAttribute('height')
    if (!img.style.width) {
      img.style.width = `${DEFAULT_QUILL_IMAGE_WIDTH_PERCENT}%`
    }
    img.style.maxWidth = '100%'
    img.style.height = 'auto'
    img.style.display = 'block'
    positionOverlay(img)
    resizeObserver = new ResizeObserver(() => {
      if (activeImg) {
        positionOverlay(activeImg)
      }
    })
    resizeObserver.observe(img)
  }

  overlay.addEventListener('mousedown', (e) => {
    const handle = (e.target as HTMLElement).closest('[data-corner]') as HTMLElement | null
    if (!handle || !activeImg) {
      return
    }
    const corner = handle.getAttribute('data-corner') as Corner | null
    if (!corner) {
      return
    }
    e.preventDefault()
    e.stopPropagation()
    drag = {
      corner,
      startX: e.clientX,
      startY: e.clientY,
      startWidth: activeImg.offsetWidth,
    }
  })

  document.addEventListener('mousemove', (e: MouseEvent) => {
    if (!drag || !activeImg) {
      return
    }
    const deltaX = e.clientX - drag.startX
    let nextWidth = drag.startWidth
    if (drag.corner === 'se' || drag.corner === 'ne') {
      nextWidth = drag.startWidth + deltaX
    } else {
      nextWidth = drag.startWidth - deltaX
    }
    nextWidth = Math.max(40, Math.min(editorWidth(), nextWidth))
    const percent = Math.round((nextWidth / editorWidth()) * 100)
    setImageWidthPercent(activeImg, percent)
  })

  document.addEventListener('mouseup', () => {
    drag = null
  })

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

  root.addEventListener('scroll', () => {
    if (activeImg) {
      positionOverlay(activeImg)
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
      node.removeAttribute('width')
      node.removeAttribute('height')
      if (!node.style.maxWidth) {
        node.style.maxWidth = '100%'
      }
      if (!node.style.height) {
        node.style.height = 'auto'
      }
      node.style.display = 'block'
    }
  })

  return { selectImage }
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

/** True when the attachment id appears as an embedded image in HTML. */
export function isAttachmentEmbeddedInHtml(html: string, attachmentId: number): boolean {
  return extractImageUrlsFromHtml(html).some(
    (url) => attachmentIdFromImageUrl(url) === attachmentId,
  )
}

/** Remove img tags that reference a signed ticket attachment id. */
export function removeAttachmentImagesFromHtml(html: string, attachmentId: number): string {
  if (!html || !isHtmlContent(html)) {
    return html
  }
  const doc = new DOMParser().parseFromString(html, 'text/html')
  doc.querySelectorAll('img[src]').forEach((img) => {
    const src = img.getAttribute('src') ?? ''
    if (attachmentIdFromImageUrl(src) === attachmentId) {
      img.remove()
    }
  })
  return doc.body.innerHTML
}

const DATA_URI_IMAGE_SRC_RE = /^data:image\/(png|jpe?g|gif|webp);base64,/i

/** True when HTML still contains Quill-pasted base64 image embeds. */
export function htmlContainsDataUriImages(html: string): boolean {
  if (!html) {
    return false
  }
  if (DATA_URI_IMAGE_SRC_RE.test(html)) {
    return true
  }
  return collectDataUriImagesFromHtml(html).length > 0
}

/** data:image/...;base64,... values from img src attributes. */
export function collectDataUriImagesFromHtml(html: string): string[] {
  if (!html || !isHtmlContent(html)) {
    return []
  }
  const doc = new DOMParser().parseFromString(html, 'text/html')
  return Array.from(doc.querySelectorAll('img[src]'))
    .map((img) => img.getAttribute('src') ?? '')
    .filter((src) => DATA_URI_IMAGE_SRC_RE.test(src))
}

/** Decode a data URI into a File for upload. */
export function dataUriToFile(dataUri: string, index = 0): File | null {
  const match = dataUri.match(/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i)
  if (!match?.[1] || !match[2]) {
    return null
  }
  try {
    const binary = atob(match[2])
    const bytes = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i)
    }
    const mime = match[1].toLowerCase()
    const ext = mime.includes('jpeg') || mime.includes('jpg')
      ? 'jpg'
      : mime.includes('gif')
        ? 'gif'
        : mime.includes('webp')
          ? 'webp'
          : 'png'
    return new File([bytes], `pasted-image-${index}.${ext}`, { type: mime })
  } catch {
    return null
  }
}
