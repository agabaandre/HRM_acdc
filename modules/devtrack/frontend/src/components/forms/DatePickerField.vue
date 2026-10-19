<script setup lang="ts">
import { computed, ref } from 'vue'

const model = defineModel<string | null>({ default: null })

withDefaults(
  defineProps<{
    label?: string
    density?: 'default' | 'comfortable' | 'compact'
    hideDetails?: boolean | 'auto'
    variant?: 'outlined' | 'plain' | 'filled' | 'underlined' | 'solo' | 'solo-inverted' | 'solo-filled'
    clearable?: boolean
    placeholder?: string
    error?: boolean
  }>(),
  {
    density: 'comfortable',
    hideDetails: 'auto',
    variant: 'outlined',
    clearable: true,
    placeholder: 'Select date',
  },
)

const menu = ref(false)

function parseLocal(iso: string | null | undefined): Date | null {
  if (!iso) return null
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso)
  if (!match) return null
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

function toIso(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function display(iso: string | null | undefined): string {
  const date = parseLocal(iso)
  if (!date) return ''
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(date)
}

const pickerValue = computed(() => parseLocal(model.value))

function applyValue(value: unknown) {
  const picked = Array.isArray(value) ? value[0] : value
  if (!picked) {
    model.value = null
    menu.value = false
    return
  }
  if (picked instanceof Date) {
    model.value = toIso(picked)
  } else if (typeof picked === 'string') {
    model.value = picked.slice(0, 10)
  }
  menu.value = false
}

function clear() {
  model.value = null
}
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" location="bottom">
    <template #activator="{ props: activatorProps }">
      <v-text-field
        :model-value="display(model)"
        :label="label"
        :density="density"
        :hide-details="hideDetails"
        :variant="variant"
        :placeholder="placeholder"
        :error="error"
        readonly
        :clearable="clearable && !!model"
        prepend-inner-icon="mdi-calendar"
        v-bind="activatorProps"
        @click:clear.stop.prevent="clear"
      />
    </template>
    <v-date-picker
      :model-value="pickerValue"
      color="primary"
      hide-header
      @update:model-value="applyValue"
    />
  </v-menu>
</template>
