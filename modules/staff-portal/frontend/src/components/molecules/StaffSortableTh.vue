<script setup lang="ts">
defineProps<{
  label: string
  active?: boolean
  direction?: 'asc' | 'desc'
  sortable?: boolean
  icon?: string
}>()

defineEmits<{
  click: []
}>()
</script>

<template>
  <th
    :class="{ 'portal-th-sortable': sortable !== false }"
    :aria-sort="active ? (direction === 'asc' ? 'ascending' : 'descending') : undefined"
  >
    <button
      v-if="sortable !== false"
      type="button"
      class="portal-th-sort-btn"
      @click="$emit('click')"
    >
      <span>{{ label }}</span>
      <i
        class="fa-solid"
        :class="[icon || 'fa-sort', { 'is-active': active }]"
        aria-hidden="true"
      />
    </button>
    <template v-else>{{ label }}</template>
  </th>
</template>
