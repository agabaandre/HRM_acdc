<script setup lang="ts">
import { onMounted, ref } from 'vue'
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

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v1/tickets')
    rows.value = data.data as TicketRow[]
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Failed to load tickets'
  }
})
</script>

<template>
  <div>
    <CbpPageHeading title="Tickets" back-to="/" back-label="← Overview" />
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
          <tr v-if="rows.length === 0">
            <td colspan="6" class="muted">
              No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
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
</style>
