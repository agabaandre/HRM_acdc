import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/lib/api'
import { appBasePath } from '@/lib/basePath'
import { recoverFromChunkLoadError } from '@/lib/chunkLoadRecovery'
import { useAuthStore } from '@/stores/auth'

const routerBase = `${appBasePath()}/`

const router = createRouter({
  history: createWebHistory(routerBase),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    {
      path: '/login/redirect',
      name: 'login-microsoft-redirect',
      component: () => import('@/views/MicrosoftRedirectView.vue'),
      // Not guest: must not skip the OAuth callback when a stale session token exists.
    },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
    { path: '/verify-2fa', name: 'verify-2fa', component: () => import('@/views/Verify2FAView.vue'), meta: { guest: true } },
    {
      path: '/setup-authenticator',
      name: 'setup-authenticator',
      component: () => import('@/views/SetupAuthenticatorView.vue'),
      meta: { requiresAuth: true, forceTotpSetup: true },
    },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/ForgotPasswordView.vue'), meta: { guest: true } },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/views/ResetPasswordView.vue'), meta: { guest: true } },
    {
      path: '/',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'projects', name: 'projects', component: () => import('@/views/devtrack/ProjectsView.vue') },
        {
          path: 'projects/archived',
          name: 'archived-projects',
          component: () => import('@/views/devtrack/ArchivedProjectsView.vue'),
          meta: { requiresAdmin: true },
        },
        { path: 'projects/new', name: 'project-new', component: () => import('@/views/devtrack/ProjectFormView.vue') },
        { path: 'projects/:id/edit', name: 'project-edit', component: () => import('@/views/devtrack/ProjectFormView.vue') },
        { path: 'projects/:id/enhancements/new', name: 'enhancement-new', component: () => import('@/views/devtrack/EnhancementFormView.vue') },
        { path: 'projects/:id/modules/new', name: 'module-new', component: () => import('@/views/devtrack/ModuleFormView.vue') },
        { path: 'projects/:id', name: 'project-detail', component: () => import('@/views/devtrack/ProjectDetailView.vue') },
        { path: 'enhancements/:id', name: 'enhancement-detail', component: () => import('@/views/devtrack/EnhancementDetailView.vue') },
        { path: 'enhancements/:id/edit', name: 'enhancement-edit', component: () => import('@/views/devtrack/EnhancementFormView.vue') },
        { path: 'modules/:id', name: 'module-detail', component: () => import('@/views/devtrack/ModuleDetailView.vue') },
        { path: 'modules/:id/edit', name: 'module-edit', component: () => import('@/views/devtrack/ModuleFormView.vue') },
        { path: 'requests', name: 'requests', component: () => import('@/views/devtrack/IntakeView.vue') },
        { path: 'requests/new', name: 'request-new', component: () => import('@/views/devtrack/IntakeFormView.vue') },
        { path: 'requests/:id', name: 'request-detail', component: () => import('@/views/devtrack/IntakeDetailView.vue') },
        { path: 'requests/:id/edit', name: 'request-edit', component: () => import('@/views/devtrack/IntakeFormView.vue') },
        { path: 'intake', redirect: { name: 'requests' } },
        { path: 'intake/new', redirect: { name: 'request-new' } },
        { path: 'intake/:id', redirect: (to) => ({ name: 'request-detail', params: { id: to.params.id } }) },
        {
          path: 'intake/:id/edit',
          redirect: (to) => ({ name: 'request-edit', params: { id: to.params.id } }),
        },
        { path: 'work', name: 'work', component: () => import('@/views/devtrack/WorkBoardView.vue') },
        {
          path: 'my-work',
          name: 'my-work',
          component: () => import('@/views/devtrack/WorkBoardView.vue'),
          props: { mine: true },
        },
        { path: 'sprints', name: 'sprints', component: () => import('@/views/devtrack/SprintsView.vue') },
        { path: 'reports', name: 'reports', component: () => import('@/views/devtrack/ReportsView.vue') },
        { path: 'reports/new', name: 'report-new', component: () => import('@/views/devtrack/ReportFormView.vue') },
        {
          path: 'reports/data-quality',
          name: 'report-data-quality',
          component: () => import('@/views/devtrack/DataQualityReportView.vue'),
        },
        {
          path: 'reports/projects',
          name: 'report-projects',
          component: () => import('@/views/devtrack/ProjectsListReportView.vue'),
        },
        {
          path: 'reports/tasks',
          name: 'report-tasks',
          component: () => import('@/views/devtrack/TasksListReportView.vue'),
        },
        { path: 'reports/:id', name: 'report-detail', component: () => import('@/views/devtrack/ReportFormView.vue') },
        { path: 'indicators', name: 'indicators', component: () => import('@/views/devtrack/IndicatorsView.vue') },
        { path: 'providers', name: 'providers', component: () => import('@/views/ProvidersView.vue'), meta: { requiresAdmin: true } },
        { path: 'providers/new', name: 'provider-new', component: () => import('@/views/ProviderFormView.vue'), meta: { requiresAdmin: true } },
        { path: 'providers/:id/edit', name: 'provider-edit', component: () => import('@/views/ProviderFormView.vue'), meta: { requiresAdmin: true } },
        { path: 'integrations', name: 'integrations', component: () => import('@/views/IntegrationsView.vue') },
        { path: 'integrations/new', name: 'integration-new', component: () => import('@/views/IntegrationFormView.vue') },
        { path: 'integrations/:id/edit', name: 'integration-edit', component: () => import('@/views/IntegrationFormView.vue') },
        {
          path: 'staff-share',
          name: 'staff-share',
          component: () => import('@/views/devtrack/StaffShareView.vue'),
          meta: { requiresAdmin: true },
        },
        { path: 'send-mail', name: 'send-mail', component: () => import('@/views/SendMailView.vue'), meta: { requiresAdmin: true } },
        { path: 'logs', name: 'logs', component: () => import('@/views/EmailLogsView.vue') },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/users/UsersListView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/new',
          name: 'user-new',
          component: () => import('@/views/users/UserFormView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/:id/edit',
          name: 'user-edit',
          component: () => import('@/views/users/UserFormView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'staff',
          name: 'staff',
          component: () => import('@/views/devtrack/StaffView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'branding',
          name: 'branding',
          component: () => import('@/views/BrandingView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'backup',
          name: 'backup',
          component: () => import('@/views/BackupRestoreView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'security',
          name: 'security',
          component: () => import('@/views/SecuritySettingsView.vue'),
        },
        {
          path: 'audit-logs',
          name: 'audit-logs',
          component: () => import('@/views/AuditLogsView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'blocked-ips',
          name: 'blocked-ips',
          component: () => import('@/views/BlockedIpsView.vue'),
          meta: { requiresAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const authed = !!getToken()
  const auth = useAuthStore()

  if (to.name === 'verify-2fa') {
    if (!auth.pending2fa) return { name: 'login' }
    return
  }

  if (to.meta.requiresAuth && !authed) return { name: 'login' }
  if (to.meta.guest && authed) {
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        return
      }
    }
    if (auth.mustSetupTotp) return { name: 'setup-authenticator' }
    return { name: 'dashboard' }
  }

  if (authed && (!auth.user || auth.bootstrapped === false)) {
    try {
      if (!auth.user) await auth.fetchMe()
    } catch {
      return { name: 'login' }
    }
  }

  if (authed && auth.mustSetupTotp && to.name !== 'setup-authenticator') {
    return { name: 'setup-authenticator' }
  }

  if (to.name === 'setup-authenticator') {
    if (!authed) return { name: 'login' }
    if (!auth.mustSetupTotp) return { name: 'dashboard' }
    return
  }

  if (to.meta.requiresAdmin && authed) {
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        return { name: 'login' }
      }
    }
    if (!auth.user?.is_admin) return { name: 'dashboard' }
  }
})

router.onError((error) => {
  recoverFromChunkLoadError(error)
})

export default router
