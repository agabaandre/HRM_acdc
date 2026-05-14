<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
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

const myStats = ref<{ total_received: number; pending: number; resolved: number } | null>(null)
const myTickets = ref<ReportTicket[]>([])

const adminCounts = ref<Record<string, number> | null>(null)
const adminRecent = ref<ReportTicket[]>([])

const isAdmin = computed(() => auth.me?.profile?.role === 'admin')

async function loadMine() {
  const { data } = await api.get('/api/v1/reports/my-requester')
  myStats.value = data.data.stats
  const tickets = data.data.tickets
  const raw = Array.isArray(tickets) ? tickets : tickets.data ?? []
  myTickets.value = raw as ReportTicket[]
}

async function loadAdmin() {
  const { data } = await api.get('/api/v1/reports/admin-summary')
  adminCounts.value = data.data.counts
  adminRecent.value = data.data.recent as ReportTicket[]
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
  }
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
        <div class="tile"><span class="n">{{ myStats.total_received }}</span><span class="l">Total received</span></div>
        <div class="tile"><span class="n">{{ myStats.pending }}</span><span class="l">Pending resolution</span></div>
        <div class="tile"><span class="n">{{ myStats.resolved }}</span><span class="l">Resolved</span></div>
      </div>
      <p class="tools">
        <button type="button" class="btn" @click="downloadExcel('mine')">Export my issues (Excel)</button>
      </p>
      <h2>My tickets &amp; assignees</h2>
      <ul class="list">
        <li v-for="(t, i) in myTickets" :key="i" class="row">
          <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="link">
            {{ t.ticket_number }}
          </RouterLink>
          <span class="grow">{{ t.subject }}</span>
          <div v-if="t.assignee" class="assignee">
            <CbpAvatar size="xs" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
            <span class="assignee-name">{{ t.assignee.name }}</span>
          </div>
          <span class="muted">{{ t.status }}</span>
        </li>
      </ul>
    </template>

    <template v-else-if="tab === 'admin' && adminCounts">
      <div class="tiles">
        <div class="tile" v-for="(v, k) in adminCounts" :key="k">
          <span class="n">{{ v }}</span>
          <span class="l">{{ k.replace(/_/g, ' ') }}</span>
        </div>
      </div>
      <p class="tools">
        <button type="button" class="btn" @click="downloadExcel('all')">Export all tickets (Excel)</button>
        <button type="button" class="btn secondary" @click="downloadExcel('assigned')">Export my assigned (Excel)</button>
      </p>
      <h2>Recent activity</h2>
      <ul class="list">
        <li v-for="(t, i) in adminRecent" :key="i" class="row">
          <RouterLink v-if="t.id" :to="`/tickets/${t.id}`" class="link">
            {{ t.ticket_number }}
          </RouterLink>
          <span class="grow">{{ t.subject }}</span>
          <div v-if="t.assignee" class="assignee">
            <CbpAvatar size="xs" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
            <span class="assignee-name">{{ t.assignee.name }}</span>
          </div>
        </li>
      </ul>
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
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 0.65rem;
  margin-bottom: 1rem;
}
.tile {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.65rem;
}
.n {
  font-size: 1.35rem;
  font-weight: 800;
}
.l {
  font-size: 0.72rem;
  color: #64748b;
  text-transform: capitalize;
}
.tools {
  margin: 1rem 0;
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
.list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding: 0.45rem 0;
  border-bottom: 1px solid #e2e8f0;
}
.grow {
  flex: 1;
  min-width: 0;
  font-size: 0.9rem;
  color: #334155;
}
.assignee {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.assignee-name {
  font-size: 0.82rem;
  color: #475569;
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.link {
  color: #0d7a3a;
  font-weight: 700;
  text-decoration: none;
  min-width: 7rem;
}
.muted {
  color: #64748b;
  font-size: 0.85rem;
}
.err {
  color: #b91c1c;
}
</style>
