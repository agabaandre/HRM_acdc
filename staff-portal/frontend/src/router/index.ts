import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guest: true, chrome: false },
    },
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, permission: 76, title: 'Dashboard' },
    },
    {
      path: '/staff',
      name: 'staff',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Staff directory' },
    },
    {
      path: '/leave',
      name: 'leave',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Leave' },
    },
    {
      path: '/performance',
      name: 'performance',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Performance' },
    },
    {
      path: '/attendance',
      name: 'attendance',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Attendance' },
    },
    {
      path: '/tasks',
      name: 'tasks',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Tasks' },
    },
    {
      path: '/workplan',
      name: 'workplan',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Workplan' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Settings' },
    },
    {
      path: '/permissions',
      name: 'permissions',
      component: () => import('../views/PlaceholderView.vue'),
      meta: { requiresAuth: true, title: 'Permissions' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/PlaceholderView.vue'),
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
