<script setup lang="ts">
import { computed } from 'vue'
import { hasRichTextContent, richTextToHtml } from '@/lib/richText'
import '@vueup/vue-quill/dist/vue-quill.snow.css'

const props = withDefaults(
  defineProps<{
    value?: string | null
    label?: string
    emptyText?: string
    compact?: boolean
  }>(),
  {
    emptyText: '—',
    compact: false,
  },
)

const hasContent = computed(() => hasRichTextContent(props.value))
const html = computed(() => richTextToHtml(props.value))
</script>

<template>
  <div class="portal-rich-display">
    <div v-if="label" class="portal-rich-display__label">{{ label }}</div>
    <div
      v-if="hasContent"
      class="portal-rich-display__body ql-editor"
      :class="{ 'portal-rich-display__body--compact': compact }"
      v-html="html"
    />
    <div v-else class="portal-rich-display__empty">{{ emptyText }}</div>
  </div>
</template>

<style scoped>
.portal-rich-display {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  width: 100%;
}

.portal-rich-display__label {
  font-size: var(--portal-label-size, 0.775rem);
  font-weight: var(--portal-label-weight, 650);
  color: var(--portal-label-color, #455a64);
  line-height: 1.3;
}

.portal-rich-display__body {
  border: 1px solid var(--portal-field-border, #b0bec5);
  border-radius: 8px;
  background: #fafbfc;
  min-height: 4.5rem;
  padding: 0.7rem 0.85rem !important;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--portal-field-text, #37474f);
  overflow-wrap: anywhere;
}

.portal-rich-display__body :deep(p) {
  margin: 0 0 0.45em;
}

.portal-rich-display__body :deep(p:last-child) {
  margin-bottom: 0;
}

.portal-rich-display__body :deep(ul),
.portal-rich-display__body :deep(ol) {
  padding-left: 1.25rem;
  margin: 0 0 0.45em;
}

.portal-rich-display__body--compact {
  border: 0;
  background: transparent;
  min-height: 0;
  padding: 0 !important;
  border-radius: 0;
}

.portal-rich-display__empty {
  border: 1px dashed #cfd8dc;
  border-radius: 8px;
  min-height: 4.5rem;
  padding: 0.7rem 0.85rem;
  color: #90a4ae;
  font-size: 0.875rem;
}
</style>
