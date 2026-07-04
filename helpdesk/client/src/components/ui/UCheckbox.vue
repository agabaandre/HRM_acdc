<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey } from './formContext'

const model = defineModel<boolean | 'indeterminate'>({ default: false })

const props = defineProps<{
  label?: string
  disabled?: boolean
}>()

const injectedLabel = inject(fieldLabelKey, undefined)
const fieldLabel = computed(() => props.label ?? injectedLabel?.value)
</script>

<template>
  <v-checkbox
    :model-value="model === true"
    :indeterminate="model === 'indeterminate'"
    :label="$slots.label ? undefined : fieldLabel"
    :disabled="disabled"
    color="primary"
    hide-details
    class="hd-v-checkbox"
    @update:model-value="(v: boolean | null) => (model = v === null ? 'indeterminate' : v)"
  >
    <template v-if="$slots.label" #label>
      <slot name="label" />
    </template>
  </v-checkbox>
</template>

<style scoped>
.hd-v-checkbox :deep(.v-label) {
  opacity: 1 !important;
  color: #0f172a !important;
}

.hd-v-checkbox :deep(.v-label *) {
  opacity: 1 !important;
}

html.helpdesk-theme-dark .hd-v-checkbox :deep(.v-label) {
  color: #e2e8f0 !important;
}
</style>
