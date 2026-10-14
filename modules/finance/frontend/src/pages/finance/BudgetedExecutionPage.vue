<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import SapTrendCharts from '@/components/finance/SapTrendCharts.vue'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import { downloadClientExcelSheets } from '@/lib/clientTableExport'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type DivisionRow = {
  division_id: number
  division_name: string
  division_short_name: string
  approved_budget: number
  budget_balance: number
  commitment: number
  actual_expenditure: number
  execution_rate: number | null
  fund_center_count: number
  fund_centers?: Array<Record<string, unknown>>
}

type BudgetCodeRow = {
  fund_center: string
  budget_code?: string | null
  division_id: number
  division_name: string
  division_short_name: string
  approved_budget: number
  commitment: number
  actual_expenditure: number
  budget_balance: number
  execution_rate: number | null
}

type ExpenseRow = {
  fund_center: string
  budget_code?: string | null
  gl_account: string
  gl_description?: string | null
  commitment: number
  actual_expenditure: number
}

type TrendPoint = {
  period_ym?: string
  label: string
  has_data?: boolean
  approved_budget: number
  actual_expenditure: number
  commitment: number
  execution_rate: number | null
  quarter?: number
}

type Payload = {
  budget_year: number
  financial_year?: { fy_year: number; start_month: number; start_month_name: string; label: string }
  scope: 'all' | 'directorate' | 'division' | 'none'
  can_view_all: boolean
  show_org_charts?: boolean
  show_directorate_charts?: boolean
  show_budget_code_charts?: boolean
  filter_division_id: number | null
  filter_options: Array<{ id: number; name: string; short_name: string }>
  kpis: {
    approved_budget: number
    used_budget: number
    burn_rate: number | null
    commitment: number
    actual_expenditure: number
    budget_balance: number
    execution_rate: number | null
    fund_center_count: number
    division_count: number
  }
  divisions: DivisionRow[]
  budget_codes?: BudgetCodeRow[]
  trends?: { monthly: TrendPoint[]; quarterly: TrendPoint[] }
  data_as_at?: string | null
  last_updated_at?: string | null
  sap_import?: {
    id?: number | null
    original_filename?: string | null
    period_ym?: string | null
    created_at?: string | null
    updated_at?: string | null
  } | null
}

type DrillState = {
  open: boolean
  title: string
  mode: 'centers' | 'expenses'
  rows: Array<Record<string, unknown>>
  loading?: boolean
}

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<Payload | null>(null)
const filterDivisionId = ref<number | ''>('')
const filterText = ref('')
const expanded = ref<Record<number, boolean>>({})
const drill = ref<DrillState>({ open: false, title: '', mode: 'centers', rows: [] })

const kpis = computed(() => data.value?.kpis || null)
const divisions = computed(() => data.value?.divisions || [])
const budgetCodes = computed(() => data.value?.budget_codes || [])

const showDivisionCharts = computed(
  () => !!(data.value?.show_org_charts || data.value?.show_directorate_charts),
)
const showBudgetCodeCharts = computed(() => !!data.value?.show_budget_code_charts)
const trendMonthly = computed(() => data.value?.trends?.monthly || [])
const trendQuarterly = computed(() => data.value?.trends?.quarterly || [])
const fyTrendLabel = computed(() => data.value?.financial_year?.label || (data.value ? `FY ${data.value.budget_year}` : ''))

const dataAsAtLabel = computed(() => formatDisplayDate(data.value?.data_as_at))
const lastUpdatedLabel = computed(() => formatDisplayDateTime(data.value?.last_updated_at || data.value?.sap_import?.created_at))

const scopeLabel = computed(() => {
  if (!data.value) return ''
  if (data.value.can_view_all) return 'All divisions'
  if (data.value.scope === 'directorate') return 'Your directorate'
  if (data.value.scope === 'division') return 'Your division'
  return 'No division mapped'
})

const divisionSelectItems = computed(() => {
  const opts = data.value?.filter_options || []
  return [
    { title: 'All in scope', value: '' as number | '' },
    ...opts.map((o) => ({
      title: o.short_name ? `${o.short_name} — ${o.name}` : o.name,
      value: o.id as number | '',
    })),
  ]
})

const filteredDivisions = computed(() => {
  const q = filterText.value.trim().toLowerCase()
  if (!q) return divisions.value
  return divisions.value.filter(
    (d) =>
      d.division_name.toLowerCase().includes(q) ||
      d.division_short_name.toLowerCase().includes(q),
  )
})

const filteredBudgetCodes = computed(() => {
  const q = filterText.value.trim().toLowerCase()
  if (!q) return budgetCodes.value
  return budgetCodes.value.filter(
    (c) =>
      c.fund_center.toLowerCase().includes(q) ||
      (c.budget_code || '').toLowerCase().includes(q) ||
      c.division_name.toLowerCase().includes(q) ||
      c.division_short_name.toLowerCase().includes(q),
  )
})

const pieSeries = computed(() => [
  {
    name: 'Used',
    data: filteredDivisions.value
      .map((d) => ({
        name: d.division_name,
        y: Number(((d.commitment || 0) + (d.actual_expenditure || 0)).toFixed(2)),
      }))
      .filter((p) => p.y > 0)
      .sort((a, b) => b.y - a.y)
      .slice(0, 12),
  },
])

const usageCategories = computed(() =>
  filteredDivisions.value
    .slice()
    .sort((a, b) => b.approved_budget - a.approved_budget)
    .slice(0, 10)
    .map((d) => d.division_short_name || d.division_name),
)

const usageSeries = computed(() => {
  const top = filteredDivisions.value
    .slice()
    .sort((a, b) => b.approved_budget - a.approved_budget)
    .slice(0, 10)
  return [
    {
      name: 'Approved',
      data: top.map((d) => Number(d.approved_budget.toFixed(0))),
      color: '#119a48',
    },
    {
      name: 'Used',
      data: top.map((d) =>
        Number(((d.commitment || 0) + (d.actual_expenditure || 0)).toFixed(0)),
      ),
      color: '#c3a366',
    },
  ]
})

const codeCategories = computed(() =>
  filteredBudgetCodes.value
    .slice()
    .sort((a, b) => b.approved_budget - a.approved_budget)
    .slice(0, 12)
    .map((c) => c.fund_center),
)

const codeSeries = computed(() => {
  const top = filteredBudgetCodes.value
    .slice()
    .sort((a, b) => b.approved_budget - a.approved_budget)
    .slice(0, 12)
  return [
    {
      name: 'Approved',
      data: top.map((c) => Number(c.approved_budget.toFixed(0))),
      color: '#119a48',
    },
    {
      name: 'Used',
      data: top.map((c) =>
        Number(((c.commitment || 0) + (c.actual_expenditure || 0)).toFixed(0)),
      ),
      color: '#c3a366',
    },
  ]
})

const codePieSeries = computed(() => [
  {
    name: 'Used',
    data: filteredBudgetCodes.value
      .map((c) => ({
        name: c.fund_center,
        y: Number(((c.commitment || 0) + (c.actual_expenditure || 0)).toFixed(2)),
      }))
      .filter((p) => p.y > 0)
      .sort((a, b) => b.y - a.y)
      .slice(0, 12),
  },
])

function usd(n: number | null | undefined): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(Number(n || 0))
}

function compactUsd(n: number | null | undefined): string {
  const v = Number(n || 0)
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return `${(v / 1_000_000).toFixed(2)}M`
  if (abs >= 1_000) return `${(v / 1_000).toFixed(2)}K`
  return v.toFixed(0)
}

function pct(n: number | null | undefined): string {
  if (n == null || Number.isNaN(Number(n))) return '—'
  const v = Number(n)
  const ratio = Math.abs(v) > 1 ? v / 100 : v
  return `${(ratio * 100).toFixed(1)}%`
}

function pctNumber(n: number | null | undefined): number | string {
  if (n == null || Number.isNaN(Number(n))) return ''
  const v = Number(n)
  const ratio = Math.abs(v) > 1 ? v / 100 : v
  return Number((ratio * 100).toFixed(2))
}

function formatDisplayDate(value: string | null | undefined): string {
  if (!value) return ''
  const d = new Date(value.includes('T') || value.includes(' ') ? value : `${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDisplayDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function exportExcel() {
  const asAt = dataAsAtLabel.value || 'current'
  const stamp = new Date().toISOString().slice(0, 10)
  const divisionRows = filteredDivisions.value.map((d) => [
    d.division_short_name,
    d.division_name,
    d.fund_center_count,
    Number(d.approved_budget || 0),
    Number(d.commitment || 0),
    Number(d.actual_expenditure || 0),
    Number(d.budget_balance || 0),
    pctNumber(d.execution_rate),
  ])
  const fundCenterRows: Array<Array<string | number>> = []
  for (const d of filteredDivisions.value) {
    for (const fc of d.fund_centers || []) {
      fundCenterRows.push([
        d.division_short_name,
        d.division_name,
        String(fc.fund_center || ''),
        String(fc.budget_code || ''),
        Number(fc.approved_budget || 0),
        Number(fc.commitment || 0),
        Number(fc.actual_expenditure || 0),
        Number(fc.budget_balance || 0),
        pctNumber(fc.execution_rate == null ? null : Number(fc.execution_rate)),
      ])
    }
  }
  type Sheet = { name: string; headers: string[]; rows: Array<Array<string | number>> }
  const sheets: Sheet[] = [
    {
      name: 'Divisions',
      headers: [
        'Division short',
        'Division',
        'Fund centers',
        'Released',
        'Commitment',
        'Actual',
        'Balance',
        'Exec %',
      ],
      rows: divisionRows,
    },
    {
      name: 'Fund centers',
      headers: [
        'Division short',
        'Division',
        'Fund center',
        'Budget code',
        'Released',
        'Commitment',
        'Actual',
        'Balance',
        'Exec %',
      ],
      rows: fundCenterRows,
    },
  ]
  if (filteredBudgetCodes.value.length) {
    sheets.push({
      name: 'Budget codes',
      headers: [
        'Fund center',
        'Budget code',
        'Division short',
        'Division',
        'Released',
        'Commitment',
        'Actual',
        'Balance',
        'Exec %',
      ],
      rows: filteredBudgetCodes.value.map((fc) => [
        fc.fund_center,
        fc.budget_code || '',
        fc.division_short_name,
        fc.division_name,
        Number(fc.approved_budget || 0),
        Number(fc.commitment || 0),
        Number(fc.actual_expenditure || 0),
        Number(fc.budget_balance || 0),
        pctNumber(fc.execution_rate),
      ]),
    })
  }
  downloadClientExcelSheets(
    `budget-execution-as-at-${asAt.replace(/\s+/g, '-')}-${stamp}`,
    sheets,
  )
}

function toggleExpand(id: number) {
  expanded.value = { ...expanded.value, [id]: !expanded.value[id] }
}

function openDrill(title: string, rows: Array<Record<string, unknown>>, mode: 'centers' | 'expenses' = 'centers') {
  drill.value = { open: true, title, mode, rows, loading: false }
}

function openDivisionDrill(div: DivisionRow) {
  openDrill(`${div.division_short_name} — ${div.division_name}`, div.fund_centers || [], 'centers')
  expanded.value = { ...expanded.value, [div.division_id]: true }
}

async function openFundCenterExpenses(fc: Record<string, unknown> | BudgetCodeRow) {
  const code = String(fc.fund_center || '').trim()
  if (!code) return
  drill.value = {
    open: true,
    title: `Expenses — ${code}`,
    mode: 'expenses',
    rows: [],
    loading: true,
  }
  try {
    const { data: res } = await api.get(`/api/v1/sap/fund-centers/${encodeURIComponent(code)}/expenses`)
    const expenses = (res.data?.expenses || []) as ExpenseRow[]
    drill.value = {
      open: true,
      title: `Expenses — ${code}`,
      mode: 'expenses',
      rows: expenses as unknown as Array<Record<string, unknown>>,
      loading: false,
    }
  } catch (e) {
    drill.value = {
      open: true,
      title: `Expenses — ${code}`,
      mode: 'expenses',
      rows: [],
      loading: false,
    }
    error.value = apiErrorMessage(e, 'Failed to load fund-center expenses.')
  }
}

function onPieClick(payload: { name: string }) {
  const match = divisions.value.find((d) => d.division_name === payload.name)
  if (match) openDivisionDrill(match)
}

function onUsageClick(payload: { name: string; category?: string }) {
  const key = payload.category || payload.name
  const match = divisions.value.find(
    (d) => d.division_short_name === key || d.division_name === key,
  )
  if (match) openDivisionDrill(match)
}

function onBudgetCodeClick(payload: { name: string; category?: string }) {
  const key = payload.category || payload.name
  const match = budgetCodes.value.find((c) => c.fund_center === key)
  if (!match) return
  void openFundCenterExpenses(match)
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, string | number> = {}
    if (filterDivisionId.value !== '') {
      params.division_id = Number(filterDivisionId.value)
    }
    const { data: res } = await api.get('/api/v1/budget-execution', { params })
    data.value = res.data as Payload
    if (data.value.filter_division_id) {
      filterDivisionId.value = data.value.filter_division_id
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load budgeted execution.')
  } finally {
    loading.value = false
  }
}

function applyFilter() {
  void load()
}

const narrowFilters = ref(false)
let narrowMq: MediaQueryList | null = null
function syncNarrowFilters() {
  narrowFilters.value = !!narrowMq?.matches
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    narrowMq = window.matchMedia('(max-width: 720px)')
    syncNarrowFilters()
    narrowMq.addEventListener('change', syncNarrowFilters)
  }
  void load()
})

onUnmounted(() => {
  narrowMq?.removeEventListener('change', syncNarrowFilters)
})
</script>

<template>
  <div class="be">
    <header class="be__header">
      <div>
        <div class="be__title-row">
          <h1>Budgeted execution</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="be__sub">
          Intramural SAP budget execution
          <span v-if="data" class="be__chip">{{ fyTrendLabel }}</span>
          <span v-if="data?.financial_year" class="be__chip be__chip--muted">
            starts {{ data.financial_year.start_month_name }}
          </span>
          <span v-if="data" class="be__chip be__chip--muted">{{ scopeLabel }}</span>
        </p>
        <p v-if="dataAsAtLabel || lastUpdatedLabel" class="be__asat">
          <template v-if="dataAsAtLabel">
            Budget execution as at <strong>{{ dataAsAtLabel }}</strong>
          </template>
          <template v-if="dataAsAtLabel && lastUpdatedLabel"> · </template>
          <template v-if="lastUpdatedLabel">
            Last updated <strong>{{ lastUpdatedLabel }}</strong>
          </template>
          <template v-if="data?.sap_import?.original_filename">
            <span class="be__asat-file">({{ data.sap_import.original_filename }})</span>
          </template>
        </p>
      </div>
      <button
        v-if="kpis"
        type="button"
        class="be__export"
        :disabled="!filteredDivisions.length && !filteredBudgetCodes.length"
        @click="exportExcel"
      >
        <i class="fa-solid fa-file-excel" aria-hidden="true" />
        Export Excel
      </button>
    </header>

    <div class="be__filters" :class="{ 'be__filters--narrow': narrowFilters }">
      <v-text-field
        v-model="filterText"
        :density="narrowFilters ? 'compact' : 'comfortable'"
        variant="outlined"
        hide-details
        clearable
        :placeholder="showBudgetCodeCharts ? 'Search budget code or division…' : 'Search division…'"
        prepend-inner-icon="mdi-magnify"
        class="be__search"
      />
      <v-select
        v-if="data && (data.can_view_all || data.scope === 'directorate') && divisionSelectItems.length > 2"
        v-model="filterDivisionId"
        :items="divisionSelectItems"
        :density="narrowFilters ? 'compact' : 'comfortable'"
        variant="outlined"
        hide-details
        :label="narrowFilters ? undefined : 'Division'"
        :placeholder="narrowFilters ? 'Division' : undefined"
        class="be__select"
      />
      <button type="button" class="be__apply" @click="applyFilter">Apply</button>
    </div>

    <FinSkeleton v-if="loading" variant="dashboard" :rows="3" />
    <div v-else-if="error" class="be__error">{{ error }}</div>

    <template v-else-if="kpis">
      <section class="be__kpis">
        <article class="be__kpi be__kpi--green">
          <div class="be__kpi-body">
            <span>Approved budget</span>
            <strong>{{ compactUsd(kpis.approved_budget) }}</strong>
            <small>{{ usd(kpis.approved_budget) }}</small>
          </div>
          <span class="be__kpi-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping" /></span>
        </article>
        <article class="be__kpi be__kpi--gold">
          <div class="be__kpi-body">
            <span>Used budget</span>
            <strong>{{ compactUsd(kpis.used_budget) }}</strong>
            <small>{{ usd(kpis.used_budget) }}</small>
          </div>
          <span class="be__kpi-icon" aria-hidden="true"><i class="fa-solid fa-wallet" /></span>
        </article>
        <article class="be__kpi be__kpi--maroon">
          <div class="be__kpi-body">
            <span>Execution rate</span>
            <strong>{{ pct(kpis.execution_rate) }}</strong>
            <small>{{ kpis.division_count }} division(s) · {{ kpis.fund_center_count }} codes</small>
          </div>
          <span class="be__kpi-icon" aria-hidden="true"><i class="fa-solid fa-chart-column" /></span>
        </article>
      </section>

      <p class="be__hint">
        <template v-if="showDivisionCharts">
          Click a chart segment or “Show budget codes” to preview fund-center expenses.
        </template>
        <template v-else>
          Charts show your budget codes. Expand a division card for the full code list.
        </template>
      </p>

      <section v-if="showDivisionCharts" class="be__charts">
        <div class="be__card">
          <PortalHighchart
            :title="data?.can_view_all ? 'Proportion of used by division' : 'Proportion of used in your directorate'"
            type="pie"
            :series="pieSeries"
            :height="340"
            :colors="['#c3a366', '#911c39', '#119a48', '#385cad', '#0b3b5c']"
            @point-click="onPieClick"
          />
        </div>
        <div class="be__card">
          <PortalHighchart
            :title="data?.can_view_all ? 'Division usage' : 'Directorate division usage'"
            type="column"
            :categories="usageCategories"
            :series="usageSeries"
            y-axis-title="Amount (USD)"
            :height="340"
            @point-click="onUsageClick"
          />
        </div>
      </section>

      <section v-else-if="showBudgetCodeCharts" class="be__charts">
        <div class="be__card">
          <PortalHighchart
            title="Proportion of used by budget code"
            type="pie"
            :series="codePieSeries"
            :height="340"
            :colors="['#c3a366', '#911c39', '#119a48', '#385cad', '#0b3b5c']"
            @point-click="onBudgetCodeClick"
          />
        </div>
        <div class="be__card">
          <PortalHighchart
            title="Budget code usage"
            type="column"
            :categories="codeCategories"
            :series="codeSeries"
            y-axis-title="Amount (USD)"
            :height="340"
            @point-click="onBudgetCodeClick"
          />
        </div>
      </section>

      <section
        v-if="!data?.can_view_all && filteredBudgetCodes.length"
        class="be__card be__codes-table"
      >
        <div class="be__table-head">
          <h2>Budget codes in your scope</h2>
          <p class="be__muted">{{ filteredBudgetCodes.length }} code(s)</p>
        </div>
        <div class="be__table-wrap">
          <table class="be__table">
            <thead>
              <tr>
                <th>Fund center</th>
                <th>Budget code</th>
                <th>Division</th>
                <th>Released</th>
                <th>Commitment</th>
                <th>Actual</th>
                <th>Balance</th>
                <th>Exec %</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="fc in filteredBudgetCodes"
                :key="fc.fund_center + '-' + fc.division_id"
                class="be__row-link"
                @click="openFundCenterExpenses(fc)"
              >
                <td><code>{{ fc.fund_center }}</code></td>
                <td><code class="be__fund">{{ fc.budget_code || '—' }}</code></td>
                <td>{{ fc.division_short_name }}</td>
                <td>{{ usd(fc.approved_budget) }}</td>
                <td>{{ usd(fc.commitment) }}</td>
                <td>{{ usd(fc.actual_expenditure) }}</td>
                <td>{{ usd(fc.budget_balance) }}</td>
                <td>{{ pct(fc.execution_rate) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="be__list">
        <article
          v-for="div in filteredDivisions"
          :key="div.division_id"
          class="be__div-card"
        >
          <header class="be__div-head">
            <div>
              <h2>
                <span class="be__badge">{{ div.division_short_name }}</span>
                {{ div.division_name }}
              </h2>
              <p class="be__muted">{{ div.fund_center_count }} fund center(s)</p>
            </div>
            <button type="button" class="be__codes" @click="toggleExpand(div.division_id)">
              {{ expanded[div.division_id] ? 'Hide budget codes' : 'Show budget codes' }}
            </button>
          </header>
          <div class="be__metrics" @click="openDivisionDrill(div)">
            <div><span>Main / released</span><strong>{{ usd(div.approved_budget) }}</strong></div>
            <div><span>Commitment</span><strong>{{ usd(div.commitment) }}</strong></div>
            <div><span>Actual expenditure</span><strong>{{ usd(div.actual_expenditure) }}</strong></div>
            <div><span>Released balance</span><strong>{{ usd(div.budget_balance) }}</strong></div>
            <div><span>Execution</span><strong>{{ pct(div.execution_rate) }}</strong></div>
          </div>
          <div v-if="expanded[div.division_id]" class="be__table-wrap">
            <table class="be__table">
              <thead>
                <tr>
                  <th>Fund center</th>
                  <th>Budget code</th>
                  <th>Released</th>
                  <th>Commitment</th>
                  <th>Actual</th>
                  <th>Balance</th>
                  <th>Exec %</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="fc in div.fund_centers || []"
                  :key="String(fc.fund_center)"
                  class="be__row-link"
                  @click="openFundCenterExpenses(fc)"
                >
                  <td><code>{{ fc.fund_center }}</code></td>
                  <td><code class="be__fund">{{ String(fc.budget_code || '—') }}</code></td>
                  <td>{{ usd(Number(fc.approved_budget || 0)) }}</td>
                  <td>{{ usd(Number(fc.commitment || 0)) }}</td>
                  <td>{{ usd(Number(fc.actual_expenditure || 0)) }}</td>
                  <td>{{ usd(Number(fc.budget_balance || 0)) }}</td>
                  <td>{{ pct(fc.execution_rate == null ? null : Number(fc.execution_rate)) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
        <p v-if="!filteredDivisions.length" class="be__muted">
          No SAP fund centers mapped for your scope yet.
        </p>
      </section>

      <SapTrendCharts
        :monthly="trendMonthly"
        :quarterly="trendQuarterly"
        :fy-label="fyTrendLabel"
      />
    </template>

    <v-dialog v-model="drill.open" max-width="960" scrollable>
      <v-card>
        <v-card-title class="be__dialog-title">
          <span>{{ drill.title }}</span>
          <button type="button" class="be__dialog-close" aria-label="Close" @click="drill.open = false">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </button>
        </v-card-title>
        <v-card-text>
          <p v-if="drill.loading" class="be__muted">Loading expenses…</p>
          <div v-else-if="!drill.rows.length" class="be__muted">
            {{ drill.mode === 'expenses' ? 'No expenses for this fund center.' : 'No fund centers.' }}
          </div>
          <div v-else class="be__table-wrap">
            <table v-if="drill.mode === 'expenses'" class="be__table">
              <thead>
                <tr>
                  <th>Budget code</th>
                  <th>GL account</th>
                  <th>Description</th>
                  <th>Commitment</th>
                  <th>Actual expenditure</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(ex, idx) in drill.rows" :key="idx">
                  <td><code class="be__fund">{{ ex.budget_code || '—' }}</code></td>
                  <td><code>{{ ex.gl_account }}</code></td>
                  <td>{{ ex.gl_description || '—' }}</td>
                  <td>{{ usd(Number(ex.commitment || 0)) }}</td>
                  <td>{{ usd(Number(ex.actual_expenditure || 0)) }}</td>
                </tr>
              </tbody>
            </table>
            <table v-else class="be__table">
              <thead>
                <tr>
                  <th>Fund center</th>
                  <th>Budget code</th>
                  <th>Released</th>
                  <th>Commitment</th>
                  <th>Actual</th>
                  <th>Balance</th>
                  <th>Exec %</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(fc, idx) in drill.rows"
                  :key="idx"
                  class="be__row-link"
                  @click="openFundCenterExpenses(fc)"
                >
                  <td><code>{{ fc.fund_center }}</code></td>
                  <td><code class="be__fund">{{ fc.budget_code || '—' }}</code></td>
                  <td>{{ usd(Number(fc.approved_budget || 0)) }}</td>
                  <td>{{ usd(Number(fc.commitment || 0)) }}</td>
                  <td>{{ usd(Number(fc.actual_expenditure || 0)) }}</td>
                  <td>{{ usd(Number(fc.budget_balance || 0)) }}</td>
                  <td>{{ pct(fc.execution_rate == null ? null : Number(fc.execution_rate)) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.be { width: 100%; padding-bottom: 2rem; --be-radius: 2px; }
.be__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;
}
.be__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem; }
.be__header h1 { margin: 0; font-size: 1.55rem; font-weight: 700; color: #0b3b5c; }
.be__sub { margin: 0.35rem 0 0; color: #64748b; }
.be__asat {
  margin: 0.45rem 0 0; color: #334155; font-size: 0.9rem;
}
.be__asat strong { color: #0b3b5c; font-weight: 700; }
.be__asat-file { color: #64748b; margin-left: 0.25rem; font-size: 0.82rem; }
.be__export {
  flex: 0 0 auto; display: inline-flex; align-items: center; gap: 0.4rem;
  border: 1px solid rgba(15, 23, 42, 0.15); background: #fff; color: #0b3b5c;
  font-weight: 700; border-radius: var(--be-radius); padding: 0.5rem 0.9rem; cursor: pointer;
}
.be__export:disabled { opacity: 0.5; cursor: not-allowed; }
.be__export i { color: #217346; }
.be__chip {
  display: inline-block; margin-left: 0.35rem; padding: 0.12rem 0.5rem; border-radius: var(--be-radius);
  background: #e8f1f7; color: #0b3b5c; font-size: 0.78rem; font-weight: 700;
}
.be__chip--muted { background: #f1f5f9; color: #475569; }
.be__filters {
  display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; margin-bottom: 1rem;
}
.be__search { flex: 1 1 220px; max-width: 420px; }
.be__select { flex: 0 1 280px; }
.be__search :deep(.v-field),
.be__select :deep(.v-field) { border-radius: var(--be-radius) !important; }
.be__apply {
  border: none; background: #119a48; color: #fff; font-weight: 700; border-radius: var(--be-radius);
  padding: 0.55rem 1.1rem; cursor: pointer; min-height: 40px;
}
.be__filters--narrow {
  gap: 0.45rem;
  margin-bottom: 0.75rem;
}
.be__filters--narrow .be__search :deep(.v-field),
.be__filters--narrow .be__select :deep(.v-field) {
  --v-input-control-height: 36px;
  min-height: 36px !important;
}
.be__filters--narrow .be__search :deep(.v-field__input),
.be__filters--narrow .be__select :deep(.v-field__input) {
  min-height: 36px !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.875rem;
}
.be__filters--narrow .be__search :deep(.v-field--variant-outlined),
.be__filters--narrow .be__select :deep(.v-field--variant-outlined) {
  --v-field-padding-top: 2px;
  --v-field-padding-bottom: 2px;
}
.be__filters--narrow .be__search :deep(.v-icon),
.be__filters--narrow .be__select :deep(.v-icon) {
  font-size: 1.05rem;
  opacity: 0.75;
}
.be__filters--narrow .be__apply {
  min-height: 36px;
  padding: 0.3rem 0.85rem;
  font-size: 0.875rem;
}
.be__muted { color: #64748b; margin: 0; }
.be__error { color: #b42318; }
.be__hint { color: #64748b; font-size: 0.85rem; margin: 0 0 0.85rem; }
.be__kpis {
  display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.85rem;
  margin-bottom: 1rem;
}
.be__kpi {
  display: flex; justify-content: space-between; align-items: center; gap: 0.85rem;
  border-radius: 2px; padding: 1.1rem 1.2rem; color: #1e293b; min-height: 108px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
  position: relative; overflow: hidden;
}
.be__kpi-body { position: relative; z-index: 1; min-width: 0; }
.be__kpi-body > span {
  display: block; font-size: 0.78rem; color: #64748b; font-weight: 500;
}
.be__kpi-body strong {
  display: block; margin-top: 0.4rem; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; color: #1e293b;
}
.be__kpi-body small {
  display: block; margin-top: 0.35rem; font-size: 0.75rem; color: #94a3b8; white-space: nowrap;
  overflow: hidden; text-overflow: ellipsis;
}
.be__kpi-icon {
  position: relative; z-index: 1; flex: 0 0 auto;
  width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center;
  border-radius: 2px; background: #eef2f7; color: #475569; border: none;
}
.be__kpi-icon i { font-size: 1.15rem; }
.be__kpi--green .be__kpi-icon { background: #e3f6eb; color: #119a48; }
.be__kpi--gold .be__kpi-icon { background: #f7efd9; color: #b8924a; }
.be__kpi--maroon .be__kpi-icon { background: #f8e4ea; color: #911c39; }
.be__charts {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;
  margin-bottom: 1.25rem;
}
.be__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.1); border-radius: var(--be-radius); padding: 0.75rem 0.9rem;
  min-width: 0;
}
.be__codes-table { margin-bottom: 1.25rem; padding: 1rem 1.1rem; }
.be__table-head {
  display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; margin-bottom: 0.65rem;
}
.be__table-head h2 { margin: 0; font-size: 1.05rem; color: #0b3b5c; }
.be__list { display: flex; flex-direction: column; gap: 0.85rem; }
.be__div-card {
  background: #fff; border: 1px solid rgba(15,23,42,0.1); border-radius: var(--be-radius);
  padding: 1rem 1.1rem; box-shadow: 0 2px 8px rgba(15,23,42,0.04);
}
.be__div-head {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 0.75rem;
}
.be__div-head h2 {
  margin: 0; font-size: 1.05rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}
.be__badge {
  display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: var(--be-radius);
  background: #dbeafe; color: #1e3a8a; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.02em;
}
.be__codes {
  border: 1px solid rgba(15,23,42,0.15); background: #fff; border-radius: var(--be-radius);
  padding: 0.4rem 0.75rem; cursor: pointer; font-weight: 600; color: #0f172a;
}
.be__metrics {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.55rem;
  cursor: pointer;
}
.be__metrics > div {
  background: #f8fafc; border: 1px solid rgba(15,23,42,0.06); border-radius: var(--be-radius); padding: 0.65rem 0.7rem;
}
.be__metrics span {
  display: block; font-size: 0.72rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em;
}
.be__metrics strong { display: block; margin-top: 0.25rem; color: #0f172a; font-size: 1rem; }
.be__table-wrap { overflow: auto; max-height: 55vh; margin-top: 0.85rem; }
.be__table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.be__table th, .be__table td {
  text-align: left; padding: 0.5rem 0.55rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.be__table th { background: #0b3b5c; color: #fff; position: sticky; top: 0; }
.be__row-link { cursor: pointer; }
.be__row-link:hover { background: #f1f5f9; }
.be__fund {
  display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
  background: #e8f6ee; color: #0f5132; font-size: 0.78rem; font-weight: 700;
}
.be__dialog-title {
  display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
  font-weight: 700; color: #0b3b5c;
}
.be__dialog-close {
  border: none; background: transparent; cursor: pointer; color: #64748b; font-size: 1.1rem;
}
@media (max-width: 900px) {
  .be__charts { grid-template-columns: 1fr; }
  .be__kpis { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .be__header { flex-direction: column; }
  .be__export { width: 100%; justify-content: center; }
  .be__filters { flex-direction: column; align-items: stretch; }
  .be__search, .be__select { max-width: none; width: 100%; }
  .be__apply { width: 100%; }
}
</style>
