<script setup lang="ts">
import { computed } from 'vue'

const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(
  defineProps<{
    title?: string
    description?: string
    ui?: { content?: string }
  }>(),
  {
    title: '',
    description: '',
  },
)

const maxWidth = computed(() => {
  const cls = props.ui?.content ?? ''
  if (cls.includes('max-w-lg')) return 560
  if (cls.includes('max-w-xl')) return 720
  if (cls.includes('max-w-2xl')) return 960
  return 640
})
</script>

<template>
  <v-dialog
    v-model="open"
    class="hd-v-dialog"
    attach=".v-application"
    :max-width="maxWidth"
    scrollable
  >
    <v-card class="hd-v-modal" rounded="lg">
      <v-card-title v-if="title" class="hd-v-modal__title">
        {{ title }}
        <p v-if="description" class="hd-v-modal__desc">{{ description }}</p>
      </v-card-title>
      <v-divider v-if="title" />
      <v-card-text v-if="$slots.body" class="hd-v-modal__body">
        <slot name="body" />
      </v-card-text>
      <v-card-text v-else-if="$slots.default">
        <slot />
      </v-card-text>
      <v-divider v-if="$slots.footer" />
      <v-card-actions v-if="$slots.footer" class="hd-v-modal__footer">
        <slot name="footer" />
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.hd-v-modal__title {
  font-weight: 700;
  line-height: 1.3;
  white-space: normal;
  padding-bottom: 0.75rem !important;
}

.hd-v-modal__desc {
  margin: 0.35rem 0 0;
  font-size: 0.875rem;
  font-weight: 400;
  color: #64748b;
}

.hd-v-modal__body {
  padding-top: 1rem !important;
  padding-bottom: 1rem !important;
}

.hd-v-modal__footer {
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding: 0.75rem 1rem 1rem !important;
}

html.helpdesk-theme-dark .hd-v-modal__desc {
  color: #94a3b8;
}
</style>
