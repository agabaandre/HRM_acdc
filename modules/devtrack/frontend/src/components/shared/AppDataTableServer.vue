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
    page?: number
    itemsPerPage?: number
  }>(),
  {
    headers: () => [],
    hideRowNumber: false,
    page: 1,
    itemsPerPage: 10,
  },
)

defineOptions({ inheritAttrs: false })

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
  const perPage = props.itemsPerPage
  if (perPage === -1) return index + 1
  return (props.page - 1) * perPage + index + 1
}

const passthroughSlots = computed(() =>
  Object.keys(slots).filter((name) => name !== 'item.index' || props.hideRowNumber),
)
</script>

<template>
  <v-data-table-server v-bind="$attrs" :headers="headersWithIndex">
    <template v-for="name in passthroughSlots" :key="name" #[name]="slotData">
      <slot :name="name" v-bind="slotData || {}" />
    </template>
    <template v-if="!hideRowNumber" #item.index="{ index }">
      <span class="text-medium-emphasis">{{ rowNumber(index) }}</span>
    </template>
  </v-data-table-server>
</template>
