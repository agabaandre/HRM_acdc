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
      meta: { guest: true, chrome: false, title: 'Access error' },
    },
    { path: '/', name: 'home', redirect: { name: 'licenses' } },
    { path: '/home', redirect: { name: 'licenses' } },
    { path: '/home/index', redirect: { name: 'licenses' } },
    {
      path: '/licenses',
      name: 'licenses',
      component: () => import('../pages/it/LicensesView.vue'),
      meta: { requiresAuth: true, title: 'Licenses' },
    },
    { path: '/it-assets', redirect: { name: 'itsm' } },
    {
      path: '/itsm',
      name: 'itsm',
      component: () => import('../pages/it/ItAssetsView.vue'),
      meta: { requiresAuth: true, title: 'ITSM' },
    },
    {
      path: '/information-systems',
      name: 'information-systems',
      component: () => import('../pages/it/InformationSystemsView.vue'),
      meta: { requiresAuth: true, title: 'Information Systems' },
    },
    {
      path: '/servers',
      name: 'servers',
      component: () => import('../pages/it/ServersView.vue'),
      meta: { requiresAuth: true, title: 'Servers' },
    },
    {
      path: '/settings/license-alerts',
      name: 'license-alert-settings',
      component: () => import('../pages/it/LicenseAlertSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Settings' },
    },
    {
      path: '/budget-execution',
      name: 'budget-execution',
      component: () => import('../pages/finance/BudgetedExecutionPage.vue'),
      meta: { requiresAuth: true, title: 'Budget execution', module: 'dashboard' },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/finance/FundingPortfolioPage.vue'),
      meta: { requiresAuth: true, title: 'Funding Portfolio', module: 'dashboard' },
    },
    {
      path: '/performance',
      name: 'performance-summary',
      component: () => import('../pages/finance/PerformanceSummaryPage.vue'),
      meta: { requiresAuth: true, title: 'Performance summary', module: 'dashboard' },
    },
    {
      path: '/portfolio/entry',
      name: 'portfolio-entry',
      component: () => import('../pages/finance/PortfolioEntryPage.vue'),
      meta: { requiresAuth: true, title: 'Portfolio data entry', module: 'portfolio' },
    },
    {
      path: '/sap-import',
      name: 'sap-import',
      component: () => import('../pages/finance/SapImportPage.vue'),
      meta: { requiresAuth: true, title: 'SAP import', module: 'sap' },
    },
    {
      path: '/settings/email',
      name: 'settings-email',
      component: () => import('../pages/finance/EmailSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Email settings', module: 'settings' },
    },
    {
      path: '/settings/staff-api',
      name: 'settings-staff-api',
      component: () => import('../pages/finance/StaffApiSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Staff API credentials', module: 'settings' },
    },
    {
      path: '/settings/app-logs',
      name: 'settings-app-logs',
      component: () => import('../pages/finance/AppLogsPage.vue'),
      meta: { requiresAuth: true, title: 'Application logs', module: 'settings' },
    },
    {
      path: '/settings/apm-mapping',
      redirect: { name: 'settings-apm-divisions' },
    },
    {
      path: '/settings/apm-mapping/divisions',
      name: 'settings-apm-divisions',
      component: () => import('../pages/finance/ApmDivisionMappingPage.vue'),
      meta: { requiresAuth: true, title: 'APM division mapping', module: 'settings' },
    },
    {
      path: '/settings/apm-mapping/funders',
      name: 'settings-apm-funders',
      component: () => import('../pages/finance/ApmFunderMappingPage.vue'),
      meta: { requiresAuth: true, title: 'APM funder mapping', module: 'settings' },
    },
    {
      path: '/settings/grants',
      name: 'settings-grants',
      component: () => import('../pages/finance/GrantManagementPage.vue'),
      meta: { requiresAuth: true, title: 'Grant management', module: 'settings' },
    },
    {
      path: '/settings/geography',
      name: 'settings-geography',
      component: () => import('../pages/finance/GeographySettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Geography', module: 'settings' },
    },
    {
      path: '/settings/afef-categories',
      name: 'settings-afef-categories',
      component: () => import('../pages/finance/AfefCategoriesPage.vue'),
      meta: { requiresAuth: true, title: 'AfEF categories', module: 'settings' },
    },
    {
      path: '/settings/financial-year',
      name: 'settings-financial-year',
      component: () => import('../pages/finance/FinancialYearSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Financial year', module: 'settings' },
    },
    {
      path: '/settings/azure-sap-storage',
      name: 'settings-azure-sap-storage',
      component: () => import('../pages/finance/AzureSapStorageSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Azure SAP storage', module: 'settings' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const locale = useLocaleStore()
  const token = getStoredToken()

  if (to.meta.guest) {
    return true
  }

  if (to.meta.requiresAuth !== false) {
    if (!token) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        return { name: 'login', query: { redirect: to.fullPath } }
      }
    }
  }

  if (typeof to.meta.title === 'string') {
    document.title = `${to.meta.title} · IT`
  }

  void locale
  return true
})

export default router
