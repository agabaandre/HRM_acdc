<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import { useLocaleStore } from '@/stores/locale'

const route = useRoute()
const locale = useLocaleStore()

const items = computed<PortalPillNavItem[]>(() => [
  {
    key: 'expired',
    to: '/admanager/expired',
    label: locale.t('subnav.to_disable', 'To disable'),
    icon: 'fa-solid fa-user-slash',
    active: route.path.startsWith('/admanager/expired') || route.path === '/admanager',
  },
  {
    key: 'disabled',
    to: '/admanager/disabled',
    label: locale.t('subnav.disabled', 'Disabled'),
    icon: 'fa-solid fa-ban',
    active: route.path.startsWith('/admanager/disabled'),
  },
])
</script>

<template>
  <PortalPillSubnav :items="items" :aria-label="locale.t('subnav.ad_lists', 'AD manager lists')" />
</template>
