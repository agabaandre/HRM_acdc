import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { getStoredToken } from '../lib/api'
import { useLocaleStore } from '../stores/locale'

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
      path: '/access-error',
      name: 'access-error',
      component: () => import('../pages/risks/RiskAccessErrorPage.vue'),
      meta: { guest: true, chrome: false, title: 'Access error', titleKey: 'rr.access_title' },
    },
    { path: '/', name: 'home', redirect: { name: 'dashboard' } },
    { path: '/home', redirect: { name: 'dashboard' } },
    { path: '/home/index', redirect: { name: 'dashboard' } },
    {
      path: '/risks',
      name: 'risks',
      component: () => import('../pages/risks/RiskRegisterPage.vue'),
      meta: { requiresAuth: true, title: 'Risk Register', titleKey: 'rr.register_title', module: 'risks' },
    },
    {
      path: '/risks/new',
      name: 'risk-new',
      component: () => import('../pages/risks/RiskShowPage.vue'),
      meta: { requiresAuth: true, title: 'New risk', titleKey: 'rr.new_title', module: 'risks' },
    },
    {
      path: '/risks/:id',
      name: 'risk-show',
      component: () => import('../pages/risks/RiskProfilePage.vue'),
      meta: { requiresAuth: true, title: 'Risk profile', titleKey: 'rr.profile_title', module: 'risks' },
    },
    {
      path: '/risks/:id/edit',
      name: 'risk-edit',
      component: () => import('../pages/risks/RiskShowPage.vue'),
      meta: { requiresAuth: true, title: 'Edit risk', titleKey: 'rr.edit_title', module: 'risks' },
    },
    {
      path: '/approvals',
      name: 'my-approvals',
      component: () => import('../pages/risks/MyApprovalsPage.vue'),
      meta: { requiresAuth: true, title: 'My approvals', titleKey: 'rr.approvals_title', module: 'risks' },
    },
    {
      path: '/workflows',
      name: 'workflows',
      component: () => import('../pages/risks/WorkflowSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Approval workflows', titleKey: 'rr.workflows_title', module: 'risks' },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/risks/RiskDashboardPage.vue'),
      meta: { requiresAuth: true, title: 'Risk dashboard', titleKey: 'rr.dash_title', module: 'dashboard' },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('../pages/risks/ReportsHubPage.vue'),
      meta: { requiresAuth: true, title: 'Reports', titleKey: 'rr.reports_title', module: 'reports' },
    },
    {
      path: '/reports/enterprise-themes',
      name: 'report-enterprise-themes',
      component: () => import('../pages/risks/EnterpriseThemeReportPage.vue'),
      meta: {
        requiresAuth: true,
        title: 'Enterprise Risk Register by Theme',
        titleKey: 'rr.theme_report_title',
        module: 'reports',
      },
    },
    {
      path: '/reports/enterprise-themes/:id',
      name: 'report-enterprise-theme',
      component: () => import('../pages/risks/EnterpriseThemeDrilldownPage.vue'),
      meta: {
        requiresAuth: true,
        title: 'Theme detail',
        titleKey: 'rr.theme_drill_title',
        module: 'reports',
      },
    },
    {
      path: '/reports/heat-map',
      name: 'report-heat-map',
      component: () => import('../pages/risks/HeatMapReportPage.vue'),
      meta: {
        requiresAuth: true,
        title: 'Enterprise Risk Heat Map',
        titleKey: 'rr.heat_report_title',
        module: 'reports',
      },
    },
    {
      path: '/import',
      name: 'import',
      component: () => import('../pages/risks/RiskImportPage.vue'),
      meta: { requiresAuth: true, title: 'Excel import', titleKey: 'rr.import_title', module: 'import' },
    },
    {
      path: '/reference',
      name: 'reference',
      component: () => import('../pages/risks/RiskReferencePage.vue'),
      meta: { requiresAuth: true, title: 'Reference & methodology', titleKey: 'rr.reference_title', module: 'reference' },
    },
    {
      path: '/risk-settings',
      name: 'risk-settings',
      component: () => import('../pages/risks/RiskSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Risk settings', titleKey: 'rr.settings_title', module: 'settings' },
    },
    {
      path: '/risk-settings/lookups/:key',
      name: 'risk-settings-lookup',
      component: () => import('../pages/risks/RiskLookupListPage.vue'),
      meta: { requiresAuth: true, title: 'Lookup list', titleKey: 'rr.lookup_fallback_title', module: 'settings' },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'dashboard' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const token = getStoredToken()

  if (to.meta.guest) {
    return true
  }

  if (to.meta.requiresAuth) {
    if (!token && !auth.token) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    if (to.meta.module === 'import' && auth.me?.enabled_modules?.import === false) {
      return { name: 'risks' }
    }
  }

  return true
})

router.afterEach((to) => {
  const titleKey = typeof to.meta.titleKey === 'string' ? to.meta.titleKey : ''
  const fallback = typeof to.meta.title === 'string' ? to.meta.title : 'Risk Register'
  if (titleKey || fallback) {
    const locale = useLocaleStore()
    document.title = titleKey ? locale.t(titleKey, fallback) : fallback
  }

  // Warm likely next chunks while the user is reading the current page.
  const warm: Array<() => Promise<unknown>> = []
  if (to.name === 'risks') {
    warm.push(
      () => import('../pages/risks/RiskShowPage.vue'),
      () => import('../pages/risks/RiskDashboardPage.vue'),
      () => import('../pages/risks/WorkflowSettingsPage.vue'),
    )
  } else if (to.name === 'workflows') {
    warm.push(() => import('../pages/risks/RiskRegisterPage.vue'))
  }
  for (const load of warm) {
    void load()
  }
})

export default router
