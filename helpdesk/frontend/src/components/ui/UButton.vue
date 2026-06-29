<script setup lang="ts">
import { computed } from 'vue'
import { mapLucideIcon } from './iconMap'

const props = withDefaults(
  defineProps<{
    color?: string
    variant?: string
    size?: string
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
    icon?: string
    to?: string | Record<string, unknown>
    href?: string
    label?: string
    block?: boolean
  }>(),
  {
    color: 'primary',
    variant: 'flat',
    size: 'default',
    type: 'button',
    loading: false,
    disabled: false,
  },
)

const vColor = computed(() => {
  if (props.color === 'neutral') return undefined
  if (props.color === 'error') return 'error'
  return props.color === 'primary' ? 'primary' : props.color
})

const vVariant = computed(() => {
  switch (props.variant) {
    case 'outline':
      return 'outlined'
    case 'soft':
      return 'tonal'
    case 'link':
    case 'ghost':
      return 'text'
    default:
      return 'flat'
  }
})

const vSize = computed(() => {
  if (props.size === 'xs' || props.size === 'sm') return 'small'
  return 'default'
})

const prependIcon = computed(() => mapLucideIcon(props.icon))
</script>

<template>
  <v-btn
    :color="vColor"
    :variant="vVariant"
    :size="vSize"
    :type="type"
    :loading="loading"
    :disabled="disabled"
    :to="to"
    :href="href"
    :prepend-icon="prependIcon"
    :block="block"
    class="hd-v-btn"
  >
    <slot>{{ label }}</slot>
  </v-btn>
</template>

<style scoped>
.hd-v-btn {
  text-transform: none;
  letter-spacing: 0.01em;
  font-weight: 600;
}
</style>
