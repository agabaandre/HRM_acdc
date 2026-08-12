<script setup lang="ts">
import { computed } from 'vue'
import {
  staffDirectoryColumns,
  type StaffDirectoryColumnKey,
} from '@/lib/staffDirectoryColumns'

const props = defineProps<{
  modelValue: StaffDirectoryColumnKey[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: StaffDirectoryColumnKey[]]
}>()

const selected = computed(() => new Set(props.modelValue))

function toggleColumn(key: StaffDirectoryColumnKey): void {
  const next = new Set(props.modelValue)
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }
  emit('update:modelValue', Array.from(next))
}
</script>

<template>
  <v-menu location="bottom end">
    <template #activator="{ props: menuProps }">
      <v-btn v-bind="menuProps" size="small" variant="outlined">Columns</v-btn>
    </template>

    <v-card min-width="240" variant="outlined">
      <v-list density="compact" slim>
        <v-list-subheader>Visible columns</v-list-subheader>
        <v-list-item
          v-for="column in staffDirectoryColumns"
          :key="column.key"
          :title="column.label"
          @click="toggleColumn(column.key)"
        >
          <template #prepend>
            <v-checkbox-btn :model-value="selected.has(column.key)" />
          </template>
        </v-list-item>
      </v-list>
    </v-card>
  </v-menu>
</template>
