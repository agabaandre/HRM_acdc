import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../pages/LoginPage.vue'),
      meta: { guest: true, chrome: false },
    },
    {
      path: '/',
      name: 'home',
      component: () => import('../pages/HomePage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../pages/profile/ProfilePage.vue'),
      meta: { requiresAuth: true, title: 'My profile' },
    },
    {
      path: '/profile/password',
      name: 'profile-password',
      component: () => import('../pages/profile/ProfilePasswordPage.vue'),
      meta: { requiresAuth: true, title: 'Change password', requiresPasswordLogin: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/dashboard/DashboardPage.vue'),
      meta: { requiresAuth: true, permission: 76, title: 'Dashboard', module: 'dashboard' },
    },
    {
      path: '/admin/staff',
      name: 'admin-staff',
      redirect: { name: 'staff' },
    },
    {
      path: '/admin/rbac',
      name: 'admin-rbac',
      redirect: { name: 'permissions' },
    },
    {
      path: '/staff',
      name: 'staff',
      component: () => import('../pages/staff/StaffIndexPage.vue'),
      meta: { requiresAuth: true, permission: 72, title: 'Staff directory', module: 'staff' },
    },
    {
      path: '/staff/birthdays',
      name: 'staff-birthdays',
      component: () => import('../pages/staff/StaffBirthdaysPage.vue'),
      meta: { requiresAuth: true, title: 'Birthdays', module: 'staff' },
    },
    {
      path: '/staff/data-quality',
      name: 'staff-data-quality',
      component: () => import('../pages/staff/StaffDataQualityPage.vue'),
      meta: { requiresAuth: true, permission: 72, title: 'Data quality', module: 'staff' },
    },
    {
      path: '/staff/signatures',
      name: 'staff-signatures',
      component: () => import('../pages/staff/StaffSignaturesPage.vue'),
      meta: { requiresAuth: true, permission: 71, title: 'Signature Manager', module: 'staff' },
    },
    {
      path: '/staff/next-of-kin',
      name: 'staff-next-of-kin',
      component: () => import('../pages/staff/StaffNextOfKinPage.vue'),
      meta: { requiresAuth: true, anyPermission: [71, 72], title: 'Staff Next of Kin', module: 'staff' },
    },
    {
      path: '/staff/new',
      name: 'staff-new',
      component: () => import('../pages/staff/StaffNewPage.vue'),
      meta: { requiresAuth: true, permission: 71, title: 'New staff', module: 'staff' },
    },
    {
      path: '/staff/history',
      name: 'staff-history',
      component: () => import('../pages/staff/StaffHistoryPage.vue'),
      meta: { requiresAuth: true, permission: 72, title: 'Staff history', module: 'staff' },
    },
    {
      path: '/staff/:id',
      name: 'staff-show',
      component: () => import('../pages/staff/StaffShowPage.vue'),
      meta: { requiresAuth: true, title: 'Staff profile', module: 'staff' },
    },
    {
      path: '/leave',
      name: 'leave',
      component: () => import('../pages/leave/LeavePage.vue'),
      meta: { requiresAuth: true, title: 'Leave', anyPermission: [37, 73, 95, 96, 97, 77], module: 'leave' },
    },
    {
      path: '/leave/apply',
      name: 'leave-apply',
      component: () => import('../pages/leave/LeaveApplyPage.vue'),
      meta: { requiresAuth: true, title: 'Apply for leave', permission: 37, module: 'leave' },
    },
    {
      path: '/leave/admin/balances',
      name: 'leave-admin-balances',
      component: () => import('../pages/leave/LeaveAdminBalancesPage.vue'),
      meta: { requiresAuth: true, title: 'Leave balances', anyPermission: [96], module: 'leave' },
    },
    {
      path: '/settings/leave',
      name: 'settings-leave',
      component: () => import('../pages/settings/LeaveSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Leave settings', anyPermission: [97, 15], module: 'settings' },
    },
    {
      path: '/settings/performance',
      name: 'settings-performance',
      component: () => import('../pages/settings/PerformanceSettingsPage.vue'),
      meta: {
        requiresAuth: true,
        permission: 15,
        // System admin (10), HR Manager (20), HR Admin (22)
        anyRole: [10, 20, 22],
        title: 'Performance settings',
        module: 'settings',
      },
    },
    {
      path: '/settings/org-structure',
      name: 'settings-org-structure',
      component: () => import('../pages/settings/OrgStructurePage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Organizational structure', module: 'settings' },
    },
    {
      path: '/settings/lookup/divisions',
      name: 'settings-divisions',
      component: () => import('../pages/settings/DivisionsSettingsPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Divisions', module: 'settings' },
    },
    {
      path: '/settings/lookup/directorates',
      name: 'settings-directorates',
      component: () => import('../pages/settings/DirectoratesSettingsPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Directorates', module: 'settings' },
    },
    {
      path: '/settings/lookup/cbp_modules',
      name: 'settings-cbp-modules',
      component: () => import('../pages/settings/CbpModulesSettingsPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'CBP modules', module: 'settings' },
    },
    {
      path: '/settings/lookup/:table',
      name: 'settings-lookup',
      component: () => import('../pages/settings/LookupTablePage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Lookup settings', module: 'settings' },
    },
    {
      path: '/settings/portal-modules',
      name: 'settings-portal-modules',
      component: () => import('../pages/settings/PortalModulesPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Portal modules', module: 'settings' },
    },
    {
      path: '/settings/email-servers',
      name: 'settings-email-servers',
      component: () => import('../pages/settings/EmailServersPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Email servers', module: 'settings' },
    },
    {
      path: '/settings/shared-storage',
      name: 'settings-shared-storage',
      component: () => import('../pages/settings/SharedStoragePage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Shared storage', module: 'settings' },
    },
    {
      path: '/settings/staff-jobs',
      name: 'settings-staff-jobs',
      component: () => import('../pages/settings/StaffJobsSettingsPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Staff jobs', module: 'settings' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../pages/settings/SettingsHubPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Settings', module: 'settings' },
    },
    {
      path: '/payroll',
      name: 'payroll',
      component: () => import('../pages/payroll/PayrollHubPage.vue'),
      meta: {
        requiresAuth: true,
        title: 'Payroll',
        anyPermission: [110, 111, 112, 113, 114, 115, 116, 117],
        module: 'payroll',
      },
    },
    {
      path: '/payroll/runs',
      name: 'payroll-runs',
      component: () => import('../pages/payroll/PayrollRunsPage.vue'),
      meta: { requiresAuth: true, title: 'Payroll runs', anyPermission: [113, 110, 17], module: 'payroll' },
    },
    {
      path: '/payroll/runs/:id',
      name: 'payroll-run-detail',
      component: () => import('../pages/payroll/PayrollRunDetailPage.vue'),
      meta: { requiresAuth: true, title: 'Payroll run', anyPermission: [113, 110, 17], module: 'payroll' },
    },
    {
      path: '/payroll/payslips',
      name: 'payroll-payslips',
      component: () => import('../pages/payroll/PayrollPayslipsPage.vue'),
      meta: { requiresAuth: true, title: 'Payslips', anyPermission: [117, 113, 110, 17], module: 'payroll' },
    },
    {
      path: '/payroll/loans',
      name: 'payroll-loans',
      component: () => import('../pages/payroll/PayrollLoansPage.vue'),
      meta: { requiresAuth: true, title: 'Loans', anyPermission: [116, 115, 114, 110, 17], module: 'payroll' },
    },
    {
      path: '/payroll/setup',
      name: 'payroll-setup',
      component: () => import('../pages/payroll/PayrollSetupPage.vue'),
      meta: { requiresAuth: true, title: 'Payroll setup', anyPermission: [111, 112, 113, 17], module: 'payroll' },
    },
    {
      path: '/performance',
      name: 'performance',
      component: () => import('../pages/performance/PerformancePage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Performance', module: 'performance' },
    },
    {
      path: '/performance/create',
      name: 'performance-create',
      component: () => import('../pages/performance/PerformanceFormPage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Create PPA', module: 'performance' },
    },
    {
      path: '/performance/form/:phase/:entryId/:staffId',
      name: 'performance-form',
      component: () => import('../pages/performance/PerformanceFormPage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Performance form', module: 'performance' },
    },
    {
      path: '/tasks',
      redirect: { name: 'tasks-weekly' },
    },
    {
      path: '/tasks/weekly',
      name: 'tasks-weekly',
      component: () => import('../pages/tasks/WeeklyTasksPage.vue'),
      meta: { requiresAuth: true, permission: 75, title: 'Weekly tasks', module: 'tasks' },
    },
    {
      path: '/workplan',
      name: 'workplan',
      component: () => import('../pages/workplan/WorkplanPage.vue'),
      meta: { requiresAuth: true, permission: 79, title: 'Workplan', module: 'workplan' },
    },
    {
      path: '/workplan/:id',
      name: 'workplan-show',
      component: () => import('../pages/workplan/WorkplanShowPage.vue'),
      meta: { requiresAuth: true, permission: 79, title: 'Workplan activity', module: 'workplan' },
    },
    {
      path: '/admanager',
      redirect: { name: 'admanager-expired' },
    },
    {
      path: '/admanager/expired',
      name: 'admanager-expired',
      component: () => import('../pages/admanager/AdManagerListPage.vue'),
      props: { mode: 'expired' },
      meta: { requiresAuth: true, permission: 77, title: 'Accounts to disable', module: 'admanager' },
    },
    {
      path: '/admanager/disabled',
      name: 'admanager-disabled',
      component: () => import('../pages/admanager/AdManagerListPage.vue'),
      props: { mode: 'disabled' },
      meta: { requiresAuth: true, permission: 77, title: 'Disabled accounts', module: 'admanager' },
    },
    {
      path: '/auth/users',
      name: 'auth-users',
      component: () => import('../pages/auth/UsersAdminPage.vue'),
      meta: { requiresAuth: true, permission: 17, title: 'Users' },
    },
    {
      path: '/auth/oauth-clients',
      name: 'auth-oauth-clients',
      component: () => import('../pages/auth/OAuthClientsPage.vue'),
      meta: { requiresAuth: true, permission: 17, title: 'OAuth clients' },
    },
    {
      path: '/auth/audit-logs',
      name: 'auth-audit-logs',
      component: () => import('../pages/auth/AuditLogsPage.vue'),
      meta: { requiresAuth: true, permission: 17, title: 'Audit logs' },
    },
    {
      path: '/permissions',
      name: 'permissions',
      component: () => import('../pages/permissions/PermissionsPage.vue'),
      meta: { requiresAuth: true, permission: 17, title: 'Permissions' },
    },
    {
      // CI3 report builders are linked from Staff / Dashboard — no SPA hub.
      path: '/reports',
      redirect: { name: 'staff' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../pages/PlaceholderPage.vue'),
      meta: { requiresAuth: true, title: 'Page' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.guest) {
    if (auth.isAuthenticated) {
      return { name: 'home' }
    }
    return true
  }

  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      return { name: 'login' }
    }
    if (!auth.me) {
      try {
        await auth.fetchMe()
      } catch {
        auth.invalidateSession()
        return { name: 'login' }
      }
    }
    const moduleKey = to.meta.module as string | undefined
    if (moduleKey && !auth.isModuleEnabled(moduleKey)) {
      return { name: 'home' }
    }
    const roleId = Number(auth.me?.profile?.role_id || 0)
    const isHr =
      !!auth.me?.profile?.is_hr ||
      !!auth.me?.profile?.is_hr_admin ||
      roleId === 20 ||
      roleId === 22
    const isSystemAdmin = !!auth.me?.profile?.is_system_admin || roleId === 10
    const hasStaff = Number(auth.me?.profile?.staff_id || 0) > 0
    const anyRoles = to.meta.anyRole as number[] | undefined
    const roleOk = !!anyRoles?.length && anyRoles.includes(roleId)
    const anyPerm = to.meta.anyPermission as Array<number | string> | undefined
    if (anyPerm?.length) {
      const ok =
        isHr ||
        isSystemAdmin ||
        roleOk ||
        anyPerm.some((p) => auth.hasPermission(p)) ||
        // Legacy soft access: linked staff can open Leave self-service without 37 yet.
        ((to.name === 'leave' || to.name === 'leave-apply') && hasStaff)
      if (!ok) return { name: 'home' }
    }
    const perm = to.meta.permission as number | undefined
    if (perm !== undefined) {
      const leaveSelfService =
        (to.name === 'leave-apply' || to.name === 'leave') && (isHr || hasStaff)
      if (!auth.hasPermission(perm) && !leaveSelfService && !isHr && !isSystemAdmin && !roleOk) {
        return { name: 'home' }
      }
    }
    if (to.meta.requiresPasswordLogin && !auth.passwordLoginAvailable) {
      return { name: 'profile' }
    }
  }

  return true
})

export default router
