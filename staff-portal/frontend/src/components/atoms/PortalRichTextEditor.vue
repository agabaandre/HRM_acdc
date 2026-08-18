<script setup lang="ts">
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'
import { computed, onMounted, ref } from 'vue'
import {
  buildPerformanceQuillOptions,
  editorMinHeightPx,
  patchQuillExternalLinks,
  setupQuillAutoGrow,
} from '@/lib/richText'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    disabled?: boolean
    minRows?: number
    hint?: string
  }>(),
  {
    placeholder: '',
    disabled: false,
    minRows: 4,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const editorReady = ref(false)
const editorMinPx = computed(() => editorMinHeightPx(props.minRows))
const editorStyle = computed(() => ({
  '--portal-rich-editor-min': `${editorMinPx.value}px`,
}))
const quillOptions = computed(() =>
  buildPerformanceQuillOptions({
    placeholder: props.placeholder || props.label || 'Enter text…',
    readOnly: props.disabled,
  }),
)

onMounted(async () => {
  await patchQuillExternalLinks()
  editorReady.value = true
})

function onContentUpdate(html: string): void {
  emit('update:modelValue', html ?? '')
}

function onReady(quill: unknown): void {
  const q = quill as { root: HTMLElement; on: (e: string, fn: () => void) => void }
  setupQuillAutoGrow(q, editorMinPx.value)
}
</script>

<template>
  <div class="portal-rich-field" :class="{ 'portal-rich-field--disabled': disabled }">
    <div v-if="label" class="portal-rich-field__label">{{ label }}</div>
    <QuillEditor
      v-if="editorReady"
      :content="modelValue"
      content-type="html"
      theme="snow"
      class="portal-rich-editor"
      :style="editorStyle"
      :options="quillOptions"
      :read-only="disabled"
      @update:content="onContentUpdate"
      @ready="onReady"
    />
    <div
      v-else
      class="portal-rich-editor portal-rich-editor--loading"
      :style="{ minHeight: `${editorMinPx}px` }"
      aria-busy="true"
    >
      Loading editor…
    </div>
    <div v-if="hint" class="portal-rich-field__hint">{{ hint }}</div>
  </div>
</template>

<style scoped>
.portal-rich-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  width: 100%;
}

.portal-rich-field__label {
  font-size: var(--portal-label-size, 0.775rem);
  font-weight: var(--portal-label-weight, 650);
  color: var(--portal-label-color, #455a64);
  line-height: 1.3;
}

.portal-rich-field__hint {
  font-size: 0.75rem;
  color: #78909c;
  line-height: 1.35;
}

.portal-rich-editor {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
}

.portal-rich-editor--loading {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #78909c;
  font-size: 0.875rem;
  background: #f8fafc;
  border: 1px solid var(--portal-field-border, #b0bec5);
  border-radius: 8px;
}

.portal-rich-editor :deep(.ql-toolbar.ql-snow) {
  border: 1px solid var(--portal-field-border, #b0bec5);
  border-bottom: 0;
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
  background: #f8fafc;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px;
  padding: 0.3rem 0.45rem;
}

.portal-rich-editor :deep(.ql-toolbar .ql-formats) {
  margin-right: 0.4rem;
}

.portal-rich-editor :deep(.ql-container.ql-snow) {
  height: auto;
  border: 1px solid var(--portal-field-border, #b0bec5);
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
  font-size: 0.9rem;
  font-family: inherit;
}

.portal-rich-editor :deep(.ql-editor) {
  min-height: var(--portal-rich-editor-min, 104px);
  height: auto;
  line-height: 1.55;
  overflow-y: hidden;
  padding: 0.7rem 0.85rem;
  color: var(--portal-field-text, #37474f);
}

.portal-rich-editor :deep(.ql-editor.ql-blank::before) {
  font-style: normal;
  color: #90a4ae;
}

.portal-rich-field--disabled :deep(.ql-toolbar) {
  display: none;
}

.portal-rich-field--disabled :deep(.ql-container.ql-snow) {
  border-radius: 8px;
  background: #fafbfc;
}
</style>
