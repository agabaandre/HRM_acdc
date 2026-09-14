<script setup lang="ts">
import { useLocaleStore } from '@/stores/locale'

defineProps<{
  theme: 'dark' | 'light'
}>()

const emit = defineEmits<{
  'update:theme': [value: 'dark' | 'light']
}>()

const locale = useLocaleStore()
</script>

<template>
  <div class="cbp-theme-switch" role="group" :aria-label="locale.t('chrome.theme', 'Colour theme')">
    <button
      type="button"
      class="cbp-theme-btn"
      :class="{ active: theme === 'dark' }"
      :aria-pressed="theme === 'dark'"
      @click="emit('update:theme', 'dark')"
    >
      {{ locale.t('chrome.dark', 'Dark') }}
    </button>
    <button
      type="button"
      class="cbp-theme-btn"
      :class="{ active: theme === 'light' }"
      :aria-pressed="theme === 'light'"
      @click="emit('update:theme', 'light')"
    >
      {{ locale.t('chrome.light', 'Light') }}
    </button>
  </div>
</template>
