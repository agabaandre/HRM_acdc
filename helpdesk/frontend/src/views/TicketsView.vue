<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'

interface AssigneeBrief {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
}

interface TicketRow {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  requester_name?: string | null
  assignee?: AssigneeBrief | null
}

const rows = ref<TicketRow[]>([])
const err = ref<string | null>(null)
const loading = ref(false)
const q = ref('')
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
const lastPage = ref(1)

const hasPrev = computed(() => page.value > 1)
const hasNext = computed(() => page.value < lastPage.value)
const rangeLabel = computed(() => {
  if (total.value === 0) return '0 results'
  const start = (page.value - 1) * perPage.value + 1
  const end = Math.min(total.value, page.value * perPage.value)
  return `${start}-${end} of ${total.value}`
})

async function load() {
  err.value = null
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/tickets', {
      params: {
        q: q.value.trim() || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })
    rows.value = data.data as TicketRow[]
    total.value = Number(data.meta?.total ?? rows.value.length ?? 0)
    lastPage.value = Math.max(1, Number(data.meta?.last_page ?? 1))
    page.value = Math.max(1, Number(data.meta?.current_page ?? page.value))
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Failed to load tickets'
  } finally {
    loading.value = false
  }
}

function doSearch() {
  page.value = 1
  load()
}

function resetSearch() {
  q.value = ''
  page.value = 1
  load()
}

watch(perPage, () => {
  page.value = 1
  load()
})

onMounted(load)
</script>

<template>
  <div>
    <CbpPageHeading title="Tickets" back-to="/" back-label="← Overview" />
    <div class="tools">
      <form class="searchbar" @submit.prevent="doSearch">
        <input
          v-model="q"
          type="search"
          placeholder="Search by ticket #, subject, requester, assignee, category, status…"
          aria-label="Search tickets"
        />
        <button type="submit">Search</button>
        <button type="button" class="ghost" @click="resetSearch">Clear</button>
      </form>
      <div class="meta">
        <label>
          Per page
          <select v-model.number="perPage">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </label>
        <span class="muted">{{ rangeLabel }}</span>
      </div>
    </div>
    <p v-if="err" class="err">{{ err }}</p>
    <div v-else class="cbp-card tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th>
            <th>Subject</th>
            <th>Requester</th>
            <th>Assigned to</th>
            <th>Status</th>
            <th>Priority</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="muted">Loading…</td>
          </tr>
          <tr v-for="t in rows" :key="t.id">
            <td>
              <RouterLink :to="'/tickets/' + t.id">{{ t.ticket_number }}</RouterLink>
            </td>
            <td>
              <RouterLink :to="'/tickets/' + t.id" class="subj-link">{{ t.subject }}</RouterLink>
            </td>
            <td>
              <div class="cell-person">
                <CbpAvatar
                  size="sm"
                  :name="t.requester_name || 'Requester'"
                  :image-url="null"
                />
                <span class="person-name">{{ t.requester_name || '—' }}</span>
              </div>
            </td>
            <td>
              <div v-if="t.assignee" class="cell-person">
                <CbpAvatar size="sm" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
                <span class="person-name">{{ t.assignee.name }}</span>
              </div>
              <span v-else class="muted">—</span>
            </td>
            <td>{{ t.status }}</td>
            <td>{{ t.priority }}</td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="6" class="muted">
              No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
            </td>
          </tr>
        </tbody>
      </table>
      <div class="pager">
        <button type="button" :disabled="!hasPrev || loading" @click="page -= 1; load()">Previous</button>
        <span>Page {{ page }} of {{ lastPage }}</span>
        <button type="button" :disabled="!hasNext || loading" @click="page += 1; load()">Next</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tools {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
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
.searchbar button {
  border: 1px solid #119a48;
  background: #119a48;
  color: #fff;
  border-radius: 8px;
  padding: 0.45rem 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.searchbar button.ghost {
  border-color: #cbd5e1;
  background: #fff;
  color: #334155;
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
.tbl-wrap {
  overflow-x: auto;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}
th,
td {
  border: 1px solid #e2e8f0;
  padding: 0.5rem 0.65rem;
  text-align: left;
  vertical-align: middle;
}
th {
  background: #f1f5f9;
}
.cell-person {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.person-name {
  font-size: 0.88rem;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 12rem;
}
.subj-link {
  color: #0d7a3a;
  font-weight: 600;
  text-decoration: none;
}
.subj-link:hover {
  text-decoration: underline;
}
.muted {
  color: #64748b;
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
