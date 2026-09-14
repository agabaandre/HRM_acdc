<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    src?: string
    alt?: string
    size?: string
  }>(),
  {
    alt: '',
    size: 'default',
  },
)

const vSize = computed(() => {
  if (props.size === 'sm') return 32
  if (props.size === 'lg') return 48
  return 40
})
</script>

<template>
  <v-avatar :image="src" :size="vSize" color="primary" variant="tonal">
    <span v-if="!src && alt" class="hd-v-avatar__initials">{{ alt.slice(0, 1).toUpperCase() }}</span>
  </v-avatar>
</template>

<style scoped>
.hd-v-avatar__initials {
  font-weight: 700;
  font-size: 0.85rem;
}
</style>
