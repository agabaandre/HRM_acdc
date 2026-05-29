<script setup lang="ts">
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'
import { computed, ref, watch } from 'vue'
import { api } from '../../lib/api'
import {
  buildQuillOptions,
  DEFAULT_RICH_TEXT_MIN_ROWS,
  editorMinHeightPx,
  patchQuillExternalLinks,
  setupQuillAutoGrow,
  type RichTextVariant,
} from '../../lib/richText'

patchQuillExternalLinks()

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    variant?: RichTextVariant
    /** When set, images upload as ticket attachments; otherwise to shared rich-text storage. */
    ticketId?: number | null
    disabled?: boolean
    /** Minimum visible rows before auto-grow expands further. */
    minRows?: number
    /** Enable toolbar image button, paste, and drag-drop uploads. */
    enableImages?: boolean
  }>(),
  {
    placeholder: 'Enter text…',
    variant: 'standard',
    ticketId: null,
    disabled: false,
    minRows: DEFAULT_RICH_TEXT_MIN_ROWS,
    enableImages: true,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  ready: [quill: unknown]
  uploading: [busy: boolean]
}>()

const editorRef = ref<InstanceType<typeof QuillEditor> | null>(null)
const inlineImageBusy = ref(false)
const imageHint = ref<string | null>(null)

watch(inlineImageBusy, (busy) => {
  emit('uploading', busy)
})

const MAX_INLINE_IMAGE_BYTES = 10 * 1024 * 1024
const ALLOWED_INLINE_MIME = new Set([
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
])

const editorMinPx = computed(() => editorMinHeightPx(props.minRows))

const editorStyle = computed(() => ({
  '--cbp-rich-editor-min': `${editorMinPx.value}px`,
}))

function getQuill(): any {
  const wrapper = editorRef.value as unknown as { getQuill?: () => any } | null
  return wrapper?.getQuill ? wrapper.getQuill() : null
}

function insertImageAtCursor(url: string): void {
  const quill = getQuill()
  if (!quill) {
    return
  }
  const sel = quill.getSelection(true)
  const index = sel ? sel.index : quill.getLength()
  quill.insertEmbed(index, 'image', url, 'user')
  quill.setSelection(index + 1, 0, 'silent')
}

function validateImageFile(file: File): boolean {
  const type = (file.type || '').toLowerCase()
  if (!ALLOWED_INLINE_MIME.has(type) && !type.startsWith('image/')) {
    imageHint.value = `“${file.name}” is not supported. Use JPEG, PNG, GIF, or WebP.`
    return false
  }
  if (file.size > MAX_INLINE_IMAGE_BYTES) {
    imageHint.value = `“${file.name}” is over 10 MB.`
    return false
  }
  imageHint.value = null
  return true
}

async function uploadInlineImage(file: File): Promise<string | null> {
  if (!validateImageFile(file)) {
    return null
  }
  inlineImageBusy.value = true
  try {
    const fd = new FormData()
    fd.append('image', file)
    const url = props.ticketId
      ? `/api/v1/tickets/${props.ticketId}/inline-images`
      : '/api/v1/rich-text-images'
    const { data } = await api.post(url, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data.url as string
  } catch {
    imageHint.value = 'Image upload failed. Try a smaller file or a different format.'
    return null
  } finally {
    inlineImageBusy.value = false
  }
}

function handleImagePick(): void {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/jpeg,image/png,image/gif,image/webp'
  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file) {
      return
    }
    const url = await uploadInlineImage(file)
    if (url) {
      insertImageAtCursor(url)
    }
  }
  input.click()
}

const quillOptions = computed(() =>
  buildQuillOptions({
    variant: props.variant,
    placeholder: props.placeholder,
    onImagePick: props.enableImages ? handleImagePick : undefined,
  }),
)

function onContentUpdate(value: string) {
  emit('update:modelValue', value)
}

function setupPasteAndDrop(quill: any): void {
  const root = quill.root as HTMLElement
  root.addEventListener('paste', async (ev: ClipboardEvent) => {
    const items = ev.clipboardData?.items
    if (!items) {
      return
    }
    for (const it of Array.from(items)) {
      if (it.kind === 'file' && it.type.startsWith('image/')) {
        ev.preventDefault()
        ev.stopPropagation()
        const file = it.getAsFile()
        if (file) {
          const url = await uploadInlineImage(file)
          if (url) {
            insertImageAtCursor(url)
          }
        }
        return
      }
    }
  })
  root.addEventListener('drop', async (ev: DragEvent) => {
    if (!ev.dataTransfer?.files?.length) {
      return
    }
    const images = Array.from(ev.dataTransfer.files).filter((f) => f.type.startsWith('image/'))
    if (!images.length) {
      return
    }
    ev.preventDefault()
    ev.stopPropagation()
    for (const f of images) {
      const url = await uploadInlineImage(f)
      if (url) {
        insertImageAtCursor(url)
      }
    }
  })
}

function onReady(quill: unknown) {
  const q = quill as { root: HTMLElement; on: (e: string, fn: () => void) => void }
  setupQuillAutoGrow(q, editorMinPx.value)
  if (props.enableImages) {
    setupPasteAndDrop(q)
  }
  emit('ready', quill)
}
</script>

<template>
  <div
    class="cbp-rich-text"
    :class="{ 'cbp-rich-text--busy': inlineImageBusy }"
    :style="editorStyle"
  >
    <QuillEditor
      ref="editorRef"
      :content="modelValue"
      content-type="html"
      theme="snow"
      class="cbp-rich-text__editor"
      :options="quillOptions"
      :read-only="disabled"
      @update:content="onContentUpdate"
      @ready="onReady"
    />
    <p v-if="inlineImageBusy" class="cbp-rich-text__status" role="status">Uploading image…</p>
    <p v-else-if="imageHint" class="cbp-rich-text__hint" role="alert">{{ imageHint }}</p>
    <p v-else-if="enableImages" class="cbp-rich-text__tip muted">
      Paste screenshots (⌘V / Ctrl+V), drag images here, or use the image button. JPEG, PNG, GIF, WebP · max 10 MB.
    </p>
  </div>
</template>

<style scoped>
.cbp-rich-text {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.cbp-rich-text__editor {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
}
.cbp-rich-text__editor :deep(.ql-toolbar) {
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
  border-color: #cbd5e1;
  background: #f8fafc;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px;
  padding: 0.35rem 0.5rem;
}
.cbp-rich-text__editor :deep(.ql-toolbar .ql-formats) {
  margin-right: 0.35rem;
}
.cbp-rich-text__editor :deep(.ql-container) {
  height: auto;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
  border-color: #cbd5e1;
  font-size: 0.95rem;
}
.cbp-rich-text__editor :deep(.ql-editor) {
  min-height: var(--cbp-rich-editor-min, 160px);
  height: auto;
  line-height: 1.6;
  overflow-y: visible;
  padding: 0.75rem 1rem;
}
.cbp-rich-text__editor :deep(.ql-editor img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 0.35rem 0;
}
.cbp-rich-text__editor :deep(.ql-editor.ql-blank::before) {
  font-style: normal;
  color: #94a3b8;
}
.cbp-rich-text--busy .cbp-rich-text__editor {
  opacity: 0.85;
  pointer-events: none;
}
.cbp-rich-text__status,
.cbp-rich-text__hint,
.cbp-rich-text__tip {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.4;
}
.cbp-rich-text__hint {
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 0.4rem 0.55rem;
  border-radius: 6px;
}
.cbp-rich-text__tip.muted {
  color: #64748b;
}
</style>
