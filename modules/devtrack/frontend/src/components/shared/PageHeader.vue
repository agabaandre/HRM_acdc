<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
    <div>
      <h1 class="page-title mb-1">{{ title }}</h1>
      <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-0">{{ subtitle }}</p>
    </div>
    <div class="d-flex ga-2">
      <slot name="actions" />
    </div>
  </div>
</template>
