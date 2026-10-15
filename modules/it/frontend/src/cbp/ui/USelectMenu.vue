<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey, fieldRequiredKey } from './formContext'

type SelectItem = { label: string; value: string | number | null; subtitle?: string | null }

const model = defineModel<string | number | (string | number)[] | null>({ default: null })
const search = defineModel<string>('search', { default: '' })

const props = withDefaults(
  defineProps<{
    items?: SelectItem[]
    label?: string
    icon?: string
    multiple?: boolean
    searchable?: boolean
    placeholder?: string
    disabled?: boolean
    valueKey?: string
    clearable?: boolean
    hideDetailsLabel?: boolean
    noFilter?: boolean
  }>(),
  {
    items: () => [],
    multiple: false,
    searchable: false,
    disabled: false,
    valueKey: 'value',
    clearable: true,
    hideDetailsLabel: false,
    noFilter: false,
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)

const fieldLabel = computed(() => {
  if (props.hideDetailsLabel) return undefined
  return props.label ?? injectedLabel?.value
})
const fieldRequired = computed(() => injectedRequired?.value ?? false)
const prependIcon = computed(() => {
  const icon = props.icon
  if (!icon) return undefined
  return icon.startsWith('mdi-') ? icon : undefined
})
const persistFloatLabel = computed(
  () => Boolean(props.placeholder?.trim()) && !props.hideDetailsLabel && Boolean(fieldLabel.value),
)

function filterItems(_value: string, query: string, item?: { raw?: SelectItem; title?: string }): boolean {
  const q = query.trim().toLowerCase()
  if (!q) return true
  const raw = item?.raw
  const hay = `${raw?.label ?? item?.title ?? ''} ${raw?.subtitle ?? ''} ${raw?.value ?? ''}`.toLowerCase()
  return hay.includes(q)
}
</script>

<template>
  <v-autocomplete
    v-if="searchable"
    v-model="model"
    v-model:search="search"
    :items="items"
    item-title="label"
    :item-value="valueKey"
    :label="fieldLabel"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    :required="fieldRequired"
    :clearable="clearable"
    :prepend-inner-icon="prependIcon"
    :chips="multiple"
    closable-chips
    density="compact"
    variant="outlined"
    hide-details="auto"
    :no-filter="noFilter"
    :custom-filter="noFilter ? undefined : filterItems"
    :active="persistFloatLabel ? true : undefined"
    :persistent-placeholder="persistFloatLabel"
    class="hd-v-select-menu w-full"
    :class="{ 'hd-v-input--persist-label': persistFloatLabel }"
    v-bind="$attrs"
  >
    <template v-if="$slots.item" #item="slotProps">
      <slot name="item" v-bind="slotProps" />
    </template>
    <template v-else #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :subtitle="item.raw?.subtitle || undefined" />
    </template>
  </v-autocomplete>
  <v-select
    v-else
    v-model="model"
    :items="items"
    item-title="label"
    :item-value="valueKey"
    :label="fieldLabel"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    :required="fieldRequired"
    :clearable="clearable && !multiple"
    :prepend-inner-icon="prependIcon"
    :chips="multiple"
    closable-chips
    density="compact"
    variant="outlined"
    hide-details="auto"
    :active="persistFloatLabel ? true : undefined"
    :persistent-placeholder="persistFloatLabel"
    class="hd-v-select-menu w-full"
    :class="{ 'hd-v-input--persist-label': persistFloatLabel }"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
