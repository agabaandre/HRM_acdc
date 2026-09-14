<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import { useAuthStore } from '@/stores/auth'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import { useLocaleStore } from '@/stores/locale'

const auth = useAuthStore()
const locale = useLocaleStore()
const route = useRoute()

const roleId = computed(() => Number(auth.me?.profile?.role_id || 0))
const isHr = computed(
  () => !!auth.me?.profile?.is_hr || roleId.value === 20 || roleId.value === 22,
)
const isAdmin = computed(
  () => !!auth.me?.profile?.is_system_admin || roleId.value === 10 || auth.hasPermission(17),
)

const items = computed<PortalPillNavItem[]>(() => {
  const canRun = isHr.value || isAdmin.value || auth.hasPermission(PAYROLL_PERMS.RUN_PAYROLL)
  const canSetup =
    isHr.value ||
    isAdmin.value ||
    auth.hasPermission(PAYROLL_PERMS.MANAGE_SETUP) ||
    auth.hasPermission(PAYROLL_PERMS.MANAGE_STAFF_PAY)
  const canLoans =
    isHr.value ||
    isAdmin.value ||
    auth.hasPermission(PAYROLL_PERMS.MANAGE_LOANS) ||
    auth.hasPermission(PAYROLL_PERMS.APPROVE_LOANS) ||
    auth.hasPermission(PAYROLL_PERMS.REQUEST_LOAN)
  const canPayslips =
    isHr.value ||
    isAdmin.value ||
    auth.hasPermission(PAYROLL_PERMS.VIEW_OWN_PAYSLIPS) ||
    auth.hasPermission(PAYROLL_PERMS.RUN_PAYROLL) ||
    auth.hasPermission(PAYROLL_PERMS.VIEW_HUB)

  return [
    {
      key: 'overview',
      to: '/payroll',
      label: locale.t('subnav.overview', 'Overview'),
      icon: 'fa-solid fa-gauge',
      active: /^\/payroll\/?$/.test(route.path),
    },
    {
      key: 'runs',
      to: '/payroll/runs',
      label: locale.t('subnav.runs', 'Runs'),
      icon: 'fa-solid fa-play',
      active: /^\/payroll\/runs/.test(route.path),
    },
    {
      key: 'payslips',
      to: '/payroll/payslips',
      label: locale.t('subnav.payslips', 'Payslips'),
      icon: 'fa-solid fa-file-invoice-dollar',
      active: /^\/payroll\/payslips/.test(route.path),
    },
    {
      key: 'loans',
      to: '/payroll/loans',
      label: locale.t('subnav.loans', 'Loans'),
      icon: 'fa-solid fa-hand-holding-dollar',
      active: /^\/payroll\/loans/.test(route.path),
    },
    {
      key: 'setup',
      to: '/payroll/setup',
      label: locale.t('subnav.setup', 'Setup'),
      icon: 'fa-solid fa-sliders',
      active: /^\/payroll\/setup/.test(route.path),
    },
  ].filter((item) => {
    if (item.key === 'runs') return canRun
    if (item.key === 'setup') return canSetup
    if (item.key === 'loans') return canLoans
    if (item.key === 'payslips') return canPayslips
    return true
  })
})
</script>

<template>
  <PortalPillSubnav :items="items" :aria-label="locale.t('subnav.payroll_sections', 'Payroll sections')" />
</template>
