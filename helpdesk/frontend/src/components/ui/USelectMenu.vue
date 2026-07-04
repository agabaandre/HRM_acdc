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
    searchable?: boolean
    placeholder?: string
    disabled?: boolean
    size?: string
    valueKey?: string
    clearable?: boolean
  }>(),
  {
    items: () => [],
    multiple: false,
    searchable: false,
    disabled: false,
    valueKey: 'value',
    clearable: true,
  },
)

const injectedLabel = inject(fieldLabelKey, undefined)
const injectedRequired = inject(fieldRequiredKey, undefined)

const fieldLabel = computed(() => props.label ?? injectedLabel?.value)
const fieldRequired = computed(() => injectedRequired?.value ?? false)
const prependIcon = computed(() => mapLucideIcon(props.icon))
</script>

<template>
  <v-autocomplete
    v-if="searchable"
    v-model="model"
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
    class="hd-v-select-menu w-full"
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
    class="hd-v-select-menu w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
