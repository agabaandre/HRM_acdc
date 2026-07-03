<script setup lang="ts">
import { computed } from 'vue'

type SelectItem = { label: string; value: string | number }

const model = defineModel<string | number | (string | number)[] | null>({ default: null })

const props = withDefaults(
  defineProps<{
    items?: SelectItem[]
    multiple?: boolean
    placeholder?: string
    disabled?: boolean
    size?: string
  }>(),
  {
    items: () => [],
    multiple: false,
    disabled: false,
  },
)

const vItems = computed(() => props.items ?? [])

const density = computed(() => (props.size === 'lg' ? 'default' : 'compact'))
</script>

<template>
  <v-select
    v-model="model"
    :items="vItems"
    item-title="label"
    item-value="value"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    :density="density"
    :chips="multiple"
    closable-chips
    class="hd-v-select w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
