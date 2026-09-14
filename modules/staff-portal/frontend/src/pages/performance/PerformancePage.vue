<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import { api } from '@/lib/api'
import { downloadClientCsv, openClientPdfTable } from '@/lib/clientTableExport'
import {
  fetchPerformanceHub,
  type PerformanceHubData,
  type PerformanceTab,
} from '@/lib/performanceApi'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'
import { useLocaleStore } from '@/stores/locale'

type HubTab = PerformanceTab | 'analytics'
type AnalyticsPhase = 'ppa' | 'midterm' | 'endterm'
type NameY = { name: string; y: number }
type TrendPoint = { date: string; count: number }
type AvgRow = { name: string; avg_score: number }
type AnalyticsSummary = {
  total?: number
  approved?: number
  submitted?: number
  draft?: number
  without?: number
  pdps?: number
  require_calibration?: number
}
type AnalyticsPayload = {
  phase?: string
  phase_label?: string
  period?: string
  summary?: AnalyticsSummary
  approval_breakdown?: NameY[]
  avg_approval_days?: number
  by_division?: NameY[]
  by_contract?: NameY[]
  trend?: TrendPoint[]
  training_categories?: NameY[]
  training_skills?: NameY[]
  avg_score?: number | null
  score_bands?: { outstanding?: number; satisfactory?: number; poor?: number; not_rated?: number } | null
  division_averages?: AvgRow[]
  funder_averages?: AvgRow[]
}

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()

const tab = ref<HubTab>('dashboard')
const analyticsPhase = ref<AnalyticsPhase>('ppa')
const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<PerformanceHubData | null>(null)
const analytics = ref<AnalyticsPayload | null>(null)
const funders = ref<Array<{ funder_id: number; funder: string }>>([])
const period = ref<string>('')
const divisionId = ref<number | null>(null)
const funderId = ref<number | null>(null)
const page = ref(1)
const perPage = ref(25)
const pendingPage = ref(1)
const pendingPerPage = ref(25)
const exporting = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    if (tab.value === 'analytics') {
      const { data: res } = await api.get<{
        data: AnalyticsPayload
        meta: {
          periods: string[]
          divisions: Array<{ division_id: number; division_name: string }>
          funders?: Array<{ funder_id: number; funder: string }>
        }
      }>('/api/v1/performance/analytics', {
        params: {
          phase: analyticsPhase.value,
          period: period.value || undefined,
          division_id: divisionId.value || undefined,
          funder_id: analyticsPhase.value === 'endterm' ? funderId.value || undefined : undefined,
        },
      })
      analytics.value = res.data
      funders.value = res.meta.funders || []
      if (!period.value && typeof res.data.period === 'string') {
        period.value = res.data.period
      }
      if (data.value) {
        data.value = {
          ...data.value,
          periods: res.meta.periods || data.value.periods,
          divisions: res.meta.divisions || data.value.divisions,
        }
      } else {
        data.value = {
          summary: { total: 0, approved: 0, submitted: 0, draft: 0, without_ppa: 0 },
          periods: res.meta.periods || [],
          period: String(res.data.period || ''),
          divisions: res.meta.divisions || [],
          pending: [],
          pending_count: 0,
          workflow_summary: {},
          submission_windows: {},
          ppa_submission_open: false,
          self_actions: null,
          my_ppas: null,
        }
      }
      // Keep personal create/open actions available while viewing analytics.
      const hub = await fetchPerformanceHub({
        tab: 'dashboard',
        period: period.value || undefined,
      })
      data.value = {
        ...(data.value as PerformanceHubData),
        self_actions: hub.self_actions,
        create_ppa_url: hub.create_ppa_url,
        ppa_submission_open: hub.ppa_submission_open,
        midterm_submission_open: hub.midterm_submission_open,
        endterm_submission_open: hub.endterm_submission_open,
        submission_windows: hub.submission_windows,
        periods: hub.periods?.length ? hub.periods : data.value?.periods || [],
      }
    } else {
    data.value = await fetchPerformanceHub({
        tab: tab.value === 'my' ? 'my' : tab.value === 'pending' ? 'pending' : 'dashboard',
      period: period.value || undefined,
      division_id: divisionId.value,
      page: page.value,
        per_page: perPage.value,
    })
    if (!period.value && data.value.period) {
      period.value = data.value.period
      }
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load performance')
  } finally {
    loading.value = false
  }
}

function setTab(next: HubTab) {
  tab.value = next
  page.value = 1
  pendingPage.value = 1
  const query: Record<string, string> = { tab: next }
  if (next === 'analytics') query.phase = analyticsPhase.value
  void router.replace({ query })
}

const hubTabItems = computed<PortalPillNavItem[]>(() => [
  {
    key: 'dashboard',
    label: locale.t('subnav.perf_my_forms', 'My forms'),
    icon: 'fa-solid fa-file-lines',
    active: tab.value === 'dashboard',
  },
  {
    key: 'my',
    label: locale.t('subnav.perf_history', 'History'),
    icon: 'fa-solid fa-clock-rotate-left',
    active: tab.value === 'my',
  },
  {
    key: 'pending',
    label: locale.t('subnav.perf_pending', 'Pending reviews'),
    icon: 'fa-solid fa-clipboard-check',
    active: tab.value === 'pending',
    badge: data.value?.pending_count || null,
  },
  {
    key: 'analytics',
    label: locale.t('subnav.perf_analytics', 'Analytics'),
    icon: 'fa-solid fa-chart-line',
    active: tab.value === 'analytics',
  },
])

const analyticsPhaseItems = computed<PortalPillNavItem[]>(() => [
  { key: 'ppa', label: locale.t('subnav.ppa', 'PPA'), icon: 'fa-solid fa-flag', active: analyticsPhase.value === 'ppa' },
  {
    key: 'midterm',
    label: locale.t('subnav.midterm', 'Midterm'),
    icon: 'fa-solid fa-chart-simple',
    active: analyticsPhase.value === 'midterm',
  },
  {
    key: 'endterm',
    label: locale.t('subnav.endterm', 'Endterm'),
    icon: 'fa-solid fa-flag-checkered',
    active: analyticsPhase.value === 'endterm',
  },
])

const myTotal = computed(() => data.value?.my_ppas?.meta.total ?? 0)
const myLastPage = computed(() => data.value?.my_ppas?.meta.last_page ?? 1)
const myRows = computed(() => data.value?.my_ppas?.data || [])

const pendingAll = computed(() => data.value?.pending || [])
const pendingTotal = computed(() => pendingAll.value.length)
const pendingLastPage = computed(() =>
  Math.max(1, Math.ceil(pendingTotal.value / pendingPerPage.value)),
)
const pendingRows = computed(() => {
  const start = (pendingPage.value - 1) * pendingPerPage.value
  return pendingAll.value.slice(start, start + pendingPerPage.value)
})

function onMyPerPage(v: number) {
  perPage.value = v
  page.value = 1
}

function onPendingPerPage(v: number) {
  pendingPerPage.value = v
  pendingPage.value = 1
}

function exportMyCsv() {
  exporting.value = true
  try {
    downloadClientCsv(
      'performance-history.csv',
      ['Period', 'PPA', 'Midterm', 'Endterm', 'Updated'],
      myRows.value.map((row) => [
        row.performance_period || '',
        row.draft_status_label || row.draft_status || '',
        row.midterm_status_label || '',
        row.endterm_status_label || '',
        row.updated_at || row.created_at || '',
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportMyPdf() {
  exporting.value = true
  try {
    openClientPdfTable(
      'Performance history',
      ['Period', 'PPA', 'Midterm', 'Endterm', 'Updated'],
      myRows.value.map((row) => [
        row.performance_period || '',
        row.draft_status_label || row.draft_status || '',
        row.midterm_status_label || '',
        row.endterm_status_label || '',
        row.updated_at || row.created_at || '',
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportPendingCsv() {
  exporting.value = true
  try {
    downloadClientCsv(
      'performance-pending.csv',
      ['Staff', 'Type', 'Period', 'Status'],
      pendingAll.value.map((row) => [
        row.staff_name || row.staff_id || '',
        row.approval_type_label || row.approval_type || '',
        row.performance_period || '',
        row.overall_status || '',
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportPendingPdf() {
  exporting.value = true
  try {
    openClientPdfTable(
      'Pending performance reviews',
      ['Staff', 'Type', 'Period', 'Status'],
      pendingAll.value.map((row) => [
        row.staff_name || row.staff_id || '',
        row.approval_type_label || row.approval_type || '',
        row.performance_period || '',
        row.overall_status || '',
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function setAnalyticsPhase(phase: AnalyticsPhase) {
  analyticsPhase.value = phase
  if (phase !== 'endterm') funderId.value = null
  void router.replace({ query: { tab: 'analytics', phase } })
}

function openForm(url: unknown) {
  const href = typeof url === 'string' ? url : ''
  if (!href) return
  if (href === '/performance/create' || href.startsWith('/performance/create?')) {
    void router.push({
      path: '/performance/create',
      query: period.value ? { period: period.value } : {},
    })
    return
  }
  if (href.startsWith('/performance')) {
    void router.push(href)
    return
  }
  window.location.assign(href)
}

function analyticsExportParams() {
  return {
    phase: analyticsPhase.value,
    period: period.value,
    division_id: divisionId.value,
    funder_id: analyticsPhase.value === 'endterm' ? funderId.value : undefined,
  }
}

async function exportAnalyticsCsv() {
  exporting.value = true
  try {
    await downloadApiExport(
      '/api/v1/performance/analytics/export/csv',
      `performance-${analyticsPhase.value}-dashboard.csv`,
      analyticsExportParams(),
    )
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

function printAnalytics() {
  window.print()
}

function namedSeries(rows: NameY[] | undefined, seriesName: string) {
  const list = rows || []
  return {
    categories: list.map((r) => r.name),
    series: [{ name: seriesName, data: list.map((r) => Number(r.y) || 0) }],
  }
}

const summary = computed(() => analytics.value?.summary || {})
const isEndterm = computed(() => analyticsPhase.value === 'endterm')
const isTrainingPhase = computed(() => analyticsPhase.value === 'ppa' || analyticsPhase.value === 'midterm')

const kpiCards = computed(() => {
  const s = summary.value
  if (isEndterm.value) {
    return [
      { label: 'Endterm reviews', value: s.total ?? 0, icon: 'fa-solid fa-file-lines', color: '#911C39' },
      { label: 'Approved', value: s.approved ?? 0, icon: 'fa-solid fa-circle-check', color: '#119A48' },
      { label: 'Require calibration', value: s.require_calibration ?? 0, icon: 'fa-solid fa-triangle-exclamation', color: '#fbb924' },
      { label: 'Without endterm', value: s.without ?? 0, icon: 'fa-solid fa-user-xmark', color: '#C3A366' },
    ]
  }
  const noun = analyticsPhase.value === 'midterm' ? 'midterms' : 'PPAs'
  return [
    { label: `Staff ${noun}`, value: s.total ?? 0, icon: 'fa-solid fa-file-lines', color: '#911C39' },
    { label: 'Approved', value: s.approved ?? 0, icon: 'fa-solid fa-circle-check', color: '#119A48' },
    { label: 'Staff PDPs', value: s.pdps ?? 0, icon: 'fa-solid fa-user-check', color: '#385CAD' },
    { label: `Without ${noun}`, value: s.without ?? 0, icon: 'fa-solid fa-user-xmark', color: '#C3A366' },
  ]
})

const approvalSeries = computed(() => [
  {
    name: 'Status',
    data: (analytics.value?.approval_breakdown || []).map((r) => ({
      name: r.name,
      y: Number(r.y) || 0,
    })),
  },
])

const contractChart = computed(() =>
  namedSeries(analytics.value?.by_contract, analyticsPhase.value === 'endterm' ? 'Endterm reviews' : 'Submissions'),
)
const divisionChart = computed(() => namedSeries(analytics.value?.by_division, 'Submissions'))
const trainingCategoriesChart = computed(() => namedSeries(analytics.value?.training_categories, 'Requests'))
const trainingSkillsChart = computed(() => namedSeries(analytics.value?.training_skills, 'Skills'))
const trendCategories = computed(() => (analytics.value?.trend || []).map((t) => t.date))
const trendSeries = computed(() => [
  { name: 'Submissions', data: (analytics.value?.trend || []).map((t) => Number(t.count) || 0) },
])
const scoreBandsSeries = computed(() => [
  {
    name: 'Number of staff',
    data: [
      Number(analytics.value?.score_bands?.outstanding) || 0,
      Number(analytics.value?.score_bands?.satisfactory) || 0,
      Number(analytics.value?.score_bands?.poor) || 0,
      Number(analytics.value?.score_bands?.not_rated) || 0,
    ],
  },
])
const divisionAvgChart = computed(() => {
  const rows = analytics.value?.division_averages || []
  return {
    categories: rows.map((r) => r.name),
    series: [{ name: 'Average score', data: rows.map((r) => Number(r.avg_score) || 0) }],
  }
})
const funderAvgChart = computed(() => {
  const rows = analytics.value?.funder_averages || []
  return {
    categories: rows.map((r) => r.name),
    series: [{ name: 'Average score', data: rows.map((r) => Number(r.avg_score) || 0) }],
  }
})
const avgApprovalSeries = computed(() => [
  { name: 'Days', data: [Number(analytics.value?.avg_approval_days) || 0] },
])
const avgScoreSeries = computed(() => [
  { name: 'Score', data: [Number(analytics.value?.avg_score) || 0] },
])

const self = computed(() => data.value?.self_actions || null)

const formCards = computed(() => {
  const s = self.value
  if (!s || s.staff_id < 1) return []
  return [
    {
      key: 'ppa',
      title: 'PPA',
      subtitle: s.ppa_exists ? 'Open your current performance agreement' : 'Start your PPA for this period',
      status: s.ppa_exists ? (s.ppa_approved ? 'Approved' : 'In progress') : 'Not started',
      windowOpen: !!data.value?.ppa_submission_open,
      actionLabel: s.show_create_ppa ? 'Create PPA' : s.show_current_ppa ? 'Open PPA' : 'Unavailable',
      url: s.show_create_ppa ? s.create_ppa_url : s.current_ppa_url,
      enabled: !!(s.show_create_ppa ? s.create_ppa_url : s.current_ppa_url),
      isCreate: !!s.show_create_ppa,
    },
    {
      key: 'midterm',
      title: 'Midterm review',
      subtitle: s.midterm_exists ? 'Continue your midterm appraisal' : 'Open midterm after PPA approval',
      status: s.midterm_exists ? 'In progress' : s.ppa_approved ? 'Ready' : 'Needs approved PPA',
      windowOpen: !!s.midterm_window_open,
      actionLabel: s.midterm_label || 'Midterm',
      url: s.midterm_url,
      enabled: !!s.show_midterm && !!s.midterm_url,
      isCreate: !!s.show_midterm && !s.midterm_exists,
    },
    {
      key: 'endterm',
      title: 'Endterm review',
      subtitle: s.endterm_exists ? 'Continue your endterm appraisal' : 'Open endterm after PPA approval',
      status: s.endterm_exists ? 'In progress' : s.ppa_approved ? 'Ready' : 'Needs approved PPA',
      windowOpen: !!s.endterm_window_open,
      actionLabel: s.endterm_label || 'Endterm',
      url: s.endterm_url,
      enabled: !!s.show_endterm && !!s.endterm_url,
      isCreate: !!s.show_endterm && !s.endterm_exists,
    },
  ]
})

const filtersReady = ref(false)

watch([tab, analyticsPhase, period, divisionId, funderId, page, perPage], () => {
  if (!filtersReady.value) return
  void load()
})

onMounted(() => {
  const q = String(route.query.tab || 'dashboard')
  if (q === 'my' || q === 'pending' || q === 'dashboard' || q === 'analytics') {
    tab.value = q as HubTab
  }
  const p = String(route.query.phase || 'ppa')
  if (p === 'ppa' || p === 'midterm' || p === 'endterm') {
    analyticsPhase.value = p
  }
  void load().finally(() => {
    // Avoid a second hub fetch when load() defaults `period` from the API response.
    filtersReady.value = true
  })
})
</script>

<template>
  <div>
    <PortalPageChrome
      title="Performance"
      lede="Create and manage your PPA, midterm, and endterm reviews for the selected period."
    >
      <template #tabs>
        <PortalPillSubnav
          :items="hubTabItems"
          :aria-label="locale.t('subnav.perf_sections', 'Performance sections')"
          @select="(key) => setTab(key as HubTab)"
        />
      </template>
      <template #actions>
        <template v-if="tab === 'analytics'">
          <v-btn size="small" variant="tonal" prepend-icon="mdi-printer" class="me-2" @click="printAnalytics">
            Print
          </v-btn>
          <v-btn
            size="small"
            variant="outlined"
            prepend-icon="mdi-file-delimited"
            :loading="exporting"
            @click="exportAnalyticsCsv"
          >
            CSV
          </v-btn>
        </template>
      </template>
    </PortalPageChrome>

    <div class="portal-staff-filters analytics-filters">
      <v-row dense>
        <v-col cols="12" sm="6" :md="tab === 'analytics' && isEndterm ? 3 : 4">
        <v-select
          v-model="period"
          :items="(data?.periods || []).map((p) => ({ title: p, value: p }))"
            label="Performance period"
          density="compact"
          hide-details
        />
      </v-col>
        <v-col v-if="tab === 'analytics'" cols="12" sm="6" :md="isEndterm ? 3 : 4">
        <v-select
          v-model="divisionId"
            :items="[
              { title: 'All divisions', value: null },
              ...(data?.divisions || []).map((d) => ({ title: d.division_name, value: d.division_id })),
            ]"
          label="Division"
          density="compact"
          hide-details
        />
      </v-col>
        <v-col v-if="tab === 'analytics' && isEndterm" cols="12" sm="6" md="3">
          <v-select
            v-model="funderId"
            :items="[
              { title: 'All funders', value: null },
              ...funders.map((f) => ({ title: f.funder, value: f.funder_id })),
            ]"
            label="Funder"
            density="compact"
            hide-details
          />
        </v-col>
    </v-row>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="tab === 'analytics' && analytics">
      <PortalPillSubnav
        class="mb-3 analytics-filters"
        :items="analyticsPhaseItems"
        :aria-label="locale.t('subnav.analytics_phase', 'Analytics phase')"
        @select="(key) => setAnalyticsPhase(key as AnalyticsPhase)"
      />

      <v-row dense class="mb-4">
        <v-col v-for="card in kpiCards" :key="card.label" cols="12" sm="6" md="3">
          <v-sheet rounded border class="pa-4 perf-kpi perf-kpi--muted">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-uppercase perf-kpi__label">{{ card.label }}</div>
                <div class="text-h5 font-weight-bold perf-kpi__value">{{ card.value }}</div>
              </div>
              <i :class="card.icon" class="perf-kpi__icon" aria-hidden="true" />
            </div>
          </v-sheet>
        </v-col>
      </v-row>

      <v-row dense>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="approvalSeries[0].data.some((d) => d.y > 0)"
              title="Approval status breakdown"
              type="pie"
              :series="approvalSeries"
              :colors="['#119A48', '#fbb924']"
              :height="300"
            />
            <div v-else class="text-medium-emphasis text-body-2">No approval data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="!isEndterm" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="contractChart.categories.length"
              title="Completion by contract type"
              type="bar"
              :categories="contractChart.categories"
              :series="contractChart.series"
              color="#911C39"
              y-axis-title="Submissions"
              :height="300"
            />
            <div v-else class="text-medium-emphasis text-body-2">No contract data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="isEndterm" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              title="Average performance score"
              type="solidgauge"
              :series="avgScoreSeries"
              gauge-unit="out of 100"
              :y-axis-max="100"
              :height="300"
            />
          </v-sheet>
        </v-col>

        <v-col v-if="!isEndterm" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              title="Average approval time"
              type="solidgauge"
              :series="avgApprovalSeries"
              gauge-unit="days"
              :y-axis-max="30"
              :height="300"
            />
          </v-sheet>
        </v-col>

        <v-col v-if="isTrainingPhase" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="trainingCategoriesChart.categories.length"
              title="Training categories from PPA"
              type="bar"
              :categories="trainingCategoriesChart.categories"
              :series="trainingCategoriesChart.series"
              color="#C3A366"
              y-axis-title="Requests"
              :height="300"
            />
            <div v-else class="text-medium-emphasis text-body-2">No training category data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="isEndterm" cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              title="Performance score distribution"
              type="column"
              :categories="[
                'Outstanding (80–100)',
                'Satisfactory (51–79)',
                'Poor (0–50)',
                'Not rated – new in position',
              ]"
              :series="scoreBandsSeries"
              :colors="['#119A48']"
              color="#119A48"
              y-axis-title="Number of staff"
              :height="360"
            />
          </v-sheet>
        </v-col>

        <v-col v-if="isEndterm" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="divisionAvgChart.categories.length"
              title="Average score by division"
              type="bar"
              :categories="divisionAvgChart.categories"
              :series="divisionAvgChart.series"
              color="#119A48"
              y-axis-title="Average score"
              :y-axis-max="100"
              :height="360"
            />
            <div v-else class="text-medium-emphasis text-body-2">No division score data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="isEndterm" cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="contractChart.categories.length"
              title="Endterm completion by contract type"
              type="bar"
              :categories="contractChart.categories"
              :series="contractChart.series"
              color="#911C39"
              y-axis-title="Endterm reviews"
              :height="360"
            />
            <div v-else class="text-medium-emphasis text-body-2">No contract data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="isEndterm" cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="funderAvgChart.categories.length"
              title="Average score by funder"
              type="bar"
              :categories="funderAvgChart.categories"
              :series="funderAvgChart.series"
              color="#C3A366"
              y-axis-title="Average score"
              :y-axis-max="100"
              :height="360"
            />
            <div v-else class="text-medium-emphasis text-body-2">No funder score data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="!isEndterm" cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="divisionChart.categories.length"
              title="Submissions by division"
              type="column"
              :categories="divisionChart.categories"
              :series="divisionChart.series"
              color="#119A48"
              y-axis-title="Submissions"
              :height="360"
            />
            <div v-else class="text-medium-emphasis text-body-2">No division data</div>
          </v-sheet>
        </v-col>

        <v-col v-if="isTrainingPhase" cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="trainingSkillsChart.categories.length"
              title="Top 10 training skills requested"
              type="bar"
              :categories="trainingSkillsChart.categories"
              :series="trainingSkillsChart.series"
              color="#fbb924"
              y-axis-title="Mentions"
              :height="360"
            />
            <div v-else class="text-medium-emphasis text-body-2">No training skills data</div>
          </v-sheet>
        </v-col>

        <v-col cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="trendCategories.length"
              title="Submission trend over time"
              type="area"
              :categories="trendCategories"
              :series="trendSeries"
              color="#119A48"
              y-axis-title="Submissions"
              :height="320"
            />
            <div v-else class="text-medium-emphasis text-body-2">No trend data</div>
          </v-sheet>
        </v-col>
      </v-row>
    </template>

    <template v-else-if="data && tab !== 'analytics'">
      <template v-if="tab === 'dashboard'">
        <v-row dense class="mb-4">
          <v-col v-for="card in formCards" :key="card.key" cols="12" md="4">
            <v-card
              variant="outlined"
              class="h-100 perf-form-card"
              :class="{ 'perf-form-card--ready': card.enabled && card.isCreate }"
            >
              <v-card-text class="d-flex flex-column ga-3">
                <div class="d-flex justify-space-between align-start ga-2">
                  <div>
                    <div class="text-h6">{{ card.title }}</div>
                    <div class="text-body-2 text-medium-emphasis">{{ card.subtitle }}</div>
                  </div>
                  <v-chip size="small" variant="outlined">
                    {{ card.windowOpen ? 'Window open' : 'Window closed' }}
                  </v-chip>
                </div>
                <div class="text-caption text-medium-emphasis">Status: {{ card.status }}</div>
                <v-btn
                  class="perf-form-card__action"
                  :color="card.enabled ? 'primary' : undefined"
                  :variant="card.enabled ? 'flat' : 'outlined'"
                  size="large"
                  block
                  :disabled="!card.enabled"
                  @click="openForm(card.url)"
                >
                  <i
                    v-if="card.isCreate && card.enabled"
                    class="fa-solid fa-plus me-2"
                    aria-hidden="true"
                  />
                  <i
                    v-else-if="card.enabled"
                    class="fa-solid fa-folder-open me-2"
                    aria-hidden="true"
                  />
                  {{ card.actionLabel }}
                </v-btn>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col v-if="!formCards.length" cols="12">
            <v-alert type="warning" variant="tonal">
              Your account is not linked to a staff profile, so personal forms are unavailable.
            </v-alert>
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Submission windows</v-card-title>
              <v-card-text>
                <div
                  v-for="(win, key) in data.submission_windows"
                  :key="key"
                  class="perf-window-row mb-2"
                >
                  <div class="d-flex align-start justify-space-between ga-2">
                    <div>
                      <div class="font-weight-medium">{{ win.label || key }}</div>
                      <div class="text-body-2 text-medium-emphasis">{{ win.message }}</div>
                    </div>
                    <v-chip size="x-small" variant="outlined">
                      {{ win.open ? 'Open' : 'Closed' }}
                    </v-chip>
                  </div>
              </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <i class="fa-solid fa-users text-medium-emphasis" aria-hidden="true" />
                Team snapshot
              </v-card-title>
              <v-card-text>
                <v-row dense>
                  <v-col
                    v-for="card in [
                      { label: 'Submitted+', value: data.summary.total, icon: 'fa-solid fa-paper-plane' },
                      { label: 'Approved', value: data.summary.approved, icon: 'fa-solid fa-circle-check' },
                      { label: 'Draft', value: data.summary.draft, icon: 'fa-solid fa-pen-to-square' },
                      { label: 'Without PPA', value: data.summary.without_ppa, icon: 'fa-solid fa-triangle-exclamation' },
                    ]"
                    :key="card.label"
                    cols="6"
                  >
                    <v-sheet rounded border class="pa-3 perf-kpi perf-kpi--muted">
                      <div class="d-flex align-center justify-space-between">
                        <div>
                          <div class="text-caption text-uppercase perf-kpi__label">{{ card.label }}</div>
                          <div class="text-h6 font-weight-bold perf-kpi__value">{{ card.value }}</div>
                        </div>
                        <i :class="card.icon" class="perf-kpi__icon" aria-hidden="true" />
                      </div>
            </v-sheet>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>

      <template v-else-if="tab === 'my'">
        <v-card class="portal-data-table-card mb-3" variant="outlined">
          <div class="px-3 pt-1">
            <PortalTableToolbar
              placement="header"
              :page="page"
              :last-page="myLastPage"
              :total="myTotal"
              :per-page="perPage"
              total-label="Total forms"
              :exporting="exporting"
              @update:per-page="onMyPerPage"
              @export-csv="exportMyCsv"
              @export-pdf="exportMyPdf"
            />
          </div>
          <v-table density="compact">
          <thead>
            <tr>
                <th style="width: 3rem">#</th>
              <th>Period</th>
                <th>PPA</th>
                <th>Midterm</th>
                <th>Endterm</th>
              <th>Updated</th>
                <th></th>
            </tr>
          </thead>
          <tbody>
              <tr v-for="(row, i) in myRows" :key="String(row.entry_id ?? i)">
                <td>
                  <span class="portal-dt-row-num">{{ (page - 1) * perPage + i + 1 }}</span>
                </td>
              <td>{{ row.performance_period || '—' }}</td>
                <td>{{ row.draft_status_label || row.draft_status || '—' }}</td>
                <td>{{ row.midterm_status_label || '—' }}</td>
                <td>{{ row.endterm_status_label || '—' }}</td>
              <td>{{ row.updated_at || row.created_at || '—' }}</td>
                <td class="text-end text-no-wrap">
                  <v-btn
                    v-if="row.form_url"
                    size="x-small"
                    variant="tonal"
                    class="me-1"
                    @click="openForm(row.form_url)"
                  >
                    PPA
                  </v-btn>
                  <v-btn
                    v-if="row.midterm_url"
                    size="x-small"
                    variant="text"
                    class="me-1"
                    @click="openForm(row.midterm_url)"
                  >
                    Midterm
                  </v-btn>
                  <v-btn
                    v-if="row.endterm_url"
                    size="x-small"
                    variant="text"
                    class="me-1"
                    @click="openForm(row.endterm_url)"
                  >
                    Endterm
                  </v-btn>
                  <v-btn
                    v-if="row.entry_id"
                    size="x-small"
                    variant="text"
                    @click="openApiPdf(`/api/v1/performance/entries/${row.entry_id}/print`, { phase: 'ppa' })"
                  >
                    PDF
                  </v-btn>
                </td>
            </tr>
              <tr v-if="!myRows.length">
                <td colspan="7" class="text-medium-emphasis text-center py-6">
                  No performance forms for this period.
                </td>
            </tr>
          </tbody>
        </v-table>
          <div class="px-3 pb-1">
            <PortalTableToolbar
              placement="footer"
              :page="page"
              :last-page="myLastPage"
              :total="myTotal"
              :per-page="perPage"
              :show-csv="false"
              :show-pdf="false"
              :show-per-page="false"
              @update:page="(v) => (page = v)"
            />
        </div>
        </v-card>
      </template>

      <template v-else>
        <v-card class="portal-data-table-card mb-3" variant="outlined">
          <div class="px-3 pt-1">
            <PortalTableToolbar
              placement="header"
              :page="pendingPage"
              :last-page="pendingLastPage"
              :total="pendingTotal"
              :per-page="pendingPerPage"
              total-label="Total pending"
              :exporting="exporting"
              @update:per-page="onPendingPerPage"
              @export-csv="exportPendingCsv"
              @export-pdf="exportPendingPdf"
            />
          </div>
        <v-table density="compact">
          <thead>
            <tr>
                <th style="width: 3rem">#</th>
              <th>Staff</th>
              <th>Type</th>
              <th>Period</th>
              <th>Status</th>
                <th></th>
            </tr>
          </thead>
          <tbody>
              <tr v-for="(row, i) in pendingRows" :key="String(row.entry_id ?? i)">
                <td>
                  <span class="portal-dt-row-num">{{ (pendingPage - 1) * pendingPerPage + i + 1 }}</span>
                </td>
              <td>{{ row.staff_name || row.staff_id || '—' }}</td>
                <td>{{ row.approval_type_label || row.approval_type || '—' }}</td>
              <td>{{ row.performance_period || '—' }}</td>
              <td>{{ row.overall_status || '—' }}</td>
                <td class="text-end">
                  <v-btn
                    v-if="row.form_url"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    @click="openForm(row.form_url)"
                  >
                    Review
                  </v-btn>
                </td>
            </tr>
              <tr v-if="!pendingRows.length">
                <td colspan="6" class="text-medium-emphasis text-center py-6">No pending actions.</td>
            </tr>
          </tbody>
        </v-table>
          <div class="px-3 pb-1">
            <PortalTableToolbar
              placement="footer"
              :page="pendingPage"
              :last-page="pendingLastPage"
              :total="pendingTotal"
              :per-page="pendingPerPage"
              :show-csv="false"
              :show-pdf="false"
              :show-per-page="false"
              @update:page="(v) => (pendingPage = v)"
            />
          </div>
        </v-card>
      </template>
    </template>
  </div>
</template>

<style scoped>
.perf-form-card {
  min-height: 100%;
  background: #fff;
}
.perf-form-card--ready {
  border-color: rgba(58, 71, 82, 0.22) !important;
}
.perf-form-card__action {
  font-weight: 700;
  letter-spacing: 0.02em;
  margin-top: auto;
}
.perf-form-card__action.v-btn--disabled {
  opacity: 0.55;
}
.perf-window-row {
  padding: 0.65rem 0.75rem;
  border: 1px solid rgba(58, 71, 82, 0.12);
  border-radius: 0.5rem;
  background: #fff;
}
.perf-kpi {
  min-height: 5.5rem;
}
.perf-kpi--muted {
  --perf-kpi-bg: #f8fafc !important;
  background: #f8fafc !important;
  border-color: rgba(58, 71, 82, 0.12) !important;
}
.perf-kpi--muted .perf-kpi__label {
  color: rgba(58, 71, 82, 0.62) !important;
  letter-spacing: 0.04em;
  margin-bottom: 0.25rem;
}
.perf-kpi--muted .perf-kpi__value {
  color: #3a4752 !important;
}
.perf-kpi--muted .perf-kpi__icon {
  font-size: 1.35rem;
  color: rgba(58, 71, 82, 0.28) !important;
}
@media print {
  .analytics-filters,
  :deep(.portal-page-chrome__actions),
  :deep(.portal-pill-subnav) {
    display: none !important;
  }
}
</style>
