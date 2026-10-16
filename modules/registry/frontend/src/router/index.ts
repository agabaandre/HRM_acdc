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
      component: () => import('../pages/registry/RegistryDashboardPage.vue'),
      meta: { requiresAuth: true, title: 'Dashboard' },
    },
    {
      path: '/documents/week',
      name: 'weekly-documents',
      component: () => import('../pages/registry/WeeklyDocumentsPage.vue'),
      meta: { requiresAuth: true, title: 'Weekly Documents' },
    },
    {
      path: '/documents/year',
      name: 'current-year-documents',
      component: () => import('../pages/registry/CurrentYearDocumentsPage.vue'),
      meta: { requiresAuth: true, title: 'Current Year Documents' },
    },
    {
      path: '/archives/:year',
      name: 'archive-year',
      component: () => import('../pages/registry/ArchiveDocumentsPage.vue'),
      meta: { requiresAuth: true, title: 'Archives' },
    },
    {
      path: '/settings',
      name: 'registry-settings',
      component: () => import('../pages/registry/RegistrySettingsPage.vue'),
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
    const year = typeof to.params.year === 'string' ? ` ${to.params.year}` : ''
    document.title = `${to.meta.title}${year} · Registry`
  }

  void locale
  return true
})

export default router
