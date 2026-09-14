<script setup lang="ts">
import { computed } from 'vue'

const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(
  defineProps<{
    url?: string | null
    title?: string
  }>(),
  {
    url: null,
    title: 'Document preview',
  },
)

const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp']
const OFFICE_EXTS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp']

function extensionOf(rawUrl: string): string {
  const clean = rawUrl.split('#')[0].split('?')[0]
  const dot = clean.lastIndexOf('.')
  return dot >= 0 ? clean.slice(dot + 1).toLowerCase() : ''
}

const kind = computed<'image' | 'pdf' | 'office' | 'unknown' | 'empty'>(() => {
  const u = props.url?.trim()
  if (!u) return 'empty'
  const ext = extensionOf(u)
  if (IMAGE_EXTS.includes(ext)) return 'image'
  if (ext === 'pdf') return 'pdf'
  if (OFFICE_EXTS.includes(ext)) return 'office'
  return 'unknown'
})

const pdfSrc = computed(() => `${props.url ?? ''}#toolbar=1&navpanes=0&scrollbar=1`)
const officeSrc = computed(
  () => `https://docs.google.com/viewer?url=${encodeURIComponent(props.url ?? '')}&embedded=true`,
)

function close() {
  open.value = false
}
</script>

<template>
  <UModal v-model:open="open" :title="title" :ui="{ content: 'max-w-2xl' }">
    <template #body>
      <div class="doc-preview-body">
        <p v-if="kind === 'empty'" class="muted">No link provided.</p>
        <img v-else-if="kind === 'image'" :src="url ?? ''" class="preview-img" alt="Document preview" />
        <iframe v-else-if="kind === 'pdf'" :src="pdfSrc" class="preview-frame" title="PDF preview" />
        <iframe v-else-if="kind === 'office'" :src="officeSrc" class="preview-frame" title="Document preview" />
        <div v-else class="preview-fallback">
          <p>Preview isn't available for this link.</p>
          <a :href="url ?? '#'" target="_blank" rel="noopener noreferrer" class="fallback-link">Open in new tab →</a>
        </div>
      </div>
    </template>
    <template #footer>
      <a
        v-if="url && kind !== 'empty'"
        :href="url"
        target="_blank"
        rel="noopener noreferrer"
        class="open-tab-btn"
      >Open in new tab</a>
      <UButton color="primary" @click="close">Close</UButton>
    </template>
  </UModal>
</template>

<style scoped>
.doc-preview-body {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
}
.preview-img {
  max-width: 100%;
  max-height: 70vh;
  margin: auto;
  display: block;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.preview-frame {
  width: 100%;
  height: 70vh;
  border: none;
  border-radius: 0.5rem;
}
.preview-fallback {
  text-align: center;
  color: #475569;
}
.fallback-link {
  color: #0d7a3a;
  font-weight: 600;
  text-decoration: none;
}
.fallback-link:hover {
  text-decoration: underline;
}
.muted {
  color: #64748b;
}
.open-tab-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  padding: 0 1rem;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-weight: 600;
  font-size: 0.875rem;
  text-decoration: none;
}
.open-tab-btn:hover {
  background: #f8fafc;
}
</style>
