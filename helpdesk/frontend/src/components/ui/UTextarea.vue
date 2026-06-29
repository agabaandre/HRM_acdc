<script setup lang="ts">
import { computed } from 'vue'

const model = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    rows?: number
    placeholder?: string
    disabled?: boolean
    autoGrow?: boolean
    maxRows?: number
  }>(),
  {
    rows: 3,
    disabled: false,
    autoGrow: false,
  },
)

const maxRowsValue = computed(() => props.maxRows ?? props.rows + 2)
</script>

<template>
  <v-textarea
    v-model="model"
    :rows="rows"
    :placeholder="placeholder"
    :disabled="disabled"
    :auto-grow="autoGrow"
    :max-rows="autoGrow ? maxRowsValue : undefined"
    density="compact"
    hide-details="auto"
    class="hd-v-textarea w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.hd-v-textarea :deep(textarea) {
  resize: vertical;
  min-height: 0;
  field-sizing: fixed;
}

.hd-v-textarea :deep(.v-field__input) {
  min-height: unset;
  padding-top: 8px;
  padding-bottom: 8px;
}

.w-full {
  width: 100%;
}
</style>
