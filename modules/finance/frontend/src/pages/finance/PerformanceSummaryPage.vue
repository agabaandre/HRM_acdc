<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import SapTrendCharts from '@/components/finance/SapTrendCharts.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import { FINANCE_DASHBOARD_PERMS } from '@/lib/financePermissions'
import { useAuthStore } from '@/stores/auth'

type PerfRow = {
  rank: number
  division_id: number
  division_name: string
  division_short_name: string
  fund_center_count: number
  approved_budget: number
  budget_balance: number
  commitment: number
  actual_expenditure: number
  cy_expenditure: number
  execution_rate: number | null
  fund_centers?: Array<Record<string, unknown>>
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

type Summary = {
  budget_year: number
  financial_year?: { fy_year: number; start_month: number; start_month_name: string; label: string }
  performance?: PerfRow[]
  trends?: { monthly: TrendPoint[]; quarterly: TrendPoint[] }
  intramural?: {
    approved_budget: number
    execution_rate: number | null
    fund_center_count: number
  }
}

const auth = useAuthStore()
const loading = ref(true)
const error = ref<string | null>(null)
const summary = ref<Summary | null>(null)
const drillOpen = ref(false)
const drillTitle = ref('')
const drillMode = ref<'centers' | 'expenses'>('centers')
const drillLoading = ref(false)
const drillRows = ref<Array<Record<string, unknown>>>([])

const canAccess = computed(
  () => auth.isAdmin || auth.hasAnyPermission([...FINANCE_DASHBOARD_PERMS]),
)

const rows = computed(() => summary.value?.performance || [])
const trendMonthly = computed(() => summary.value?.trends?.monthly || [])
const trendQuarterly = computed(() => summary.value?.trends?.quarterly || [])
const fyTrendLabel = computed(
  () => summary.value?.financial_year?.label || (summary.value ? `FY ${summary.value.budget_year}` : ''),
)

const topCategories = computed(() =>
  rows.value.slice(0, 10).map((r) => r.division_short_name || r.division_name),
)

const topSeries = computed(() => [
  {
    name: 'Execution %',
    data: rows.value.slice(0, 10).map((r) => Number((((r.execution_rate ?? 0) * 100)).toFixed(1))),
  },
])

const budgetCategories = computed(() =>
  rows.value.slice(0, 10).map((r) => r.division_short_name || r.division_name),
)

const budgetSeries = computed(() => [
  {
    name: 'Released',
    data: rows.value.slice(0, 10).map((r) => Number(r.approved_budget.toFixed(0))),
  },
  {
    name: 'Actual',
    data: rows.value.slice(0, 10).map((r) => Number(r.actual_expenditure.toFixed(0))),
  },
])

const avgExecution = computed(() => {
  const withRate = rows.value.filter((r) => r.execution_rate != null)
  if (!withRate.length) return null
  return withRate.reduce((s, r) => s + Number(r.execution_rate || 0), 0) / withRate.length
})

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

function openDivision(row: PerfRow) {
  drillTitle.value = `${row.rank}. ${row.division_short_name} — ${row.division_name}`
  drillMode.value = 'centers'
  drillLoading.value = false
  drillRows.value = (row.fund_centers || []).map((fc) => ({
    fund_center: fc.fund_center,
    budget_code: fc.budget_code,
    approved_budget: fc.approved_budget,
    commitment: fc.commitment,
    actual_expenditure: fc.actual_expenditure,
    budget_balance: fc.budget_balance,
    execution_rate: fc.execution_rate,
  }))
  drillOpen.value = true
}

async function openFundCenterExpenses(fc: Record<string, unknown>) {
  const code = String(fc.fund_center || '').trim()
  if (!code) return
  drillTitle.value = `Expenses — ${code}`
  drillMode.value = 'expenses'
  drillLoading.value = true
  drillRows.value = []
  drillOpen.value = true
  try {
    const { data } = await api.get(`/api/v1/sap/fund-centers/${encodeURIComponent(code)}/expenses`)
    drillRows.value = (data.data?.expenses || []) as Array<Record<string, unknown>>
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load fund-center expenses.')
  } finally {
    drillLoading.value = false
  }
}

function onChartClick(payload: { name: string; category?: string }) {
  const key = payload.category || payload.name
  const match = rows.value.find(
    (r) => r.division_short_name === key || r.division_name === key,
  )
  if (match) openDivision(match)
}

async function load() {
  if (!canAccess.value) {
    loading.value = false
    error.value = 'Performance summary requires a Finance permission (or system admin).'
    return
  }
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/portfolio/summary')
    summary.value = data.data as Summary
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load performance summary.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="ps">
    <header class="ps__header">
      <div>
        <div class="ps__title-row">
          <h1>Performance summary</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="ps__sub">
          Divisional budget execution ranking from the latest SAP import
          <span v-if="summary" class="ps__chip">{{ fyTrendLabel }}</span>
          <span v-if="summary?.financial_year" class="ps__chip ps__chip--muted">
            starts {{ summary.financial_year.start_month_name }}
          </span>
        </p>
      </div>
      <div class="ps__actions">
        <RouterLink class="ps__link" :to="{ name: 'dashboard' }">Funding Portfolio</RouterLink>
        <RouterLink class="ps__link" :to="{ name: 'portfolio-entry' }">Data entry</RouterLink>
      </div>
    </header>

    <FinSkeleton v-if="loading" variant="dashboard" :rows="4" />
    <div v-else-if="error" class="ps__error">{{ error }}</div>

    <template v-else-if="summary">
      <section class="ps__kpis">
        <article class="ps__kpi ps__kpi--blue">
          <div class="ps__kpi-body">
            <span>Divisions ranked</span>
            <strong>{{ rows.length }}</strong>
            <small>Performance league table</small>
          </div>
          <span class="ps__kpi-icon" aria-hidden="true"><i class="fa-solid fa-sitemap" /></span>
        </article>
        <article class="ps__kpi ps__kpi--gold">
          <div class="ps__kpi-body">
            <span>Avg execution</span>
            <strong>{{ pct(avgExecution) }}</strong>
            <small>Across ranked divisions</small>
          </div>
          <span class="ps__kpi-icon" aria-hidden="true"><i class="fa-solid fa-chart-line" /></span>
        </article>
        <article class="ps__kpi ps__kpi--green">
          <div class="ps__kpi-body">
            <span>Intramural released</span>
            <strong>{{ compactUsd(summary.intramural?.approved_budget) }}</strong>
            <small>{{ usd(summary.intramural?.approved_budget) }}</small>
          </div>
          <span class="ps__kpi-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping" /></span>
        </article>
        <article class="ps__kpi ps__kpi--maroon">
          <div class="ps__kpi-body">
            <span>Overall execution</span>
            <strong>{{ pct(summary.intramural?.execution_rate) }}</strong>
            <small>{{ summary.intramural?.fund_center_count || 0 }} fund center(s)</small>
          </div>
          <span class="ps__kpi-icon" aria-hidden="true"><i class="fa-solid fa-chart-column" /></span>
        </article>
      </section>

      <section class="ps__grid">
        <div class="ps__card">
          <PortalHighchart
            title="Top 10 divisions by execution %"
            type="bar"
            :categories="topCategories"
            :series="topSeries"
            y-axis-title="Execution %"
            :height="360"
            @point-click="onChartClick"
          />
        </div>
        <div class="ps__card">
          <PortalHighchart
            title="Released vs actual (top 10)"
            type="column"
            :categories="budgetCategories"
            :series="budgetSeries"
            y-axis-title="USD"
            :height="360"
            @point-click="onChartClick"
          />
        </div>
      </section>

      <section class="ps__card ps__card--table">
        <div class="ps__table-head">
          <h2>Division ranking</h2>
          <p class="ps__muted">Click a row for fund-center drill-down.</p>
        </div>
        <div class="ps__table-wrap">
          <table class="ps__table">
            <thead>
              <tr>
                <th>#</th>
                <th>Division</th>
                <th>Fund centers</th>
                <th>Released</th>
                <th>Commitment</th>
                <th>Actual</th>
                <th>Balance</th>
                <th>Execution</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.division_id"
                class="ps__row"
                @click="openDivision(row)"
              >
                <td><span class="ps__rank">{{ row.rank }}</span></td>
                <td>
                  <strong>{{ row.division_short_name }}</strong>
                  <div class="ps__muted">{{ row.division_name }}</div>
                </td>
                <td>{{ row.fund_center_count }}</td>
                <td>{{ usd(row.approved_budget) }}</td>
                <td>{{ usd(row.commitment) }}</td>
                <td>{{ usd(row.actual_expenditure) }}</td>
                <td>{{ usd(row.budget_balance) }}</td>
                <td>
                  <span class="ps__exec" :class="{ 'is-high': (row.execution_rate || 0) >= 0.5 }">
                    {{ pct(row.execution_rate) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!rows.length" class="ps__muted">
          No mapped APM divisions with SAP data yet. Import SAP and map fund centers first.
        </p>
      </section>

      <SapTrendCharts
        :monthly="trendMonthly"
        :quarterly="trendQuarterly"
        :fy-label="fyTrendLabel"
      />
    </template>

    <v-dialog v-model="drillOpen" max-width="920" scrollable>
      <v-card>
        <v-card-title class="ps__dialog-title">
          <span>{{ drillTitle }}</span>
          <button type="button" class="ps__dialog-close" aria-label="Close" @click="drillOpen = false">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </button>
        </v-card-title>
        <v-card-text>
          <p v-if="drillLoading" class="ps__muted">Loading expenses…</p>
          <div v-else-if="!drillRows.length" class="ps__muted">
            {{ drillMode === 'expenses' ? 'No expenses for this fund center.' : 'No fund centers for this division.' }}
          </div>
          <div v-else class="ps__table-wrap">
            <table v-if="drillMode === 'expenses'" class="ps__table">
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
                <tr v-for="(ex, idx) in drillRows" :key="idx">
                  <td><code class="ps__fund">{{ ex.budget_code || '—' }}</code></td>
                  <td><code>{{ ex.gl_account }}</code></td>
                  <td>{{ ex.gl_description || '—' }}</td>
                  <td>{{ usd(Number(ex.commitment || 0)) }}</td>
                  <td>{{ usd(Number(ex.actual_expenditure || 0)) }}</td>
                </tr>
              </tbody>
            </table>
            <table v-else class="ps__table">
              <thead>
                <tr>
                  <th>Fund center</th>
                  <th>Budget code</th>
                  <th>Released</th>
                  <th>Commitment</th>
                  <th>Actual</th>
                  <th>Balance</th>
                  <th>Execution</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(fc, idx) in drillRows"
                  :key="idx"
                  class="ps__row-link"
                  @click="openFundCenterExpenses(fc)"
                >
                  <td><code>{{ fc.fund_center }}</code></td>
                  <td><code class="ps__fund">{{ fc.budget_code || '—' }}</code></td>
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
.ps { width: 100%; padding-bottom: 2rem; --ps-radius: 2px; }
.ps__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1.1rem;
}
.ps__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem; }
.ps__header h1 { margin: 0; font-size: 1.55rem; font-weight: 700; color: #0b3b5c; }
.ps__sub { margin: 0.35rem 0 0; color: #64748b; }
.ps__chip {
  display: inline-block; margin-left: 0.4rem; padding: 0.12rem 0.5rem; border-radius: var(--ps-radius);
  background: #e8f1f7; color: #0b3b5c; font-size: 0.78rem; font-weight: 700;
}
.ps__chip--muted { background: #f1f5f9; color: #475569; }
.ps__actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.ps__link { color: #0b3b5c; font-weight: 650; text-decoration: none; }
.ps__muted { color: #64748b; margin: 0; }
.ps__error { color: #b42318; }
.ps__kpis {
  display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.85rem;
  margin-bottom: 1rem;
}
.ps__kpi {
  display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
  color: #1e293b; border-radius: 2px; padding: 1.05rem 1.1rem; min-height: 104px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
}
.ps__kpi-body { min-width: 0; }
.ps__kpi-body > span {
  display: block; font-size: 0.78rem; color: #64748b; font-weight: 500;
}
.ps__kpi-body strong {
  display: block; margin-top: 0.35rem; font-size: 1.45rem; font-weight: 800;
  letter-spacing: -0.02em; line-height: 1.1; color: #1e293b;
}
.ps__kpi-body small {
  display: block; margin-top: 0.3rem; font-size: 0.72rem; color: #94a3b8;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ps__kpi-icon {
  flex: 0 0 auto;
  width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center;
  border-radius: 2px; background: #eef2f7; color: #475569;
}
.ps__kpi-icon i { font-size: 1.05rem; }
.ps__kpi--blue .ps__kpi-icon { background: #e8f0fe; color: #1d4ed8; }
.ps__kpi--gold .ps__kpi-icon { background: #f7efd9; color: #b8924a; }
.ps__kpi--green .ps__kpi-icon { background: #e3f6eb; color: #119a48; }
.ps__kpi--maroon .ps__kpi-icon { background: #f8e4ea; color: #911c39; }
@media (max-width: 900px) {
  .ps__kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .ps__kpis { grid-template-columns: 1fr; }
}
.ps__grid {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;
  margin-bottom: 1rem;
}
.ps__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.1); border-radius: var(--ps-radius); padding: 0.85rem 1rem;
  min-width: 0;
}
.ps__card--table { padding: 1rem 1.1rem; }
.ps__table-head {
  display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; margin-bottom: 0.65rem;
}
.ps__table-head h2 { margin: 0; font-size: 1.05rem; color: #0b3b5c; }
.ps__table-wrap { overflow: auto; max-height: 70vh; }
.ps__table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.ps__table th, .ps__table td {
  text-align: left; padding: 0.55rem 0.5rem; border-bottom: 1px solid rgba(0,0,0,0.06); vertical-align: top;
}
.ps__table th { background: #0b3b5c; color: #fff; position: sticky; top: 0; }
.ps__row { cursor: pointer; }
.ps__row:hover { background: #f0f7fb; }
.ps__row-link { cursor: pointer; }
.ps__row-link:hover { background: #f1f5f9; }
.ps__fund {
  display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
  background: #e8f6ee; color: #0f5132; font-size: 0.78rem; font-weight: 700;
}
.ps__rank {
  display: inline-flex; min-width: 1.6rem; justify-content: center; font-weight: 700; color: #0b3b5c;
}
.ps__exec {
  display: inline-block; background: #e8f1f7; color: #0b3b5c; border-radius: var(--ps-radius);
  padding: 0.12rem 0.5rem; font-weight: 700; font-size: 0.8rem;
}
.ps__exec.is-high { background: #dcfce7; color: #166534; }
@media (max-width: 900px) {
  .ps__grid { grid-template-columns: 1fr; }
}
.ps__dialog-title {
  display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
  font-weight: 700; color: #0b3b5c;
}
.ps__dialog-close {
  border: none; background: transparent; cursor: pointer; color: #64748b; font-size: 1.1rem;
}
@media (max-width: 720px) {
  .ps__header { flex-direction: column; }
}
</style>
