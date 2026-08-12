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
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/dashboard/DashboardPage.vue'),
      meta: { requiresAuth: true, permission: 76, title: 'Dashboard' },
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
      meta: { requiresAuth: true, permission: 72, title: 'Staff directory' },
    },
    {
      path: '/staff/birthdays',
      name: 'staff-birthdays',
      component: () => import('../pages/staff/StaffBirthdaysPage.vue'),
      meta: { requiresAuth: true, title: 'Birthdays' },
    },
    {
      path: '/staff/data-quality',
      name: 'staff-data-quality',
      component: () => import('../pages/staff/StaffDataQualityPage.vue'),
      meta: { requiresAuth: true, permission: 72, title: 'Data quality' },
    },
    {
      path: '/staff/new',
      name: 'staff-new',
      component: () => import('../pages/staff/StaffNewPage.vue'),
      meta: { requiresAuth: true, permission: 71, title: 'New staff' },
    },
    {
      path: '/staff/:id',
      name: 'staff-show',
      component: () => import('../pages/staff/StaffShowPage.vue'),
      meta: { requiresAuth: true, title: 'Staff profile' },
    },
    {
      path: '/leave',
      name: 'leave',
      component: () => import('../pages/leave/LeavePage.vue'),
      meta: { requiresAuth: true, title: 'Leave' },
    },
    {
      path: '/leave/apply',
      name: 'leave-apply',
      component: () => import('../pages/leave/LeaveApplyPage.vue'),
      meta: { requiresAuth: true, title: 'Apply for leave' },
    },
    {
      path: '/settings/leave',
      name: 'settings-leave',
      component: () => import('../pages/settings/LeaveSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Leave settings' },
    },
    {
      path: '/settings/performance',
      name: 'settings-performance',
      component: () => import('../pages/settings/PerformanceSettingsPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Performance settings' },
    },
    {
      path: '/settings/lookup/:table',
      name: 'settings-lookup',
      component: () => import('../pages/settings/LookupTablePage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Lookup settings' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../pages/settings/SettingsHubPage.vue'),
      meta: { requiresAuth: true, permission: 15, title: 'Settings' },
    },
    {
      path: '/performance',
      name: 'performance',
      component: () => import('../pages/performance/PerformancePage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Performance' },
    },
    {
      path: '/performance/create',
      name: 'performance-create',
      component: () => import('../pages/performance/PerformanceFormPage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Create PPA' },
    },
    {
      path: '/performance/form/:phase/:entryId/:staffId',
      name: 'performance-form',
      component: () => import('../pages/performance/PerformanceFormPage.vue'),
      meta: { requiresAuth: true, permission: 74, title: 'Performance form' },
    },
    {
      path: '/tasks',
      name: 'tasks',
      component: () => import('../pages/tasks/TasksHubPage.vue'),
      meta: { requiresAuth: true, permission: 78, title: 'Tasks' },
    },
    {
      path: '/tasks/weekly',
      name: 'tasks-weekly',
      component: () => import('../pages/tasks/WeeklyTasksPage.vue'),
      meta: { requiresAuth: true, permission: 75, title: 'Weekly tasks' },
    },
    {
      path: '/workplan',
      name: 'workplan',
      component: () => import('../pages/workplan/WorkplanPage.vue'),
      meta: { requiresAuth: true, permission: 79, title: 'Workplan' },
    },
    {
      path: '/workplan/:id',
      name: 'workplan-show',
      component: () => import('../pages/workplan/WorkplanShowPage.vue'),
      meta: { requiresAuth: true, permission: 79, title: 'Workplan activity' },
    },
    {
      path: '/admanager',
      name: 'admanager',
      component: () => import('../pages/admanager/AdManagerHubPage.vue'),
      meta: { requiresAuth: true, permission: 77, title: 'AD manager' },
    },
    {
      path: '/admanager/expired',
      name: 'admanager-expired',
      component: () => import('../pages/admanager/AdManagerListPage.vue'),
      props: { mode: 'expired' },
      meta: { requiresAuth: true, permission: 77, title: 'Accounts to disable' },
    },
    {
      path: '/admanager/disabled',
      name: 'admanager-disabled',
      component: () => import('../pages/admanager/AdManagerListPage.vue'),
      props: { mode: 'disabled' },
      meta: { requiresAuth: true, permission: 77, title: 'Disabled accounts' },
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
      path: '/reports',
      name: 'reports',
      component: () => import('../pages/reports/ReportsPage.vue'),
      meta: { requiresAuth: true, permission: 72, title: 'Reports' },
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
    const perm = to.meta.permission as number | undefined
    if (perm !== undefined && !auth.hasPermission(perm)) {
      return { name: 'home' }
    }
  }

  return true
})

export default router
