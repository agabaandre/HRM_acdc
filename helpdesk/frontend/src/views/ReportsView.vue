<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
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
import { normalizePageSize, type PageSize, type SelectNumberItem } from '../lib/helpdeskForm'

import { useAuthStore } from '../stores/auth'

type SortItem = { key: string; order: 'asc' | 'desc' }

const auth = useAuthStore()
const tab = ref<'mine' | 'admin'>('mine')
const itemsPerPageOptions = [10, 20, 50, 100] as const

const reportHeaders: DataTableHeader[] = [
  { title: '#', key: 'row_num', sortable: false, width: '52px', align: 'center' },
  { title: 'Ticket', key: 'ticket_number', sortable: false, minWidth: '120px' },
  { title: 'Subject', key: 'subject', sortable: false, minWidth: '200px' },
  { title: 'Assigned to', key: 'assignee_name', sortable: false, minWidth: '150px' },
  { title: 'Status', key: 'status', sortable: false, width: '130px' },
]

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
const myTotal = ref(0)
const myItemsPerPage = ref<PageSize>(20)
const mySortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const mySearchState = reactive<{ q: string }>({ q: '' })
const myLoading = ref(false)

const adminCounts = ref<Record<string, number> | null>(null)
const adminRecent = ref<ReportTicket[]>([])
const adminPage = ref(1)
const adminTotal = ref(0)
const adminItemsPerPage = ref<PageSize>(20)
const adminSortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const adminSearchState = reactive<{
  q: string
  agentIds: number[]
  dateFrom: string
  dateTo: string
}>({ q: '', agentIds: [], dateFrom: '', dateTo: '' })
const adminLoading = ref(false)
const adminAgents = ref<{ id: number; name: string; email: string }[]>([])

const adminFilterCount = computed(() => {
  let n = 0
  if (adminSearchState.q.trim()) n += 1
  if (adminSearchState.agentIds.length) n += 1
  if (adminSearchState.dateFrom) n += 1
  if (adminSearchState.dateTo) n += 1
  return n
})

const adminAgentItems = computed((): SelectNumberItem[] =>
  adminAgents.value.map((a) => ({ label: a.name, value: a.id })),
)

const myFilterCount = computed(() => (mySearchState.q.trim() ? 1 : 0))

const isAdmin = computed(
  () => !!auth.me?.profile?.is_helpdesk_admin || auth.me?.profile?.role === 'admin',
)
const myTableCountLabel = computed(() =>
  formatTableCountLabel(myTickets.value.length, myTotal.value, myPage.value, myItemsPerPage.value),
)
const adminTableCountLabel = computed(() =>
  formatTableCountLabel(adminRecent.value.length, adminTotal.value, adminPage.value, adminItemsPerPage.value),
)

function myCounter(idx: number): number {
  return rowIndex(myPage.value, myItemsPerPage.value, idx)
}

function adminCounter(idx: number): number {
  return rowIndex(adminPage.value, adminItemsPerPage.value, idx)
}

async function loadMine() {
  myLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/my-requester', {
      params: {
        q: mySearchState.q.trim() || undefined,
        page: myPage.value,
        per_page: myItemsPerPage.value,
      },
    })
    myStats.value = data.data.stats
    const tickets = (data.data.tickets ?? {}) as Partial<PaginatedTickets>
    myTickets.value = (tickets.data ?? []) as ReportTicket[]
    myPage.value = Number(tickets.current_page ?? myPage.value)
    myItemsPerPage.value = normalizePageSize(Number(tickets.per_page ?? myItemsPerPage.value))
    myTotal.value = Number(tickets.total ?? myTickets.value.length)
  } finally {
    myLoading.value = false
  }
}

function adminReportParams() {
  return {
    q: adminSearchState.q.trim() || undefined,
    page: adminPage.value,
    per_page: adminItemsPerPage.value,
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
    adminItemsPerPage.value = normalizePageSize(Number(recent.per_page ?? adminItemsPerPage.value))
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

function onMyUpdateOptions(options: { page: number; itemsPerPage: number; sortBy: SortItem[] }) {
  myPage.value = options.page
  myItemsPerPage.value = options.itemsPerPage as PageSize
  mySortBy.value = options.sortBy
  void loadMine()
}

function onAdminUpdateOptions(options: { page: number; itemsPerPage: number; sortBy: SortItem[] }) {
  adminPage.value = options.page
  adminItemsPerPage.value = options.itemsPerPage as PageSize
  adminSortBy.value = options.sortBy
  void loadAdmin()
}

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
      <div v-if="isAdmin" class="report-tabs" role="tablist" aria-label="Report views">
        <button
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'admin' }"
          :aria-selected="tab === 'admin'"
          @click="switchTab('admin')"
        >
          Admin overview
        </button>
        <button
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'mine' }"
          :aria-selected="tab === 'mine'"
          @click="switchTab('mine')"
        >
          My issues
        </button>
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
      <details class="hd-filter-panel">
        <summary class="hd-filter-panel__toggle">
          <span class="hd-filter-panel__toggle-label">
            <i class="bx bx-filter-alt" aria-hidden="true" />
            Search &amp; filters
          </span>
          <span v-if="myFilterCount" class="hd-filter-panel__badge">{{ myFilterCount }} active</span>
        </summary>
        <UForm :state="mySearchState" class="hd-form hd-filter-panel__body" @submit="mySearch">
          <UFormField label="Search" name="q" class="full">
            <UInput
              v-model="mySearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Ticket #, subject, status, assignee…"
              aria-label="Search my tickets"
              class="w-full"
            />
          </UFormField>
          <div class="hd-form-actions full">
            <UButton type="submit" color="primary">Apply filters</UButton>
            <UButton type="button" color="neutral" variant="outline" @click="myClear">
              Clear
            </UButton>
          </div>
        </UForm>
      </details>
      <div class="report-tools">
        <UButton type="button" color="primary" class="report-tools__btn" @click="downloadExcel('mine')">
          Export my issues (Excel)
        </UButton>
      </div>
      <h2>My tickets &amp; assignees</h2>
      <v-card class="hd-data-table-card" variant="outlined">
        <v-card-text class="hd-data-table-card__head">
          <p class="table-count" role="status">
            Showing <strong>{{ myTableCountLabel }}</strong>
          </p>
        </v-card-text>
        <v-data-table-server
          v-model:page="myPage"
          v-model:items-per-page="myItemsPerPage"
          v-model:sort-by="mySortBy"
          class="hd-data-table"
          :headers="reportHeaders"
          :items="myTickets"
          :items-length="myTotal"
          :items-per-page-options="[...itemsPerPageOptions]"
          :loading="myLoading"
          density="compact"
          hover
          item-value="id"
          @update:options="onMyUpdateOptions"
        >
          <template #item.row_num="{ index }">
            <span class="hd-dt-row-num">{{ myCounter(index) }}</span>
          </template>
          <template #item.ticket_number="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-ticket-link">
              {{ item.ticket_number }}
            </RouterLink>
            <span v-else class="hd-dt-ticket-link">{{ item.ticket_number }}</span>
          </template>
          <template #item.subject="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-subject-link">
              {{ item.subject }}
            </RouterLink>
            <span v-else class="hd-dt-subject-link">{{ item.subject }}</span>
          </template>
          <template #item.assignee_name="{ item }">
            <div v-if="item.assignee" class="hd-dt-person">
              <CbpAvatar size="sm" :name="item.assignee.name" :image-url="item.assignee.avatar_url ?? null" />
              <span class="hd-dt-person-name">{{ item.assignee.name }}</span>
            </div>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.status="{ item }">
            <span
              v-if="item.status"
              class="hd-dt-pill"
              :style="{ background: statusMeta(item.status).bg, color: statusMeta(item.status).color }"
            >
              {{ statusMeta(item.status).label }}
            </span>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #no-data>
            <div class="hd-dt-empty-msg">No matching tickets.</div>
          </template>
          <template #loading>
            <div class="hd-dt-loading">Loading…</div>
          </template>
        </v-data-table-server>
      </v-card>
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
      <div class="report-tools">
        <UButton type="button" color="primary" class="report-tools__btn" @click="downloadExcel('all')">
          Export all tickets (Excel)
        </UButton>
        <UButton
          type="button"
          color="neutral"
          variant="outline"
          class="report-tools__btn"
          @click="downloadExcel('assigned')"
        >
          Export my assigned (Excel)
        </UButton>
      </div>
      <details class="hd-filter-panel">
        <summary class="hd-filter-panel__toggle">
          <span class="hd-filter-panel__toggle-label">
            <i class="bx bx-filter-alt" aria-hidden="true" />
            Search &amp; filters
          </span>
          <span v-if="adminFilterCount" class="hd-filter-panel__badge">{{ adminFilterCount }} active</span>
        </summary>
        <UForm :state="adminSearchState" class="hd-form hd-filter-panel__body" @submit="adminSearch">
          <UFormField label="Search" name="q" class="full">
            <UInput
              v-model="adminSearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Ticket #, subject, requester, assignee…"
              aria-label="Search admin recent activity"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Agents" name="agentIds" class="full">
            <USelectMenu
              v-model="adminSearchState.agentIds"
              :items="adminAgentItems"
              value-key="value"
              multiple
              searchable
              placeholder="All agents"
              class="w-full"
            />
          </UFormField>
          <div class="hd-form hd-form--grid hd-form--grid-2 full">
            <UFormField label="From date" name="dateFrom">
              <UInput v-model="adminSearchState.dateFrom" type="date" class="w-full" />
            </UFormField>
            <UFormField label="To date" name="dateTo">
              <UInput v-model="adminSearchState.dateTo" type="date" class="w-full" />
            </UFormField>
          </div>
          <div class="hd-form-actions full">
            <UButton type="submit" color="primary">Apply filters</UButton>
            <UButton type="button" color="neutral" variant="outline" @click="adminClear">
              Clear all
            </UButton>
          </div>
        </UForm>
      </details>
      <h2>Recent activity</h2>
      <v-card class="hd-data-table-card" variant="outlined">
        <v-card-text class="hd-data-table-card__head">
          <p class="table-count" role="status">
            Showing <strong>{{ adminTableCountLabel }}</strong>
          </p>
        </v-card-text>
        <v-data-table-server
          v-model:page="adminPage"
          v-model:items-per-page="adminItemsPerPage"
          v-model:sort-by="adminSortBy"
          class="hd-data-table"
          :headers="reportHeaders"
          :items="adminRecent"
          :items-length="adminTotal"
          :items-per-page-options="[...itemsPerPageOptions]"
          :loading="adminLoading"
          density="compact"
          hover
          item-value="id"
          @update:options="onAdminUpdateOptions"
        >
          <template #item.row_num="{ index }">
            <span class="hd-dt-row-num">{{ adminCounter(index) }}</span>
          </template>
          <template #item.ticket_number="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-ticket-link">
              {{ item.ticket_number }}
            </RouterLink>
            <span v-else class="hd-dt-ticket-link">{{ item.ticket_number }}</span>
          </template>
          <template #item.subject="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-subject-link">
              {{ item.subject }}
            </RouterLink>
            <span v-else class="hd-dt-subject-link">{{ item.subject }}</span>
          </template>
          <template #item.assignee_name="{ item }">
            <div v-if="item.assignee" class="hd-dt-person">
              <CbpAvatar size="sm" :name="item.assignee.name" :image-url="item.assignee.avatar_url ?? null" />
              <span class="hd-dt-person-name">{{ item.assignee.name }}</span>
            </div>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.status="{ item }">
            <span
              v-if="item.status"
              class="hd-dt-pill"
              :style="{ background: statusMeta(item.status).bg, color: statusMeta(item.status).color }"
            >
              {{ statusMeta(item.status).label }}
            </span>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #no-data>
            <div class="hd-dt-empty-msg">No matching tickets.</div>
          </template>
          <template #loading>
            <div class="hd-dt-loading">Loading…</div>
          </template>
        </v-data-table-server>
      </v-card>
    </template>
    <p v-else class="muted">Loading…</p>
    </div>
  </div>
</template>

<style scoped>
.report-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.report-tab {
  padding: 0.65rem 0.85rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #fff;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9rem;
  text-align: center;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.report-tab--on {
  background: #e8f5ee;
  border-color: #119a48;
  color: #065f2c;
}

h2 {
  font-size: 1.05rem;
  margin: 1rem 0 0.5rem;
  color: #2c3e50;
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

.report-tools {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0 0 1rem;
}

@media (min-width: 640px) {
  .report-tools {
    flex-direction: row;
    flex-wrap: wrap;
  }

  .report-tools__btn {
    width: auto;
    flex: 0 1 auto;
  }
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
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
  align-items: center;
}

@media (min-width: 640px) {
  .pager {
    justify-content: flex-end;
  }
}

.pager__label {
  font-size: 0.88rem;
  color: #475569;
  text-align: center;
  width: 100%;
}

@media (min-width: 640px) {
  .pager__label {
    width: auto;
  }
}
</style>
