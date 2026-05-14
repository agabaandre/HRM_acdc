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

const ctx = useInjectedHelpdeskAdminSettings()

const divisions = ref<DivisionRow[]>([])
const divisionsErr = ref<string | null>(null)
const divisionsLoading = ref(false)
const divisionSearch = ref("")

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

      <label class="row">
        <input v-model="ctx.form.require_resolution_confirmation" type="checkbox" />
        Require requester email confirmation before a ticket is fully resolved
      </label>

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
</style>
