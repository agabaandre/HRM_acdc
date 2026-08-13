<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'

const route = useRoute()
const title = computed(() => (route.meta.title as string) ?? 'Staff Portal')
const legacyPath = computed(() => {
  const base = (import.meta.env.VITE_STAFF_PORTAL_API_BASE_URL as string) || '/staff/staff-portal/backend'
  return `${base.replace(/\/$/, '')}${route.path}`
})
</script>

<template>
  <div>
    <CbpPageHeading
      :title="title"
      subtitle="This module is being migrated to the Vue shell. Use the legacy Livewire route until porting is complete."
    />
    <p class="text-medium-emphasis">
      Legacy URL:
      <code>{{ legacyPath }}</code>
    </p>
  </div>
</template>
