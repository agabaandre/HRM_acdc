<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import {
  formatTableCountLabel,
  priorityMeta,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'

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
const tableCountLabel = computed(() =>
  formatTableCountLabel(rows.value.length, total.value, page.value, perPage.value),
)

function counterFor(idx: number): number {
  return rowIndex(page.value, perPage.value, idx)
}

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
      </div>
    </div>
    <p v-if="err" class="err">{{ err }}</p>
    <div v-else class="cbp-card table-section">
      <p class="table-count" role="status">
        Showing <strong>{{ tableCountLabel }}</strong>
      </p>
      <div class="table-scroll">
        <table class="ticket-table">
          <thead>
            <tr>
              <th class="col-idx" scope="col">#</th>
              <th class="col-id" scope="col">Ticket</th>
              <th class="col-subj" scope="col">Subject</th>
              <th class="col-req" scope="col">Requester</th>
              <th class="col-assignee" scope="col">Assigned to</th>
              <th class="col-status" scope="col">Status</th>
              <th class="col-priority" scope="col">Priority</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="cell-loading">Loading…</td>
            </tr>
            <template v-else>
            <tr v-for="(t, idx) in rows" :key="t.id">
              <td class="col-idx">
                <span class="row-counter">{{ counterFor(idx) }}</span>
              </td>
              <td class="col-id">
                <RouterLink :to="`/tickets/${t.id}`" class="ticket-link">{{ t.ticket_number }}</RouterLink>
              </td>
              <td class="col-subj">
                <RouterLink :to="`/tickets/${t.id}`" class="row-subj-line">{{ t.subject }}</RouterLink>
              </td>
              <td class="col-req">
                <div class="row-person">
                  <CbpAvatar size="sm" :name="t.requester_name || 'Requester'" :image-url="null" />
                  <span class="row-person-name">{{ t.requester_name || '—' }}</span>
                </div>
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
                  class="pill"
                  :style="{ background: statusMeta(t.status).bg, color: statusMeta(t.status).color }"
                >
                  {{ statusMeta(t.status).label }}
                </span>
              </td>
              <td class="col-priority">
                <span
                  class="pill"
                  :style="{ background: priorityMeta(t.priority).bg, color: priorityMeta(t.priority).color }"
                >
                  {{ priorityMeta(t.priority).label }}
                </span>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="7" class="cell-empty-msg">
                No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
              </td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>
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
.table-section {
  padding: 1rem 1.1rem;
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
