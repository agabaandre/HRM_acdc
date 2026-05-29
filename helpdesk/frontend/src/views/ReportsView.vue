<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import {
  formatTableCountLabel,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const err = ref<string | null>(null)
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
const myPerPage = ref(20)
const myLastPage = ref(1)
const myTotal = ref(0)
const myQuery = ref('')
const myLoading = ref(false)

const adminCounts = ref<Record<string, number> | null>(null)
const adminRecent = ref<ReportTicket[]>([])
const adminPage = ref(1)
const adminPerPage = ref(20)
const adminLastPage = ref(1)
const adminTotal = ref(0)
const adminQuery = ref('')
const adminLoading = ref(false)

const isAdmin = computed(() => auth.me?.profile?.role === 'admin')
const myHasPrev = computed(() => myPage.value > 1)
const myHasNext = computed(() => myPage.value < myLastPage.value)
const adminHasPrev = computed(() => adminPage.value > 1)
const adminHasNext = computed(() => adminPage.value < adminLastPage.value)
const myTableCountLabel = computed(() =>
  formatTableCountLabel(myTickets.value.length, myTotal.value, myPage.value, myPerPage.value),
)
const adminTableCountLabel = computed(() =>
  formatTableCountLabel(adminRecent.value.length, adminTotal.value, adminPage.value, adminPerPage.value),
)

function myCounter(idx: number): number {
  return rowIndex(myPage.value, myPerPage.value, idx)
}

function adminCounter(idx: number): number {
  return rowIndex(adminPage.value, adminPerPage.value, idx)
}

async function loadMine() {
  myLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/my-requester', {
      params: {
        q: myQuery.value.trim() || undefined,
        page: myPage.value,
        per_page: myPerPage.value,
      },
    })
    myStats.value = data.data.stats
    const tickets = (data.data.tickets ?? {}) as Partial<PaginatedTickets>
    myTickets.value = (tickets.data ?? []) as ReportTicket[]
    myPage.value = Number(tickets.current_page ?? myPage.value)
    myLastPage.value = Math.max(1, Number(tickets.last_page ?? 1))
    myPerPage.value = Number(tickets.per_page ?? myPerPage.value)
    myTotal.value = Number(tickets.total ?? myTickets.value.length)
  } finally {
    myLoading.value = false
  }
}

async function loadAdmin() {
  adminLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/admin-summary', {
      params: {
        q: adminQuery.value.trim() || undefined,
        page: adminPage.value,
        per_page: adminPerPage.value,
      },
    })
    adminCounts.value = data.data.counts
    const recent = (data.data.recent ?? {}) as Partial<PaginatedTickets>
    adminRecent.value = (recent.data ?? []) as ReportTicket[]
    adminPage.value = Number(recent.current_page ?? adminPage.value)
    adminLastPage.value = Math.max(1, Number(recent.last_page ?? 1))
    adminPerPage.value = Number(recent.per_page ?? adminPerPage.value)
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
  err.value = null
  try {
    if (tab.value === 'admin' && isAdmin.value) {
      await loadAdmin()
    } else {
      await loadMine()
    }
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Failed to load report'
    myLoading.value = false
    adminLoading.value = false
  }
}

function mySearch() {
  myPage.value = 1
  loadMine()
}
function myClear() {
  myQuery.value = ''
  myPage.value = 1
  loadMine()
}
function adminSearch() {
  adminPage.value = 1
  loadAdmin()
}
function adminClear() {
  adminQuery.value = ''
  adminPage.value = 1
  loadAdmin()
}

async function downloadExcel(scope: 'assigned' | 'all' | 'mine') {
  try {
    const res = await api.get('/api/v1/reports/export', {
      params: { scope },
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
    err.value = 'Export failed (check you are signed in as staff).'
  }
}

onMounted(async () => {
  if (isAdmin.value) {
    tab.value = 'admin'
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

      <p v-if="err" class="err">{{ err }}</p>

      <template v-else-if="tab === 'mine' && myStats">
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
        <form class="searchbar" @submit.prevent="mySearch">
          <input
            v-model="myQuery"
            type="search"
            placeholder="Search my tickets by #, subject, status, assignee…"
            aria-label="Search my tickets"
          />
          <button type="submit" class="btn">Search</button>
          <button type="button" class="btn secondary" @click="myClear">Clear</button>
        </form>
        <div class="meta">
          <label>
            Per page
            <select v-model.number="myPerPage" @change="myPage = 1; loadMine()">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </label>
        </div>
      </div>
      <p class="tools">
        <button type="button" class="btn" @click="downloadExcel('mine')">Export my issues (Excel)</button>
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
        <button type="button" class="btn" @click="downloadExcel('all')">Export all tickets (Excel)</button>
        <button type="button" class="btn secondary" @click="downloadExcel('assigned')">Export my assigned (Excel)</button>
      </p>
      <div class="toolbar">
        <form class="searchbar" @submit.prevent="adminSearch">
          <input
            v-model="adminQuery"
            type="search"
            placeholder="Search recent activity by #, subject, requester, assignee…"
            aria-label="Search admin recent activity"
          />
          <button type="submit" class="btn">Search</button>
          <button type="button" class="btn secondary" @click="adminClear">Clear</button>
        </form>
        <div class="meta">
          <label>
            Per page
            <select v-model.number="adminPerPage" @change="adminPage = 1; loadAdmin()">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </label>
        </div>
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
  border-radius: 8px;
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
  border-radius: 12px;
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
  border-radius: 8px;
  padding: 0.45rem 0.65rem;
}
.meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
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
  border-radius: 8px;
  padding: 0.25rem 0.5rem;
}
.btn {
  padding: 0.5rem 1rem;
  border-radius: 8px;
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
  border-radius: 8px;
  padding: 0.35rem 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.pager button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
