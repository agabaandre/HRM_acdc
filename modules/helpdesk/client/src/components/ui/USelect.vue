<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey, fieldRequiredKey } from './formContext'
import { mapLucideIcon } from './iconMap'

type SelectItem = { label: string; value: string | number }

const model = defineModel<string | number | (string | number)[] | null>({ default: null })

const props = withDefaults(
  defineProps<{
    items?: SelectItem[]
    label?: string
    icon?: string
    multiple?: boolean
    placeholder?: string
    disabled?: boolean
    size?: string
    valueKey?: string
    clearable?: boolean
  }>(),
  {
    items: () => [],
    multiple: false,
    disabled: false,
    clearable: true,
    valueKey: 'value',
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)

const fieldLabel = computed(() => props.label ?? injectedLabel?.value)
const fieldRequired = computed(() => injectedRequired?.value ?? false)
const prependIcon = computed(() => mapLucideIcon(props.icon))
const vItems = computed(() => props.items ?? [])
const persistFloatLabel = computed(() => Boolean(props.placeholder?.trim()))

const density = computed(() => (props.size === 'lg' ? 'default' : 'compact'))
</script>

<template>
  <v-select
    v-model="model"
    :items="vItems"
    item-title="label"
    :item-value="props.valueKey"
    :label="fieldLabel"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    :density="density"
    :required="fieldRequired"
    :clearable="clearable && !multiple"
    :prepend-inner-icon="prependIcon"
    :chips="multiple"
    closable-chips
    :active="persistFloatLabel ? true : undefined"
    :persistent-placeholder="persistFloatLabel"
    class="hd-v-select w-full"
    :class="{ 'hd-v-input--persist-label': persistFloatLabel }"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
