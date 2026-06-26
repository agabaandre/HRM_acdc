<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { notifyError } from '../lib/notify'
import {
  formatTableCountLabel,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'
import { PER_PAGE_ITEMS, normalizePageSize, type PageSize, type SelectNumberItem } from '../lib/helpdeskForm'

import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const tab = ref<'mine' | 'admin'>('mine')

/** Ticket rows from report APIs (aligned with ticket API resource fields). */
interface ReportTicket {
  id: number
  ticket_number: string
  subject?: string
  status?: string
  assignee?: { name: string; avatar_url?: string | null } | null
}

interface PaginatedTickets {
  current_page: number
  data: ReportTicket[]
  last_page: number
  per_page: number
  total: number
}

const myStats = ref<{ total_received: number; pending: number; resolved: number } | null>(null)
const myTickets = ref<ReportTicket[]>([])
const myPage = ref(1)
const myLastPage = ref(1)
const myTotal = ref(0)
const mySearchState = reactive<{ q: string; perPage: PageSize }>({ q: '', perPage: 20 })
const myLoading = ref(false)

const adminCounts = ref<Record<string, number> | null>(null)
const adminRecent = ref<ReportTicket[]>([])
const adminPage = ref(1)
const adminLastPage = ref(1)
const adminTotal = ref(0)
const adminSearchState = reactive<{
  q: string
  perPage: PageSize
  agentIds: number[]
  dateFrom: string
  dateTo: string
}>({ q: '', perPage: 20, agentIds: [], dateFrom: '', dateTo: '' })
const adminLoading = ref(false)
const adminAgents = ref<{ id: number; name: string; email: string }[]>([])

const adminAgentItems = computed((): SelectNumberItem[] =>
  adminAgents.value.map((a) => ({ label: `${a.name} (${a.email})`, value: a.id })),
)

const isAdmin = computed(
  () => !!auth.me?.profile?.is_helpdesk_admin || auth.me?.profile?.role === 'admin',
)
const myHasPrev = computed(() => myPage.value > 1)
const myHasNext = computed(() => myPage.value < myLastPage.value)
const adminHasPrev = computed(() => adminPage.value > 1)
const adminHasNext = computed(() => adminPage.value < adminLastPage.value)
const myTableCountLabel = computed(() =>
  formatTableCountLabel(myTickets.value.length, myTotal.value, myPage.value, mySearchState.perPage),
)
const adminTableCountLabel = computed(() =>
  formatTableCountLabel(adminRecent.value.length, adminTotal.value, adminPage.value, adminSearchState.perPage),
)

function myCounter(idx: number): number {
  return rowIndex(myPage.value, mySearchState.perPage, idx)
}

function adminCounter(idx: number): number {
  return rowIndex(adminPage.value, adminSearchState.perPage, idx)
}

async function loadMine() {
  myLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/my-requester', {
      params: {
        q: mySearchState.q.trim() || undefined,
        page: myPage.value,
        per_page: mySearchState.perPage,
      },
    })
    myStats.value = data.data.stats
    const tickets = (data.data.tickets ?? {}) as Partial<PaginatedTickets>
    myTickets.value = (tickets.data ?? []) as ReportTicket[]
    myPage.value = Number(tickets.current_page ?? myPage.value)
    myLastPage.value = Math.max(1, Number(tickets.last_page ?? 1))
    mySearchState.perPage = normalizePageSize(Number(tickets.per_page ?? mySearchState.perPage))
    myTotal.value = Number(tickets.total ?? myTickets.value.length)
  } finally {
    myLoading.value = false
  }
}

function adminReportParams() {
  return {
    q: adminSearchState.q.trim() || undefined,
    page: adminPage.value,
    per_page: adminSearchState.perPage,
    agent_ids: adminSearchState.agentIds.length ? adminSearchState.agentIds : undefined,
    date_from: adminSearchState.dateFrom || undefined,
    date_to: adminSearchState.dateTo || undefined,
  }
}

async function loadAdmin() {
  adminLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/admin-summary', {
      params: adminReportParams(),
    })
    adminCounts.value = data.data.counts
    const recent = (data.data.recent ?? {}) as Partial<PaginatedTickets>
    adminRecent.value = (recent.data ?? []) as ReportTicket[]
    adminPage.value = Number(recent.current_page ?? adminPage.value)
    adminLastPage.value = Math.max(1, Number(recent.last_page ?? 1))
    adminSearchState.perPage = normalizePageSize(Number(recent.per_page ?? adminSearchState.perPage))
    adminTotal.value = Number(recent.total ?? adminRecent.value.length)
  } finally {
    adminLoading.value = false
  }
}

async function switchTab(next: 'mine' | 'admin') {
  tab.value = next
  await load()
}

async function load() {
  try {
    if (tab.value === 'admin' && isAdmin.value) {
      await loadAdmin()
    } else {
      await loadMine()
    }
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load report'))
    myLoading.value = false
    adminLoading.value = false
  }
}

function mySearch() {
  myPage.value = 1
  loadMine()
}
function myClear() {
  mySearchState.q = ''
  myPage.value = 1
  loadMine()
}
function adminSearch() {
  adminPage.value = 1
  loadAdmin()
}
function adminClear() {
  adminSearchState.q = ''
  adminSearchState.agentIds = []
  adminSearchState.dateFrom = ''
  adminSearchState.dateTo = ''
  adminPage.value = 1
  loadAdmin()
}

watch(() => mySearchState.perPage, () => {
  myPage.value = 1
  loadMine()
})

watch(() => adminSearchState.perPage, () => {
  adminPage.value = 1
  loadAdmin()
})

watch(
  () => [adminSearchState.agentIds, adminSearchState.dateFrom, adminSearchState.dateTo] as const,
  () => {
    adminPage.value = 1
    loadAdmin()
  },
)

async function downloadExcel(scope: 'assigned' | 'all' | 'mine') {
  try {
    const params: Record<string, unknown> = { scope }
    if (scope === 'all' && tab.value === 'admin') {
      Object.assign(params, {
        q: adminSearchState.q.trim() || undefined,
        agent_ids: adminSearchState.agentIds.length ? adminSearchState.agentIds : undefined,
        date_from: adminSearchState.dateFrom || undefined,
        date_to: adminSearchState.dateTo || undefined,
      })
    }
    const res = await api.get('/api/v1/reports/export', {
      params,
      responseType: 'blob',
    })
    const blob = new Blob([res.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `helpdesk-export-${scope}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    notifyError('Export failed (check you are signed in as staff).')
  }
}

onMounted(async () => {
  if (isAdmin.value) {
    tab.value = 'admin'
    try {
      const { data } = await api.get('/api/v1/admin/agents')
      adminAgents.value = Array.isArray(data.data) ? data.data : []
    } catch {
      adminAgents.value = []
    }
  } else {
    tab.value = 'mine'
  }
  await load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Reports" back-to="/" back-label="← Overview" />
    <div class="cbp-card">
      <div v-if="isAdmin" class="tabs">
        <button type="button" :class="{ on: tab === 'admin' }" @click="switchTab('admin')">Admin overview</button>
        <button type="button" :class="{ on: tab === 'mine' }" @click="switchTab('mine')">My issues</button>
      </div>

      <template v-if="tab === 'mine' && myStats">
      <div class="tiles">
        <article class="tile tile-total">
          <header>
            <span class="tile-icon" aria-hidden="true">📥</span>
            <span class="l">Total received</span>
          </header>
          <span class="n">{{ myStats.total_received }}</span>
          <small class="tile-sub">All tickets logged for you</small>
        </article>
        <article class="tile tile-pending">
          <header>
            <span class="tile-icon" aria-hidden="true">⏳</span>
            <span class="l">Pending resolution</span>
          </header>
          <span class="n">{{ myStats.pending }}</span>
          <small class="tile-sub">Still being worked on</small>
        </article>
        <article class="tile tile-resolved">
          <header>
            <span class="tile-icon" aria-hidden="true">✅</span>
            <span class="l">Resolved</span>
          </header>
          <span class="n">{{ myStats.resolved }}</span>
          <small class="tile-sub">Completed tickets</small>
        </article>
      </div>
      <div class="toolbar">
        <UForm :state="mySearchState" class="hd-search-form searchbar" @submit="mySearch">
          <UFormField name="q" class="hd-form-toolbar-grow">
            <UInput
              v-model="mySearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Search my tickets by #, subject, status, assignee…"
              aria-label="Search my tickets"
              class="w-full"
            />
          </UFormField>
          <UButton type="submit" color="primary">Search</UButton>
          <UButton type="button" color="neutral" variant="outline" @click="myClear">Clear</UButton>
        </UForm>
        <UFormField label="Per page" name="perPage" class="meta">
          <USelect v-model="mySearchState.perPage" :items="PER_PAGE_ITEMS" class="w-full" />
        </UFormField>
      </div>
      <p class="tools">
        <UButton type="button" color="primary" @click="downloadExcel('mine')">Export my issues (Excel)</UButton>
      </p>
      <h2>My tickets &amp; assignees</h2>
      <div class="table-wrap">
        <p class="table-count" role="status">
          Showing <strong>{{ myTableCountLabel }}</strong>
        </p>
        <div class="table-scroll">
          <table class="ticket-table cols-report">
            <thead>
              <tr>
                <th class="col-idx" scope="col">#</th>
                <th class="col-id" scope="col">Ticket</th>
                <th class="col-subj" scope="col">Subject</th>
                <th class="col-assignee" scope="col">Assigned to</th>
                <th class="col-status" scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="myLoading">
                <td colspan="5" class="cell-loading">Loading…</td>
              </tr>
              <template v-else>
                <tr v-for="(t, idx) in myTickets" :key="t.id ?? idx">
                  <td class="col-idx">
                    <span class="row-counter">{{ myCounter(idx) }}</span>
                  </td>
                  <td class="col-id">
                    <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="ticket-link">
                      {{ t.ticket_number }}
                    </RouterLink>
                    <span v-else class="ticket-link">{{ t.ticket_number }}</span>
                  </td>
                  <td class="col-subj">
                    <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="row-subj-line">
                      {{ t.subject }}
                    </RouterLink>
                    <span v-else class="row-subj-line">{{ t.subject }}</span>
                  </td>
                  <td class="col-assignee">
                    <div v-if="t.assignee" class="row-person">
                      <CbpAvatar size="sm" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
                      <span class="row-person-name">{{ t.assignee.name }}</span>
                    </div>
                    <span v-else class="cell-empty">—</span>
                  </td>
                  <td class="col-status">
                    <span
                      v-if="t.status"
                      class="pill"
                      :style="{ background: statusMeta(t.status).bg, color: statusMeta(t.status).color }"
                    >
                      {{ statusMeta(t.status).label }}
                    </span>
                    <span v-else class="cell-empty">—</span>
                  </td>
                </tr>
                <tr v-if="myTickets.length === 0">
                  <td colspan="5" class="cell-empty-msg">No matching tickets.</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="pager">
        <button type="button" :disabled="!myHasPrev || myLoading" @click="myPage -= 1; loadMine()">Previous</button>
        <span>Page {{ myPage }} of {{ myLastPage }}</span>
        <button type="button" :disabled="!myHasNext || myLoading" @click="myPage += 1; loadMine()">Next</button>
      </div>
    </template>

    <template v-else-if="tab === 'admin' && adminCounts">
      <div class="tiles">
        <article class="tile tile-total">
          <header>
            <span class="tile-icon" aria-hidden="true">🎫</span>
            <span class="l">Total</span>
          </header>
          <span class="n">{{ adminCounts.total ?? 0 }}</span>
          <small class="tile-sub">All helpdesk tickets</small>
        </article>
        <article class="tile tile-open">
          <header>
            <span class="tile-icon" aria-hidden="true">🗂</span>
            <span class="l">Open</span>
          </header>
          <span class="n">{{ adminCounts.open ?? 0 }}</span>
          <small class="tile-sub">Open + pending + in progress</small>
        </article>
        <article class="tile tile-awaiting">
          <header>
            <span class="tile-icon" aria-hidden="true">⌛</span>
            <span class="l">Awaiting requester</span>
          </header>
          <span class="n">{{ adminCounts.awaiting_requester_confirmation ?? 0 }}</span>
          <small class="tile-sub">Resolution shared, waiting confirmation</small>
        </article>
        <article class="tile tile-resolved">
          <header>
            <span class="tile-icon" aria-hidden="true">✅</span>
            <span class="l">Resolved</span>
          </header>
          <span class="n">{{ adminCounts.resolved ?? 0 }}</span>
          <small class="tile-sub">Marked resolved</small>
        </article>
        <article class="tile tile-closed">
          <header>
            <span class="tile-icon" aria-hidden="true">🔒</span>
            <span class="l">Closed</span>
          </header>
          <span class="n">{{ adminCounts.closed ?? 0 }}</span>
          <small class="tile-sub">Finalized after confirmation</small>
        </article>
      </div>
      <p class="tools">
        <UButton type="button" color="primary" @click="downloadExcel('all')">Export all tickets (Excel)</UButton>
        <UButton type="button" color="neutral" variant="outline" @click="downloadExcel('assigned')">Export my assigned (Excel)</UButton>
      </p>
      <div class="toolbar">
        <UForm :state="adminSearchState" class="hd-search-form searchbar" @submit="adminSearch">
          <UFormField name="q" class="hd-form-toolbar-grow">
            <UInput
              v-model="adminSearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Search recent activity by #, subject, requester, assignee…"
              aria-label="Search admin recent activity"
              class="w-full"
            />
          </UFormField>
          <UButton type="submit" color="primary">Search</UButton>
          <UButton type="button" color="neutral" variant="outline" @click="adminClear">Clear</UButton>
        </UForm>
        <UFormField label="Agents" name="agentIds" class="meta meta--agents">
          <USelect
            v-model="adminSearchState.agentIds"
            multiple
            :items="adminAgentItems"
            placeholder="All agents"
            class="w-full admin-agent-select"
          />
        </UFormField>
        <UFormField label="From" name="dateFrom" class="meta meta--date">
          <UInput v-model="adminSearchState.dateFrom" type="date" class="w-full" />
        </UFormField>
        <UFormField label="To" name="dateTo" class="meta meta--date">
          <UInput v-model="adminSearchState.dateTo" type="date" class="w-full" />
        </UFormField>
        <UFormField label="Per page" name="perPage" class="meta">
          <USelect v-model="adminSearchState.perPage" :items="PER_PAGE_ITEMS" class="w-full" />
        </UFormField>
      </div>
      <h2>Recent activity</h2>
      <div class="table-wrap">
        <p class="table-count" role="status">
          Showing <strong>{{ adminTableCountLabel }}</strong>
        </p>
        <div class="table-scroll">
          <table class="ticket-table cols-report">
            <thead>
              <tr>
                <th class="col-idx" scope="col">#</th>
                <th class="col-id" scope="col">Ticket</th>
                <th class="col-subj" scope="col">Subject</th>
                <th class="col-assignee" scope="col">Assigned to</th>
                <th class="col-status" scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="adminLoading">
                <td colspan="5" class="cell-loading">Loading…</td>
              </tr>
              <template v-else>
                <tr v-for="(t, idx) in adminRecent" :key="t.id ?? idx">
                  <td class="col-idx">
                    <span class="row-counter">{{ adminCounter(idx) }}</span>
                  </td>
                  <td class="col-id">
                    <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="ticket-link">
                      {{ t.ticket_number }}
                    </RouterLink>
                    <span v-else class="ticket-link">{{ t.ticket_number }}</span>
                  </td>
                  <td class="col-subj">
                    <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="row-subj-line">
                      {{ t.subject }}
                    </RouterLink>
                    <span v-else class="row-subj-line">{{ t.subject }}</span>
                  </td>
                  <td class="col-assignee">
                    <div v-if="t.assignee" class="row-person">
                      <CbpAvatar size="sm" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
                      <span class="row-person-name">{{ t.assignee.name }}</span>
                    </div>
                    <span v-else class="cell-empty">—</span>
                  </td>
                  <td class="col-status">
                    <span
                      v-if="t.status"
                      class="pill"
                      :style="{ background: statusMeta(t.status).bg, color: statusMeta(t.status).color }"
                    >
                      {{ statusMeta(t.status).label }}
                    </span>
                    <span v-else class="cell-empty">—</span>
                  </td>
                </tr>
                <tr v-if="adminRecent.length === 0">
                  <td colspan="5" class="cell-empty-msg">No matching tickets.</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="pager">
        <button type="button" :disabled="!adminHasPrev || adminLoading" @click="adminPage -= 1; loadAdmin()">Previous</button>
        <span>Page {{ adminPage }} of {{ adminLastPage }}</span>
        <button type="button" :disabled="!adminHasNext || adminLoading" @click="adminPage += 1; loadAdmin()">Next</button>
      </div>
    </template>
    <p v-else class="muted">Loading…</p>
    </div>
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
h2 {
  font-size: 1.05rem;
  margin: 1rem 0 0.5rem;
  color: #2c3e50;
}
.tabs button {
  padding: 0.4rem 0.85rem;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #fff;
  cursor: pointer;
  font-weight: 600;
}
.tabs button.on {
  background: #e8f5ee;
  border-color: #119a48;
  color: #065f2c;
}
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.1rem;
}
.tile {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 0.85rem 0.9rem 0.8rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.tile::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--tile-accent, #334155);
}
.tile header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}
.tile-icon {
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.82rem;
  background: var(--tile-soft, #e2e8f0);
}
.n {
  font-size: 2rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: #0f172a;
  line-height: 1;
  display: block;
}
.l {
  font-size: 0.74rem;
  color: #64748b;
  text-transform: capitalize;
  font-weight: 700;
}
.tile-sub {
  margin-top: 0.35rem;
  display: block;
  font-size: 0.73rem;
  color: #64748b;
}
.tile-total {
  --tile-accent: #334155;
  --tile-soft: #e2e8f0;
}
.tile-open,
.tile-pending {
  --tile-accent: #3b82f6;
  --tile-soft: #dbeafe;
}
.tile-awaiting {
  --tile-accent: #a855f7;
  --tile-soft: #f3e8ff;
}
.tile-resolved {
  --tile-accent: #16a34a;
  --tile-soft: #dcfce7;
}
.tile-closed {
  --tile-accent: #64748b;
  --tile-soft: #e2e8f0;
}
.tools {
  margin: 1rem 0;
}
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin: 0.75rem 0;
  flex-wrap: wrap;
}
.searchbar {
  display: flex;
  gap: 0.5rem;
  flex: 1;
  min-width: min(36rem, 100%);
}
.searchbar input {
  flex: 1;
  min-width: 14rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.45rem 0.65rem;
}
.meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.meta--agents {
  min-width: 12rem;
  max-width: 18rem;
}
.meta--date {
  min-width: 9rem;
}
.admin-agent-select {
  min-width: 12rem;
}
.meta label {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: #475569;
  font-size: 0.88rem;
}
.meta select {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
}
.btn {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  border: none;
  background: #119a48;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  margin-right: 0.5rem;
}
.btn.secondary {
  background: #334155;
}
.table-wrap {
  margin-bottom: 0.5rem;
}
.muted {
  color: #64748b;
  font-size: 0.85rem;
}
.err {
  color: #b91c1c;
}
.pager {
  margin-top: 0.75rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  align-items: center;
}
.pager button {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 4px;
  padding: 0.35rem 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.pager button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
