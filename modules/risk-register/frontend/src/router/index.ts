import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { getStoredToken } from '../lib/api'

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
    { path: '/', name: 'home', redirect: { name: 'risks' } },
    { path: '/home', redirect: { name: 'risks' } },
    { path: '/home/index', redirect: { name: 'risks' } },
    {
      path: '/risks',
      name: 'risks',
      component: () => import('../pages/risks/RiskRegisterPage.vue'),
      meta: { requiresAuth: true, title: 'Risk Register', module: 'risks' },
    },
    {
      path: '/risks/new',
      name: 'risk-new',
      component: () => import('../pages/risks/RiskShowPage.vue'),
      meta: { requiresAuth: true, title: 'New risk', module: 'risks' },
    },
    {
      path: '/risks/:id',
      name: 'risk-show',
      component: () => import('../pages/risks/RiskShowPage.vue'),
      meta: { requiresAuth: true, title: 'Risk detail', module: 'risks' },
    },
    {
      path: '/approvals',
      name: 'my-approvals',
      component: () => import('../pages/risks/MyApprovalsPage.vue'),
      meta: { requiresAuth: true, title: 'My approvals', module: 'risks' },
    },
    {
      path: '/workflows',
      name: 'workflows',
      component: () => import('../pages/risks/WorkflowSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Approval workflows', module: 'risks' },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/risks/RiskDashboardPage.vue'),
      meta: { requiresAuth: true, title: 'Risk dashboard', module: 'dashboard' },
    },
    {
      path: '/import',
      name: 'import',
      component: () => import('../pages/risks/RiskImportPage.vue'),
      meta: { requiresAuth: true, title: 'Excel import', module: 'import' },
    },
    {
      path: '/reference',
      name: 'reference',
      component: () => import('../pages/risks/RiskReferencePage.vue'),
      meta: { requiresAuth: true, title: 'Reference & methodology', module: 'reference' },
    },
    {
      path: '/risk-settings',
      name: 'risk-settings',
      component: () => import('../pages/risks/RiskSettingsPage.vue'),
      meta: { requiresAuth: true, title: 'Risk settings', module: 'settings' },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'risks' },
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

export default router
