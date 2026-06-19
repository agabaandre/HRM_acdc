<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { staffPortalHomeUrl } from '../lib/sso'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { notifyError } from '../lib/notify'

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

const auth = useAuthStore()
const portalHref = computed(() => staffPortalHomeUrl())

const canManageKb = computed(() => {
  const role = auth.me?.profile?.role ?? ''
  return !!auth.me?.profile?.is_helpdesk_admin || role === 'admin' || !!auth.me?.profile?.can_manage_kb
})

const search = ref('')
const articles = ref<KbArticle[]>([])
const loading = ref(false)
const expanded = ref<Set<number>>(new Set())

let searchTimer: number | undefined

async function loadArticles(query = ''): Promise<void> {
  if (!auth.isAuthenticated) {
    articles.value = []
    return
  }
  loading.value = true
  try {
    const params: Record<string, string | number> = {}
    if (query.trim() !== '') {
      params.q = query.trim()
    }
    const { data } = await api.get<{ data: KbArticle[] }>('/api/v1/kb/articles', { params })
    articles.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not load knowledge base.'))
    articles.value = []
  } finally {
    loading.value = false
  }
}

const grouped = computed<Array<{ id: number; name: string; rows: KbArticle[] }>>(() => {
  const groups = new Map<number, { id: number; name: string; rows: KbArticle[] }>()
  for (const a of articles.value) {
    const cid = a.category?.id ?? 0
    const cname = a.category?.name ?? 'Uncategorised'
    if (!groups.has(cid)) {
      groups.set(cid, { id: cid, name: cname, rows: [] })
    }
    groups.get(cid)!.rows.push(a)
  }
  return [...groups.values()].sort((g1, g2) => g1.name.localeCompare(g2.name))
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
  <div>
    <CbpBadgeStrip product="ITSM" />
    <CbpPageHeading title="IT Service Desk">
      <template #lede>
        Log and track incidents and requests for Africa CDC. Arrive from the Staff portal home with the same secure session as Finance and APM.
      </template>
    </CbpPageHeading>

    <div v-if="!auth.isAuthenticated" class="cbp-card gate">
      <p class="gate-title">No active session in this app</p>
      <p class="gate-text">
        Open the Staff portal, sign in there, then choose <strong>IT Service Desk (Helpdesk)</strong> from your home dashboard.
      </p>
      <a class="hd-btn hd-btn--primary" :href="portalHref">Go to Staff portal home</a>
    </div>

    <template v-else>
      <section class="hd-hero" aria-label="Quick start">
        <div>
          <p class="hd-hero-eyebrow">Africa CDC · IT Service Desk</p>
          <h2 class="hd-hero-title">Get support faster</h2>
          <p class="hd-hero-text">
            Ask our AI assistant for guided troubleshooting, browse FAQs, or log a request for the service desk team.
          </p>
        </div>
        <div class="hd-hero-actions">
          <RouterLink class="hd-btn hd-btn--white" to="/ask">
            <i class="bx bx-bot" aria-hidden="true" /> Ask Helpdesk
          </RouterLink>
          <RouterLink class="hd-btn hd-btn--ghost-light" to="/tickets/new">New request</RouterLink>
        </div>
      </section>

      <div class="hd-quick-grid">
        <RouterLink class="hd-quick-tile" to="/ask">
          <i class="bx bx-bot" aria-hidden="true" />
          <strong>Ask Helpdesk</strong>
          <span>AI-guided answers and step-by-step fixes from our knowledge base.</span>
        </RouterLink>
        <RouterLink class="hd-quick-tile" to="/tickets">
          <i class="bx bx-support" aria-hidden="true" />
          <strong>My tickets</strong>
          <span>Track open requests, replies, and resolution status.</span>
        </RouterLink>
        <RouterLink class="hd-quick-tile" to="/tickets/new">
          <i class="bx bx-plus-circle" aria-hidden="true" />
          <strong>New request</strong>
          <span>Log an incident or service request for an IT agent.</span>
        </RouterLink>
      </div>
    </template>

    <section v-if="auth.isAuthenticated" class="cbp-card kb-card" aria-labelledby="kb-heading">
      <header class="kb-header">
        <div>
          <p class="panel-title">Knowledge base</p>
          <h2 id="kb-heading" class="kb-title">Frequently asked questions</h2>
          <p class="kb-lede">Browse answers by category, or search across every article below.</p>
        </div>
        <RouterLink v-if="canManageKb" class="kb-manage-link" to="/knowledge-base/manage">
          Manage articles →
        </RouterLink>
      </header>

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

      <p v-if="loading" class="kb-status" role="status">Loading articles…</p>
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

      <div v-else class="kb-groups">
        <section v-for="g in grouped" :key="g.id" class="kb-group">
          <h3 class="kb-group-title">{{ g.name }}<span class="kb-group-count">({{ g.rows.length }})</span></h3>
          <ul class="kb-list">
            <li v-for="a in g.rows" :key="a.id" class="kb-item" :class="{ 'is-open': isExpanded(a.id) }">
              <button
                type="button"
                class="kb-item-toggle"
                :aria-expanded="isExpanded(a.id)"
                @click="toggle(a.id)"
              >
                <span class="kb-question">{{ a.question }}</span>
                <span class="kb-caret" aria-hidden="true">{{ isExpanded(a.id) ? '−' : '+' }}</span>
              </button>
              <div v-if="isExpanded(a.id)" class="kb-answer">
                <div v-if="isHtml(a.answer)" class="kb-answer-body rich-text-content" v-html="a.answer"></div>
                <p v-else class="kb-answer-body kb-answer-plain">{{ a.answer }}</p>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </section>
  </div>
</template>

<style scoped>
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

.kb-card {
  margin-top: 0;
}
.kb-header {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 1rem;
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
  margin: 0.25rem 0 1rem;
}
.kb-status,
.kb-empty {
  margin: 0.5rem 0;
  color: #5c6c7c;
  font-size: 0.92rem;
}
.kb-groups {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}
.kb-group-title {
  margin: 0 0 0.5rem;
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #0d7a3a;
}
.kb-group-count {
  margin-left: 0.4rem;
  color: #94a3b8;
  font-weight: 600;
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
  border: 1px solid #e2e8f0;
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
  align-items: center;
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
.kb-caret {
  font-size: 1.25rem;
  color: #119a48;
  font-weight: 700;
  width: 1.25rem;
  text-align: center;
}
.kb-answer {
  padding: 0 0.95rem 0.95rem;
  border-top: 1px solid #f1f5f9;
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
