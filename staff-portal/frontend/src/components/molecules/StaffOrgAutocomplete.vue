<script setup lang="ts">
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { toAbsoluteMediaUrl } from '@/lib/personAvatar'

export type StaffSelectOption = {
  title: string
  value: number
  email?: string | null
  photo_url?: string | null
}

const model = defineModel<number | null>({ default: null })

withDefaults(
  defineProps<{
    items: StaffSelectOption[]
    label: string
    placeholder?: string
    filterFn?: (itemTitle: string, queryText: string, item: unknown) => boolean
  }>(),
  {
    placeholder: 'Search staff…',
  },
)

function photoUrl(raw: StaffSelectOption | undefined): string | null {
  return toAbsoluteMediaUrl(raw?.photo_url)
}
</script>

<template>
  <v-autocomplete
    v-model="model"
    :items="items"
    item-title="title"
    item-value="value"
    :custom-filter="filterFn"
    :label="label"
    :placeholder="placeholder"
    density="compact"
    hide-details="auto"
    clearable
    auto-select-first
    class="bg-white staff-org-autocomplete"
  >
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined">
        <template #prepend>
          <CbpAvatar
            size="sm"
            class="me-2"
            :name="item.raw.title"
            :image-url="photoUrl(item.raw)"
          />
        </template>
      </v-list-item>
    </template>
    <template #selection="{ item }">
      <div class="d-flex align-center ga-2 staff-org-autocomplete__selection">
        <CbpAvatar size="xs" :name="item.raw.title" :image-url="photoUrl(item.raw)" />
        <span class="text-truncate">{{ item.title }}</span>
      </div>
    </template>
  </v-autocomplete>
</template>

<style scoped>
.staff-org-autocomplete__selection {
  max-width: 100%;
  min-width: 0;
}
</style>
