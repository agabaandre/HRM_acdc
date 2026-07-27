<script setup lang="ts">
import { computed, inject } from 'vue'
import { fieldLabelKey, fieldRequiredKey } from './formContext'
import { mapLucideIcon } from './iconMap'

type SelectItem = { label: string; value: string | number }

const model = defineModel<string | number | (string | number)[] | null>({ default: null })
/** Search text for searchable menus (supports API-backed filtering via parent watchers). */
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
    size?: string
    valueKey?: string
    clearable?: boolean
    /** Prefer stacked UFormField label; hide floating Vuetify label. */
    hideDetailsLabel?: boolean
  }>(),
  {
    items: () => [],
    multiple: false,
    searchable: false,
    disabled: false,
    valueKey: 'value',
    clearable: true,
    hideDetailsLabel: false,
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)

const fieldLabel = computed(() => {
  if (props.hideDetailsLabel) return undefined
  return props.label ?? injectedLabel?.value
})
const fieldRequired = computed(() => injectedRequired?.value ?? false)
const prependIcon = computed(() => mapLucideIcon(props.icon))
const persistFloatLabel = computed(
  () => Boolean(props.placeholder?.trim()) && !props.hideDetailsLabel && Boolean(fieldLabel.value),
)
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
    no-filter
    :active="persistFloatLabel ? true : undefined"
    :persistent-placeholder="persistFloatLabel"
    class="hd-v-select-menu w-full"
    :class="{ 'hd-v-input--persist-label': persistFloatLabel }"
    v-bind="$attrs"
  />
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
