<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { api } from "../../lib/api"
import { useInjectedHelpdeskAdminSettings } from "../../composables/useHelpdeskAdminSettings"

interface DivisionRow {
  id: number
  name: string
  short_name?: string | null
  directorate_id?: number | null
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
  last_synced_at?: string | null
}

const ctx = useInjectedHelpdeskAdminSettings()

const divisions = ref<DivisionRow[]>([])
const divisionsErr = ref<string | null>(null)
const divisionsLoading = ref(false)
const divisionSearch = ref("")

const candidates = ref<CandidateRow[]>([])
const candidatesLoading = ref(false)
const candidatesErr = ref<string | null>(null)
const candidatesMessage = ref<string | null>(null)
const candidatesLoaded = ref(false)
const candidateSearch = ref("")
const onlyMarked = ref(false)
const busyStaffId = ref<number | null>(null)
const lastAction = ref<string | null>(null)

function parseDivisionCsv(csv: string): number[] {
  return csv
    .split(",")
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => !Number.isNaN(n) && n > 0)
}

const selectedDivisionIds = computed<number[]>({
  get() {
    return parseDivisionCsv(ctx.form.default_agent_division_ids)
  },
  set(ids: number[]) {
    const uniq = [...new Set(ids.filter((n) => n > 0))].sort((a, b) => a - b)
    ctx.form.default_agent_division_ids = uniq.length ? uniq.join(",") : ""
  },
})

const divisionOptions = computed(() => {
  const selected = parseDivisionCsv(ctx.form.default_agent_division_ids)
  const byId = new Map<number, DivisionRow>()
  for (const d of divisions.value) {
    if (d.id > 0) {
      byId.set(d.id, d)
    }
  }
  for (const id of selected) {
    if (!byId.has(id)) {
      byId.set(id, {
        id,
        name: `Division ${id} (not in directory)`,
        short_name: null,
        directorate_id: null,
      })
    }
  }
  return [...byId.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const filteredDivisionOptions = computed(() => {
  const q = divisionSearch.value.trim().toLowerCase()
  if (!q) {
    return divisionOptions.value
  }
  return divisionOptions.value.filter((d) => {
    const idStr = String(d.id)
    const name = (d.name || "").toLowerCase()
    const short = (d.short_name || "").toLowerCase()
    return name.includes(q) || short.includes(q) || idStr.includes(q)
  })
})

const selectionSummaryCsv = computed(() => {
  const ids = selectedDivisionIds.value
  return ids.length ? ids.join(", ") : ""
})

function divisionLabel(d: DivisionRow): string {
  const short = d.short_name ? ` (${d.short_name})` : ""
  return `${d.name}${short}`
}

function isDivisionSelected(id: number): boolean {
  return selectedDivisionIds.value.includes(id)
}

function toggleDivision(id: number, checked: boolean) {
  const current = [...selectedDivisionIds.value]
  if (checked) {
    if (!current.includes(id)) {
      current.push(id)
    }
  } else {
    const i = current.indexOf(id)
    if (i >= 0) {
      current.splice(i, 1)
    }
  }
  selectedDivisionIds.value = current
}

function onDivisionCheckboxChange(id: number, ev: Event) {
  const t = ev.target as HTMLInputElement
  toggleDivision(id, t.checked)
}

function selectAllFiltered() {
  const set = new Set(selectedDivisionIds.value)
  for (const d of filteredDivisionOptions.value) {
    if (d.id > 0) {
      set.add(d.id)
    }
  }
  selectedDivisionIds.value = [...set]
}

function clearAllSelections() {
  selectedDivisionIds.value = []
}

async function loadDivisions() {
  divisionsLoading.value = true
  divisionsErr.value = null
  try {
    const { data } = await api.get<{ data: { divisions: DivisionRow[] } }>("/api/v1/reference-data")
    const raw = data.data?.divisions ?? []
    divisions.value = raw.filter((d) => d.id > 0 && d.name?.trim())
  } catch {
    divisionsErr.value =
      "Could not load divisions from the Staff directory. Check Staff API credentials under Integrations, run reference sync, or use manual IDs in the section below."
    divisions.value = []
  } finally {
    divisionsLoading.value = false
  }
}

onMounted(() => {
  void loadDivisions()
})

async function saveGeneral() {
  await ctx.savePartial(
    {
      branding_primary_hex: ctx.form.branding_primary_hex || null,
      branding_secondary_hex: ctx.form.branding_secondary_hex || null,
      default_agent_division_ids: ctx.form.default_agent_division_ids.trim() || null,
      require_resolution_confirmation: ctx.form.require_resolution_confirmation,
    },
    "General settings saved.",
  )
}

const filteredCandidates = computed<CandidateRow[]>(() => {
  const q = candidateSearch.value.trim().toLowerCase()
  return candidates.value.filter((c) => {
    if (onlyMarked.value && !c.is_designated_agent) {
      return false
    }
    if (q === "") {
      return true
    }
    const hay = `${c.name} ${c.work_email ?? ""} ${c.division_name} ${c.staff_id}`.toLowerCase()
    return hay.includes(q)
  })
})

const markedCount = computed(() => candidates.value.filter((c) => c.is_designated_agent).length)

async function loadCandidates() {
  candidatesLoading.value = true
  candidatesErr.value = null
  candidatesMessage.value = null
  lastAction.value = null
  try {
    const { data } = await api.get<{
      data: { candidates: CandidateRow[]; division_ids: number[] }
      meta?: { message?: string }
    }>("/api/v1/admin/agents/division-candidates")
    candidates.value = Array.isArray(data.data?.candidates) ? data.data.candidates : []
    candidatesMessage.value = data.meta?.message ?? null
    candidatesLoaded.value = true
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    candidatesErr.value = msg || (e instanceof Error ? e.message : "Failed to load division staff.")
    candidates.value = []
  } finally {
    candidatesLoading.value = false
  }
}

async function designateAgent(c: CandidateRow) {
  if (!c.work_email) {
    lastAction.value = `${c.name} has no work email in the directory — cannot designate.`
    return
  }
  busyStaffId.value = c.staff_id
  try {
    await api.post("/api/v1/admin/agents/designate", {
      staff_id: c.staff_id,
      work_email: c.work_email,
      name: c.name,
      division_id: c.division_id || null,
      duty_station: c.duty_station_name || null,
    })
    c.is_designated_agent = true
    c.has_user = true
    c.current_role = "agent"
    lastAction.value = `${c.name} marked as agent.`
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    lastAction.value = msg || (e instanceof Error ? e.message : "Failed to mark as agent.")
  } finally {
    busyStaffId.value = null
  }
}

async function undesignateAgent(c: CandidateRow) {
  busyStaffId.value = c.staff_id
  try {
    await api.delete(`/api/v1/admin/agents/designate/${c.staff_id}`)
    c.is_designated_agent = false
    if (c.current_role === "agent") {
      c.current_role = "user"
    }
    lastAction.value = `${c.name} unmarked.`
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    lastAction.value = msg || (e instanceof Error ? e.message : "Failed to unmark agent.")
  } finally {
    busyStaffId.value = null
  }
}

function roleLabel(c: CandidateRow): string {
  if (!c.has_user) {
    return "Not signed in yet"
  }
  switch (c.current_role) {
    case "admin":
      return "Admin"
    case "agent":
      return "Agent"
    case "supervisor":
      return "Supervisor"
    case "auditor":
      return "Auditor"
    case "user":
    default:
      return "User"
  }
}
</script>

<template>
  <section class="panel" aria-labelledby="general-heading">
    <h2 id="general-heading">Branding &amp; workflow</h2>
    <p class="hint">URS §22 (branding colours) and agent onboarding / resolution workflow.</p>

    <div class="card">
      <h3>Branding</h3>
      <label>Primary colour (hex)
        <input v-model="ctx.form.branding_primary_hex" type="text" pattern="^#[0-9A-Fa-f]{6}$" />
      </label>
      <label>Secondary / accent gold (hex)
        <input v-model="ctx.form.branding_secondary_hex" type="text" pattern="^#[0-9A-Fa-f]{6}$" />
      </label>

      <h3>Workflow</h3>
      <div class="field-block">
        <span class="label">Default agent divisions</span>
        <p class="hint-tight">
          Staff users whose <code>division_id</code> matches any selection become Helpdesk <strong>agents</strong> on SSO,
          unless they are portal admins (see admin role mapping).
        </p>
        <p v-if="divisionsLoading" class="muted">Loading divisions…</p>
        <template v-else>
          <p v-if="divisionsErr" class="warn">{{ divisionsErr }}</p>

          <div v-if="divisionOptions.length" class="division-picker">
            <div class="picker-toolbar">
              <label class="search-wrap">
                <span class="sr-only">Search divisions</span>
                <input
                  v-model="divisionSearch"
                  type="search"
                  class="search-input"
                  placeholder="Search by name, short name, or ID…"
                  autocomplete="off"
                />
              </label>
              <div class="picker-actions">
                <button type="button" class="ghost ghost--sm" @click="selectAllFiltered()">Select all shown</button>
                <button type="button" class="ghost ghost--sm" @click="clearAllSelections()">Clear all</button>
              </div>
            </div>
            <div class="checks-scroll" role="group" aria-label="Division checkboxes">
              <label v-for="d in filteredDivisionOptions" :key="d.id" class="check-row">
                <input
                  type="checkbox"
                  :checked="isDivisionSelected(d.id)"
                  @change="onDivisionCheckboxChange(d.id, $event)"
                />
                <span class="check-row__name">{{ divisionLabel(d) }}</span>
                <span class="check-row__id">ID {{ d.id }}</span>
              </label>
            </div>
            <p class="selection-summary" aria-live="polite">
              <template v-if="selectedDivisionIds.length">
                {{ selectedDivisionIds.length }} selected: {{ selectionSummaryCsv }}
              </template>
              <template v-else>None selected.</template>
            </p>
          </div>
          <p v-else class="muted empty-divisions-msg">
            No divisions were returned from the directory. You can still set defaults using manual division IDs below.
          </p>

          <details class="manual-details">
            <summary>Manual division IDs</summary>
            <p class="hint-tight manual-details-hint">
              Comma-separated IDs for edge cases or when the directory list is incomplete.
            </p>
            <label>
              Division IDs (comma-separated)
              <input v-model="ctx.form.default_agent_division_ids" type="text" placeholder="21, 34" />
            </label>
          </details>

          <button type="button" class="ghost" :disabled="divisionsLoading" @click="loadDivisions()">Reload divisions</button>
        </template>
      </div>

      <!-- Division agents register -->
      <div class="field-block">
        <div class="agents-head">
          <div>
            <span class="label">Agents from selected divisions</span>
            <p class="hint-tight">
              Pull the current staff list for the divisions selected above and explicitly mark who should
              act as an agent. Marking a person locks the <strong>agent</strong> role across SSO logins, even
              if they later move to a different division. Staff directory data is read live from the Staff
              portal — nothing is duplicated locally.
            </p>
          </div>
          <button type="button" class="ghost" :disabled="candidatesLoading" @click="loadCandidates()">
            {{ candidatesLoading
              ? "Loading…"
              : (candidatesLoaded ? "Reload from directory" : "View staff in these divisions") }}
          </button>
        </div>

        <p v-if="candidatesErr" class="warn">{{ candidatesErr }}</p>
        <p v-else-if="candidatesMessage" class="muted">{{ candidatesMessage }}</p>
        <p v-if="lastAction" class="action-ok" aria-live="polite">{{ lastAction }}</p>

        <template v-if="candidatesLoaded && !candidatesErr && candidates.length">
          <div class="cand-toolbar">
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
              <input v-model="onlyMarked" type="checkbox" />
              Only marked ({{ markedCount }})
            </label>
            <span class="muted cand-meta">{{ filteredCandidates.length }} of {{ candidates.length }} shown</span>
          </div>

          <div class="cand-table-wrap">
            <table class="cand-table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Division</th>
                  <th>Status</th>
                  <th>Designation</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in filteredCandidates" :key="c.staff_id" :class="{ marked: c.is_designated_agent }">
                  <td>
                    <div class="cand-name">{{ c.name }}</div>
                    <div class="cand-sub">
                      <span v-if="c.work_email">{{ c.work_email }}</span>
                      <span v-else class="missing">No work email on directory</span>
                      <span class="dot-sep">·</span>
                      <span>SID {{ c.staff_id }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-div">{{ c.division_name }}</span>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="{
                        'badge-role-agent': c.current_role === 'agent',
                        'badge-role-admin': c.current_role === 'admin',
                        'badge-role-other': c.has_user && c.current_role !== 'agent' && c.current_role !== 'admin',
                        'badge-role-none': !c.has_user,
                      }"
                    >{{ roleLabel(c) }}</span>
                  </td>
                  <td>
                    <span v-if="c.is_designated_agent" class="badge badge-marked">Marked agent</span>
                    <span v-else class="badge badge-unmarked">—</span>
                  </td>
                  <td class="cand-actions">
                    <button
                      v-if="!c.is_designated_agent"
                      type="button"
                      class="btn-mark"
                      :disabled="busyStaffId === c.staff_id || !c.work_email"
                      @click="designateAgent(c)"
                    >
                      {{ busyStaffId === c.staff_id ? "Saving…" : "Mark as agent" }}
                    </button>
                    <button
                      v-else
                      type="button"
                      class="btn-unmark"
                      :disabled="busyStaffId === c.staff_id"
                      @click="undesignateAgent(c)"
                    >
                      {{ busyStaffId === c.staff_id ? "Saving…" : "Unmark" }}
                    </button>
                  </td>
                </tr>
                <tr v-if="filteredCandidates.length === 0">
                  <td colspan="5" class="muted center">No staff match the current filter.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <p v-else-if="candidatesLoaded && !candidatesErr && !candidatesMessage" class="muted">
          No staff returned from the directory for the selected divisions.
        </p>
      </div>

      <div class="actions">
        <button type="button" class="primary" :disabled="ctx.busy" @click="saveGeneral()">
          {{ ctx.busy ? "Saving…" : "Save general" }}
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
  color: var(--cdc-ink, #0c1a12);
}
.panel h3 {
  font-size: 0.95rem;
  margin: 0.75rem 0 0.35rem;
  color: var(--cdc-ink, #0c1a12);
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.hint-tight {
  color: #64748b;
  font-size: 0.8rem;
  margin: 0 0 0.5rem;
  line-height: 1.45;
}
.field-block {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}
.label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem 1.35rem;
  border-radius: 14px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-white, #fff);
  box-shadow: var(--cdc-shadow, 0 8px 24px rgba(6, 95, 44, 0.08));
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.row {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}
input {
  padding: 0.45rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.95rem;
}
.division-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 36rem;
}
.picker-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem 0.75rem;
}
.search-wrap {
  flex: 1 1 12rem;
  min-width: 0;
}
.search-input {
  width: 100%;
}
.picker-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.checks-scroll {
  max-height: 14rem;
  overflow-y: auto;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0.35rem 0.5rem;
  background: #fff;
}
.check-row {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 0.5rem;
  font-weight: 500;
  padding: 0.25rem 0;
  cursor: pointer;
}
.check-row input[type="checkbox"] {
  margin-top: 0.2rem;
  flex-shrink: 0;
}
.check-row__name {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.88rem;
  line-height: 1.35;
  color: #0f172a;
}
.check-row__id {
  flex-shrink: 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
}
.ghost--sm {
  padding: 0.28rem 0.55rem;
  font-size: 0.78rem;
}
.selection-summary {
  margin: 0;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.4;
  word-break: break-word;
}
.manual-details {
  margin-top: 0.15rem;
  max-width: 36rem;
}
.manual-details summary {
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
}
.manual-details-hint {
  margin-top: 0.35rem;
}
.empty-divisions-msg {
  margin: 0;
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
.muted {
  color: #64748b;
  font-size: 0.85rem;
  margin: 0;
}
.warn {
  color: #92400e;
  font-size: 0.8rem;
  margin: 0;
  padding: 0.35rem 0.5rem;
  background: #fffbeb;
  border-radius: 6px;
  border: 1px solid #fcd34d;
}
.ghost {
  align-self: flex-start;
  margin-top: 0.15rem;
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  color: #334155;
}
.ghost:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.actions {
  margin-top: 0.35rem;
}
.primary {
  padding: 0.55rem 1.1rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, var(--cdc-green, #0d7a3a), var(--cdc-green-deep, #065f2c));
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
code {
  font-size: 0.85em;
}

/* Division agents register */
.agents-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  flex-wrap: wrap;
}
.agents-head .ghost {
  align-self: flex-start;
  margin-top: 0;
}
.action-ok {
  margin: 0.3rem 0 0;
  font-size: 0.82rem;
  color: #166534;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  padding: 0.35rem 0.55rem;
  border-radius: 6px;
}
.cand-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.6rem 0.85rem;
  margin: 0.65rem 0 0.55rem;
}
.cand-toolbar .search-wrap {
  flex: 1 1 16rem;
}
.filter-toggle {
  flex-direction: row;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.82rem;
  font-weight: 500;
  color: #475569;
}
.cand-meta {
  margin-left: auto;
  font-size: 0.78rem;
}
.cand-table-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: auto;
  max-height: 22rem;
  background: #fff;
}
.cand-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.cand-table th {
  position: sticky;
  top: 0;
  background: #f8fafc;
  text-align: left;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  padding: 0.55rem 0.7rem;
  border-bottom: 1px solid #e2e8f0;
}
.cand-table td {
  padding: 0.55rem 0.7rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.cand-table tr.marked {
  background: linear-gradient(90deg, rgba(13, 122, 58, 0.04) 0%, transparent 100%);
}
.cand-table tr:last-child td {
  border-bottom: none;
}
.cand-name {
  font-weight: 600;
  color: #0f172a;
}
.cand-sub {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.15rem;
}
.cand-sub .missing {
  color: #92400e;
}
.dot-sep {
  margin: 0 0.4rem;
  color: #cbd5e1;
}
.badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}
.badge-div {
  background: #eef2ff;
  color: #3730a3;
  font-weight: 600;
  text-transform: none;
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
.badge-marked {
  background: #0d7a3a;
  color: #fff;
  text-transform: uppercase;
}
.badge-unmarked {
  background: transparent;
  color: #94a3b8;
  font-weight: 500;
}
.cand-actions {
  text-align: right;
}
.btn-mark,
.btn-unmark {
  padding: 0.32rem 0.7rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
  border: 1px solid transparent;
  white-space: nowrap;
}
.btn-mark {
  background: #0d7a3a;
  color: #fff;
  border-color: #0d7a3a;
}
.btn-mark:hover {
  background: #065f2c;
}
.btn-unmark {
  background: #fff;
  color: #b91c1c;
  border-color: #fecaca;
}
.btn-unmark:hover {
  background: #fef2f2;
}
.btn-mark:disabled,
.btn-unmark:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.center {
  text-align: center;
}
</style>
