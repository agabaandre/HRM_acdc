<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { staffPortalHomeUrl } from '../lib/sso'
import { apiErrorMessage } from '../lib/apiErrorMessage'

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
  return role === 'admin' || !!auth.me?.profile?.can_manage_kb
})

const search = ref('')
const articles = ref<KbArticle[]>([])
const loading = ref(false)
const err = ref<string | null>(null)
const expanded = ref<Set<number>>(new Set())

let searchTimer: number | undefined

async function loadArticles(query = ''): Promise<void> {
  if (!auth.isAuthenticated) {
    articles.value = []
    return
  }
  loading.value = true
  err.value = null
  try {
    const params: Record<string, string | number> = {}
    if (query.trim() !== '') {
      params.q = query.trim()
    }
    const { data } = await api.get<{ data: KbArticle[] }>('/api/v1/kb/articles', { params })
    articles.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Could not load knowledge base.')
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
        Log and track incidents and requests for Africa CDC. You arrive here from the
        <strong>Staff portal home</strong> — the same secure session hand-off as Finance and APM.
      </template>
    </CbpPageHeading>

    <div v-if="!auth.isAuthenticated" class="cbp-card gate">
      <p class="gate-title">No active session in this app</p>
      <p class="gate-text">
        Open the Staff portal, sign in there, then choose <strong>IT Service Desk (Helpdesk)</strong> from your home dashboard. Your browser will receive a
        one-time token in the URL; this page exchanges it for an app session.
      </p>
      <a class="cbp-btn cbp-btn-primary" :href="portalHref">Go to Staff portal home</a>
    </div>

    <div v-else class="cbp-card welcome">
      <div class="actions">
        <RouterLink class="cbp-btn cbp-btn-primary" to="/tickets">My tickets</RouterLink>
        <RouterLink class="cbp-btn cbp-btn-ghost" to="/tickets/new">New request</RouterLink>
      </div>
    </div>

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

      <label class="kb-search">
        <span class="sr-only">Search the knowledge base</span>
        <input
          v-model="search"
          type="search"
          class="kb-search-input"
          placeholder="Search FAQs — try “password reset”, “VPN”, “printer”…"
          autocomplete="off"
        />
      </label>

      <p v-if="loading" class="kb-status" role="status">Loading articles…</p>
      <p v-else-if="err" class="kb-error" role="alert">{{ err }}</p>
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
        No articles match <em>“{{ search }}”</em>. Try a different search, or
        <RouterLink to="/tickets/new">log a new request</RouterLink>.
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
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
}
.cbp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.55rem 1.1rem;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.95rem;
  text-decoration: none;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.cbp-btn:hover {
  transform: translateY(-1px);
}
.cbp-btn-primary {
  background: linear-gradient(135deg, #119a48 0%, #0d7a3a 100%);
  color: #fff;
  box-shadow: 0 4px 14px rgba(17, 154, 72, 0.35);
}
.cbp-btn-ghost {
  background: transparent;
  color: #0d7a3a;
  border-color: rgba(17, 154, 72, 0.35);
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
  margin-top: 1rem;
  padding: 1.25rem;
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
  border-radius: 8px;
}
.kb-manage-link:hover {
  background: rgba(13, 122, 58, 0.08);
}
.kb-search {
  display: block;
  margin: 0.25rem 0 1rem;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.kb-search-input {
  width: 100%;
  padding: 0.7rem 0.9rem;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  font-size: 0.95rem;
  background: #fff;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.kb-search-input:focus {
  outline: none;
  border-color: #119a48;
  box-shadow: 0 0 0 3px rgba(17, 154, 72, 0.18);
}
.kb-status,
.kb-empty {
  margin: 0.5rem 0;
  color: #5c6c7c;
  font-size: 0.92rem;
}
.kb-error {
  margin: 0.5rem 0;
  padding: 0.65rem 0.85rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #991b1b;
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
  border-radius: 10px;
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
