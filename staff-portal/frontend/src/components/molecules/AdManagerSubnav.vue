<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'

const route = useRoute()

const items = computed<PortalPillNavItem[]>(() => [
  {
    key: 'expired',
    to: '/admanager/expired',
    label: 'To disable',
    icon: 'fa-solid fa-user-slash',
    active: route.path.startsWith('/admanager/expired') || route.path === '/admanager',
  },
  {
    key: 'disabled',
    to: '/admanager/disabled',
    label: 'Disabled',
    icon: 'fa-solid fa-ban',
    active: route.path.startsWith('/admanager/disabled'),
  },
])
</script>

<template>
  <PortalPillSubnav :items="items" aria-label="AD manager lists" />
</template>
