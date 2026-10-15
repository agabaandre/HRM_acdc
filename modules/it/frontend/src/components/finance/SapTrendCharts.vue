<script setup lang="ts">
import { computed } from 'vue'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'

export type TrendPoint = {
  period_ym?: string
  label: string
  has_data?: boolean
  approved_budget: number
  actual_expenditure: number
  commitment: number
  execution_rate: number | null
  quarter?: number
}

const props = defineProps<{
  monthly: TrendPoint[]
  quarterly: TrendPoint[]
  fyLabel?: string
}>()

const monthCategories = computed(() => props.monthly.map((p) => p.label))
const quarterCategories = computed(() => props.quarterly.map((p) => p.label))

const monthExecSeries = computed(() => [
  {
    name: 'Execution %',
    data: props.monthly.map((p) =>
      p.has_data && p.execution_rate != null
        ? Number((Number(p.execution_rate) * 100).toFixed(1))
        : null,
    ),
  },
])

const monthSpendSeries = computed(() => [
  {
    name: 'Released',
    data: props.monthly.map((p) => (p.has_data ? Number(p.approved_budget.toFixed(0)) : null)),
    color: '#119a48',
  },
  {
    name: 'Actual',
    data: props.monthly.map((p) => (p.has_data ? Number(p.actual_expenditure.toFixed(0)) : null)),
    color: '#c3a366',
  },
])

const quarterExecSeries = computed(() => [
  {
    name: 'Execution %',
    data: props.quarterly.map((p) =>
      p.has_data && p.execution_rate != null
        ? Number((Number(p.execution_rate) * 100).toFixed(1))
        : null,
    ),
  },
])

const quarterSpendSeries = computed(() => [
  {
    name: 'Released',
    data: props.quarterly.map((p) => (p.has_data ? Number(p.approved_budget.toFixed(0)) : null)),
    color: '#119a48',
  },
  {
    name: 'Actual',
    data: props.quarterly.map((p) => (p.has_data ? Number(p.actual_expenditure.toFixed(0)) : null)),
    color: '#911c39',
  },
])

const hasAnyMonth = computed(() => props.monthly.some((p) => p.has_data))
const hasAnyQuarter = computed(() => props.quarterly.some((p) => p.has_data))
</script>

<template>
  <section class="st">
    <div class="st__head">
      <h2>Trends {{ fyLabel ? `· ${fyLabel}` : '' }}</h2>
      <p class="st__muted">
        Month and quarter snapshots from retained SAP uploads. Quarters follow the configured financial year.
      </p>
    </div>

    <div v-if="!hasAnyMonth && !hasAnyQuarter" class="st__empty">
      No monthly SAP history yet. Upload a file for each month on SAP import to build trends.
    </div>

    <div v-else class="st__grid">
      <div class="st__card">
        <PortalHighchart
          title="Monthly execution %"
          type="line"
          :categories="monthCategories"
          :series="monthExecSeries"
          y-axis-title="Execution %"
          :height="300"
        />
      </div>
      <div class="st__card">
        <PortalHighchart
          title="Monthly released vs actual"
          type="column"
          :categories="monthCategories"
          :series="monthSpendSeries"
          y-axis-title="USD"
          :height="300"
        />
      </div>
      <div class="st__card">
        <PortalHighchart
          title="Quarterly execution %"
          type="column"
          :categories="quarterCategories"
          :series="quarterExecSeries"
          y-axis-title="Execution %"
          :height="300"
        />
      </div>
      <div class="st__card">
        <PortalHighchart
          title="Quarterly released vs actual"
          type="column"
          :categories="quarterCategories"
          :series="quarterSpendSeries"
          y-axis-title="USD"
          :height="300"
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.st { margin: 0 0 1.25rem; }
.st__head { margin-bottom: 0.75rem; }
.st__head h2 { margin: 0; font-size: 1.05rem; color: #0b3b5c; }
.st__muted { margin: 0.3rem 0 0; color: #64748b; font-size: 0.85rem; }
.st__empty {
  background: #f8fafc; border: 1px dashed rgba(15, 23, 42, 0.15); border-radius: 2px;
  padding: 1rem; color: #64748b; font-size: 0.9rem;
}
.st__grid {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;
}
.st__card {
  background: #fff; border: 1px solid rgba(11, 59, 92, 0.1); border-radius: 2px;
  padding: 0.75rem 0.9rem; min-width: 0;
}
@media (max-width: 900px) {
  .st__grid { grid-template-columns: 1fr; }
}
</style>
