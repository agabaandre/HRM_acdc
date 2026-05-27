<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface Cat {
  id: number
  name: string
}

interface AgentRow {
  id: number
  name: string
  email: string
  staff_id: number | null
  can_manage_kb: boolean
  can_reassign_tickets: boolean
  categories: Cat[]
}

interface CandidateRow {
  staff_id: number
  name: string
  work_email: string | null
  duty_station_name: string | null
  division_id: number
  division_name: string
  has_user: boolean
  current_role: string | null
  is_designated_agent: boolean
}

const cats = ref<Cat[]>([])
const agents = ref<AgentRow[]>([])
const selection = ref<Record<number, number[]>>({})
const kbToggle = ref<Record<number, boolean>>({})
const reassignToggle = ref<Record<number, boolean>>({})
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const catsErr = ref<string | null>(null)

const pickerOpen = ref(false)
const candidates = ref<CandidateRow[]>([])
const candidatesLoading = ref(false)
const candidatesLoaded = ref(false)
const candidatesErr = ref<string | null>(null)
const candidatesMessage = ref<string | null>(null)
const candidateSearch = ref('')
const onlyUnassigned = ref(true)
const busyStaffId = ref<number | null>(null)

async function loadCats() {
  catsErr.value = null
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
}

async function loadAgents() {
  const { data } = await api.get<{ data: AgentRow[] }>('/api/v1/admin/agents')
  const list = Array.isArray(data.data) ? data.data : []
  agents.value = list
  const map: Record<number, number[]> = {}
  const kb: Record<number, boolean> = {}
  const reassign: Record<number, boolean> = {}
  for (const a of list) {
    map[a.id] = (a.categories ?? []).map((c) => c.id)
    kb[a.id] = !!a.can_manage_kb
    reassign[a.id] = !!a.can_reassign_tickets
  }
  selection.value = map
  kbToggle.value = kb
  reassignToggle.value = reassign
}

async function loadAll() {
  err.value = null
  ok.value = null
  catsErr.value = null
  try {
    await loadAgents()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load agents.')
    agents.value = []
    selection.value = {}
  }
  try {
    await loadCats()
  } catch (e: unknown) {
    catsErr.value = apiErrorMessage(e, 'Failed to load categories.')
    cats.value = []
  }
}

async function saveAgent(userId: number) {
  ok.value = null
  err.value = null
  try {
    await api.put(`/api/v1/admin/agents/${userId}`, {
      category_ids: (selection.value[userId] ?? []).map((id) => Number(id)),
      can_manage_kb: !!kbToggle.value[userId],
      can_reassign_tickets: !!reassignToggle.value[userId],
    })
    ok.value = `Saved settings for agent #${userId}`
    await loadAgents()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed')
  }
}

async function openPicker() {
  pickerOpen.value = true
  if (!candidatesLoaded.value) {
    await loadCandidates()
  }
}

async function loadCandidates() {
  candidatesLoading.value = true
  candidatesErr.value = null
  candidatesMessage.value = null
  try {
    const { data } = await api.get<{
      data: { candidates: CandidateRow[]; division_ids: number[] }
      meta?: { message?: string }
    }>('/api/v1/admin/agents/division-candidates')
    candidates.value = Array.isArray(data.data?.candidates) ? data.data.candidates : []
    candidatesMessage.value = data.meta?.message ?? null
    candidatesLoaded.value = true
  } catch (e: unknown) {
    candidatesErr.value = apiErrorMessage(e, 'Failed to load staff from configured divisions.')
    candidates.value = []
  } finally {
    candidatesLoading.value = false
  }
}

const filteredCandidates = computed<CandidateRow[]>(() => {
  const q = candidateSearch.value.trim().toLowerCase()
  return candidates.value.filter((c) => {
    if (onlyUnassigned.value && c.current_role === 'agent') {
      return false
    }
    if (q === '') {
      return true
    }
    const hay = `${c.name} ${c.work_email ?? ''} ${c.division_name} ${c.staff_id}`.toLowerCase()
    return hay.includes(q)
  })
})

async function addAgent(c: CandidateRow) {
  if (!c.work_email) {
    err.value = `${c.name} has no work email in the directory — cannot add as agent.`
    return
  }
  busyStaffId.value = c.staff_id
  err.value = null
  ok.value = null
  try {
    await api.post('/api/v1/admin/agents/designate', {
      staff_id: c.staff_id,
      work_email: c.work_email,
      name: c.name,
      division_id: c.division_id || null,
      duty_station: c.duty_station_name || null,
    })
    c.current_role = 'agent'
    c.is_designated_agent = true
    c.has_user = true
    ok.value = `${c.name} added as agent — pick their categories below.`
    await loadAgents()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to add agent.')
  } finally {
    busyStaffId.value = null
  }
}

async function removeAgent(a: AgentRow) {
  if (!a.staff_id) {
    err.value = 'This user has no staff_id and cannot be unmarked from here.'
    return
  }
  if (!window.confirm(`Remove ${a.name} from agents? Their assigned tickets are kept; they go back to "user" role.`)) {
    return
  }
  err.value = null
  ok.value = null
  try {
    await api.delete(`/api/v1/admin/agents/designate/${a.staff_id}`)
    ok.value = `${a.name} removed from agents.`
    await loadAgents()
    // Reflect in the picker if it's open
    const match = candidates.value.find((c) => c.staff_id === a.staff_id)
    if (match) {
      match.current_role = 'user'
      match.is_designated_agent = false
    }
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to remove agent.')
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <section class="panel" aria-labelledby="agents-heading">
    <header class="panel-head">
      <div>
        <h2 id="agents-heading">Agents &amp; category routing</h2>
        <p class="lede">
          Pick which issue categories each agent may be assigned to. An agent with <strong>no</strong> categories
          selected receives <strong>all</strong> categories for automatic assignment.
        </p>
      </div>
      <button type="button" class="primary" @click="openPicker">
        + Add agent from directory
      </button>
    </header>

    <p v-if="err" class="msg msg-err">{{ err }}</p>
    <p v-if="catsErr" class="msg msg-warn">{{ catsErr }}</p>
    <p v-if="ok" class="msg msg-ok" aria-live="polite">{{ ok }}</p>

    <!-- Inline directory picker -->
    <section v-if="pickerOpen" class="picker" aria-labelledby="picker-heading">
      <header class="picker-head">
        <div>
          <h3 id="picker-heading">Add agent from directory</h3>
          <p class="picker-hint">
            Staff listed below belong to the configured
            <RouterLink to="/settings/general">default agent divisions</RouterLink>.
            Adding a person locks the <strong>agent</strong> role for them across future SSO logins.
          </p>
        </div>
        <button type="button" class="ghost" @click="pickerOpen = false">Close</button>
      </header>

      <p v-if="candidatesLoading" class="muted">Loading directory…</p>
      <p v-else-if="candidatesErr" class="msg msg-err">{{ candidatesErr }}</p>
      <p v-else-if="candidatesMessage" class="msg msg-warn">
        {{ candidatesMessage }}
        <RouterLink to="/settings/general" class="msg-link">Open General settings →</RouterLink>
      </p>
      <template v-else-if="candidates.length">
        <div class="picker-toolbar">
          <label class="search-wrap">
            <span class="sr-only">Search staff</span>
            <input
              v-model="candidateSearch"
              type="search"
              class="search-input"
              placeholder="Search by name, email, division, or staff ID…"
              autocomplete="off"
            />
          </label>
          <label class="filter-toggle">
            <input v-model="onlyUnassigned" type="checkbox" />
            Hide existing agents
          </label>
          <button type="button" class="ghost ghost-sm" :disabled="candidatesLoading" @click="loadCandidates">
            Reload
          </button>
        </div>

        <div class="picker-table-wrap">
          <table class="picker-table">
            <thead>
              <tr>
                <th>Staff</th>
                <th>Division</th>
                <th>Current role</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in filteredCandidates" :key="c.staff_id">
                <td>
                  <div class="cand-name">{{ c.name }}</div>
                  <div class="cand-sub">
                    <span v-if="c.work_email">{{ c.work_email }}</span>
                    <span v-else class="missing">No work email</span>
                    <span class="dot-sep">·</span>
                    <span>SID {{ c.staff_id }}</span>
                  </div>
                </td>
                <td><span class="badge badge-div">{{ c.division_name }}</span></td>
                <td>
                  <span
                    class="badge"
                    :class="{
                      'badge-role-agent': c.current_role === 'agent',
                      'badge-role-admin': c.current_role === 'admin',
                      'badge-role-other': c.has_user && !['agent', 'admin'].includes(c.current_role || ''),
                      'badge-role-none': !c.has_user,
                    }"
                  >
                    {{ c.current_role === 'agent' ? 'Agent' :
                       c.current_role === 'admin' ? 'Admin' :
                       c.has_user ? (c.current_role || 'User') : 'Not signed in' }}
                  </span>
                </td>
                <td class="cand-actions">
                  <button
                    v-if="c.current_role !== 'agent'"
                    type="button"
                    class="btn-add"
                    :disabled="busyStaffId === c.staff_id || !c.work_email"
                    @click="addAgent(c)"
                  >
                    {{ busyStaffId === c.staff_id ? 'Adding…' : 'Add as agent' }}
                  </button>
                  <span v-else class="muted">Already an agent</span>
                </td>
              </tr>
              <tr v-if="filteredCandidates.length === 0">
                <td colspan="4" class="muted center">No staff match the current filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <p v-else class="muted">
        No staff returned from the configured divisions.
        <RouterLink to="/settings/general">Configure default agent divisions →</RouterLink>
      </p>
    </section>

    <!-- Existing agents table -->
    <table v-if="agents.length" class="tbl">
      <thead>
        <tr>
          <th>Agent</th>
          <th>Staff ID</th>
          <th>Categories</th>
          <th>Permissions</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="a in agents" :key="a.id">
          <td>
            <div class="agent-name">{{ a.name }}</div>
            <div class="agent-email">{{ a.email }}</div>
          </td>
          <td>{{ a.staff_id ?? '—' }}</td>
          <td>
            <select v-model="selection[a.id]" multiple class="multi">
              <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p v-if="(selection[a.id] ?? []).length === 0" class="route-hint">All categories (no filter)</p>
          </td>
          <td>
            <label class="perm-toggle">
              <input v-model="kbToggle[a.id]" type="checkbox" />
              <span>Can add &amp; edit FAQs</span>
            </label>
            <label class="perm-toggle">
              <input v-model="reassignToggle[a.id]" type="checkbox" />
              <span>Can reassign tickets to other agents</span>
            </label>
          </td>
          <td class="agent-actions">
            <button type="button" class="btn" @click="saveAgent(a.id)">Save</button>
            <button type="button" class="btn-link-danger" @click="removeAgent(a)">Remove</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else-if="!err" class="empty-state">
      <p class="empty-title">No agents yet</p>
      <p class="empty-text">
        Add staff from the configured agent divisions using the button above, or have them sign in once via the Staff
        portal. Division mapping lives on <RouterLink to="/settings/general">Settings → General</RouterLink>.
      </p>
      <button type="button" class="primary" @click="openPicker">+ Add agent from directory</button>
    </div>
  </section>
</template>

<style scoped>
.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  flex-wrap: wrap;
  margin-bottom: 0.85rem;
}
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
}
.lede {
  color: #475569;
  line-height: 1.5;
  margin: 0;
  font-size: 0.9rem;
  max-width: 56rem;
}
.primary {
  padding: 0.55rem 1.1rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #0d7a3a, #065f2c);
  color: #fff;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  white-space: nowrap;
}
.primary:hover {
  filter: brightness(1.05);
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.msg {
  margin: 0 0 0.65rem;
  padding: 0.55rem 0.75rem;
  border-radius: 8px;
  font-size: 0.86rem;
}
.msg-err {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}
.msg-warn {
  background: #fffbeb;
  border: 1px solid #fcd34d;
  color: #92400e;
}
.msg-ok {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #166534;
}
.msg-link {
  margin-left: 0.4rem;
  font-weight: 600;
  color: #92400e;
}

.picker {
  margin: 0 0 1rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}
.picker-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  flex-wrap: wrap;
  margin-bottom: 0.65rem;
}
.picker-head h3 {
  margin: 0 0 0.25rem;
  font-size: 0.98rem;
}
.picker-hint {
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.45;
  max-width: 48rem;
}
.picker-hint a {
  color: #0d7a3a;
  font-weight: 600;
}
.picker-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.55rem 0.85rem;
  margin: 0.5rem 0;
}
.search-wrap {
  flex: 1 1 16rem;
}
.search-input {
  width: 100%;
  padding: 0.45rem 0.6rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
}
.filter-toggle {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #475569;
  font-weight: 500;
  cursor: pointer;
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

.picker-table-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  overflow: auto;
  max-height: 20rem;
}
.picker-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.picker-table th {
  position: sticky;
  top: 0;
  background: #f1f5f9;
  text-align: left;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  padding: 0.5rem 0.7rem;
  border-bottom: 1px solid #e2e8f0;
}
.picker-table td {
  padding: 0.5rem 0.7rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.picker-table tr:last-child td {
  border-bottom: none;
}
.cand-name {
  font-weight: 600;
  color: #0f172a;
}
.cand-sub {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.1rem;
}
.cand-sub .missing {
  color: #92400e;
}
.dot-sep {
  margin: 0 0.35rem;
  color: #cbd5e1;
}
.badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}
.badge-div {
  background: #eef2ff;
  color: #3730a3;
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0;
}
.badge-role-agent {
  background: #dcfce7;
  color: #166534;
}
.badge-role-admin {
  background: #fef3c7;
  color: #92400e;
}
.badge-role-other {
  background: #e0e7ff;
  color: #3730a3;
}
.badge-role-none {
  background: #f1f5f9;
  color: #64748b;
}

.cand-actions {
  text-align: right;
}
.btn-add {
  padding: 0.32rem 0.7rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  border: 1px solid #0d7a3a;
  background: #0d7a3a;
  color: #fff;
  cursor: pointer;
  white-space: nowrap;
}
.btn-add:hover {
  background: #065f2c;
}
.btn-add:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  background: var(--cdc-white, #fff);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 0.6rem 0.55rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: top;
}
.tbl th {
  background: #f8fafc;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
}
.agent-name {
  font-weight: 600;
  color: #0f172a;
}
.agent-email {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.15rem;
}
.multi {
  min-width: 220px;
  min-height: 110px;
  padding: 0.35rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.85rem;
}
.route-hint {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: #64748b;
  font-style: italic;
}
.perm-toggle {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #3a4452;
  cursor: pointer;
  white-space: nowrap;
  margin-bottom: 0.35rem;
}
.perm-toggle:last-child {
  margin-bottom: 0;
}
.agent-actions {
  white-space: nowrap;
}
.btn {
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  border: none;
  background: #119a48;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.btn:hover {
  background: #0d7a3a;
}
.btn-link-danger {
  background: transparent;
  color: #b91c1c;
  border: none;
  padding: 0.4rem 0.55rem;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  margin-left: 0.25rem;
}
.btn-link-danger:hover {
  text-decoration: underline;
}

.empty-state {
  text-align: center;
  padding: 2rem 1rem;
  border: 2px dashed #cbd5e1;
  border-radius: 14px;
  background: #f8fafc;
}
.empty-title {
  margin: 0 0 0.5rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: #1f2937;
}
.empty-text {
  margin: 0 0 1rem;
  color: #475569;
  font-size: 0.9rem;
  line-height: 1.55;
  max-width: 32rem;
  margin-left: auto;
  margin-right: auto;
}
.empty-text a {
  color: #0d7a3a;
  font-weight: 600;
}

.ghost {
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
}
.ghost:hover {
  background: #f1f5f9;
}
.ghost-sm {
  padding: 0.32rem 0.7rem;
  font-size: 0.8rem;
}
.muted {
  color: #64748b;
  font-size: 0.85rem;
}
.center {
  text-align: center;
}
</style>
