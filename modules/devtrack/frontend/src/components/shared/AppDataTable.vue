<script setup lang="ts">
import { computed, useSlots } from 'vue'

type TableHeader = {
  title?: string
  key?: string
  value?: string
  width?: string | number
  sortable?: boolean
  [key: string]: unknown
}

const props = withDefaults(
  defineProps<{
    headers?: TableHeader[]
    hideRowNumber?: boolean
  }>(),
  {
    headers: () => [],
    hideRowNumber: false,
  },
)

defineOptions({ inheritAttrs: false })

const page = defineModel<number>('page', { default: 1 })
const itemsPerPage = defineModel<number>('itemsPerPage', { default: 10 })
const slots = useSlots()

const headersWithIndex = computed(() => {
  const headers = props.headers ?? []
  if (props.hideRowNumber) return headers
  if (headers.some((header) => header.key === 'index')) return headers
  return [
    { title: '#', key: 'index', width: 56, sortable: false },
    ...headers,
  ]
})

function rowNumber(index: number): number {
  const perPage = itemsPerPage.value
  if (perPage === -1) return index + 1
  return (page.value - 1) * perPage + index + 1
}

const passthroughSlots = computed(() =>
  Object.keys(slots).filter((name) => name !== 'item.index' || props.hideRowNumber),
)
</script>

<template>
  <v-data-table
    v-bind="$attrs"
    :headers="headersWithIndex"
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
  >
    <template v-for="name in passthroughSlots" :key="name" #[name]="slotData">
      <slot :name="name" v-bind="slotData || {}" />
    </template>
    <template v-if="!hideRowNumber" #item.index="{ index }">
      <span class="text-medium-emphasis">{{ rowNumber(index) }}</span>
    </template>
  </v-data-table>
</template>
