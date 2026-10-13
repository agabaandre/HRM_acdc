<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { FINANCE_PERMS } from '@/lib/financePermissions'
import { useAuthStore } from '@/stores/auth'

type FieldDef = { key: string; label: string; type: string; hint?: string }
type Row = { id?: number; sort_order: number; data: Record<string, unknown> }
type GrantOpt = {
  id: number
  name: string
  short_name?: string | null
  funder_id?: number | null
  funder_name?: string | null
  end_date?: string | null
  total_budget?: number | null
  direct_cost_usd?: number | null
  indirect_cost?: number | null
  indirect_pct?: number | null
  budget_code?: string | null
}
type GeoRegion = { id: number; name: string }
type GeoCountry = { id: number; name: string; region_id: number | null; iso2?: string | null }
type GeoLocation = {
  id: number
  name: string
  country: string | null
  country_iso2?: string | null
  city?: string | null
}
type AfefCategory = { key: string; label: string; sort_order?: number; is_active?: boolean }
type DivisionRow = {
  division_id: number
  division_name: string
  division_short_name: string
  main_budget: number
  approved_budget: number
  budget_balance: number
  commitment: number
  actual_expenditure: number
  cy_expenditure?: number
  execution_rate: number | null
  fund_center_count: number
  fund_centers?: Array<{
    fund_center: string
    approved_budget: number
    budget_balance: number
    commitment: number
    actual_expenditure: number
    execution_rate: number | null
  }>
}

const SECTIONS = [
  { key: 'intramural', label: 'Intramural (SAP)', icon: 'fa-solid fa-building', readOnly: true },
  { key: 'extramural', label: 'Extramural', icon: 'fa-solid fa-handshake', readOnly: false },
  { key: 'indirect_cost', label: 'Indirect Cost', icon: 'fa-solid fa-percent', readOnly: false },
  { key: 'administrative_cost', label: 'Administrative Cost', icon: 'fa-solid fa-clipboard-list', readOnly: false },
  { key: 'investments', label: 'Investments & Bank', icon: 'fa-solid fa-landmark', readOnly: false },
  { key: 'afef', label: 'AfEF', icon: 'fa-solid fa-shield-heart', readOnly: false },
] as const

const auth = useAuthStore()
const tab = ref<(typeof SECTIONS)[number]['key']>('extramural')
const rows = ref<Row[]>([])
const schema = ref<FieldDef[]>([])
const emptyRow = ref<Record<string, unknown>>({})
const apmFunders = ref<Array<{ id: number; name: string }>>([])
const grantsCatalog = ref<GrantOpt[]>([])
const geoRegions = ref<GeoRegion[]>([])
const geoCountries = ref<GeoCountry[]>([])
const geoLocations = ref<GeoLocation[]>([])
const afefCategories = ref<AfefCategory[]>([])
const canEditApi = ref(false)
const draft = ref<Record<string, unknown>>({})
const editingIndex = ref<number | null>(null)
const showAddForm = ref(true)
const intramural = ref<{
  approved_budget: number
  budget_balance: number
  commitment: number
  actual_expenditure: number
  fund_center_count: number
  execution_rate: number | null
  mapped_count: number
  unmapped_count: number
  divisions_source?: string
  apm_divisions?: Array<{ division_id: number; division_name: string; division_short_name: string }>
  by_division: DivisionRow[]
  sap_import?: { original_filename?: string; sync_status?: string } | null
} | null>(null)
/** Empty = show all divisions with SAP data; otherwise filter to selected IDs. */
const divisionFilterIds = ref<number[]>([])
const expanded = ref<Record<number, boolean>>({})
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)

const activeMeta = computed(() => SECTIONS.find((s) => s.key === tab.value)!)
const isIntramural = computed(() => tab.value === 'intramural')
const isExtramural = computed(() => tab.value === 'extramural')
const isIndirect = computed(() => tab.value === 'indirect_cost')
const usesGrantPickers = computed(() => isExtramural.value || isIndirect.value)
const isInvestments = computed(() => tab.value === 'investments')
const isAfef = computed(() => tab.value === 'afef')
const usesGeoPickers = computed(() => isInvestments.value)
const usesAfefCategoryPicker = computed(() => isAfef.value)
const canEdit = computed(
  () =>
    canEditApi.value ||
    auth.hasPermission(FINANCE_PERMS.MANAGE_FUNDING_PORTFOLIO) ||
    auth.hasPermission(FINANCE_PERMS.SETTINGS),
)

const draftTitle = computed(() =>
  editingIndex.value === null ? 'Add new entry' : `Edit entry #${editingIndex.value + 1}`,
)

const funderItems = computed(() =>
  apmFunders.value.map((f) => ({ title: f.name, value: f.id })),
)

const grantItems = computed(() =>
  grantsCatalog.value.map((g) => ({
    title: g.short_name ? `${g.short_name} — ${g.name}` : g.name,
    value: g.id,
    funder_name: g.funder_name || '',
  })),
)

const regionItems = computed(() =>
  geoRegions.value.map((r) => ({ title: r.name, value: r.id })),
)

const countryItems = computed(() => {
  const regionId = draftRegionId.value
  const list = regionId
    ? geoCountries.value.filter((c) => !c.region_id || c.region_id === regionId)
    : geoCountries.value
  const items = list.map((c) => ({ title: c.name, value: c.id }))
  // Keep current selection visible even if filtered out.
  const current = draftCountryId.value
  if (current && !items.some((i) => i.value === current)) {
    const c = geoCountries.value.find((x) => x.id === current)
    if (c) items.unshift({ title: c.name, value: c.id })
  }
  return items
})

const locationItems = computed(() => {
  const country = String(draft.value.country || '').trim()
  const list = country
    ? geoLocations.value.filter(
        (l) =>
          !l.country ||
          l.country.toLowerCase() === country.toLowerCase() ||
          l.name.toLowerCase().includes(country.toLowerCase()),
      )
    : geoLocations.value
  const items = list.map((l) => ({
    title: l.country ? `${l.name} · ${l.country}` : l.name,
    value: l.id,
  }))
  const current = draftLocationId.value
  if (current && !items.some((i) => i.value === current)) {
    const l = geoLocations.value.find((x) => x.id === current)
    if (l) items.unshift({ title: l.country ? `${l.name} · ${l.country}` : l.name, value: l.id })
  }
  return items
})

const afefCategoryItems = computed(() => {
  const items = afefCategories.value.map((c) => ({
    title: c.label,
    value: c.key,
    subtitle: c.key,
  }))
  const current = String(draft.value.category || '').trim()
  if (current && !items.some((i) => i.value === current)) {
    items.unshift({ title: current, value: current, subtitle: 'custom' })
  }
  return items
})

const draftAfefCategory = computed<string | null>({
  get() {
    const v = draft.value.category
    if (v == null || v === '') return null
    return String(v)
  },
  set(key) {
    draft.value.category = key || ''
  },
})

const draftFunderId = computed<number | null>({
  get() {
    const id = draft.value.funder_id
    if (id == null || id === '') return null
    const n = Number(id)
    return Number.isFinite(n) ? n : null
  },
  set(id) {
    draft.value.funder_id = id
    const match = apmFunders.value.find((f) => f.id === id)
    if (match) draft.value.funder = match.name
    else if (id == null) draft.value.funder = ''
  },
})

const draftGrantId = computed<number | null>({
  get() {
    const id = draft.value.grant_id
    if (id == null || id === '') return null
    const n = Number(id)
    return Number.isFinite(n) ? n : null
  },
  set(id) {
    draft.value.grant_id = id
    applyGrantSelection(id)
  },
})

const draftRegionId = computed<number | null>({
  get() {
    const id = draft.value.region_id
    if (id == null || id === '') return null
    const n = Number(id)
    return Number.isFinite(n) ? n : null
  },
  set(id) {
    draft.value.region_id = id
    const match = geoRegions.value.find((r) => r.id === id)
    draft.value.region = match?.name || ''
  },
})

const draftCountryId = computed<number | null>({
  get() {
    const id = draft.value.country_id
    if (id == null || id === '') return null
    const n = Number(id)
    return Number.isFinite(n) ? n : null
  },
  set(id) {
    draft.value.country_id = id
    const match = geoCountries.value.find((c) => c.id === id)
    draft.value.country = match?.name || ''
    if (match?.region_id) {
      draft.value.region_id = match.region_id
      const region = geoRegions.value.find((r) => r.id === match.region_id)
      if (region) draft.value.region = region.name
    }
    // Clear location if it no longer matches the country.
    const locId = draftLocationId.value
    if (locId != null) {
      const loc = geoLocations.value.find((l) => l.id === locId)
      const countryName = String(draft.value.country || '')
      if (
        loc?.country &&
        countryName &&
        loc.country.toLowerCase() !== countryName.toLowerCase() &&
        !loc.name.toLowerCase().includes(countryName.toLowerCase())
      ) {
        draft.value.location_id = null
        draft.value.location = ''
      }
    }
  },
})

const draftLocationId = computed<number | null>({
  get() {
    const id = draft.value.location_id
    if (id == null || id === '') return null
    const n = Number(id)
    return Number.isFinite(n) ? n : null
  },
  set(id) {
    draft.value.location_id = id
    const match = geoLocations.value.find((l) => l.id === id)
    draft.value.location = match?.name || ''
  },
})

const divisionOptions = computed(() => {
  const fromApm = intramural.value?.apm_divisions || []
  const list = fromApm.length
    ? fromApm
    : (intramural.value?.by_division || []).filter((d) => d.division_id > 0)
  return list.map((d) => ({
    id: d.division_id,
    label: d.division_short_name && d.division_short_name !== '—'
      ? `${d.division_short_name} — ${d.division_name}`
      : d.division_name,
  }))
})

const divisionFilterItems = computed(() =>
  divisionOptions.value.map((d) => ({ title: d.label, value: d.id })),
)

const filteredDivisions = computed(() => {
  const list = intramural.value?.by_division || []
  const withBudget = list.filter((d) => d.fund_center_count > 0 || d.division_id === 0)
  if (!divisionFilterIds.value.length) return withBudget
  const selected = new Set(divisionFilterIds.value)
  return list.filter((d) => selected.has(d.division_id))
})

function usd(n: number | null | undefined): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(Number(n || 0))
}

function pct(n: number | null | undefined): string {
  if (n == null || Number.isNaN(Number(n))) return '—'
  const v = Number(n)
  const ratio = Math.abs(v) > 1 ? v / 100 : v
  return `${(ratio * 100).toFixed(1)}%`
}

/** Extramural execution ratio (0–1): actual ÷ released, else actual ÷ approved. */
function calcExtramuralExecution(data: Record<string, unknown>): number {
  const actual = Number(data.actual_expenditure ?? 0)
  const released = Number(data.released_budget ?? 0)
  const approved = Number(data.approved_budget ?? 0)
  const base = released > 0 ? released : approved
  if (!Number.isFinite(actual) || !Number.isFinite(base) || base <= 0) return 0
  return Number((actual / base).toFixed(6))
}

function applyExtramuralComputed(data: Record<string, unknown>): Record<string, unknown> {
  return {
    ...data,
    execution_pct: calcExtramuralExecution(data),
  }
}

function titleForRow(row: Row, idx: number): string {
  const d = row.data || {}
  return String(
    d.grant ||
      d.grant_name ||
      d.description ||
      d.account_name ||
      d.label ||
      d.funder ||
      `Entry ${idx + 1}`,
  )
}

function subtitleForRow(row: Row): string {
  const d = row.data || {}
  const bits = [
    d.funder,
    d.budget_code,
    d.funds_center,
    d.fund,
    d.bank_name,
    d.country,
    d.region,
    d.category,
  ]
    .map((x) => String(x || '').trim())
    .filter(Boolean)
  if (tab.value === 'extramural') {
    bits.push(`Exec ${pct(calcExtramuralExecution(d))}`)
  }
  return bits.slice(0, 4).join(' · ')
}

function moneyPreview(row: Row): string {
  const d = row.data || {}
  const n = d.approved_budget ?? d.total_grant ?? d.amount ?? d.bank_balance_usd
  if (n == null || n === '') return ''
  return usd(Number(n))
}

function applyGrantSelection(id: number | null) {
  if (id == null) {
    if (isExtramural.value) draft.value.grant = ''
    if (isIndirect.value) draft.value.grant_name = ''
    return
  }
  const g = grantsCatalog.value.find((x) => x.id === id)
  if (!g) return
  if (isExtramural.value) {
    draft.value.grant = g.name
  }
  if (isIndirect.value) {
    draft.value.grant_name = g.name
    if (g.end_date) draft.value.end_date = String(g.end_date).slice(0, 10)
    if (g.total_budget != null) draft.value.total_grant = Number(g.total_budget)
    if (g.direct_cost_usd != null) draft.value.direct_cost_usd = Number(g.direct_cost_usd)
    if (g.indirect_cost != null) draft.value.acdc_indirect_cost = Number(g.indirect_cost)
    if (g.indirect_pct != null) draft.value.indirect_pct = Number(g.indirect_pct)
    if (g.budget_code) draft.value.budget_code = g.budget_code
  }
  if (g.funder_id) {
    draft.value.funder_id = g.funder_id
    draft.value.funder = g.funder_name || draft.value.funder
  } else if (g.funder_name) {
    draft.value.funder = g.funder_name
  }
}

function resetDraft() {
  draft.value = { ...emptyRow.value }
  editingIndex.value = null
  showAddForm.value = usesGrantPickers.value || usesGeoPickers.value || usesAfefCategoryPicker.value
}

async function loadSection() {
  loading.value = true
  error.value = null
  message.value = null
  editingIndex.value = null
  try {
    const { data } = await api.get(`/api/v1/portfolio/sections/${tab.value}`)
    if (tab.value === 'intramural') {
      intramural.value = data.data.intramural
      rows.value = []
      schema.value = []
      apmFunders.value = []
      grantsCatalog.value = []
      geoRegions.value = []
      geoCountries.value = []
      geoLocations.value = []
      afefCategories.value = []
      canEditApi.value = false
      draft.value = {}
    } else {
      schema.value = data.data.schema || []
      emptyRow.value = data.data.empty_row || {}
      apmFunders.value = data.data.funders || []
      grantsCatalog.value = data.data.grants || []
      geoRegions.value = data.data.regions || []
      geoCountries.value = data.data.countries || []
      geoLocations.value = data.data.locations || []
      afefCategories.value = data.data.afef_categories || []
      canEditApi.value = !!data.data.can_edit
      rows.value = (data.data.rows || []).map((r: Row, i: number) => ({
        id: r.id,
        sort_order: r.sort_order ?? i,
        data: { ...emptyRow.value, ...(r.data || {}) },
      }))
      resetDraft()
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load section.')
  } finally {
    loading.value = false
  }
}

async function persistRows(next: Row[], okMessage: string) {
  if (!canEdit.value || isIntramural.value) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    await api.put(`/api/v1/portfolio/sections/${tab.value}`, {
      rows: next.map((r, i) => ({
        sort_order: i,
        data: isExtramural.value ? applyExtramuralComputed(r.data || {}) : r.data,
      })),
    })
    message.value = okMessage
    await loadSection()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    saving.value = false
  }
}

async function saveDraft() {
  if (!canEdit.value) return
  let data = { ...draft.value }
  if (isExtramural.value) {
    data = applyExtramuralComputed(data)
  }
  if (editingIndex.value === null) {
    const next = [...rows.value, { sort_order: rows.value.length, data }]
    await persistRows(next, 'Entry added.')
  } else {
    const next = rows.value.map((r, i) =>
      i === editingIndex.value ? { ...r, data } : r,
    )
    await persistRows(next, 'Entry updated.')
  }
}

function startEdit(idx: number) {
  if (!canEdit.value) return
  editingIndex.value = idx
  let data = { ...emptyRow.value, ...rows.value[idx].data }
  if (isExtramural.value) {
    data = applyExtramuralComputed(data)
  }
  draft.value = data
  showAddForm.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelDraft() {
  resetDraft()
  if (!isExtramural.value && !isInvestments.value && !isAfef.value) showAddForm.value = false
}

function openNewEntry() {
  editingIndex.value = null
  draft.value = { ...emptyRow.value }
  showAddForm.value = true
}

async function removeRow(idx: number) {
  if (!canEdit.value) return
  if (!window.confirm('Remove this entry?')) return
  const next = rows.value.filter((_, i) => i !== idx)
  await persistRows(next, 'Entry removed.')
}

function toggleExpand(id: number) {
  expanded.value = { ...expanded.value, [id]: !expanded.value[id] }
}


function setDraftField(key: string, event: Event, asNumber = false) {
  const el = event.target as HTMLInputElement | HTMLTextAreaElement
  draft.value[key] = asNumber ? Number(el.value) : el.value
  if (
    isExtramural.value &&
    (key === 'approved_budget' || key === 'released_budget' || key === 'actual_expenditure')
  ) {
    draft.value.execution_pct = calcExtramuralExecution(draft.value)
  }
}

function isComputedField(field: FieldDef): boolean {
  return field.type === 'computed' || (isExtramural.value && field.key === 'execution_pct')
}

function isFunderField(field: FieldDef): boolean {
  return field.type === 'funder' || field.key === 'funder_id'
}

function isGrantField(field: FieldDef): boolean {
  return field.type === 'grant' || field.key === 'grant_id'
}

function isRegionField(field: FieldDef): boolean {
  return field.type === 'region' || field.key === 'region_id'
}

function isCountryField(field: FieldDef): boolean {
  return field.type === 'country' || field.key === 'country_id'
}

function isLocationField(field: FieldDef): boolean {
  return field.type === 'location' || field.key === 'location_id'
}

function isAfefCategoryField(field: FieldDef): boolean {
  return field.type === 'afef_category' || (isAfef.value && field.key === 'category')
}

function isDisplayNameField(field: FieldDef): boolean {
  return (
    field.key === 'funder' ||
    field.key === 'grant' ||
    field.key === 'grant_name' ||
    field.key === 'region' ||
    field.key === 'country' ||
    field.key === 'location'
  )
}

watch(tab, loadSection)

watch(
  () =>
    [
      isExtramural.value,
      draft.value.approved_budget,
      draft.value.released_budget,
      draft.value.actual_expenditure,
    ] as const,
  () => {
    if (!isExtramural.value) return
    draft.value.execution_pct = calcExtramuralExecution(draft.value)
  },
)

onMounted(loadSection)
</script>

<template>
  <div class="pe">
    <header class="pe__header">
      <div>
        <div class="pe__title-row">
          <h1>Portfolio data entry</h1>
          <DivisionContextSwitcher />
        </div>
      </div>
      <div class="pe__actions">
        <RouterLink class="pe__link" :to="{ name: 'dashboard' }">View dashboard</RouterLink>
        <RouterLink
          v-if="isAfef"
          class="pe__link"
          :to="{ name: 'settings-afef-categories' }"
        >
          AfEF categories
        </RouterLink>
        <button
          v-if="!isIntramural && canEdit"
          type="button"
          class="btn btn-primary pe__btn-icon"
          :disabled="loading || saving"
          @click="openNewEntry"
        >
          <i class="fa-solid fa-plus" aria-hidden="true" />
          New entry
        </button>
      </div>
    </header>

    <nav class="pe__tabs" aria-label="Portfolio sections">
      <button
        v-for="s in SECTIONS"
        :key="s.key"
        type="button"
        class="pe__tab"
        :class="{ 'is-active': tab === s.key }"
        @click="tab = s.key"
      >
        <i :class="s.icon" aria-hidden="true" />
        <span>{{ s.label }}</span>
      </button>
    </nav>

    <p v-if="!isIntramural && !canEdit" class="pe__banner">
      View only — ask an administrator for <strong>manage_funding_portfolio</strong> to add or edit entries.
    </p>
    <p v-if="message" class="pe__ok">{{ message }}</p>
    <p v-if="error" class="pe__error">{{ error }}</p>
    <p v-if="loading" class="pe__muted">Loading…</p>

    <template v-else-if="isIntramural && intramural">
      <section class="pe__kpi">
        <article class="pe__kpi-card">
          <span>Released (SAP)</span>
          <strong>{{ usd(intramural.approved_budget) }}</strong>
        </article>
        <article class="pe__kpi-card">
          <span>Balance</span>
          <strong>{{ usd(intramural.budget_balance) }}</strong>
        </article>
        <article class="pe__kpi-card">
          <span>Execution</span>
          <strong>{{ pct(intramural.execution_rate) }}</strong>
        </article>
        <article class="pe__kpi-card">
          <span>Fund centers</span>
          <strong>{{ intramural.fund_center_count }}</strong>
        </article>
        <article class="pe__kpi-card">
          <span>APM division mapped</span>
          <strong>{{ intramural.mapped_count }} / {{ intramural.mapped_count + intramural.unmapped_count }}</strong>
        </article>
      </section>

      <div class="pe__toolbar">
        <div class="pe__toolbar-filter">
          <span class="pe__toolbar-label">APM divisions</span>
          <v-autocomplete
            v-model="divisionFilterIds"
            :items="divisionFilterItems"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details="auto"
            clearable
            multiple
            chips
            closable-chips
            auto-select-first
            placeholder="All divisions — search & select…"
            class="pe__autocomplete pe__division-filter"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps">
                <template #prepend>
                  <v-checkbox-btn
                    :model-value="divisionFilterIds.includes(Number(item.value))"
                    readonly
                    tabindex="-1"
                  />
                </template>
              </v-list-item>
            </template>
          </v-autocomplete>
        </div>
        <p class="pe__muted">
          Divisions from <strong>APM</strong>
          ·
          <RouterLink :to="{ name: 'sap-import' }">SAP import</RouterLink>
        </p>
      </div>

      <div v-if="!filteredDivisions.length" class="pe__empty">
        No intramural SAP totals for this year. Upload a SAP export first.
      </div>

      <article
        v-for="div in filteredDivisions"
        :key="div.division_id"
        class="pe__card pe__card--intra"
      >
        <header class="pe__card-head">
          <div>
            <h2>
              <span class="pe__badge">{{ div.division_short_name }}</span>
              {{ div.division_name }}
            </h2>
            <p class="pe__muted">{{ div.fund_center_count }} fund center(s)</p>
          </div>
          <button type="button" class="btn" @click="toggleExpand(div.division_id)">
            {{ expanded[div.division_id] ? 'Hide codes' : 'Show codes' }}
          </button>
        </header>
        <div class="pe__metrics">
          <div><span>Main / released</span><strong>{{ usd(div.approved_budget) }}</strong></div>
          <div><span>Commitment</span><strong>{{ usd(div.commitment) }}</strong></div>
          <div><span>Actual expenditure</span><strong>{{ usd(div.actual_expenditure) }}</strong></div>
          <div><span>Released balance</span><strong>{{ usd(div.budget_balance) }}</strong></div>
          <div><span>Execution</span><strong>{{ pct(div.execution_rate) }}</strong></div>
        </div>
        <div v-if="expanded[div.division_id]" class="pe__fc-table-wrap">
          <table class="pe__fc-table">
            <thead>
              <tr>
                <th>Fund center</th>
                <th>Released</th>
                <th>Commitment</th>
                <th>Actual</th>
                <th>Balance</th>
                <th>Exec %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fc in div.fund_centers || []" :key="fc.fund_center">
                <td><code>{{ fc.fund_center }}</code></td>
                <td>{{ usd(fc.approved_budget) }}</td>
                <td>{{ usd(fc.commitment) }}</td>
                <td>{{ usd(fc.actual_expenditure) }}</td>
                <td>{{ usd(fc.budget_balance) }}</td>
                <td>{{ pct(fc.execution_rate) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </template>

    <template v-else-if="!isIntramural">
      <!-- Add / edit panel (separate from list) -->
      <section v-if="canEdit && showAddForm" class="pe__panel pe__panel--form">
        <header class="pe__panel-head">
          <div>
            <h2>{{ draftTitle }}</h2>
            <p class="pe__muted">
              {{ activeMeta.label }} · fields match Africa CDC Funding Portfolio.xlsx
            </p>
          </div>
          <button type="button" class="btn" @click="cancelDraft">Cancel</button>
        </header>

        <div class="pe__grid" :class="{ 'pe__grid--extra': isExtramural }">
          <label
            v-for="field in schema"
            :key="field.key"
            class="pe__field"
            :class="{
              'pe__field--wide': field.type === 'textarea',
              'pe__field--primary':
                (usesGrantPickers && (isFunderField(field) || isGrantField(field))) ||
                (usesGeoPickers && (isRegionField(field) || isCountryField(field) || isLocationField(field))) ||
                (usesAfefCategoryPicker && isAfefCategoryField(field)),
            }"
          >
            <span>{{ field.label }}</span>
            <v-autocomplete
              v-if="isFunderField(field)"
              v-model="draftFunderId"
              :items="funderItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="apmFunders.length ? 'Search APM funder…' : 'No APM funders loaded'"
              :disabled="!apmFunders.length"
              class="pe__autocomplete"
            />
            <v-autocomplete
              v-else-if="isGrantField(field)"
              v-model="draftGrantId"
              :items="grantItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="grantsCatalog.length ? 'Search grant…' : 'No grants — add under More → Grants'"
              :disabled="!grantsCatalog.length"
              class="pe__autocomplete"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item
                  v-bind="itemProps"
                  :subtitle="item.raw.funder_name || undefined"
                />
              </template>
            </v-autocomplete>
            <v-autocomplete
              v-else-if="isRegionField(field)"
              v-model="draftRegionId"
              :items="regionItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="geoRegions.length ? 'Search region…' : 'No regions — check More → Geography'"
              :disabled="!geoRegions.length"
              class="pe__autocomplete"
            />
            <v-autocomplete
              v-else-if="isCountryField(field)"
              v-model="draftCountryId"
              :items="countryItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="geoCountries.length ? 'Search country…' : 'No countries loaded'"
              :disabled="!geoCountries.length"
              class="pe__autocomplete"
            />
            <v-autocomplete
              v-else-if="isLocationField(field)"
              v-model="draftLocationId"
              :items="locationItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="geoLocations.length ? 'Search duty station…' : 'No locations loaded'"
              :disabled="!geoLocations.length"
              class="pe__autocomplete"
            />
            <v-autocomplete
              v-else-if="isAfefCategoryField(field)"
              v-model="draftAfefCategory"
              :items="afefCategoryItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details="auto"
              clearable
              auto-select-first
              :placeholder="
                afefCategories.length
                  ? 'Search AfEF category…'
                  : 'No categories — add under More → AfEF categories'
              "
              :disabled="!afefCategories.length"
              class="pe__autocomplete"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item
                  v-bind="itemProps"
                  :subtitle="item.raw.subtitle || undefined"
                />
              </template>
            </v-autocomplete>
            <textarea
              v-else-if="field.type === 'textarea'"
              :value="String(draft[field.key] ?? '')"
              rows="2"
              @input="setDraftField(field.key, $event)"
            />
            <input
              v-else-if="isComputedField(field)"
              :value="pct(Number(draft[field.key] ?? 0))"
              type="text"
              readonly
              class="pe__computed"
            />
            <input
              v-else-if="field.type === 'number'"
              :value="draft[field.key] ?? 0"
              type="number"
              step="any"
              @input="setDraftField(field.key, $event, true)"
            />
            <input
              v-else-if="field.type === 'date'"
              :value="String(draft[field.key] ?? '')"
              type="date"
              @input="setDraftField(field.key, $event)"
            />
            <input
              v-else-if="isDisplayNameField(field)"
              :value="String(draft[field.key] ?? '')"
              type="text"
              readonly
            />
            <input
              v-else
              :value="String(draft[field.key] ?? '')"
              type="text"
              @input="setDraftField(field.key, $event)"
            />
            <small v-if="field.hint">{{ field.hint }}</small>
            <small v-else-if="isDisplayNameField(field)">Filled from dropdown selection</small>
          </label>
        </div>

        <footer class="pe__panel-foot">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="saveDraft">
            {{ saving ? 'Saving…' : editingIndex === null ? 'Add entry' : 'Save changes' }}
          </button>
        </footer>
      </section>

      <!-- Existing entries -->
      <section class="pe__panel pe__panel--list">
        <header class="pe__panel-head">
          <div>
            <h2>Existing entries</h2>
            <p class="pe__muted">{{ rows.length }} row(s) in {{ activeMeta.label }}</p>
          </div>
        </header>

        <div v-if="!rows.length" class="pe__empty">
          No entries yet.
          <template v-if="canEdit"> Use <strong>Add new entry</strong> above to create one.</template>
        </div>

        <div v-else class="pe__list">
          <article v-for="(row, idx) in rows" :key="row.id ?? idx" class="pe__list-item" :class="{ 'is-editing': editingIndex === idx }">
            <div class="pe__list-main">
              <h3>{{ titleForRow(row, idx) }}</h3>
              <p v-if="subtitleForRow(row)" class="pe__muted">{{ subtitleForRow(row) }}</p>
              <p v-if="moneyPreview(row)" class="pe__list-money">{{ moneyPreview(row) }}</p>
            </div>
            <div v-if="canEdit" class="pe__list-actions">
              <button type="button" class="btn" :disabled="saving" @click="startEdit(idx)">Edit</button>
              <button type="button" class="btn btn-danger" :disabled="saving" @click="removeRow(idx)">Remove</button>
            </div>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.pe { width: 100%; max-width: none; margin: 0; padding: 0 0 2.5rem; --pe-radius: 2px; }
.pe__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start;
  margin-bottom: 1rem;
}
.pe__title-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem;
}
.pe__header h1 { margin: 0; font-size: 1.55rem; }
.pe__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 52rem; line-height: 1.45; }
.pe__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.pe__link { color: #0b3b5c; font-weight: 600; text-decoration: none; margin-right: 0.35rem; }
.pe__btn-icon {
  display: inline-flex; align-items: center; gap: 0.4rem;
}
.pe__tabs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.75rem 0 1rem; }
.pe__tab {
  border: 1px solid rgba(11, 59, 92, 0.18); background: #fff; border-radius: var(--pe-radius);
  padding: 0.45rem 0.9rem; cursor: pointer; font-weight: 600; color: #0b3b5c;
  display: inline-flex; align-items: center; gap: 0.4rem;
}
.pe__tab i { font-size: 0.82rem; opacity: 0.9; }
.pe__tab.is-active { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
.pe__tab.is-active i { opacity: 1; }
.pe__banner {
  background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;
  border-radius: var(--pe-radius); padding: 0.7rem 0.9rem; margin: 0 0 0.85rem;
}
.pe__kpi {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.65rem;
  margin-bottom: 1rem;
}
.pe__kpi-card {
  background: #fff; border: 1px solid rgba(0,0,0,0.08); border-radius: var(--pe-radius); padding: 0.85rem 1rem;
}
.pe__kpi-card span { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.pe__kpi-card strong { display: block; margin-top: 0.35rem; font-size: 1.15rem; }
.pe__toolbar {
  display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; justify-content: space-between;
  margin-bottom: 0.85rem;
}
.pe__toolbar-filter { display: grid; gap: 0.3rem; flex: 1; min-width: min(100%, 320px); max-width: 560px; }
.pe__toolbar-label { font-size: 0.85rem; font-weight: 600; }
.pe__division-filter :deep(.v-field) { border-radius: var(--pe-radius) !important; }
.pe__panel {
  background: #fff; border: 1px solid rgba(11, 59, 92, 0.12); border-radius: var(--pe-radius);
  padding: 1rem 1.1rem; margin-bottom: 1rem;
}
.pe__panel--form {
  border-color: rgba(11, 59, 92, 0.28);
  box-shadow: 0 8px 24px rgba(11, 59, 92, 0.06);
}
.pe__panel-head {
  display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start;
  margin-bottom: 0.9rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.pe__panel-head h2 { margin: 0; font-size: 1.1rem; }
.pe__panel-foot { margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem; }
.pe__card {
  background: #fff; border: 1px solid rgba(11, 59, 92, 0.12); border-radius: var(--pe-radius);
  padding: 1rem 1.1rem; margin-bottom: 0.85rem;
}
.pe__card-head { display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.85rem; }
.pe__card-head h2 { margin: 0; font-size: 1.05rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.pe__badge {
  display: inline-flex; align-items: center; background: #e8f1f7; color: #0b3b5c;
  border-radius: var(--pe-radius); padding: 0.15rem 0.45rem; font-size: 0.78rem; font-weight: 700;
}
.pe__metrics {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.55rem;
}
.pe__metrics div { background: #f8fafc; border-radius: var(--pe-radius); padding: 0.55rem 0.65rem; }
.pe__metrics span { display: block; font-size: 0.72rem; color: #64748b; }
.pe__metrics strong { font-size: 0.98rem; }
.pe__grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem 0.9rem;
}
.pe__grid--extra { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
.pe__field { display: grid; gap: 0.3rem; font-size: 0.82rem; font-weight: 600; }
.pe__field--wide, .pe__field--primary { grid-column: 1 / -1; }
@media (min-width: 800px) {
  .pe__field--primary { grid-column: span 1; }
  .pe__grid--extra .pe__field--primary:first-of-type { grid-column: span 1; }
}
.pe__field input, .pe__field textarea, .pe__field select {
  border: 1px solid rgba(0,0,0,0.16); border-radius: var(--pe-radius); padding: 0.5rem 0.6rem;
  font: inherit; font-weight: 400;
}
.pe__field input[readonly] { background: #f8fafc; color: #475569; }
.pe__computed { font-weight: 650 !important; color: #0b3b5c !important; }
.pe__autocomplete { font-weight: 400; }
.pe__autocomplete :deep(.v-field) { border-radius: var(--pe-radius) !important; }
.pe__field small { color: #64748b; font-weight: 400; }
.pe__muted { color: #64748b; margin: 0.25rem 0 0; }
.pe__empty {
  background: #f8fafc; border: 1px dashed rgba(0,0,0,0.15); border-radius: var(--pe-radius);
  padding: 1.25rem; color: #64748b;
}
.pe__list { display: grid; gap: 0.55rem; }
.pe__list-item {
  display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start;
  border: 1px solid rgba(0,0,0,0.08); border-radius: var(--pe-radius); padding: 0.85rem 1rem;
  background: #f8fafc;
}
.pe__list-item.is-editing {
  border-color: #0b3b5c; background: #eef5fa;
}
.pe__list-main h3 { margin: 0; font-size: 0.98rem; }
.pe__list-money { margin: 0.35rem 0 0; font-weight: 700; color: #0b3b5c; }
.pe__list-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
.pe__fc-table-wrap { margin-top: 0.85rem; overflow-x: auto; }
.pe__fc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.pe__fc-table th, .pe__fc-table td {
  text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.pe__ok { color: #027a48; }
.pe__error { color: #b42318; }
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: var(--pe-radius);
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
.btn-danger { color: #b42318; }
@media (max-width: 720px) {
  .pe__header, .pe__list-item { flex-direction: column; }
}
</style>
