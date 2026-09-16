<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'

type FundCenter = {
  fund_center: string
  approved_budget: number
  budget_balance: number
  execution_rate: number | null
}

type Summary = {
  budget_year: number
  intramural: {
    approved_budget: number
    budget_balance: number
    fund_center_count: number
    execution_rate: number | null
    by_fund_center: FundCenter[]
  }
  sections: Record<string, { row_count: number; rows: Array<{ data: Record<string, unknown> }> }>
}

const loading = ref(true)
const error = ref<string | null>(null)
const summary = ref<Summary | null>(null)

function usd(n: number | null | undefined): string {
  const v = Number(n || 0)
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(v)
}

function pct(n: number | null | undefined): string {
  if (n == null || Number.isNaN(n)) return '—'
  return `${(n * 100).toFixed(1)}%`
}

const afefTotal = computed(() => {
  const rows = summary.value?.sections?.afef?.rows || []
  const total = rows.find((r) => String(r.data.label || '').toLowerCase().includes('total'))
  return Number(total?.data.amount ?? rows.reduce((s, r) => s + Number(r.data.amount || 0), 0))
})

const columnSeries = computed(() => {
  const rows = (summary.value?.intramural.by_fund_center || []).slice(0, 12)
  return [
    {
      name: 'Execution %',
      data: rows.map((r) => Number(((r.execution_rate ?? 0) * 100).toFixed(1))),
    },
  ]
})

const columnCategories = computed(() =>
  (summary.value?.intramural.by_fund_center || []).slice(0, 12).map((r) => r.fund_center),
)

const pieSeries = computed(() => {
  const intra = Number(summary.value?.intramural.approved_budget || 0)
  const extra = Number(summary.value?.sections?.extramural?.rows?.[0]?.data?.budget || 0)
  const afef = afefTotal.value
  return [
    {
      name: 'Portfolio mix',
      data: [
        { name: 'Intramural', y: intra },
        { name: 'Extramural', y: extra },
        { name: 'AfEF', y: Math.abs(afef) },
      ].filter((p) => p.y > 0),
    },
  ]
})

async function load() {
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
  <div class="fin-portfolio">
    <header class="rr-page__header">
      <div>
        <h1 class="rr-page__title">Funding Portfolio</h1>
        <p class="rr-page__sub">
          Africa CDC funding overview
          <span v-if="summary" class="rr-period-chip">FY {{ summary.budget_year }}</span>
        </p>
      </div>
    </header>

    <div v-if="loading" class="rr-muted">Loading portfolio…</div>
    <div v-else-if="error" class="rr-error">{{ error }}</div>
    <template v-else-if="summary">
      <section class="rr-kpi">
        <div class="rr-kpi__grid">
          <div class="rr-kpi__card">
            <div class="rr-kpi__label">Intramural approved</div>
            <div class="rr-kpi__value">{{ usd(summary.intramural.approved_budget) }}</div>
          </div>
          <div class="rr-kpi__card">
            <div class="rr-kpi__label">Intramural balance</div>
            <div class="rr-kpi__value">{{ usd(summary.intramural.budget_balance) }}</div>
          </div>
          <div class="rr-kpi__card">
            <div class="rr-kpi__label">Intramural execution</div>
            <div class="rr-kpi__value">{{ pct(summary.intramural.execution_rate) }}</div>
          </div>
          <div class="rr-kpi__card">
            <div class="rr-kpi__label">Fund centers</div>
            <div class="rr-kpi__value">{{ summary.intramural.fund_center_count }}</div>
          </div>
          <div class="rr-kpi__card">
            <div class="rr-kpi__label">AfEF (demo)</div>
            <div class="rr-kpi__value">{{ usd(afefTotal) }}</div>
          </div>
        </div>
      </section>

      <section class="rr-card-grid rr-card-grid--half">
        <div class="rr-card">
          <PortalHighchart
            title="Intramural execution by fund center"
            type="column"
            :categories="columnCategories"
            :series="columnSeries"
            y-axis-title="Execution %"
            :height="320"
          />
        </div>
        <div class="rr-card">
          <PortalHighchart title="Portfolio mix" type="pie" :series="pieSeries" :height="320" />
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.fin-portfolio {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.rr-kpi__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}
.rr-kpi__card {
  background: var(--portal-surface, #fff);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  padding: 1rem;
}
.rr-kpi__label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  opacity: 0.7;
}
.rr-kpi__value {
  font-size: 1.35rem;
  font-weight: 650;
  margin-top: 0.35rem;
}
.rr-card-grid--half {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1rem;
}
.rr-card {
  background: var(--portal-surface, #fff);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 0.75rem;
}
.rr-period-chip {
  display: inline-block;
  margin-left: 0.5rem;
  padding: 0.15rem 0.55rem;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.06);
  font-size: 0.8rem;
}
.rr-error {
  color: #b42318;
}
</style>
