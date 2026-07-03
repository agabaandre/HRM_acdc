<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: string
  }>(),
  {
    variant: 'outline',
  },
)

const vVariant = computed(() => {
  if (props.variant === 'outline') return 'outlined'
  if (props.variant === 'subtle') return 'tonal'
  return 'elevated'
})
</script>

<template>
  <v-card class="hd-v-card" :variant="vVariant" v-bind="$attrs">
    <v-card-title v-if="$slots.header" class="hd-v-card__header">
      <slot name="header" />
    </v-card-title>
    <v-card-text v-if="$slots.default" class="hd-v-card__body">
      <slot />
    </v-card-text>
    <v-card-actions v-if="$slots.footer" class="hd-v-card__footer">
      <slot name="footer" />
    </v-card-actions>
  </v-card>
</template>

<style scoped>
.hd-v-card__header {
  font-weight: 700;
  padding-bottom: 0;
}
.hd-v-card__body {
  padding-top: 0.75rem;
}
.hd-v-card__footer {
  padding-top: 0;
}
</style>
