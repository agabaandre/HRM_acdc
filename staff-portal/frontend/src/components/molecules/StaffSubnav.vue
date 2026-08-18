<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(
  defineProps<{
    /** When false, hide the primary New staff action (e.g. already on that page). */
    showNewStaff?: boolean
  }>(),
  { showNewStaff: true },
)

const auth = useAuthStore()
const route = useRoute()

const items = computed<PortalPillNavItem[]>(() => {
  const canManage = auth.hasPermission(71)
  const canDirectory = auth.hasPermission(72) || canManage

  return [
    {
      key: 'directory',
      to: '/staff',
      label: 'Directory',
      icon: 'fa-solid fa-users',
      active: route.path === '/staff' || /^\/staff\/\d+(\/|$)/.test(route.path),
    },
    {
      key: 'history',
      to: '/staff/history',
      label: 'Staff history',
      icon: 'fa-solid fa-clock-rotate-left',
      active: route.path.startsWith('/staff/history'),
    },
    {
      key: 'new',
      to: '/staff/new',
      label: 'New staff',
      icon: 'fa-solid fa-user-plus',
      active: route.path === '/staff/new' || route.path.startsWith('/staff/new/'),
    },
    {
      key: 'birthdays',
      to: '/staff/birthdays',
      label: 'Birthdays',
      icon: 'fa-solid fa-cake-candles',
      active: route.path.startsWith('/staff/birthdays'),
    },
    {
      key: 'nok',
      to: '/staff/next-of-kin',
      label: 'Next of kin',
      icon: 'fa-solid fa-people-roof',
      active: route.path.startsWith('/staff/next-of-kin'),
    },
    {
      key: 'signatures',
      to: '/staff/signatures',
      label: 'Signatures',
      icon: 'fa-solid fa-signature',
      active: route.path.startsWith('/staff/signatures'),
    },
    {
      key: 'quality',
      to: '/staff/data-quality',
      label: 'Data quality',
      icon: 'fa-solid fa-clipboard-check',
      active: route.path.startsWith('/staff/data-quality'),
    },
    {
      key: 'payroll',
      to: '/payroll',
      label: 'Payroll',
      icon: 'fa-solid fa-money-check-dollar',
      active: route.path.startsWith('/payroll'),
    },
  ].filter((item) => {
    if (item.key === 'directory' || item.key === 'history') return canDirectory
    if (item.key === 'new') return props.showNewStaff && canManage
    if (item.key === 'nok') return canManage || auth.hasPermission(72)
    if (item.key === 'signatures') return canManage
    if (item.key === 'quality') return canDirectory
    if (item.key === 'payroll') {
      return (
        auth.isModuleEnabled('payroll') &&
        (auth.hasPermission(110) ||
          auth.hasPermission(111) ||
          auth.hasPermission(112) ||
          auth.hasPermission(113) ||
          auth.hasPermission(114) ||
          auth.hasPermission(115) ||
          auth.hasPermission(116) ||
          auth.hasPermission(117) ||
          auth.hasPermission(17) ||
          Number(auth.me?.profile?.role_id || 0) === 20 ||
          Number(auth.me?.profile?.role_id || 0) === 22 ||
          Number(auth.me?.profile?.role_id || 0) === 10)
      )
    }
    return true
  })
})
</script>

<template>
  <PortalPillSubnav :items="items" aria-label="Staff tools" />
</template>
