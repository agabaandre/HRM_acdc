<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'

interface Counts {
  total_received: number
  pending: number
  awaiting_requester_confirmation: number
  resolved: number
  reassigned: number
}

interface RecentRow {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  requester_name: string | null
  assignee?: { id: number; name: string; avatar_url?: string | null } | null
}

const err = ref<string | null>(null)
const counts = ref<Counts | null>(null)
const recent = ref<RecentRow[]>([])

async function load() {
  err.value = null
  try {
    const { data } = await api.get('/api/v1/reports/agent-dashboard')
    counts.value = data.data.counts as Counts
    recent.value = data.data.recent as RecentRow[]
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Unable to load dashboard'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <CbpPageHeading title="Agent dashboard" back-to="/" back-label="← Overview">
      <template #lede>Issues assigned to you: workload, resolution hand-offs, and recent queue.</template>
    </CbpPageHeading>
    <p v-if="err" class="err">{{ err }}</p>
    <template v-else-if="counts">
      <div class="cbp-card">
      <div class="tiles">
        <div class="tile">
          <span class="n">{{ counts.total_received }}</span>
          <span class="l">Assigned to you (total)</span>
        </div>
        <div class="tile">
          <span class="n">{{ counts.pending }}</span>
          <span class="l">Open / in progress</span>
        </div>
        <div class="tile">
          <span class="n">{{ counts.awaiting_requester_confirmation }}</span>
          <span class="l">Awaiting requester confirm</span>
        </div>
        <div class="tile">
          <span class="n">{{ counts.resolved }}</span>
          <span class="l">Resolved</span>
        </div>
      </div>

      <h2>Recent issues</h2>
      <table class="tbl">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Subject</th>
            <th>Requester</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in recent" :key="r.id">
            <td>{{ r.ticket_number }}</td>
            <td>{{ r.subject }}</td>
            <td>
              <div class="cell-person">
                <CbpAvatar size="sm" :name="r.requester_name || 'Requester'" :image-url="null" />
                <span>{{ r.requester_name ?? '—' }}</span>
              </div>
            </td>
            <td><span class="pill">{{ r.status }}</span></td>
            <td>
              <RouterLink :to="`/tickets/${r.id}`" class="link">Open</RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="recent.length === 0" class="muted">No tickets assigned yet.</p>
      </div>
    </template>
    <p v-else-if="!err" class="muted">Loading…</p>
  </div>
</template>

<style scoped>
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.75rem;
  margin-bottom: 2rem;
}
.tile {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.n {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
}
.l {
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.3;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 0.5rem 0.35rem;
  border-bottom: 1px solid #e2e8f0;
}
.pill {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  background: #e2e8f0;
}
.link {
  color: #0d7a3a;
  font-weight: 600;
}
.muted {
  color: #64748b;
}
.cell-person {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.err {
  color: #b91c1c;
}
</style>
