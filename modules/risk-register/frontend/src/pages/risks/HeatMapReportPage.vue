<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type HeatPoint = {
  enterprise_theme_id: number
  ref: string
  theme_name: string
  likelihood: number
  impact: number
  residual_score: number | null
  residual_rating: string | null
  fill_color: string
  text_color: string
  principal_risk_id: number
  principal_risk_name: string
}

const router = useRouter()
const locale = useLocaleStore()
const loading = ref(true)
const error = ref<string | null>(null)
const points = ref<HeatPoint[]>([])
const divisions = ref<Array<{ division_id: number | null; division_name: string }>>([])
const directorates = ref<Array<{ directorate_id: number; directorate_name: string }>>([])

const year = ref<number | null>(2026)
const quarter = ref<number | null>(2)
const divisionId = ref<number | null>(null)
const directorateId = ref<number | null>(null)

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

const heatSeries = computed(() => {
  const dataPoints: Array<[number, number, number]> = points.value.map((p) => [
    Math.max(0, Math.min(4, p.impact - 1)),
    Math.max(0, Math.min(4, p.likelihood - 1)),
    p.residual_score ?? 1,
  ])
  return [{ name: locale.t('rr.series_themes', 'Themes'), data: dataPoints }]
})

const summaryText = computed(() => {
  if (!points.value.length) return ''
  const critical = points.value.filter((p) => (p.residual_score ?? 0) >= 20)
  const high = points.value.filter((p) => (p.residual_score ?? 0) >= 15 && (p.residual_score ?? 0) < 20)
  const moderate = points.value.filter((p) => (p.residual_score ?? 0) >= 10 && (p.residual_score ?? 0) < 15)
  return locale.t(
    'rr.heat_summary',
    '{critical} critical · {high} high · {moderate} moderate principal themes',
    { critical: critical.length, high: high.length, moderate: moderate.length },
  )
})

async function loadReport() {
  if ((year.value == null) !== (quarter.value == null)) return
  loading.value = true
  error.value = null
  try {
    const params: Record<string, number> = {}
    if (year.value != null) params.year = year.value
    if (quarter.value != null) params.quarter = quarter.value
    if (divisionId.value != null) params.division_id = divisionId.value
    if (directorateId.value != null) params.directorate_id = directorateId.value
    const { data } = await api.get<{
      points: HeatPoint[]
      divisions?: Array<{ division_id: number | null; division_name: string }>
      directorates?: Array<{ directorate_id: number; directorate_name: string }>
    }>('/api/v1/reports/heat-map', { params })
    points.value = data.points || []
    if (data.divisions?.length) divisions.value = data.divisions
    if (data.directorates?.length) directorates.value = data.directorates
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_heat_report_error', 'Could not load heat map report'))
  } finally {
    loading.value = false
  }
}

function openTheme(id: number) {
  const query: Record<string, string> = {}
  if (year.value != null) query.year = String(year.value)
  if (quarter.value != null) query.quarter = String(quarter.value)
  if (divisionId.value != null) query.division_id = String(divisionId.value)
  if (directorateId.value != null) query.directorate_id = String(directorateId.value)
  void router.push({ name: 'report-enterprise-theme', params: { id }, query })
}

watch([year, quarter], () => {
  if ((year.value == null) !== (quarter.value == null)) return
  void loadReport()
})

onMounted(() => {
  void loadReport()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <p class="rr-page__crumb">
          <button type="button" class="rr-linkish" @click="router.push({ name: 'reports' })">
            {{ locale.t('rr.back_reports', '← Reports') }}
          </button>
        </p>
        <h1>{{ locale.t('rr.heat_report_title', 'Enterprise Risk Heat Map') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.heat_report_sub', 'Principal institutional risks by impact and likelihood') }}
          ·
          <span class="rr-period-chip">{{ periodBadge }}</span>
        </p>
        <p v-if="summaryText" class="rr-muted">{{ summaryText }}</p>
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
            @update:model-value="loadReport"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_division', 'Division')">
          <USelectMenu
            v-model="divisionId"
            :items="divisionOptions"
            searchable
            hide-details
            @update:model-value="loadReport"
          />
        </UFormField>
      </div>
    </section>

    <RrSkeleton v-if="loading" variant="cards" :rows="2" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else>
      <div class="rr-card">
        <PortalHighchart
          :title="locale.t('rr.heat_chart_title', 'Enterprise risk heat map')"
          type="heatmap"
          :categories="IMPACT_LABELS"
          :y-categories="LIKELIHOOD_LABELS"
          :series="heatSeries"
          :height="480"
        />
      </div>

      <section class="rr-card rr-heat-legend">
        <h2>{{ locale.t('rr.heat_legend_title', 'Principal themes') }}</h2>
        <ul class="rr-heat-legend__list">
          <li v-for="p in points" :key="p.enterprise_theme_id">
            <button type="button" class="rr-theme-link" @click="openTheme(p.enterprise_theme_id)">
              <span class="rr-heat-swatch" :style="{ background: p.fill_color }" />
              <strong>{{ p.ref }}</strong>
              {{ p.theme_name }}
              <span class="rr-muted">({{ p.residual_rating || p.residual_score || '—' }})</span>
            </button>
          </li>
        </ul>
        <p v-if="points.length === 0" class="rr-muted">
          {{ locale.t('rr.no_heat_points', 'No principal themes to plot for the current filters.') }}
        </p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page__crumb { margin: 0 0 0.35rem; }
.rr-linkish {
  border: 0;
  background: transparent;
  color: #1e3a8a;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}
.rr-period-chip {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #1e3a8a;
  background: #dbeafe;
  border-radius: 999px;
}
.rr-filters { margin-bottom: 1rem; }
.rr-filters__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}
.rr-heat-legend h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}
.rr-heat-legend__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}
.rr-theme-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 0;
  background: transparent;
  color: #1e3a8a;
  text-align: left;
  padding: 0.2rem 0;
  cursor: pointer;
  font-size: 0.9rem;
}
.rr-heat-swatch {
  width: 0.85rem;
  height: 0.85rem;
  border-radius: 0.2rem;
  flex: 0 0 auto;
}
</style>
