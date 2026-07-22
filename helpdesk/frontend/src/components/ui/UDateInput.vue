<script setup lang="ts">
import { computed } from 'vue'

const model = defineModel<string | null>({ default: '' })

withDefaults(
  defineProps<{
    label?: string
    disabled?: boolean
    placeholder?: string
    clearable?: boolean
  }>(),
  {
    clearable: true,
  },
)

function toIso(d: Date | null | undefined): string {
  if (!d || Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function fromIso(iso: string | null | undefined): Date | null {
  if (!iso || !/^\d{4}-\d{2}-\d{2}/.test(iso)) return null
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  if (!y || !m || !d) return null
  const date = new Date(y, m - 1, d)
  return Number.isNaN(date.getTime()) ? null : date
}

const dateModel = computed<Date | null>({
  get: () => fromIso(model.value),
  set: (v) => {
    model.value = toIso(v)
  },
})
</script>

<template>
  <v-date-input
    v-model="dateModel"
    :label="label"
    :disabled="disabled"
    :placeholder="placeholder"
    :clearable="clearable"
    prepend-icon=""
    prepend-inner-icon="mdi-calendar"
    class="hd-v-date-input w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
