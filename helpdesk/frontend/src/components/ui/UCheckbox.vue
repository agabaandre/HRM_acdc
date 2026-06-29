<script setup lang="ts">
const model = defineModel<boolean | 'indeterminate'>({ default: false })

defineProps<{
  label?: string
  disabled?: boolean
}>()
</script>

<template>
  <v-checkbox
    :model-value="model === true"
    :indeterminate="model === 'indeterminate'"
    :label="label"
    :disabled="disabled"
    color="primary"
    hide-details
    class="hd-v-checkbox"
    @update:model-value="(v: boolean | null) => (model = v === null ? 'indeterminate' : v)"
  />
</template>
