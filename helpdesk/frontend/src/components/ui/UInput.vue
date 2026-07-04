<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey, fieldRequiredKey } from './formContext'
import { mapLucideIcon } from './iconMap'

const model = defineModel<string | number | null>({ default: '' })

const props = withDefaults(
  defineProps<{
    type?: string
    label?: string
    placeholder?: string
    disabled?: boolean
    min?: number | string
    maxlength?: number | string
    icon?: string
    autocomplete?: string
    ariaLabel?: string
    size?: string
    clearable?: boolean
  }>(),
  {
    type: 'text',
    disabled: false,
    clearable: false,
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)

const fieldLabel = computed(() => props.label ?? injectedLabel?.value)
const fieldRequired = computed(() => injectedRequired?.value ?? false)
const prependIcon = computed(() => mapLucideIcon(props.icon))

const inputType = computed(() => {
  if (props.type === 'search') return 'text'
  return props.type
})

const density = computed(() => (props.size === 'lg' ? 'default' : 'compact'))
</script>

<template>
  <v-text-field
    v-model="model"
    :label="fieldLabel"
    :type="inputType"
    :placeholder="placeholder"
    :disabled="disabled"
    :min="min"
    :maxlength="maxlength"
    :prepend-inner-icon="prependIcon"
    :autocomplete="autocomplete"
    :density="density"
    :required="fieldRequired"
    :clearable="clearable"
    :aria-label="ariaLabel ?? fieldLabel ?? ($attrs['aria-label'] as string | undefined)"
    class="hd-v-input w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
