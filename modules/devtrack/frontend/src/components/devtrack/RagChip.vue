<script setup lang="ts">
import { ragColor } from '@/lib/devtrack'

const props = withDefaults(
  defineProps<{
    status?: string | null
    label?: string | null
    small?: boolean
  }>(),
  { small: false },
)
</script>

<template>
  <v-chip
    :size="small ? 'x-small' : 'small'"
    :color="ragColor(props.status)"
    variant="flat"
    class="text-uppercase font-weight-bold"
  >
    {{ label || status || 'n/a' }}
  </v-chip>
</template>
