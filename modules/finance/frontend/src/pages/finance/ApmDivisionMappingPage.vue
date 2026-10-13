<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type Division = { division_id: number; division_name: string; division_short_name: string }
type DivAlias = { label: string; division_id: number | null; match_source: string }

const loading = ref(true)
const fetching = ref(false)
const saving = ref(false)
const remapping = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const apmReady = ref(false)
const divisions = ref<Division[]>([])
const divisionAliases = ref<DivAlias[]>([])
const remapFrom = ref<number | null>(null)
const remapTo = ref<number | null>(null)
const selectedLabels = ref<string[]>([])
const bulkDivisionId = ref<number | null>(null)

const divisionMapped = computed(() => divisionAliases.value.filter((r) => r.division_id).length)

const divisionItems = computed(() =>
  divisions.value.map((d) => ({
    title: d.division_short_name ? `${d.division_short_name} — ${d.division_name}` : d.division_name,
    value: d.division_id,
  })),
)

const allLabelsSelected = computed(
  () =>
    divisionAliases.value.length > 0 &&
    selectedLabels.value.length === divisionAliases.value.length,
)

/** Labels that share an APM division target (useful when consolidating). */
const duplicateTargets = computed(() => {
  const counts = new Map<number, string[]>()
  for (const row of divisionAliases.value) {
    if (!row.division_id) continue
    const list = counts.get(row.division_id) || []
    list.push(row.label)
    counts.set(row.division_id, list)
  }
  return [...counts.entries()]
    .filter(([, labels]) => labels.length > 1)
    .map(([division_id, labels]) => ({
      division_id,
      labels,
      name: divisionLabel(division_id),
    }))
})

const remapFromCount = computed(() => {
  if (!remapFrom.value) return 0
  return divisionAliases.value.filter((r) => r.division_id === remapFrom.value).length
})

function divisionLabel(id: number | null): string {
  if (!id) return '—'
  const d = divisions.value.find((x) => x.division_id === id)
  if (!d) return `#${id}`
  return d.division_short_name
    ? `${d.division_short_name} — ${d.division_name}`
    : d.division_name
}

async function applyPayload(data: any) {
  apmReady.value = !!data.apm_ready
  divisions.value = data.divisions || []
  divisionAliases.value = (data.division_aliases || []).map((r: DivAlias) => ({
    ...r,
    division_id: r.division_id ?? null,
  }))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/apm-mapping')
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load APM division mappings.')
  } finally {
    loading.value = false
  }
}

async function fetchApm() {
  fetching.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/apm-mapping/fetch')
    message.value = data.message || 'Fetched from APM.'
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Fetch from APM failed.')
  } finally {
    fetching.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/apm-mapping', {
      division_aliases: divisionAliases.value.map((r) => ({
        ...r,
        match_source: r.division_id
          ? r.match_source === 'auto' || r.match_source === 'remap'
            ? r.match_source
            : 'manual'
          : 'unmatched',
      })),
    })
    message.value = data.message || 'Division mappings saved.'
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    saving.value = false
  }
}

async function applyRemap() {
  if (!remapFrom.value || !remapTo.value || remapFrom.value === remapTo.value) {
    error.value = 'Choose two different APM divisions to remap.'
    return
  }
  const from = remapFrom.value
  const to = remapTo.value
  const count = divisionAliases.value.filter((r) => r.division_id === from).length
  if (!count) {
    error.value = 'No workbook labels currently map to the source division.'
    return
  }
  if (
    !window.confirm(
      `Remap ${count} label(s) from “${divisionLabel(from)}” → “${divisionLabel(to)}”?`,
    )
  ) {
    return
  }
  remapping.value = true
  error.value = null
  message.value = null
  for (const row of divisionAliases.value) {
    if (row.division_id === from) {
      row.division_id = to
      row.match_source = 'remap'
    }
  }
  try {
    await save()
    message.value = `Remapped ${count} label(s) onto ${divisionLabel(to)}.`
    remapFrom.value = null
    remapTo.value = null
  } finally {
    remapping.value = false
  }
}

function toggleLabel(label: string) {
  const set = new Set(selectedLabels.value)
  if (set.has(label)) set.delete(label)
  else set.add(label)
  selectedLabels.value = [...set]
}

function toggleAllLabels() {
  if (allLabelsSelected.value) {
    selectedLabels.value = []
    return
  }
  selectedLabels.value = divisionAliases.value.map((r) => r.label)
}

function onDivisionPick(row: DivAlias) {
  row.match_source = row.division_id ? 'manual' : 'unmatched'
}

async function applyBulkAssign() {
  if (!selectedLabels.value.length) {
    error.value = 'Select one or more workbook labels first.'
    return
  }
  const set = new Set(selectedLabels.value)
  for (const row of divisionAliases.value) {
    if (!set.has(row.label)) continue
    row.division_id = bulkDivisionId.value
    row.match_source = bulkDivisionId.value ? 'manual' : 'unmatched'
  }
  await save()
  message.value = bulkDivisionId.value
    ? `Assigned ${selectedLabels.value.length} label(s) to ${divisionLabel(bulkDivisionId.value)}.`
    : `Cleared mapping for ${selectedLabels.value.length} label(s).`
  selectedLabels.value = []
}

onMounted(load)
</script>

<template>
  <div class="map">
    <header class="map__header">
      <div class="map__title-block">
        <div class="map__title-row">
          <h1>APM division mapping</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="map__sub">
          Map Funding Portfolio workbook BU labels to APM divisions. Funders are managed separately.
        </p>
      </div>
      <div class="map__actions">
        <RouterLink class="map__link" :to="{ name: 'settings-apm-funders' }">Funder mapping</RouterLink>
        <button type="button" class="btn" :disabled="fetching || loading" @click="fetchApm">
          {{ fetching ? 'Fetching…' : 'Fetch from APM' }}
        </button>
        <button type="button" class="btn btn-primary" :disabled="saving || loading" @click="save">
          {{ saving ? 'Saving…' : 'Save mappings' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="map__error">{{ error }}</p>
    <p v-if="message" class="map__ok">{{ message }}</p>
    <p v-if="loading" class="map__muted">Loading…</p>

    <template v-else>
      <p class="map__status">
        Status:
        <strong>{{ apmReady ? 'APM database reachable' : 'APM database not configured' }}</strong>
        · {{ divisions.length }} division(s)
        · {{ divisionMapped }}/{{ divisionAliases.length }} mapped
      </p>

      <section class="map__card map__card--standalone">
        <h2 class="map__section-title">Remap divisions</h2>
        <p class="map__muted">
          Consolidate duplicate APM targets: move every workbook label currently pointed at one
          division onto another. Useful when two APM divisions are effectively the same unit.
        </p>
        <div class="map__remap">
          <label>
            From (source)
            <v-autocomplete
              v-model="remapFrom"
              :items="divisionItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              placeholder="Search division…"
              class="map__autocomplete"
            />
            <small v-if="remapFrom">{{ remapFromCount }} label(s) currently mapped here</small>
          </label>
          <span class="map__remap-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right" /></span>
          <label>
            To (canonical)
            <v-autocomplete
              v-model="remapTo"
              :items="divisionItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              placeholder="Search division…"
              class="map__autocomplete"
            />
          </label>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="remapping || saving || !remapFrom || !remapTo || remapFrom === remapTo"
            @click="applyRemap"
          >
            {{ remapping ? 'Remapping…' : 'Remap &amp; save' }}
          </button>
        </div>

        <div v-if="duplicateTargets.length" class="map__dupes">
          <h3>Shared targets</h3>
          <p class="map__muted">These APM divisions already have more than one workbook label — not necessarily wrong.</p>
          <ul>
            <li v-for="g in duplicateTargets" :key="g.division_id">
              <strong>{{ g.name }}</strong>
              <span class="map__chip">{{ g.labels.length }}</span>
              — {{ g.labels.join('; ') }}
            </li>
          </ul>
        </div>
      </section>

      <section class="map__card map__card--standalone">
        <h2 class="map__section-title">Workbook → APM division</h2>
        <p class="map__muted">
          Year-specific budget codes still drive SAP intramural rollups; this mapping is for BU labels.
          Use checkboxes to assign several labels to one division at once.
        </p>

        <div class="map__bulk">
          <v-autocomplete
            v-model="bulkDivisionId"
            :items="divisionItems"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details="auto"
            clearable
            auto-select-first
            placeholder="Assign selected labels to…"
            class="map__autocomplete"
          />
          <button
            type="button"
            class="btn btn-primary"
            :disabled="saving || !selectedLabels.length"
            @click="applyBulkAssign"
          >
            Apply to {{ selectedLabels.length || 0 }} selected
          </button>
        </div>

        <div class="map__table-wrap">
          <table class="map__table">
            <thead>
              <tr>
                <th style="width: 2.5rem">
                  <input
                    type="checkbox"
                    :checked="allLabelsSelected"
                    title="Select all"
                    @change="toggleAllLabels"
                  />
                </th>
                <th>Portfolio / workbook label</th>
                <th>APM division</th>
                <th>Match</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in divisionAliases" :key="idx">
                <td>
                  <input
                    type="checkbox"
                    :checked="selectedLabels.includes(row.label)"
                    :aria-label="`Select ${row.label}`"
                    @change="toggleLabel(row.label)"
                  />
                </td>
                <td>{{ row.label }}</td>
                <td>
                  <v-autocomplete
                    v-model="row.division_id"
                    :items="divisionItems"
                    item-title="title"
                    item-value="value"
                    density="compact"
                    variant="outlined"
                    hide-details="auto"
                    clearable
                    auto-select-first
                    placeholder="Search division…"
                    class="map__autocomplete"
                    @update:model-value="onDivisionPick(row)"
                  />
                </td>
                <td><span class="map__chip">{{ row.match_source }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.map { width: 100%; max-width: none; margin: 0; padding-bottom: 2rem; }
.map__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.map__title-block { min-width: 0; flex: 1; }
.map__title-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem;
}
.map__header h1 { margin: 0; font-size: 1.45rem; }
.map__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 52rem; line-height: 1.45; }
.map__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.map__link { color: #0b3b5c; font-weight: 600; text-decoration: none; }
.map__status { color: #64748b; margin: 0 0 0.85rem; font-size: 0.92rem; }
.map__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.12);
  border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 0.9rem;
}
.map__section-title { margin: 0 0 0.35rem; font-size: 1.05rem; }
.map__muted { color: #64748b; margin: 0 0 0.75rem; }
.map__error { color: #b42318; }
.map__ok { color: #027a48; }
.map__remap {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto;
  gap: 0.75rem;
  align-items: end;
  margin-bottom: 1rem;
}
.map__remap label { display: flex; flex-direction: column; gap: 0.35rem; font-weight: 600; font-size: 0.85rem; }
.map__remap small { font-weight: 500; color: #64748b; }
.map__remap-arrow { color: #0b3b5c; padding-bottom: 0.55rem; }
.map__bulk {
  display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; margin-bottom: 0.85rem;
}
.map__bulk .map__autocomplete { flex: 1; min-width: 240px; }
.map__autocomplete { font-weight: 400; }
.map__autocomplete :deep(.v-field) { border-radius: 8px; }
.map__table input[type='checkbox'] { width: 1rem; height: 1rem; cursor: pointer; }
.map__dupes { border-top: 1px solid rgba(11,59,92,0.1); padding-top: 0.85rem; }
.map__dupes h3 { margin: 0 0 0.35rem; font-size: 0.95rem; }
.map__dupes ul { margin: 0; padding-left: 1.1rem; color: #334155; font-size: 0.9rem; }
.map__dupes li { margin-bottom: 0.35rem; }
.map__table-wrap { overflow-x: auto; }
.map__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.map__table th, .map__table td {
  text-align: left; padding: 0.5rem 0.45rem; border-bottom: 1px solid rgba(0,0,0,0.06); vertical-align: middle;
}
.map__table select {
  width: 100%; min-width: 220px; border: 1px solid rgba(0,0,0,0.16); border-radius: 8px; padding: 0.4rem 0.5rem;
}
.map__chip {
  display: inline-block; background: #e8f1f7; color: #0b3b5c; border-radius: 999px;
  padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
}
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: 8px;
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600; white-space: nowrap;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
@media (max-width: 900px) {
  .map__header { flex-direction: column; }
  .map__remap { grid-template-columns: 1fr; }
  .map__remap-arrow { display: none; }
}
</style>
