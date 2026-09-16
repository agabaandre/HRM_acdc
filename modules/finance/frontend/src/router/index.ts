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
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/finance/FundingPortfolioPage.vue'),
      meta: { requiresAuth: true, title: 'Funding Portfolio', module: 'dashboard' },
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
    document.title = `${to.meta.title} · Finance`
  }

  void locale
  return true
})

export default router
