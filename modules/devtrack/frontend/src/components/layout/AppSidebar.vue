<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { sidebarItems } from '@/config/sidebar'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const items = computed(() => {
  const isAdmin = !!auth.user?.is_admin
  const visible = sidebarItems.filter((item) => !item.adminOnly || isAdmin)

  // Drop section headers that have no visible items under them.
  return visible.filter((item, index) => {
    if (!item.header) return true
    for (let i = index + 1; i < visible.length; i++) {
      if (visible[i].header) return false
      if (visible[i].to) return true
    }
    return false
  })
})
</script>

<template>
  <v-list nav density="comfortable" class="px-3 py-2">
    <template v-for="(item, index) in items" :key="`${item.title ?? item.header}-${index}`">
      <v-list-subheader v-if="item.header" class="text-uppercase font-weight-bold">
        {{ item.header }}
      </v-list-subheader>
      <v-list-item
        v-else-if="item.to"
        :to="item.to"
        :prepend-icon="item.icon"
        :title="item.title"
        rounded="lg"
        color="primary"
        :active="typeof item.to === 'object' && item.to.name === route.name"
      />
    </template>
  </v-list>
</template>
