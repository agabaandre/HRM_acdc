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
    { path: '/', name: 'home', redirect: { name: 'dashboard' } },
    { path: '/home', redirect: { name: 'dashboard' } },
    { path: '/home/index', redirect: { name: 'dashboard' } },
    // Legacy finance URLs from the IT/finance fork → IT dashboard
    { path: '/budget-execution', redirect: { name: 'dashboard' } },
    { path: '/performance', redirect: { name: 'dashboard' } },
    { path: '/portfolio/entry', redirect: { name: 'dashboard' } },
    { path: '/sap-import', redirect: { name: 'dashboard' } },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/it/ItDashboardPage.vue'),
      meta: { requiresAuth: true, title: 'Dashboard' },
    },
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
