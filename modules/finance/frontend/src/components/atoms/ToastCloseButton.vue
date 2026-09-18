<script setup lang="ts">
withDefaults(
  defineProps<{
    label?: string
  }>(),
  { label: 'Dismiss notification' },
)

const emit = defineEmits<{
  click: []
}>()
</script>

<template>
  <button type="button" class="toast-close" :aria-label="label" @click="emit('click')">
    &times;
  </button>
</template>
