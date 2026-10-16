<script setup lang="ts">
defineProps<{
  message: string
  tone?: 'default' | 'error' | 'muted'
}>()
</script>

<template>
  <p
    class="portal-status-text"
    :class="{
      'text-error': tone === 'error',
      'text-medium-emphasis': tone === 'muted' || !tone,
    }"
  >
    {{ message }}
  </p>
</template>
