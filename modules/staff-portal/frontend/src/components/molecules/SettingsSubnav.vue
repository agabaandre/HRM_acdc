<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import { useLocaleStore } from '@/stores/locale'

const route = useRoute()
const locale = useLocaleStore()

const items = computed<PortalPillNavItem[]>(() => {
  const path = route.path
  return [
    {
      key: 'hub',
      label: locale.t('settings.nav_hub', 'All settings'),
      icon: 'bx bx-grid-alt',
      to: '/settings',
      active: path === '/settings',
    },
    {
      key: 'staff-api',
      label: locale.t('settings.nav_staff_api', 'Staff API'),
      icon: 'bx bx-key',
      to: '/settings/staff-api',
      active: path.startsWith('/settings/staff-api'),
    },
    {
      key: 'email',
      label: locale.t('settings.nav_email', 'Email settings'),
      icon: 'bx bx-envelope',
      to: '/settings/email-servers',
      active: path.startsWith('/settings/email-servers'),
    },
    {
      key: 'portal-modules',
      label: locale.t('settings.nav_modules', 'Portal modules'),
      icon: 'bx bx-toggle-left',
      to: '/settings/portal-modules',
      active: path.startsWith('/settings/portal-modules'),
    },
    {
      key: 'shared-storage',
      label: locale.t('settings.nav_storage', 'Shared storage'),
      icon: 'bx bx-hdd',
      to: '/settings/shared-storage',
      active: path.startsWith('/settings/shared-storage'),
    },
    {
      key: 'staff-jobs',
      label: locale.t('settings.nav_jobs', 'Staff jobs'),
      icon: 'bx bx-timer',
      to: '/settings/staff-jobs',
      active: path.startsWith('/settings/staff-jobs'),
    },
  ]
})
</script>

<template>
  <PortalPillSubnav class="mb-4" :items="items" :aria-label="locale.t('settings.subnav', 'Settings sections')" />
</template>
