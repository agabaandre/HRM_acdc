<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey, fieldRequiredKey } from './formContext'

const model = defineModel<string | null>({ default: '' })

const props = withDefaults(
  defineProps<{
    label?: string
    disabled?: boolean
    placeholder?: string
    clearable?: boolean
    required?: boolean
  }>(),
  {
    clearable: true,
    required: false,
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)
const fieldLabel = computed(() => props.label ?? injectedLabel?.value)
const fieldRequired = computed(() => props.required || injectedRequired?.value || false)
const persistFloatLabel = computed(() => Boolean(props.placeholder?.trim()))

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
    :label="fieldRequired ? undefined : fieldLabel"
    :disabled="disabled"
    :placeholder="placeholder"
    :clearable="clearable"
    :required="fieldRequired"
    :active="persistFloatLabel ? true : undefined"
    :persistent-placeholder="persistFloatLabel"
    prepend-icon=""
    prepend-inner-icon="mdi-calendar"
    class="hd-v-date-input w-full"
    :class="{ 'hd-v-input--persist-label': persistFloatLabel }"
    v-bind="$attrs"
  >
    <template v-if="fieldRequired && fieldLabel" #label>
      {{ fieldLabel }} <span class="hd-req-asterisk">*</span>
    </template>
  </v-date-input>
</template>

<style scoped>
.w-full {
  width: 100%;
}
.hd-req-asterisk {
  color: rgb(var(--v-theme-error));
}
</style>
