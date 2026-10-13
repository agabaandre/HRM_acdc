<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import { FINANCE_DASHBOARD_PERMS } from '@/lib/financePermissions'
import { useAuthStore } from '@/stores/auth'

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

type Summary = {
  budget_year: number
  intramural: {
    approved_budget: number
    budget_balance: number
    commitment?: number
    actual_expenditure?: number
    fund_center_count: number
    execution_rate: number | null
    by_division: DivisionRow[]
    by_fund_center: Array<Record<string, unknown>>
  }
  sections: Record<string, { row_count: number; rows: Array<{ data: Record<string, unknown> }> }>
  kpis?: {
    intramural: Record<string, number | null>
    extramural: Record<string, number | null>
    indirect_cost: Record<string, number | null>
    administrative_cost: Record<string, number | null>
    investments: Record<string, number | null>
    afef: { balance: number; by_category: Record<string, number>; row_count: number }
  }
}

type DrillColumn = { key: string; label: string; format?: 'usd' | 'pct' | 'text' | 'number' }
type DrillState = {
  open: boolean
  title: string
  columns: DrillColumn[]
  rows: Array<Record<string, unknown>>
}

const auth = useAuthStore()
const loading = ref(true)
const error = ref<string | null>(null)
const summary = ref<Summary | null>(null)
const drill = ref<DrillState>({ open: false, title: '', columns: [], rows: [] })

const canAccess = computed(
  () => auth.isAdmin || auth.hasAnyPermission([...FINANCE_DASHBOARD_PERMS]),
)

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
  if (abs >= 1_000) return `${(v / 1_000).toFixed(1)}K`
  return usd(v)
}

function pct(n: number | null | undefined): string {
  if (n == null || Number.isNaN(Number(n))) return '—'
  const v = Number(n)
  const ratio = Math.abs(v) > 1 ? v / 100 : v
  return `${(ratio * 100).toFixed(1)}%`
}

function cell(row: Record<string, unknown>, col: DrillColumn): string {
  const v = row[col.key]
  if (col.format === 'usd') return usd(Number(v || 0))
  if (col.format === 'pct') return pct(v == null ? null : Number(v))
  if (col.format === 'number') return String(v ?? '—')
  return String(v ?? '—')
}

const kpis = computed(() => summary.value?.kpis || null)

const divisionsWithBudget = computed(() =>
  (summary.value?.intramural.by_division || []).filter(
    (d) => d.fund_center_count > 0 && d.division_id > 0,
  ),
)

const executionCategories = computed(() =>
  divisionsWithBudget.value
    .slice()
    .sort((a, b) => (b.execution_rate ?? 0) - (a.execution_rate ?? 0))
    .slice(0, 12)
    .map((d) => d.division_short_name || d.division_name),
)

const executionSeries = computed(() => {
  const top = divisionsWithBudget.value
    .slice()
    .sort((a, b) => (b.execution_rate ?? 0) - (a.execution_rate ?? 0))
    .slice(0, 12)
  return [
    {
      name: 'Execution %',
      data: top.map((d) => Number((((d.execution_rate ?? 0) * 100)).toFixed(1))),
    },
  ]
})

const mixSeries = computed(() => {
  const k = kpis.value
  if (!k) return []
  return [
    {
      name: 'Portfolio mix',
      data: [
        { name: 'Intramural', y: Number(k.intramural.approved_budget || 0) },
        { name: 'Extramural', y: Number(k.extramural.approved_budget || 0) },
        { name: 'Indirect', y: Number(k.indirect_cost.earned || 0) },
        { name: 'Admin', y: Number(k.administrative_cost.amount || 0) },
        { name: 'Investments', y: Number(k.investments.bank_balance_usd || 0) },
        { name: 'AfEF', y: Math.abs(Number(k.afef.balance || 0)) },
      ].filter((p) => p.y > 0),
    },
  ]
})

const extramuralByFunder = computed(() => {
  const map = new Map<string, { approved: number; actual: number }>()
  for (const row of summary.value?.sections?.extramural?.rows || []) {
    const d = row.data || {}
    const name = String(d.funder || 'Unassigned').trim() || 'Unassigned'
    const cur = map.get(name) || { approved: 0, actual: 0 }
    cur.approved += Number(d.approved_budget || 0)
    cur.actual += Number(d.actual_expenditure || 0)
    map.set(name, cur)
  }
  return [...map.entries()]
    .map(([name, v]) => ({ name, ...v }))
    .sort((a, b) => b.approved - a.approved)
})

const funderCategories = computed(() => extramuralByFunder.value.slice(0, 10).map((r) => r.name))
const funderSeries = computed(() => [
  {
    name: 'Approved budget',
    data: extramuralByFunder.value.slice(0, 10).map((r) => Number(r.approved.toFixed(0))),
  },
])

const investByRegion = computed(() => {
  const map = new Map<string, number>()
  for (const row of summary.value?.sections?.investments?.rows || []) {
    const d = row.data || {}
    const region = String(d.region || 'Unspecified').trim() || 'Unspecified'
    map.set(region, (map.get(region) || 0) + Number(d.bank_balance_usd || 0))
  }
  return [...map.entries()]
    .map(([name, y]) => ({ name, y }))
    .filter((r) => r.y > 0)
    .sort((a, b) => b.y - a.y)
})

const investSeries = computed(() => [
  {
    name: 'Bank balance',
    data: investByRegion.value,
  },
])

function openDrill(title: string, columns: DrillColumn[], rows: Array<Record<string, unknown>>) {
  drill.value = { open: true, title, columns, rows }
}

function closeDrill() {
  drill.value.open = false
}

function onExecutionClick(payload: { name: string; category?: string }) {
  const key = payload.category || payload.name
  const match = divisionsWithBudget.value.find(
    (d) => d.division_short_name === key || d.division_name === key,
  )
  const rows = match?.fund_centers?.length
    ? (match.fund_centers as Array<Record<string, unknown>>)
    : match
      ? [match as unknown as Record<string, unknown>]
      : divisionsWithBudget.value as unknown as Array<Record<string, unknown>>
  openDrill(
    match ? `${match.division_short_name} — fund centers` : 'Intramural by division',
    [
      { key: 'fund_center', label: 'Fund center', format: 'text' },
      { key: 'division_short_name', label: 'Division', format: 'text' },
      { key: 'approved_budget', label: 'Released', format: 'usd' },
      { key: 'commitment', label: 'Commitment', format: 'usd' },
      { key: 'actual_expenditure', label: 'Actual', format: 'usd' },
      { key: 'budget_balance', label: 'Balance', format: 'usd' },
      { key: 'execution_rate', label: 'Execution', format: 'pct' },
    ],
    rows.map((r) => ({
      fund_center: r.fund_center ?? '—',
      division_short_name: r.division_short_name ?? match?.division_short_name ?? '',
      approved_budget: r.approved_budget,
      commitment: r.commitment,
      actual_expenditure: r.actual_expenditure,
      budget_balance: r.budget_balance,
      execution_rate: r.execution_rate,
    })),
  )
}

function onMixClick(payload: { name: string }) {
  const name = payload.name
  if (name === 'Intramural') {
    openDrill('Intramural by division', [
      { key: 'division_short_name', label: 'Code', format: 'text' },
      { key: 'division_name', label: 'Division', format: 'text' },
      { key: 'approved_budget', label: 'Released', format: 'usd' },
      { key: 'actual_expenditure', label: 'Actual', format: 'usd' },
      { key: 'budget_balance', label: 'Balance', format: 'usd' },
      { key: 'execution_rate', label: 'Execution', format: 'pct' },
      { key: 'fund_center_count', label: 'Fund centers', format: 'number' },
    ], divisionsWithBudget.value as unknown as Array<Record<string, unknown>>)
    return
  }
  if (name === 'Extramural') {
    openDrill(
      'Extramural grants',
      [
        { key: 'funder', label: 'Funder', format: 'text' },
        { key: 'grant', label: 'Grant', format: 'text' },
        { key: 'approved_budget', label: 'Approved', format: 'usd' },
        { key: 'released_budget', label: 'Released', format: 'usd' },
        { key: 'actual_expenditure', label: 'Actual', format: 'usd' },
        { key: 'execution_pct', label: 'Execution', format: 'pct' },
      ],
      (summary.value?.sections?.extramural?.rows || []).map((r) => r.data),
    )
    return
  }
  if (name === 'Investments') {
    openDrill(
      'Investments & bank',
      [
        { key: 'account_name', label: 'Account', format: 'text' },
        { key: 'bank_name', label: 'Bank', format: 'text' },
        { key: 'country', label: 'Country', format: 'text' },
        { key: 'region', label: 'Region', format: 'text' },
        { key: 'bank_balance_usd', label: 'Balance', format: 'usd' },
      ],
      (summary.value?.sections?.investments?.rows || []).map((r) => r.data),
    )
    return
  }
  if (name === 'AfEF') {
    openDrill(
      'AfEF lines',
      [
        { key: 'category', label: 'Category', format: 'text' },
        { key: 'label', label: 'Description', format: 'text' },
        { key: 'amount', label: 'Amount', format: 'usd' },
      ],
      (summary.value?.sections?.afef?.rows || []).map((r) => r.data),
    )
    return
  }
  if (name === 'Indirect') {
    openDrill(
      'Indirect cost',
      [
        { key: 'funder', label: 'Funder', format: 'text' },
        { key: 'grant_name', label: 'Grant', format: 'text' },
        { key: 'acdc_indirect_cost', label: 'Indirect', format: 'usd' },
        { key: 'indirect_available', label: 'Available', format: 'usd' },
      ],
      (summary.value?.sections?.indirect_cost?.rows || []).map((r) => r.data),
    )
    return
  }
  openDrill(
    'Administrative cost',
    [
      { key: 'description', label: 'Description', format: 'text' },
      { key: 'amount', label: 'Amount', format: 'usd' },
      { key: 'funds_center', label: 'Funds center', format: 'text' },
    ],
    (summary.value?.sections?.administrative_cost?.rows || []).map((r) => r.data),
  )
}

function onFunderClick(payload: { name: string; category?: string }) {
  const funder = payload.category || payload.name
  const rows = (summary.value?.sections?.extramural?.rows || [])
    .map((r) => r.data)
    .filter((d) => String(d.funder || 'Unassigned') === funder)
  openDrill(`Extramural — ${funder}`, [
    { key: 'grant', label: 'Grant', format: 'text' },
    { key: 'approved_budget', label: 'Approved', format: 'usd' },
    { key: 'released_budget', label: 'Released', format: 'usd' },
    { key: 'actual_expenditure', label: 'Actual', format: 'usd' },
    { key: 'execution_pct', label: 'Execution', format: 'pct' },
  ], rows)
}

function onInvestClick(payload: { name: string }) {
  const rows = (summary.value?.sections?.investments?.rows || [])
    .map((r) => r.data)
    .filter((d) => String(d.region || 'Unspecified') === payload.name)
  openDrill(`Investments — ${payload.name}`, [
    { key: 'account_name', label: 'Account', format: 'text' },
    { key: 'bank_name', label: 'Bank', format: 'text' },
    { key: 'country', label: 'Country', format: 'text' },
    { key: 'currency', label: 'Currency', format: 'text' },
    { key: 'bank_balance_usd', label: 'Balance', format: 'usd' },
  ], rows)
}

async function load() {
  if (!canAccess.value) {
    loading.value = false
    error.value = 'Funding Portfolio requires a Finance permission (or system admin).'
    return
  }
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/portfolio/summary')
    summary.value = data.data as Summary
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load funding portfolio.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="fp">
    <header class="fp__header">
      <div>
        <div class="fp__title-row">
          <h1>Funding Portfolio</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="fp__sub">
          Africa CDC funding overview
          <span v-if="summary" class="fp__chip">FY {{ summary.budget_year }}</span>
        </p>
      </div>
      <div class="fp__actions">
        <RouterLink class="fp__link" :to="{ name: 'performance-summary' }">Performance</RouterLink>
        <RouterLink class="fp__link" :to="{ name: 'portfolio-entry' }">Data entry</RouterLink>
      </div>
    </header>

    <div v-if="loading" class="fp__muted">Loading portfolio…</div>
    <div v-else-if="error" class="fp__error">{{ error }}</div>

    <template v-else-if="summary && kpis">
      <section class="fp__kpis">
        <article class="fp__kpi fp__kpi--green" @click="onMixClick({ name: 'Intramural' })">
          <div class="fp__kpi-body">
            <span>Intramural released</span>
            <strong>{{ compactUsd(kpis.intramural.approved_budget) }}</strong>
            <small>Exec {{ pct(kpis.intramural.execution_rate) }} · {{ usd(kpis.intramural.approved_budget) }}</small>
          </div>
          <span class="fp__kpi-icon" aria-hidden="true"><i class="fa-solid fa-building-columns" /></span>
        </article>
        <article class="fp__kpi fp__kpi--teal" @click="onMixClick({ name: 'Extramural' })">
          <div class="fp__kpi-body">
            <span>Extramural approved</span>
            <strong>{{ compactUsd(kpis.extramural.approved_budget) }}</strong>
            <small>Exec {{ pct(kpis.extramural.execution_rate) }} · {{ usd(kpis.extramural.approved_budget) }}</small>
          </div>
          <span class="fp__kpi-icon" aria-hidden="true"><i class="fa-solid fa-handshake" /></span>
        </article>
        <article class="fp__kpi fp__kpi--gold" @click="onMixClick({ name: 'Investments' })">
          <div class="fp__kpi-body">
            <span>Bank balances</span>
            <strong>{{ compactUsd(kpis.investments.bank_balance_usd) }}</strong>
            <small>{{ kpis.investments.row_count }} account(s) · {{ usd(kpis.investments.bank_balance_usd) }}</small>
          </div>
          <span class="fp__kpi-icon" aria-hidden="true"><i class="fa-solid fa-wallet" /></span>
        </article>
        <article class="fp__kpi fp__kpi--blue" @click="onMixClick({ name: 'AfEF' })">
          <div class="fp__kpi-body">
            <span>AfEF balance</span>
            <strong>{{ compactUsd(kpis.afef.balance) }}</strong>
            <small>{{ kpis.afef.row_count }} line(s) · {{ usd(kpis.afef.balance) }}</small>
          </div>
          <span class="fp__kpi-icon" aria-hidden="true"><i class="fa-solid fa-seedling" /></span>
        </article>
        <article class="fp__kpi fp__kpi--maroon" @click="onMixClick({ name: 'Indirect' })">
          <div class="fp__kpi-body">
            <span>Indirect earned</span>
            <strong>{{ compactUsd(kpis.indirect_cost.earned) }}</strong>
            <small>Available {{ usd(kpis.indirect_cost.available) }}</small>
          </div>
          <span class="fp__kpi-icon" aria-hidden="true"><i class="fa-solid fa-percent" /></span>
        </article>
      </section>

      <p class="fp__hint">Click a KPI or chart point to open the drill-down table.</p>

      <section class="fp__grid">
        <div class="fp__card">
          <PortalHighchart
            title="Intramural execution by division (top 12)"
            type="bar"
            :categories="executionCategories"
            :series="executionSeries"
            y-axis-title="Execution %"
            :height="380"
            @point-click="onExecutionClick"
          />
        </div>
        <div class="fp__card">
          <PortalHighchart
            title="Portfolio mix (approved / balances)"
            type="pie"
            :series="mixSeries"
            :height="380"
            @point-click="onMixClick"
          />
        </div>
        <div class="fp__card">
          <PortalHighchart
            title="Extramural approved by funder"
            type="column"
            :categories="funderCategories"
            :series="funderSeries"
            y-axis-title="USD"
            :height="340"
            @point-click="onFunderClick"
          />
        </div>
        <div class="fp__card">
          <PortalHighchart
            title="Investments &amp; bank by region"
            type="pie"
            :series="investSeries"
            :height="340"
            @point-click="onInvestClick"
          />
        </div>
      </section>
    </template>

    <v-dialog v-model="drill.open" max-width="960" scrollable>
      <v-card>
        <v-card-title class="fp__dialog-title">
          <span>{{ drill.title }}</span>
          <button type="button" class="fp__dialog-close" aria-label="Close" @click="closeDrill">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </button>
        </v-card-title>
        <v-card-text>
          <div v-if="!drill.rows.length" class="fp__muted">No rows for this selection.</div>
          <div v-else class="fp__table-wrap">
            <table class="fp__table">
              <thead>
                <tr>
                  <th v-for="col in drill.columns" :key="col.key">{{ col.label }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in drill.rows" :key="idx">
                  <td v-for="col in drill.columns" :key="col.key">{{ cell(row, col) }}</td>
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
.fp { width: 100%; padding-bottom: 2rem; --fp-radius: 2px; }
.fp__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1.1rem;
}
.fp__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem; }
.fp__header h1 { margin: 0; font-size: 1.55rem; font-weight: 700; color: #0b3b5c; }
.fp__sub { margin: 0.35rem 0 0; color: #64748b; }
.fp__chip {
  display: inline-block; margin-left: 0.4rem; padding: 0.12rem 0.5rem; border-radius: var(--fp-radius);
  background: #e8f1f7; color: #0b3b5c; font-size: 0.78rem; font-weight: 700;
}
.fp__actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.fp__link { color: #0b3b5c; font-weight: 650; text-decoration: none; }
.fp__muted { color: #64748b; }
.fp__error { color: #b42318; }
.fp__hint { color: #64748b; font-size: 0.85rem; margin: 0 0 0.85rem; }
.fp__kpis {
  display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.85rem;
  margin-bottom: 0.85rem;
}
.fp__kpi {
  display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
  background: #fff; color: #1e293b; border-radius: 2px;
  padding: 1.05rem 1.1rem; min-height: 104px; cursor: pointer;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
  transition: border-color .15s, box-shadow .15s;
}
.fp__kpi:hover {
  border-color: rgba(11, 59, 92, 0.22);
  box-shadow: 0 2px 4px rgba(15, 23, 42, 0.05), 0 10px 22px rgba(15, 23, 42, 0.07);
}
.fp__kpi-body { min-width: 0; }
.fp__kpi-body > span {
  display: block; font-size: 0.78rem; color: #64748b; font-weight: 500;
}
.fp__kpi-body strong {
  display: block; margin-top: 0.35rem; font-size: 1.45rem; font-weight: 800;
  letter-spacing: -0.02em; line-height: 1.1; color: #1e293b;
}
.fp__kpi-body small {
  display: block; margin-top: 0.3rem; font-size: 0.72rem; color: #94a3b8;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fp__kpi-icon {
  flex: 0 0 auto;
  width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center;
  border-radius: 2px; background: #eef2f7; color: #475569;
}
.fp__kpi-icon i { font-size: 1.05rem; }
.fp__kpi--green .fp__kpi-icon { background: #e3f6eb; color: #119a48; }
.fp__kpi--teal .fp__kpi-icon { background: #e0f5f2; color: #0f766e; }
.fp__kpi--gold .fp__kpi-icon { background: #f7efd9; color: #b8924a; }
.fp__kpi--blue .fp__kpi-icon { background: #e8f0fe; color: #1d4ed8; }
.fp__kpi--maroon .fp__kpi-icon { background: #f8e4ea; color: #911c39; }
@media (max-width: 1100px) {
  .fp__kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .fp__kpis { grid-template-columns: 1fr; }
}
.fp__grid {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;
}
.fp__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.1); border-radius: var(--fp-radius); padding: 0.75rem 0.9rem;
  min-width: 0;
}
.fp__dialog-title {
  display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
  font-weight: 700; color: #0b3b5c;
}
.fp__dialog-close {
  border: none; background: transparent; cursor: pointer; color: #64748b; font-size: 1.1rem;
}
.fp__table-wrap { overflow: auto; max-height: 60vh; }
.fp__table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.fp__table th, .fp__table td {
  text-align: left; padding: 0.5rem 0.55rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.fp__table th { background: #0b3b5c; color: #fff; position: sticky; top: 0; }
@media (max-width: 900px) {
  .fp__grid { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .fp__header { flex-direction: column; }
}
</style>
