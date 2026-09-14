<script setup lang="ts">
defineProps<{
  theme: 'dark' | 'light'
}>()

const emit = defineEmits<{
  'update:theme': [value: 'dark' | 'light']
}>()
</script>

<template>
  <div class="cbp-theme-switch" role="group" aria-label="Colour theme">
    <button
      type="button"
      class="cbp-theme-btn"
      :class="{ active: theme === 'dark' }"
      :aria-pressed="theme === 'dark'"
      @click="emit('update:theme', 'dark')"
    >
      Dark
    </button>
    <button
      type="button"
      class="cbp-theme-btn"
      :class="{ active: theme === 'light' }"
      :aria-pressed="theme === 'light'"
      @click="emit('update:theme', 'light')"
    >
      Light
    </button>
  </div>
</template>
