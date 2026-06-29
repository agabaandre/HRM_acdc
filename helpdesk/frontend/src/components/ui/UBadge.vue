<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    color?: string
    variant?: string
    size?: string
  }>(),
  {
    color: 'primary',
    variant: 'flat',
    size: 'default',
  },
)

const vColor = computed(() => {
  if (props.color === 'neutral') return 'grey'
  if (props.color === 'warning') return 'warning'
  if (props.color === 'error') return 'error'
  if (props.color === 'primary') return 'primary'
  return props.color
})

const vVariant = computed(() => (props.variant === 'subtle' ? 'tonal' : 'flat'))

const vSize = computed(() => {
  if (props.size === 'xs') return 'x-small'
  if (props.size === 'sm') return 'small'
  return 'default'
})
</script>

<template>
  <v-chip :color="vColor" :variant="vVariant" :size="vSize" class="hd-v-badge">
    <slot />
  </v-chip>
</template>

<style scoped>
.hd-v-badge {
  font-weight: 600;
}
</style>
