<script setup lang="ts">
const model = defineModel<string>({ default: '#000000' })

withDefaults(
  defineProps<{
    label?: string
    disabled?: boolean
    placeholder?: string
  }>(),
  {
    placeholder: '#000000',
  },
)
</script>

<template>
  <v-color-input
    v-model="model"
    :label="label"
    :disabled="disabled"
    :placeholder="placeholder"
    class="hd-v-color-input w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
