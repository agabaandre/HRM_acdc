<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import HomeAgentKanban from '../components/home/HomeAgentKanban.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { staffPortalHomeUrl } from '../lib/sso'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { notifyError } from '../lib/notify'
import { isAgentDeskUser } from '../lib/isAgentDeskUser'
import { HELP_DESK_NAV_ICONS } from '../lib/helpdeskNav'

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

interface QuickAction {
  to: string
  icon: string
  title: string
  description: string
}

const quickActions: QuickAction[] = [
  {
    to: '/ask',
    icon: 'bx-bot',
    title: 'Ask Helpdesk',
    description: 'AI-guided answers and step-by-step fixes from our knowledge base.',
  },
  {
    to: '/tickets',
    icon: 'bx-support',
    title: 'My tickets',
    description: 'Track open requests, replies, and resolution status.',
  },
  {
    to: '/tickets/new',
    icon: 'bx-plus-circle',
    title: 'New request',
    description: 'Log an incident or service request for an IT agent.',
  },
  {
    to: '/guide',
    icon: 'bx-book-reader',
    title: 'User guide',
    description: 'Four quick slides on signing in, logging tickets, and follow-up.',
  },
]

const auth = useAuthStore()
const portalHref = computed(() => staffPortalHomeUrl())

const isAgentHome = computed(() => isAgentDeskUser(auth.me?.profile))

const homeLede = computed(() => {
  if (isAgentHome.value) {
    return 'Log and track incidents and requests for Africa CDC — same secure Staff portal session as Finance and APM. Triage assigned tickets on your board below, browse FAQs, or use the shortcuts for Ask Helpdesk and the full agent desk.'
  }
  return 'Log and track incidents and requests for Africa CDC — same secure Staff portal session as Finance and APM. Ask our AI assistant for guided troubleshooting, browse FAQs below, or log a new request for the service desk team.'
})

const agentShortcuts = [
  { to: '/ask', icon: HELP_DESK_NAV_ICONS.ask, label: 'Ask Helpdesk' },
  { to: '/tickets', icon: HELP_DESK_NAV_ICONS.tickets, label: 'All tickets' },
  { to: '/desk/agent', icon: HELP_DESK_NAV_ICONS.agentDesk, label: 'Agent desk' },
  { to: '/guide', icon: HELP_DESK_NAV_ICONS.guide, label: 'User guide' },
]

const canManageKb = computed(() => {
  const role = auth.me?.profile?.role ?? ''
  return !!auth.me?.profile?.is_helpdesk_admin || role === 'admin' || !!auth.me?.profile?.can_manage_kb
})

const search = ref('')
const articles = ref<KbArticle[]>([])
const totalArticleCount = ref(0)
const loading = ref(false)
const expanded = ref<Set<number>>(new Set())

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
    const { data } = await api.get<{ data: KbArticle[]; meta?: { count?: number } }>('/api/v1/kb/articles', { params })
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

function toggle(id: number): void {
  const next = new Set(expanded.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  expanded.value = next
}

function isExpanded(id: number): boolean {
  return expanded.value.has(id)
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
  void loadArticles()
})
</script>

<template>
  <div class="hd-home">
    <CbpBadgeStrip product="ITSM" />
    <CbpPageHeading title="IT Service Desk">
      <template #lede>
        <span class="hd-home-lede">{{ homeLede }}</span>
      </template>
    </CbpPageHeading>

    <UCard v-if="!auth.isAuthenticated" class="gate">
      <p class="gate-title">No active session in this app</p>
      <p class="gate-text">
        Open the Staff portal, sign in there, then choose <strong>IT Service Desk (Helpdesk)</strong> from your home dashboard.
      </p>
      <UButton :href="portalHref" color="primary" size="md">Go to Staff portal home</UButton>
    </UCard>

    <template v-else>
      <nav
        v-if="isAgentHome"
        class="hd-shortcut-row"
        aria-label="Helpdesk shortcuts"
      >
        <RouterLink
          v-for="s in agentShortcuts"
          :key="s.to"
          :to="s.to"
          class="hd-shortcut-chip"
        >
          <i :class="s.icon" aria-hidden="true" />
          {{ s.label }}
        </RouterLink>
      </nav>

      <HomeAgentKanban v-if="isAgentHome" class="hd-home-kanban" />

      <div v-if="!isAgentHome" class="hd-quick-grid" role="navigation" aria-label="Helpdesk shortcuts">
        <UCard
          v-for="action in quickActions"
          :key="action.to"
          class="hd-action-card"
          :ui="{ body: 'p-4 sm:p-5' }"
        >
          <RouterLink :to="action.to" class="hd-action-link">
            <span class="hd-action-icon" aria-hidden="true">
              <i :class="['bx', action.icon]" />
            </span>
            <span class="hd-action-body">
              <strong class="hd-action-title">{{ action.title }}</strong>
              <span class="hd-action-desc">{{ action.description }}</span>
            </span>
            <i class="bx bx-chevron-right hd-action-chevron" aria-hidden="true" />
          </RouterLink>
        </UCard>
      </div>
    </template>

    <v-card v-if="auth.isAuthenticated" class="hd-kb-card" variant="outlined" aria-labelledby="kb-heading">
      <v-card-item class="kb-header">
        <div>
          <p class="panel-title">Knowledge base</p>
          <h2 id="kb-heading" class="kb-title">Top questions</h2>
          <p class="kb-lede">A short preview of common answers. Search below or use Ask Helpdesk for guided help.</p>
        </div>
        <template #append>
          <RouterLink v-if="canManageKb" class="kb-manage-link" to="/knowledge-base/manage">
            Manage articles →
          </RouterLink>
        </template>
      </v-card-item>

      <v-divider />

      <v-card-text>
      <UFormField name="kbSearch" class="kb-search">
        <UInput
          v-model="search"
          type="search"
          icon="i-lucide-search"
          placeholder="Search FAQs — try “password reset”, “VPN”, “printer”…"
          autocomplete="off"
          aria-label="Search the knowledge base"
          class="w-full"
        />
      </UFormField>

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
        <RouterLink to="/ask">Ask Helpdesk</RouterLink>
        or <RouterLink to="/tickets/new">log a new request</RouterLink>.
      </p>

      <ul v-else class="kb-list">
        <li v-for="a in previewArticles" :key="a.id" class="kb-item" :class="{ 'is-open': isExpanded(a.id) }">
          <button
            type="button"
            class="kb-item-toggle"
            :aria-expanded="isExpanded(a.id)"
            @click="toggle(a.id)"
          >
            <span class="kb-question-wrap">
              <span v-if="a.category?.name" class="kb-category-pill">{{ a.category.name }}</span>
              <span class="kb-question">{{ a.question }}</span>
            </span>
            <span class="kb-caret" aria-hidden="true">{{ isExpanded(a.id) ? '−' : '+' }}</span>
          </button>
          <div v-if="isExpanded(a.id)" class="kb-answer">
            <div v-if="isHtml(a.answer)" class="kb-answer-body rich-text-content" v-html="a.answer"></div>
            <p v-else class="kb-answer-body kb-answer-plain">{{ a.answer }}</p>
          </div>
        </li>
      </ul>

      <footer v-if="previewArticles.length > 0" class="hd-kb-foot">
        <span v-if="faqSummaryLabel">{{ faqSummaryLabel }}</span>
        <RouterLink v-if="hasMoreFaqs || search.trim() !== ''" class="hd-kb-more-link" to="/ask">
          {{ hasMoreFaqs ? 'Ask Helpdesk for more answers →' : 'Open Ask Helpdesk →' }}
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
.gate {
  border-left: 4px solid #c9a227;
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
  color: #1f2933;
}
.kb-lede {
  margin: 0.35rem 0 0;
  color: #5c6c7c;
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
.kb-search {
  display: block;
  margin: 0 0 1rem;
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
.kb-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}
.kb-item {
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  overflow: hidden;
  background: #fff;
  transition: border-color 0.15s ease;
}
.kb-item.is-open {
  border-color: rgba(17, 154, 72, 0.4);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
}
.kb-item-toggle {
  width: 100%;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 0.95rem;
  background: transparent;
  border: 0;
  text-align: left;
  font-size: 0.95rem;
  font-weight: 600;
  font-family: inherit;
  color: #1f2933;
  cursor: pointer;
}
.kb-item-toggle:hover {
  background: #f8fafc;
}
.kb-question-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}
.kb-category-pill {
  align-self: flex-start;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #0d7a3a;
  background: rgba(17, 154, 72, 0.1);
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
}
.kb-question {
  line-height: 1.4;
}
.kb-caret {
  font-size: 1.25rem;
  color: #119a48;
  font-weight: 700;
  width: 1.25rem;
  text-align: center;
  flex-shrink: 0;
}
.kb-answer {
  padding: 0 0.95rem 0.95rem;
  border-top: 1px solid var(--hd-line-subtle);
  color: #3a4452;
  font-size: 0.93rem;
  line-height: 1.6;
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
