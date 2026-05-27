import { createRouter, createWebHistory } from 'vue-router'
import { getActivePinia } from 'pinia'
import HomeView from '../views/HomeView.vue'
import SettingsLayoutView from '../views/settings/SettingsLayoutView.vue'
import GeneralSettingsPanel from '../components/settings/GeneralSettingsPanel.vue'
import AiModelsSettingsPanel from '../components/settings/AiModelsSettingsPanel.vue'
import AgentsManagementPanel from '../components/settings/AgentsManagementPanel.vue'
import CategoriesManagementPanel from '../components/settings/CategoriesManagementPanel.vue'
import JobsSlaManagementPanel from '../components/settings/JobsSlaManagementPanel.vue'
import IntegrationsSettingsPanel from '../components/settings/IntegrationsSettingsPanel.vue'
import LoggingAuditPanel from '../components/settings/LoggingAuditPanel.vue'
import TicketsView from '../views/TicketsView.vue'
import TicketCreateView from '../views/TicketCreateView.vue'
import TicketDetailView from '../views/TicketDetailView.vue'
import AgentDashboardView from '../views/AgentDashboardView.vue'
import ReportsView from '../views/ReportsView.vue'
import ConfirmResolutionView from '../views/ConfirmResolutionView.vue'
import KbManageView from '../views/KbManageView.vue'
import ScreenDashboardView from '../views/ScreenDashboardView.vue'
import { getStoredToken } from '../lib/api'
import { staffPortalHomeUrl } from '../lib/sso'
import { parseSettingsSection } from '../settings/settingsSections'
import { useAuthStore } from '../stores/auth'

const STAFF_ROLES = new Set(['agent', 'supervisor', 'admin', 'auditor'])

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    {
      path: '/tickets/confirm-resolution',
      name: 'confirm-resolution',
      component: ConfirmResolutionView,
      meta: { public: true },
    },
    {
      path: '/settings',
      component: SettingsLayoutView,
      meta: { requiresAuth: true, requiresAdmin: true },
      redirect: '/settings/general',
      children: [
        {
          path: 'general',
          name: 'settings-general',
          component: GeneralSettingsPanel,
          meta: { settingsTitle: 'General' },
        },
        {
          path: 'ai',
          name: 'settings-ai',
          component: AiModelsSettingsPanel,
          meta: { settingsTitle: 'AI models & provider' },
        },
        {
          path: 'agents',
          name: 'settings-agents',
          component: AgentsManagementPanel,
          meta: { settingsTitle: 'Agents & category routing' },
        },
        {
          path: 'categories',
          name: 'settings-categories',
          component: CategoriesManagementPanel,
          meta: { settingsTitle: 'Issue categories' },
        },
        {
          path: 'jobs',
          name: 'settings-jobs',
          component: JobsSlaManagementPanel,
          meta: { settingsTitle: 'Jobs' },
        },
        {
          path: 'integrations',
          name: 'settings-integrations',
          component: IntegrationsSettingsPanel,
          meta: { settingsTitle: 'WhatsApp & Teams' },
        },
        {
          path: 'logging',
          name: 'settings-logging',
          component: LoggingAuditPanel,
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
    { path: '/tickets', name: 'tickets', component: TicketsView, meta: { requiresAuth: true } },
    { path: '/tickets/new', name: 'tickets-new', component: TicketCreateView, meta: { requiresAuth: true } },
    { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView, meta: { requiresAuth: true } },
    { path: '/desk/agent', name: 'agent-dashboard', component: AgentDashboardView, meta: { requiresAuth: true, requiresStaff: true } },
    { path: '/reports', name: 'reports', component: ReportsView, meta: { requiresAuth: true } },
    {
      path: '/knowledge-base/manage',
      name: 'kb-manage',
      component: KbManageView,
      meta: { requiresAuth: true, requiresKbManager: true },
    },
    {
      // Public TV / lobby dashboard. Aggregate-only data, no auth.
      // `chrome: false` tells App.vue to skip the header/nav/footer.
      path: '/screen',
      name: 'screen',
      component: ScreenDashboardView,
      meta: { public: true, chrome: false },
    },
  ],
})

router.beforeEach(async (to) => {
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

  // Persisted token does not include profile — reload /me before paint so nav (Settings, Agent desk) stays correct.
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

  if (to.meta.requiresAdmin || to.meta.requiresStaff || to.meta.requiresKbManager) {
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
        return { name: 'home' }
      }
    }
    const role = auth.me?.profile?.role
    if (to.meta.requiresAdmin && role !== 'admin') {
      return { name: 'home' }
    }
    if (to.meta.requiresStaff && (!role || !STAFF_ROLES.has(role))) {
      return { name: 'home' }
    }
    if (to.meta.requiresKbManager) {
      const canKb = role === 'admin' || !!auth.me?.profile?.can_manage_kb
      if (!canKb) {
        return { name: 'home' }
      }
    }
  }
})

export default router
