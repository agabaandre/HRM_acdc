<script setup lang="ts">
const model = defineModel<boolean>({ default: false })

defineProps<{
  label?: string
  disabled?: boolean
}>()
</script>

<template>
  <v-switch
    v-model="model"
    :label="label"
    :disabled="disabled"
    color="primary"
    hide-details
    class="hd-v-switch"
  />
</template>
