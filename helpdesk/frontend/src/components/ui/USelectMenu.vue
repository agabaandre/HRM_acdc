<script setup lang="ts">
type SelectItem = { label: string; value: string | number }

const model = defineModel<string | number | (string | number)[] | null>({ default: null })

withDefaults(
  defineProps<{
    items?: SelectItem[]
    multiple?: boolean
    searchable?: boolean
    placeholder?: string
    disabled?: boolean
    size?: string
    valueKey?: string
  }>(),
  {
    items: () => [],
    multiple: false,
    searchable: false,
    disabled: false,
    valueKey: 'value',
  },
)
</script>

<template>
  <v-autocomplete
    v-if="searchable"
    v-model="model"
    :items="items"
    item-title="label"
    :item-value="valueKey"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    :chips="multiple"
    closable-chips
    clearable
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
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
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
