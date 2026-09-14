<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { cachedGet } from '../lib/apiCache'
import { scheduleIdle } from '../lib/scheduleIdle'
import { useAuthStore } from '../stores/auth'
import { staffPortalHomeUrl } from '../lib/sso'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { notifyError } from '../lib/notify'
import { isAgentDeskUser } from '../lib/isAgentDeskUser'
import { HELP_DESK_NAV_ICONS } from '../lib/helpdeskNav'

const HomeAgentKanban = defineAsyncComponent(
  () => import('../components/home/HomeAgentKanban.vue'),
)

const HOME_FAQ_LIMIT = 5

interface KbCategoryRef {
  id: number
  name: string
  slug?: string
}
interface KbArticle {
  id: number
  category: KbCategoryRef | null
  question: string
  answer: string
  sort_order: number
  is_active: boolean
  updated_at?: string | null
}

interface HomeShortcut {
  to: string
  icon: string
  label: string
  featured?: boolean
  /** When true, navigates to Ask with the hub search text pre-filled. */
  passSearchToAsk?: boolean
}

const auth = useAuthStore()
const router = useRouter()
const portalHref = computed(() => staffPortalHomeUrl())

const isAgentHome = computed(() => isAgentDeskUser(auth.me?.profile))

const homeLede = computed(() => {
  if (isAgentHome.value) {
    return 'Log and track service requests across Africa CDC business units. Triage assigned tickets on your board below, browse FAQs, or open the agent desk for your full workload.'
  }
  return 'Log and track service requests across Africa CDC business units. Ask our AI assistant for guided troubleshooting, browse FAQs below, or create a ticket for the HelpDesk team.'
})

const agentShortcuts: HomeShortcut[] = [
  { to: '/desk/agent', icon: HELP_DESK_NAV_ICONS.agentDesk, label: 'Agent desk' },
  { to: '/tickets', icon: HELP_DESK_NAV_ICONS.tickets, label: 'All tickets' },
  {
    to: '/ask',
    icon: HELP_DESK_NAV_ICONS.ask,
    label: 'Get instant answers',
    featured: true,
    passSearchToAsk: true,
  },
  { to: '/guide', icon: HELP_DESK_NAV_ICONS.guide, label: 'User guide' },
]

const requesterShortcuts: HomeShortcut[] = [
  {
    to: '/ask',
    icon: HELP_DESK_NAV_ICONS.ask,
    label: 'Get instant answers',
    featured: true,
    passSearchToAsk: true,
  },
  { to: '/tickets', icon: HELP_DESK_NAV_ICONS.tickets, label: 'My tickets' },
  { to: '/guide', icon: HELP_DESK_NAV_ICONS.guide, label: 'User guide' },
]

const homeShortcuts = computed(() => (isAgentHome.value ? agentShortcuts : requesterShortcuts))

const canManageKb = computed(() => {
  const role = auth.me?.profile?.role ?? ''
  return !!auth.me?.profile?.is_helpdesk_admin || role === 'admin' || !!auth.me?.profile?.can_manage_kb
})

const search = ref('')
const articles = ref<KbArticle[]>([])
const totalArticleCount = ref(0)
const loading = ref(false)
const expandedPanel = ref<number | undefined>(undefined)

let searchTimer: number | undefined

async function loadArticles(query = ''): Promise<void> {
  if (!auth.isAuthenticated) {
    articles.value = []
    totalArticleCount.value = 0
    return
  }
  loading.value = true
  try {
    const params: Record<string, string | number> = {}
    if (query.trim() !== '') {
      params.q = query.trim()
    }
    const data = await cachedGet<{ data: KbArticle[]; meta?: { count?: number } }>(
      'kb:articles',
      '/api/v1/kb/articles',
      60_000,
      params,
    )
    articles.value = Array.isArray(data.data) ? data.data : []
    totalArticleCount.value = typeof data.meta?.count === 'number' ? data.meta.count : articles.value.length
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not load knowledge base.'))
    articles.value = []
    totalArticleCount.value = 0
  } finally {
    loading.value = false
  }
}

const previewArticles = computed(() => articles.value.slice(0, HOME_FAQ_LIMIT))

const hasMoreFaqs = computed(() => totalArticleCount.value > HOME_FAQ_LIMIT)

const faqSummaryLabel = computed(() => {
  const shown = previewArticles.value.length
  const total = totalArticleCount.value
  if (total === 0 || shown === 0) {
    return ''
  }
  if (shown >= total) {
    return `${total} question${total === 1 ? '' : 's'}`
  }
  return `Showing ${shown} of ${total}`
})

function goToAskWithSearch(): void {
  const q = search.value.trim()
  if (q.length >= 8) {
    void router.push({ path: '/ask', query: { q } })
    return
  }
  void router.push('/ask')
}

function onHubShortcut(shortcut: HomeShortcut, event: MouseEvent): void {
  if (!shortcut.passSearchToAsk) {
    return
  }
  event.preventDefault()
  goToAskWithSearch()
}

function onHubSearchEnter(event: KeyboardEvent): void {
  if (event.shiftKey) {
    return
  }
  event.preventDefault()
  const q = search.value.trim()
  if (q.length >= 8) {
    goToAskWithSearch()
    return
  }
  const kb = document.getElementById('kb-heading')
  kb?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function isHtml(value: string): boolean {
  return /<[a-z][\s\S]*>/i.test(value)
}

watch(search, (value) => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }
  searchTimer = window.setTimeout(() => {
    void loadArticles(value)
  }, 280)
})

onMounted(() => {
  if (auth.isAuthenticated && !auth.me) {
    void auth.fetchMe().catch(() => {})
  }
  scheduleIdle(() => {
    void loadArticles()
  })
})
</script>

<template>
  <div class="hd-home">
    <v-card class="hd-home-hero" variant="flat" rounded="lg">
      <v-card-text class="pa-0">
        <CbpBadgeStrip product="ITSM" />
        <CbpPageHeading title="HelpDesk">
          <template #lede>
            <span class="hd-home-lede">{{ homeLede }}</span>
          </template>
        </CbpPageHeading>
      </v-card-text>
    </v-card>

    <v-card v-if="!auth.isAuthenticated" class="gate" variant="outlined" rounded="lg">
      <v-card-text>
        <p class="gate-title">No active session in this app</p>
        <p class="gate-text">
          Open the Staff portal, sign in there, then choose <strong>HelpDesk</strong> from your home dashboard.
        </p>
        <v-btn :href="portalHref" color="primary" variant="flat" size="large">Go to Staff portal home</v-btn>
      </v-card-text>
    </v-card>

    <template v-else>
      <v-card class="hd-home-hub" variant="flat" rounded="lg">
        <v-card-text class="hd-home-hub__body">
          <div class="hd-home-hub__intro">
            <p class="hd-home-hub__eyebrow">Start here</p>
            <RouterLink to="/tickets/new" class="hd-home-hub__create-btn">
              <i :class="HELP_DESK_NAV_ICONS.newRequest" aria-hidden="true" />
              <span>Create ticket</span>
            </RouterLink>
            <h2 class="hd-home-hub__title">What do you need help with?</h2>
            <p class="hd-home-hub__hint">
              Search common fixes below, or open our AI assistant for guided troubleshooting.
            </p>
          </div>

          <div class="hd-home-hub__search-wrap">
            <v-text-field
              v-model="search"
              placeholder="Try “VPN”, “password reset”, “printer offline”…"
              prepend-inner-icon="mdi-magnify"
              clearable
              density="comfortable"
              variant="solo-filled"
              flat
              hide-details
              class="hd-home-hub__search"
              autocomplete="off"
              aria-label="Search help topics and knowledge base"
              @keydown.enter="onHubSearchEnter"
            />
            <v-btn
              color="primary"
              variant="flat"
              size="default"
              class="hd-home-hub__ask-btn"
              prepend-icon="mdi-robot-outline"
              @click="goToAskWithSearch"
            >
              Get instant answers
            </v-btn>
          </div>

          <nav class="hd-home-hub__shortcuts" aria-label="Helpdesk shortcuts">
            <component
              :is="shortcut.passSearchToAsk ? 'button' : RouterLink"
              v-for="shortcut in homeShortcuts"
              :key="shortcut.to + shortcut.label"
              :to="shortcut.passSearchToAsk ? undefined : shortcut.to"
              type="button"
              class="hd-home-hub__chip"
              :class="{ 'hd-home-hub__chip--featured': shortcut.featured }"
              @click="shortcut.passSearchToAsk ? onHubShortcut(shortcut, $event) : undefined"
            >
              <i :class="shortcut.icon" aria-hidden="true" />
              <span>{{ shortcut.label }}</span>
            </component>
          </nav>
        </v-card-text>
      </v-card>

      <HomeAgentKanban v-if="isAgentHome" class="hd-home-kanban" />
    </template>

    <v-card v-if="auth.isAuthenticated" class="hd-kb-card" variant="outlined" aria-labelledby="kb-heading">
      <v-card-item class="kb-header">
        <div>
          <p class="panel-title">Knowledge base</p>
          <h2 id="kb-heading" class="kb-title">Top questions</h2>
          <p class="kb-lede">A short preview of common answers. Results update as you search above.</p>
        </div>
        <template #append>
          <RouterLink v-if="canManageKb" class="kb-manage-link" to="/knowledge-base/manage">
            Manage articles →
          </RouterLink>
        </template>
      </v-card-item>

      <v-divider />

      <v-card-text>
      <div v-if="loading" class="kb-skeleton" role="status" aria-busy="true" aria-label="Loading articles">
        <v-skeleton-loader
          v-for="n in 5"
          :key="n"
          type="list-item-two-line"
          class="kb-skeleton-item"
        />
      </div>
      <p v-else-if="articles.length === 0 && search.trim() === ''" class="kb-empty">
        No articles yet.
        <template v-if="canManageKb">
          <RouterLink to="/knowledge-base/manage">Add the first FAQ</RouterLink>
          to help colleagues self-serve.
        </template>
        <template v-else>
          Once your helpdesk team publishes FAQs, they will appear here.
        </template>
      </p>
      <p v-else-if="articles.length === 0" class="kb-empty">
        No articles match <em>“{{ search }}”</em>. Try
        <button type="button" class="hd-inline-link-btn" @click="goToAskWithSearch">Get instant answers</button>
        or <RouterLink to="/tickets/new">create a ticket</RouterLink>.
      </p>

      <v-expansion-panels
        v-else
        v-model="expandedPanel"
        variant="accordion"
        class="kb-panels"
        elevation="0"
      >
        <v-expansion-panel
          v-for="a in previewArticles"
          :key="a.id"
          :value="a.id"
          rounded="lg"
          elevation="0"
        >
          <v-expansion-panel-title class="kb-panel-title">
            <span class="kb-question-wrap">
              <v-chip
                v-if="a.category?.name"
                size="x-small"
                color="primary"
                variant="tonal"
                class="kb-category-chip mb-1"
                label
              >
                {{ a.category.name }}
              </v-chip>
              <span class="kb-question">{{ a.question }}</span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text class="kb-panel-text">
            <div v-if="isHtml(a.answer)" class="kb-answer-body rich-text-content" v-html="a.answer"></div>
            <p v-else class="kb-answer-body kb-answer-plain">{{ a.answer }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <footer v-if="previewArticles.length > 0" class="hd-kb-foot">
        <span v-if="faqSummaryLabel">{{ faqSummaryLabel }}</span>
        <RouterLink v-if="hasMoreFaqs || search.trim() !== ''" class="hd-kb-more-link" to="/ask">
          {{ hasMoreFaqs ? 'Get instant answers for more help →' : 'Open Get instant answers →' }}
        </RouterLink>
      </footer>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.hd-home-kanban {
  margin-bottom: 0.25rem;
}
.hd-inline-link-btn {
  border: 0;
  padding: 0;
  background: none;
  color: #0d7a3a;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
}
.kb-panels {
  background: transparent !important;
}
.kb-panels :deep(.v-expansion-panel) {
  border: 1px solid var(--hd-line);
  margin-bottom: 0.45rem;
  background: var(--cbp-card-bg, #fff);
  color: var(--cbp-card-text, #1f2933);
}
.kb-panels :deep(.v-expansion-panel-title) {
  background: transparent;
  color: inherit;
}
.kb-panels :deep(.v-expansion-panel--active) {
  border-color: rgba(17, 154, 72, 0.4);
}
.kb-panel-title {
  font-weight: 600;
  font-size: 0.95rem;
  color: inherit;
}
.kb-category-chip {
  align-self: flex-start;
}
.kb-panel-text {
  color: var(--cbp-card-text, #3a4452);
  font-size: 0.93rem;
  line-height: 1.6;
}
.gate {
  border-left: 4px solid #c9a227;
}
.gate .v-card-text {
  padding-top: 1.25rem !important;
  padding-bottom: 1.25rem !important;
}
.hd-home-hero {
  background: transparent !important;
  box-shadow: none !important;
}
.gate-title {
  margin: 0 0 0.5rem;
  font-weight: 700;
  font-size: 1.05rem;
  color: #2c3e50;
}
.gate-text {
  margin: 0 0 1.1rem;
  color: #5c6c7c;
  line-height: 1.55;
  font-size: 0.95rem;
}
.panel-title {
  margin: 0 0 0.25rem;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6c757d;
}

.kb-header.v-card-item {
  align-items: flex-end;
}
.kb-header {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  align-items: flex-end;
  width: 100%;
}
.kb-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--cbp-card-text, #1f2933);
}
.kb-lede {
  margin: 0.35rem 0 0;
  color: var(--hd-muted, #5c6c7c);
  font-size: 0.92rem;
  max-width: 40rem;
}
.kb-manage-link {
  font-weight: 700;
  font-size: 0.9rem;
  color: #0d7a3a;
  text-decoration: none;
  padding: 0.45rem 0.85rem;
  border: 1px solid rgba(13, 122, 58, 0.35);
  border-radius: 4px;
}
.kb-manage-link:hover {
  background: rgba(13, 122, 58, 0.08);
}
.kb-skeleton {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.kb-skeleton-item :deep(.v-skeleton-loader__bone) {
  border-radius: 4px;
}
.kb-empty {
  margin: 0.5rem 0;
  color: #5c6c7c;
  font-size: 0.92rem;
}
.kb-question-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}
.kb-question {
  line-height: 1.4;
}
.kb-answer-body :deep(p) {
  margin: 0.5rem 0;
}
.kb-answer-body :deep(ul),
.kb-answer-body :deep(ol) {
  margin: 0.5rem 0 0.5rem 1.25rem;
}
.kb-answer-body :deep(a) {
  color: #0d7a3a;
}
.kb-answer-plain {
  margin: 0.75rem 0 0;
  white-space: pre-wrap;
}
</style>

<style>
/* Dark mode KB panels — unscoped + hex so stale/chunk CSS cannot leave white rows */
html.helpdesk-theme-dark .hd-kb-card .kb-panels .v-expansion-panel,
html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel {
  background: #0f172a !important;
  background-color: #0f172a !important;
  color: #f1f5f9 !important;
  border-color: rgba(148, 163, 184, 0.32) !important;
}

html.helpdesk-theme-dark .hd-kb-card .kb-panels .v-expansion-panel--active,
html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel--active {
  background: #152033 !important;
  background-color: #152033 !important;
  border-color: rgba(74, 222, 128, 0.45) !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-title,
html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-title__overlay,
html.helpdesk-theme-dark .hd-kb-card .kb-panel-title,
html.helpdesk-theme-dark .hd-kb-card .kb-question,
html.helpdesk-theme-dark .hd-kb-card .kb-question-wrap {
  color: #f1f5f9 !important;
  opacity: 1 !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-title,
html.helpdesk-theme-dark .hd-kb-card .kb-panels .v-expansion-panel-title {
  background: #0f172a !important;
  background-color: #0f172a !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel--active > .v-expansion-panel-title {
  background: #152033 !important;
  background-color: #152033 !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-title .v-expansion-panel-title__overlay {
  background: transparent !important;
  opacity: 0 !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-text,
html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-text__wrapper,
html.helpdesk-theme-dark .hd-kb-card .kb-panel-text,
html.helpdesk-theme-dark .hd-kb-card .kb-answer-body,
html.helpdesk-theme-dark .hd-kb-card .kb-answer-plain {
  background: transparent !important;
  color: #cbd5e1 !important;
}

html.helpdesk-theme-dark .hd-kb-card .v-expansion-panel-title .v-icon {
  color: #94a3b8 !important;
  opacity: 1 !important;
}

html.helpdesk-theme-dark .hd-kb-card .kb-category-chip.v-chip,
html.helpdesk-theme-dark .hd-kb-card .v-chip.kb-category-chip {
  background: rgba(74, 222, 128, 0.2) !important;
  color: #bbf7d0 !important;
}

html.helpdesk-theme-dark .hd-kb-card .kb-title {
  color: #f1f5f9 !important;
}

html.helpdesk-theme-dark .hd-kb-card .kb-lede,
html.helpdesk-theme-dark .hd-kb-card .panel-title {
  color: #94a3b8 !important;
}
</style>
