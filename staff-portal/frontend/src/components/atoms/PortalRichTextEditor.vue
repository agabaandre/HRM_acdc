<script setup lang="ts">
import { QuillEditor, loadQuill } from '@vueup/vue-quill'
import { computed, onMounted } from 'vue'
import {
  buildPerformanceQuillOptions,
  editorMinHeightPx,
  patchQuillExternalLinks,
  setupQuillAutoGrow,
} from '@/lib/richText'

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    label?: string
    placeholder?: string
    disabled?: boolean
    minRows?: number
    hint?: string
  }>(),
  {
    modelValue: '',
    placeholder: '',
    disabled: false,
    minRows: 4,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const htmlContent = computed(() => props.modelValue ?? '')
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

onMounted(() => {
  void patchQuillExternalLinks(loadQuill)
})

function onContentUpdate(html: string): void {
  emit('update:modelValue', html ?? '')
}

function onReady(quill: unknown): void {
  const q = quill as { root: HTMLElement; on: (e: string, fn: () => void) => void }
  if (!q?.root) {
    return
  }
  setupQuillAutoGrow(q, editorMinPx.value)
}
</script>

<template>
  <div
    class="portal-rich-field"
    :class="{ 'portal-rich-field--disabled': disabled }"
    :style="editorStyle"
  >
    <div v-if="label" class="portal-rich-field__label">{{ label }}</div>
    <div class="portal-rich-editor">
      <QuillEditor
        :content="htmlContent"
        content-type="html"
        theme="snow"
        :options="quillOptions"
        :read-only="disabled"
        @update:content="onContentUpdate"
        @ready="onReady"
      />
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
  width: 100%;
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
  height: auto !important;
  min-height: var(--portal-rich-editor-min, 104px);
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
