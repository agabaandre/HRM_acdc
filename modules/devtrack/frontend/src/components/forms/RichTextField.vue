<script setup lang="ts">
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'

defineProps<{
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const toolbar = [
  [{ header: [1, 2, 3, false] }],
  ['bold', 'italic', 'underline', 'strike'],
  [{ color: [] }, { background: [] }],
  [{ script: 'sub' }, { script: 'super' }],
  [{ list: 'ordered' }, { list: 'bullet' }],
  [{ indent: '-1' }, { indent: '+1' }],
  [{ align: [] }],
  ['blockquote', 'code-block'],
  ['link', 'image'],
  ['clean'],
]

function onUpdate(value: string) {
  emit('update:modelValue', value === '<p><br></p>' ? '' : value)
}
</script>

<template>
  <div class="rich-text-field">
    <QuillEditor
      theme="snow"
      :content="modelValue"
      content-type="html"
      :placeholder="placeholder"
      :toolbar="toolbar"
      @update:content="onUpdate"
    />
  </div>
</template>

<style scoped>
.rich-text-field :deep(.ql-toolbar.ql-snow) {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px 4px 0 0;
  background: rgb(var(--v-theme-surface));
}

.rich-text-field :deep(.ql-container.ql-snow) {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0 0 4px 4px;
  min-height: 140px;
  font-family: inherit;
  font-size: 0.9375rem;
}

.rich-text-field :deep(.ql-editor) {
  min-height: 120px;
}

.rich-text-field :deep(.ql-editor.ql-blank::before) {
  font-style: normal;
  color: rgba(var(--v-theme-on-surface), 0.46);
}
</style>
