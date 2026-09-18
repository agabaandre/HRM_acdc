<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { themeDisplayName } from '@/lib/riskLabels'
import { useRiskLookups } from '@/composables/useRiskLookups'
import { useLocaleStore } from '@/stores/locale'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type Band = { rating: string; band_key: string; fill_color: string; text_color: string; min_score: number; max_score: number }

type Dash = {
  total: number
  unmatched_bu: number
  kpis: {
    total_risks: number
    critical_inherent: number
    high_inherent: number
    medium_inherent: number
    low_inherent: number
    avg_inherent_score: number
    avg_residual_score: number
    overall_risk_reduction_pct: number
    residual_assessed: number
    not_yet_assessed: number
    status_open_extended: number
    status_not_updated: number
  }
  by_rating: Record<string, number>
  by_inherent_rating: Record<string, number>
  by_type: Record<string, number>
  by_category: Array<{ risk_type: string; count: number; avg_inherent_score: number | null; pct_of_total: number }>
  by_bu: Record<string, number>
  by_status: Record<string, number>
  by_effectiveness: Record<string, number>
  top_residual: Array<{
    id: number
    name: string
    business_unit?: string | null
    residual_score: number | null
    residual_rating: string | null
    inherent_score?: number | null
    inherent_rating?: string | null
    inherent_fill_color?: string | null
    inherent_text_color?: string | null
    residual_fill_color?: string | null
    residual_text_color?: string | null
  }>
  heat: Array<{ likelihood: number; impact: number; count: number }>
  rating_bands: Band[]
  divisions?: Array<{ division_id: number | null; division_name: string }>
  directorates?: Array<{ directorate_id: number; directorate_name: string }>
}

const router = useRouter()
const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()
const data = ref<Dash | null>(null)
const error = ref<string | null>(null)
const loading = ref(true)
const drillFilter = ref<string | null>(null)

/** Align defaults with the risks register (Q2 2026 baseline). */
const year = ref<number | null>(2026)
const quarter = ref<number | null>(2)
const divisionId = ref<number | null>(null)
const directorateId = ref<number | null>(null)
const themeId = ref<number | null>(null)
const divisions = ref<Array<{ division_id: number | null; division_name: string }>>([])
const directorates = ref<Array<{ directorate_id: number; directorate_name: string }>>([])
const themeOptionsList = ref<Array<{ id: number; name: string }>>([])

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y + 1, y, y - 1, y - 2, y - 3].map((n) => ({ label: String(n), value: n }))
})

const quarterOptions = computed(() => [
  { label: locale.t('rr.quarter_q1', 'Q1'), value: 1 },
  { label: locale.t('rr.quarter_q2', 'Q2'), value: 2 },
  { label: locale.t('rr.quarter_q3', 'Q3'), value: 3 },
  { label: locale.t('rr.quarter_q4', 'Q4'), value: 4 },
])

const divisionOptions = computed(() => [
  { label: locale.t('rr.all_divisions', 'All divisions'), value: null as number | null },
  ...divisions.value
    .filter((d) => d.division_id != null)
    .map((d) => ({ label: d.division_name, value: d.division_id as number })),
])

const directorateOptions = computed(() => [
  { label: locale.t('rr.all_directorates', 'All directorates'), value: null as number | null },
  ...directorates.value.map((d) => ({ label: d.directorate_name, value: d.directorate_id })),
])

const themeOptions = computed(() => [
  { label: locale.t('rr.all_themes', 'All themes'), value: null as number | null },
  ...themeOptionsList.value.map((t) => ({ label: themeDisplayName(t.name), value: t.id })),
])

const periodBadge = computed(() => {
  if (year.value != null && quarter.value != null) return `Q${quarter.value} ${year.value}`
  return locale.t('rr.all_periods', 'All periods')
})

const IMPACT_LABELS = computed(() => [
  locale.t('rr.impact_negligible', 'Negligible'),
  locale.t('rr.impact_minor', 'Minor'),
  locale.t('rr.impact_moderate', 'Moderate'),
  locale.t('rr.impact_major', 'Major'),
  locale.t('rr.impact_critical', 'Critical'),
])
const LIKELIHOOD_LABELS = computed(() => [
  locale.t('rr.likelihood_unlikely', 'Unlikely'),
  locale.t('rr.likelihood_possible', 'Possible'),
  locale.t('rr.likelihood_likely', 'Likely'),
  locale.t('rr.likelihood_almost_certain', 'Almost Certain'),
  locale.t('rr.likelihood_certain', 'Certain'),
])

const risksSeriesName = computed(() => locale.t('rr.series_risks', 'Risks'))

const kpiCards = computed(() => {
  const k = data.value?.kpis
  if (!k) return []
  return [
    { label: locale.t('rr.kpi_total', 'Total Risks Logged'), value: k.total_risks },
    { label: locale.t('rr.kpi_critical', 'Critical (Inherent)'), value: k.critical_inherent, tone: 'critical' },
    { label: locale.t('rr.kpi_high', 'High (Inherent)'), value: k.high_inherent, tone: 'high' },
    { label: locale.t('rr.kpi_medium', 'Medium (Inherent)'), value: k.medium_inherent, tone: 'medium' },
    { label: locale.t('rr.kpi_low', 'Low (Inherent)'), value: k.low_inherent, tone: 'low' },
    { label: locale.t('rr.kpi_avg_inherent', 'Avg Inherent Score'), value: k.avg_inherent_score },
    { label: locale.t('rr.kpi_avg_residual', 'Avg Residual Score'), value: k.avg_residual_score },
    { label: locale.t('rr.kpi_reduction', 'Overall Risk Reduction'), value: `${k.overall_risk_reduction_pct}%` },
    { label: locale.t('rr.kpi_residual_assessed', 'Residual Assessed'), value: k.residual_assessed },
    { label: locale.t('rr.kpi_not_assessed', 'Not Yet Assessed'), value: k.not_yet_assessed },
    { label: locale.t('rr.kpi_open_extended', 'Status: Open/Extended'), value: k.status_open_extended },
    { label: locale.t('rr.kpi_not_updated', 'Status: Not Updated'), value: k.status_not_updated },
  ]
})

function bandColor(name: string): string {
  const bands = data.value?.rating_bands || []
  const hit = bands.find((b) => b.rating.toLowerCase() === name.toLowerCase() || b.band_key === name.toLowerCase())
  return hit?.fill_color || '#94A3B8'
}

function seriesFromMap(map: Record<string, number>, colored = false) {
  return Object.entries(map || {}).map(([name, y]) => ({
    name,
    y: Number(y),
    ...(colored ? { color: bandColor(name) } : {}),
  }))
}

const ratingSeries = computed(() => [{ name: risksSeriesName.value, data: seriesFromMap(data.value?.by_rating || {}, true) }])
const typeSeries = computed(() => [{ name: risksSeriesName.value, data: seriesFromMap(data.value?.by_type || {}) }])
const buCategories = computed(() => Object.keys(data.value?.by_bu || {}))
const buSeries = computed(() => [
  { name: risksSeriesName.value, data: Object.values(data.value?.by_bu || {}).map((n) => Number(n)) },
])
const statusCategories = computed(() => Object.keys(data.value?.by_status || {}))
const statusSeries = computed(() => [
  { name: risksSeriesName.value, data: Object.values(data.value?.by_status || {}).map((n) => Number(n)) },
])
const effCategories = computed(() => Object.keys(data.value?.by_effectiveness || {}))
const effSeries = computed(() => [
  { name: risksSeriesName.value, data: Object.values(data.value?.by_effectiveness || {}).map((n) => Number(n)) },
])

const heatSeries = computed(() => {
  const cells = data.value?.heat || []
  const dataPoints: Array<[number, number, number]> = cells.map((c) => [
    Math.max(0, Math.min(4, c.impact - 1)),
    Math.max(0, Math.min(4, c.likelihood - 1)),
    c.count,
  ])
  return [{ name: risksSeriesName.value, data: dataPoints }]
})

const drillRows = computed(() => {
  const d = data.value
  if (!d || !drillFilter.value) return []
  const key = drillFilter.value
  return d.top_residual.filter(
    (r) =>
      r.residual_rating === key ||
      r.inherent_rating === key ||
      (r.business_unit || '').toLowerCase() === key.toLowerCase() ||
      r.name.toLowerCase().includes(key.toLowerCase()),
  )
})

const topTableRows = computed(() => (drillFilter.value ? drillRows.value : data.value?.top_residual || []))

function ratingStyle(fill?: string | null, text?: string | null): Record<string, string> | undefined {
  if (!fill) return undefined
  return {
    backgroundColor: fill,
    color: text || '#0F172A',
    fontWeight: '700',
    textAlign: 'center',
  }
}

function onPointClick(payload: { name: string; y: number }) {
  if (!payload.name) return
  drillFilter.value = payload.name
}

function openRisk(id: number) {
  if (id < 1) return
  void router.push({ name: 'risk-show', params: { id } })
}

async function loadDashboard() {
  if ((year.value == null) !== (quarter.value == null)) {
    return
  }
  loading.value = true
  error.value = null
  drillFilter.value = null
  try {
    const params: Record<string, number> = {}
    if (year.value != null) params.year = year.value
    if (quarter.value != null) params.quarter = quarter.value
    if (divisionId.value != null) params.division_id = divisionId.value
    if (directorateId.value != null) params.directorate_id = directorateId.value
    if (themeId.value != null) params.enterprise_theme_id = themeId.value
    const res = await api.get<Dash>('/api/v1/dashboard/summary', { params })
    data.value = res.data
    if (res.data.divisions?.length) divisions.value = res.data.divisions
    if (res.data.directorates?.length) directorates.value = res.data.directorates
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_dash_error', 'Could not load dashboard'))
  } finally {
    loading.value = false
  }
}

watch([year, quarter], () => {
  if ((year.value == null) !== (quarter.value == null)) return
  void loadDashboard()
})

onMounted(async () => {
  try {
    const lookups = await loadLookups()
    themeOptionsList.value = lookups.enterprise_themes || []
  } catch {
    themeOptionsList.value = []
  }
  await loadDashboard()
})
</script>

<template>
  <div class="rr-dash">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.dash_title', 'Risk dashboard') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.dash_sub', 'Enterprise risk overview') }}
          ·
          <span class="rr-period-chip">{{ periodBadge }}</span>
        </p>
      </div>
    </header>

    <section class="rr-card rr-filters">
      <div class="rr-filters__grid">
        <UFormField :label="locale.t('rr.filter_year', 'Year')">
          <USelect v-model="year" :items="yearOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_quarter', 'Quarter')">
          <USelect v-model="quarter" :items="quarterOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_directorate', 'Directorate')">
          <USelectMenu
            v-model="directorateId"
            :items="directorateOptions"
            searchable
            hide-details
            @update:model-value="loadDashboard"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_division', 'Division')">
          <USelectMenu
            v-model="divisionId"
            :items="divisionOptions"
            searchable
            hide-details
            @update:model-value="loadDashboard"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_theme', 'Enterprise Risk Theme')">
          <USelectMenu
            v-model="themeId"
            :items="themeOptions"
            searchable
            hide-details
            @update:model-value="loadDashboard"
          />
        </UFormField>
      </div>
      <p v-if="year != null && quarter == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_quarter', 'Select a quarter to filter by review period.') }}
      </p>
      <p v-else-if="quarter != null && year == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_year', 'Select a year to filter by review period.') }}
      </p>
    </section>

    <RrSkeleton v-if="loading" variant="cards" :rows="4" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else-if="data">
      <section class="rr-kpi">
        <div class="rr-kpi__title">{{ locale.t('rr.dash_kpis', 'Key Indicators') }}</div>
        <div class="rr-kpi__grid">
          <div
            v-for="card in kpiCards"
            :key="card.label"
            class="rr-kpi__card"
            :class="card.tone ? `rr-kpi__card--${card.tone}` : undefined"
          >
            <div class="rr-kpi__value">{{ card.value }}</div>
            <div class="rr-kpi__label">{{ card.label }}</div>
          </div>
        </div>
      </section>

      <div class="rr-card-grid rr-card-grid--half">
        <div class="rr-card">
          <PortalHighchart
            :title="locale.t('rr.chart_by_residual', 'By residual rating')"
            type="pie"
            :series="ratingSeries"
            :height="300"
            @point-click="onPointClick"
          />
        </div>
        <div class="rr-card">
          <PortalHighchart
            :title="locale.t('rr.chart_by_type', 'By risk type')"
            type="pie"
            :series="typeSeries"
            :height="300"
            @point-click="onPointClick"
          />
        </div>
        <div class="rr-card rr-card--span-2">
          <PortalHighchart
            :title="locale.t('rr.chart_by_bu', 'By business unit')"
            type="bar"
            :categories="buCategories"
            :series="buSeries"
            :height="Math.max(340, buCategories.length * 28)"
            @point-click="onPointClick"
          />
        </div>
        <div class="rr-card">
          <PortalHighchart
            :title="locale.t('rr.chart_by_status', 'By status')"
            type="column"
            :categories="statusCategories"
            :series="statusSeries"
            :height="360"
            @point-click="onPointClick"
          />
        </div>
        <div class="rr-card">
          <PortalHighchart
            :title="locale.t('rr.chart_by_effectiveness', 'Mitigation effectiveness')"
            type="column"
            :categories="effCategories"
            :series="effSeries"
            :height="360"
            @point-click="onPointClick"
          />
        </div>
        <div class="rr-card">
          <PortalHighchart
            :title="locale.t('rr.chart_heat', 'Inherent risk heat map')"
            type="heatmap"
            :categories="IMPACT_LABELS"
            :y-categories="LIKELIHOOD_LABELS"
            :series="heatSeries"
            :height="360"
            :show-table-toggle="true"
          />
        </div>
      </div>

      <section class="rr-card">
        <h2>{{ locale.t('rr.by_category', 'Risks by category') }}</h2>
        <div class="rr-table-wrap">
          <table class="rr-table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.col_type', 'Risk Type') }}</th>
                <th>{{ locale.t('rr.col_count', 'Count') }}</th>
                <th>{{ locale.t('rr.col_avg_inherent', 'Avg Inherent Score') }}</th>
                <th>{{ locale.t('rr.col_pct_total', '% of Total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in data.by_category"
                :key="row.risk_type"
                class="rr-table__row"
                @click="drillFilter = row.risk_type"
              >
                <td>{{ row.risk_type }}</td>
                <td>{{ row.count }}</td>
                <td>{{ row.avg_inherent_score ?? locale.t('rr.none_dash', '—') }}</td>
                <td>{{ row.pct_of_total }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rr-card rr-top-table">
        <div class="rr-top-table__head">
          <h2>{{ drillFilter ? locale.t('rr.drill_down', 'Drill-down · {filter}', { filter: drillFilter }) : locale.t('rr.top_residual', 'Top residual risks') }}</h2>
          <button v-if="drillFilter" type="button" class="rr-btn" @click="drillFilter = null">
            {{ locale.t('rr.clear_filter', 'Clear filter') }}
          </button>
        </div>
        <div class="rr-table-wrap">
          <table class="rr-table rr-table--top">
            <thead>
              <tr>
                <th>{{ locale.t('rr.col_rank', 'Rank') }}</th>
                <th>{{ locale.t('rr.col_business_unit', 'Business unit') }}</th>
                <th>{{ locale.t('rr.risk_name', 'Risk name') }}</th>
                <th>{{ locale.t('rr.col_inherent', 'Inherent Risk Score') }}</th>
                <th>{{ locale.t('rr.col_residual', 'Residual Risk Score') }}</th>
                <th>{{ locale.t('rr.score_residual', 'Residual risk') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(r, idx) in topTableRows"
                :key="r.id || idx"
                class="rr-table__row"
                @click="openRisk(r.id)"
              >
                <td class="rr-table__rank">{{ idx + 1 }}</td>
                <td>{{ r.business_unit || locale.t('rr.none_dash', '—') }}</td>
                <td class="rr-table__name">{{ r.name }}</td>
                <td
                  class="rr-table__rating"
                  :style="ratingStyle(r.inherent_fill_color, r.inherent_text_color)"
                >
                  {{ r.inherent_score ?? locale.t('rr.none_dash', '—') }}
                </td>
                <td
                  class="rr-table__rating"
                  :style="ratingStyle(r.residual_fill_color, r.residual_text_color)"
                >
                  {{ r.residual_score ?? locale.t('rr.none_dash', '—') }}
                </td>
                <td
                  class="rr-table__rating"
                  :style="ratingStyle(r.residual_fill_color, r.residual_text_color)"
                >
                  {{ r.residual_rating || locale.t('rr.none_dash', '—') }}
                </td>
              </tr>
              <tr v-if="topTableRows.length === 0">
                <td colspan="6" class="rr-muted">
                  {{ locale.t('rr.no_matching_risks', 'No matching risks. Clear the filter or open the register.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-period-chip {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #1e3a8a;
  background: #dbeafe;
  border-radius: 2px;
  vertical-align: baseline;
}
.rr-filters {
  margin-bottom: 1rem;
}
.rr-filters__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}
.rr-filters__hint { margin: 0.65rem 0 0; }
.rr-kpi {
  margin-bottom: 1rem;
  border: 1px solid #c5d0da;
  border-radius: 2px;
  overflow: hidden;
  background: #fff;
}
.rr-kpi__title {
  background: #1e3a5f;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  padding: 0.55rem 0.85rem;
}
.rr-kpi__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
  background: #e8f1fb;
}
.rr-kpi__card {
  padding: 0.75rem 0.5rem;
  text-align: center;
  border-right: 1px solid #c5d0da;
  border-bottom: 1px solid #c5d0da;
}
.rr-kpi__value {
  font-size: 1.35rem;
  font-weight: 800;
  color: #1e3a5f;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}
.rr-kpi__label {
  margin-top: 0.35rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: #1a2b3c;
  line-height: 1.25;
}
.rr-kpi__card--critical .rr-kpi__value { color: #c00000; }
.rr-kpi__card--high .rr-kpi__value { color: #c47a00; }
.rr-kpi__card--medium .rr-kpi__value { color: #8a7a00; }
.rr-kpi__card--low .rr-kpi__value { color: #007a38; }
.rr-top-table { padding: 0; overflow: hidden; }
.rr-top-table__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  background: #1e3a5f;
  color: #fff;
  padding: 0.65rem 0.9rem;
}
.rr-top-table__head h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
}
.rr-top-table__head .rr-btn {
  background: #fff;
  color: #1e3a5f;
}
.rr-table--top { margin: 0; }
.rr-table--top th {
  background: #3d6ea8;
  color: #fff;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
}
.rr-table--top td {
  vertical-align: middle;
  font-size: 0.88rem;
}
.rr-table__rank {
  text-align: center;
  font-weight: 700;
  width: 3.25rem;
}
.rr-table__name {
  font-weight: 600;
  color: #1a2b3c;
  max-width: 28rem;
}
.rr-table__rating {
  text-align: center;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  min-width: 5.5rem;
}
</style>
