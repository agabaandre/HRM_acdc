import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { getActivePinia } from 'pinia'
import { profileHasToolsPermission } from '../lib/toolsPermissions'
import type { ToolsPermissionKey } from '../lib/toolsNav'
import { getStoredToken } from '../lib/api'
import { redirectToStaffPortalHome, staffPortalHomeUrl } from '../lib/sso'
import { parseSettingsSection } from '../settings/settingsSections'
import { useAuthStore } from '../stores/auth'
import { finishRoutePreloader, startRoutePreloader } from '../lib/appPreloader'

const STAFF_ROLES = new Set(['agent', 'supervisor', 'admin', 'auditor'])

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('../views/HomeView.vue'),
  },
  {
    path: '/guide',
    name: 'user-guide',
    component: () => import('../views/UserGuideView.vue'),
    meta: { public: true },
  },
  {
    path: '/ask',
    name: 'ask-helpdesk',
    component: () => import('../views/AskHelpdeskView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/tickets/confirm-resolution',
    name: 'confirm-resolution',
    component: () => import('../views/ConfirmResolutionView.vue'),
    meta: { public: true },
  },
  {
    path: '/settings',
    component: () => import('../views/settings/SettingsLayoutView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    redirect: '/settings/general',
    children: [
      {
        path: 'general',
        name: 'settings-general',
        component: () => import('../components/settings/GeneralSettingsPanel.vue'),
        meta: { settingsTitle: 'General' },
      },
      {
        path: 'ai',
        component: () => import('../views/settings/AiSettingsLayoutView.vue'),
        meta: { settingsTitle: 'AI models & provider' },
        children: [
          {
            path: '',
            name: 'settings-ai',
            component: () => import('../components/settings/AiModelsSettingsPanel.vue'),
          },
          {
            path: 'faq-sources',
            name: 'settings-ai-faq-sources',
            component: () => import('../components/settings/FaqSourcesSettingsPanel.vue'),
            meta: { settingsTitle: 'FAQ sources' },
          },
        ],
      },
      {
        path: 'agents',
        name: 'settings-agents',
        component: () => import('../components/settings/AgentsManagementPanel.vue'),
        meta: { settingsTitle: 'Agents & category routing' },
      },
      {
        path: 'categories',
        name: 'settings-categories',
        component: () => import('../components/settings/CategoriesManagementPanel.vue'),
        meta: { settingsTitle: 'Issue categories' },
      },
      {
        path: 'it-assets',
        name: 'settings-it-assets',
        component: () => import('../components/settings/ItAssetsSettingsPanel.vue'),
        meta: { settingsTitle: 'IT Assets' },
      },
      {
        path: 'risk-matrix',
        name: 'settings-risk-matrix',
        component: () => import('../components/settings/RiskMatrixManagementPanel.vue'),
        meta: { settingsTitle: 'Risk matrix' },
      },
      {
        path: 'jobs',
        name: 'settings-jobs',
        component: () => import('../components/settings/JobsSlaManagementPanel.vue'),
        meta: { settingsTitle: 'Jobs' },
      },
      {
        path: 'integrations',
        name: 'settings-integrations',
        component: () => import('../components/settings/IntegrationsSettingsPanel.vue'),
        meta: { settingsTitle: 'WhatsApp & Teams' },
      },
      {
        path: 'software-requests',
        name: 'settings-software-requests',
        component: () => import('../components/settings/SoftwareRequestsSettingsPanel.vue'),
        meta: { settingsTitle: 'Software requests' },
      },
      {
        path: 'logging',
        name: 'settings-logging',
        component: () => import('../components/settings/LoggingAuditPanel.vue'),
        meta: { settingsTitle: 'Audit & ISO logging' },
      },
    ],
  },
  {
    path: '/admin/agents',
    name: 'admin-agents',
    meta: { requiresAuth: true, requiresAdmin: true },
    redirect: () => ({ path: '/settings/agents' }),
  },
  {
    path: '/tickets',
    name: 'tickets',
    component: () => import('../views/TicketsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/tickets/new',
    name: 'tickets-new',
    component: () => import('../views/TicketCreateView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/tickets/:id',
    name: 'ticket-detail',
    component: () => import('../views/TicketDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/desk/agent',
    name: 'agent-dashboard',
    component: () => import('../views/AgentDashboardView.vue'),
    meta: { requiresAuth: true, requiresStaff: true },
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('../views/ReportsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/knowledge-base/manage',
    name: 'kb-manage',
    component: () => import('../views/KbManageView.vue'),
    meta: { requiresAuth: true, requiresKbManager: true },
  },
  {
    path: '/screen',
    name: 'screen',
    component: () => import('../views/ScreenDashboardView.vue'),
    meta: { public: true, chrome: false },
  },
  {
    path: '/tools',
    component: () => import('../views/tools/ToolsLayoutView.vue'),
    meta: { requiresAuth: true },
    redirect: '/tools/software-requests',
    children: [
      {
        path: 'it-assets',
        name: 'tools-it-assets',
        component: () => import('../views/tools/ItAssetsView.vue'),
        meta: { requiresAuth: true, requiresToolsPermission: 'can_manage_it_assets' as ToolsPermissionKey },
      },
      {
        path: 'licenses',
        name: 'tools-licenses',
        component: () => import('../views/tools/LicensesView.vue'),
        meta: { requiresAuth: true, requiresToolsPermission: 'can_manage_licenses' as ToolsPermissionKey },
      },
      {
        path: 'software-requests',
        name: 'tools-software-requests',
        component: () => import('../views/tools/SoftwareRequestsView.vue'),
        meta: { requiresAuth: true, requiresSoftwareRequests: true },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, _from, saved) {
    if (saved) {
      return saved
    }
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }
    return { top: 0 }
  },
})

router.beforeEach(async (to, from) => {
  if (to.path !== from.path && from.matched.length > 0) {
    startRoutePreloader()
  }

  if (to.meta.public) {
    return true
  }
  if (to.path === '/settings' && to.query.section) {
    const raw = to.query.section
    const s = typeof raw === 'string' ? parseSettingsSection(raw) : 'general'
    return { path: `/settings/${s}`, replace: true }
  }
  if (to.meta.requiresAuth && !getStoredToken()) {
    window.location.href = staffPortalHomeUrl()
    return false
  }

  if (to.meta.requiresAuth && getStoredToken()) {
    const pinia = getActivePinia()
    if (!pinia) {
      return { name: 'home' }
    }
    const auth = useAuthStore(pinia)
    if (!auth.me) {
      try {
        await auth.fetchMe()
      } catch {
        auth.invalidateSession()
        window.location.href = staffPortalHomeUrl()
        return false
      }
    }
  }

  if (
    to.meta.requiresAdmin ||
    to.meta.requiresStaff ||
    to.meta.requiresKbManager ||
    to.meta.requiresToolsPermission ||
    to.meta.requiresSoftwareRequests
  ) {
    const pinia = getActivePinia()
    if (!pinia) {
      return { name: 'home' }
    }
    const auth = useAuthStore(pinia)
    if (!auth.me) {
      try {
        await auth.fetchMe()
      } catch {
        auth.invalidateSession()
        redirectToStaffPortalHome()
        return false
      }
    }
    const role = auth.me?.profile?.role
    const isHelpdeskAdmin =
      !!auth.me?.profile?.is_helpdesk_admin || role === 'admin'
    if (to.meta.requiresAdmin && !isHelpdeskAdmin) {
      return { name: 'home' }
    }
    if (to.meta.requiresStaff && (!role || !STAFF_ROLES.has(role))) {
      return { name: 'home' }
    }
    if (to.meta.requiresKbManager) {
      const canKb =
        isHelpdeskAdmin || !!auth.me?.profile?.can_manage_kb
      if (!canKb) {
        return { name: 'home' }
      }
    }
    if (to.meta.requiresToolsPermission) {
      const key = to.meta.requiresToolsPermission as ToolsPermissionKey
      if (!profileHasToolsPermission(auth.me?.profile, key)) {
        return { name: 'home' }
      }
    }
    if (to.meta.requiresSoftwareRequests) {
      const p = auth.me?.profile
      const allowed =
        isHelpdeskAdmin ||
        !!p?.can_submit_software_requests ||
        !!p?.can_approve_software_requests ||
        !!p?.can_manage_software_requests
      if (!allowed) {
        return { name: 'home' }
      }
    }
  }
})

router.afterEach(() => {
  finishRoutePreloader()
})

router.onError(() => {
  finishRoutePreloader()
})

export default router
