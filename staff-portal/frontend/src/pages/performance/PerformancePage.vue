<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { api } from '@/lib/api'
import {
  fetchPerformanceHub,
  type PerformanceHubData,
  type PerformanceTab,
} from '@/lib/performanceApi'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'

type HubTab = PerformanceTab | 'analytics'
type AnalyticsPhase = 'ppa' | 'midterm' | 'endterm'

const route = useRoute()
const router = useRouter()

const tab = ref<HubTab>('dashboard')
const analyticsPhase = ref<AnalyticsPhase>('ppa')
const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<PerformanceHubData | null>(null)
const analytics = ref<Record<string, unknown> | null>(null)
const period = ref<string>('')
const divisionId = ref<number | null>(null)
const page = ref(1)
const exporting = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    if (tab.value === 'analytics') {
      const { data: res } = await api.get<{ data: Record<string, unknown>; meta: { periods: string[]; divisions: Array<{ division_id: number; division_name: string }> } }>(
        '/api/v1/performance/analytics',
        {
          params: {
            phase: analyticsPhase.value,
            period: period.value || undefined,
            division_id: divisionId.value || undefined,
          },
        },
      )
      analytics.value = res.data
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
          my_ppas: null,
        }
      }
    } else {
      data.value = await fetchPerformanceHub({
        tab: tab.value === 'my' ? 'my' : tab.value === 'pending' ? 'pending' : 'dashboard',
        period: period.value || undefined,
        division_id: divisionId.value,
        page: page.value,
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
  const query: Record<string, string> = { tab: next }
  if (next === 'analytics') query.phase = analyticsPhase.value
  void router.replace({ query })
}

function setAnalyticsPhase(phase: AnalyticsPhase) {
  analyticsPhase.value = phase
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

async function exportAnalyticsCsv() {
  exporting.value = true
  try {
    await downloadApiExport('/api/v1/performance/analytics/export.csv', `performance-${analyticsPhase.value}.csv`, {
      phase: analyticsPhase.value,
      period: period.value,
      division_id: divisionId.value,
    })
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

watch([tab, analyticsPhase, period, divisionId, page], () => void load())

onMounted(() => {
  const q = String(route.query.tab || 'dashboard')
  if (q === 'my' || q === 'pending' || q === 'dashboard' || q === 'analytics') {
    tab.value = q as HubTab
  }
  const p = String(route.query.phase || 'ppa')
  if (p === 'ppa' || p === 'midterm' || p === 'endterm') {
    analyticsPhase.value = p
  }
  void load()
})
</script>

<template>
  <div>
    <PortalPageChrome title="Performance" lede="PPA, midterm, and endterm — hub, analytics, and forms.">
      <template #tabs>
        <v-tabs
          :model-value="tab"
          color="primary"
          align-tabs="start"
          density="compact"
          @update:model-value="(v) => setTab(v as HubTab)"
        >
          <v-tab value="dashboard">Overview</v-tab>
          <v-tab value="analytics">Analytics</v-tab>
          <v-tab value="my">My PPAs</v-tab>
          <v-tab value="pending">
            Pending
            <v-badge
              v-if="data?.pending_count"
              :content="data.pending_count"
              color="warning"
              inline
              class="ms-1"
            />
          </v-tab>
        </v-tabs>
      </template>
      <template #actions>
        <v-btn
          v-if="data?.ppa_submission_open && data.create_ppa_url"
          size="small"
          color="primary"
          @click="openForm(data.create_ppa_url)"
        >
          Create PPA
        </v-btn>
        <v-btn
          v-if="tab === 'analytics'"
          size="small"
          variant="outlined"
          class="ms-2"
          :loading="exporting"
          @click="exportAnalyticsCsv"
        >
          CSV
        </v-btn>
      </template>
    </PortalPageChrome>

    <v-row dense class="mb-3">
      <v-col cols="12" sm="6" md="4">
        <v-select
          v-model="period"
          :items="(data?.periods || []).map((p) => ({ title: p, value: p }))"
          label="Period"
          density="compact"
          hide-details
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
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
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="tab === 'analytics' && analytics">
      <v-tabs
        :model-value="analyticsPhase"
        color="primary"
        density="compact"
        class="mb-3"
        @update:model-value="(v) => setAnalyticsPhase(v as AnalyticsPhase)"
      >
        <v-tab value="ppa">PPA</v-tab>
        <v-tab value="midterm">Midterm</v-tab>
        <v-tab value="endterm">Endterm</v-tab>
      </v-tabs>

      <v-row dense class="mb-4">
        <v-col
          v-for="card in [
            { label: 'Submitted+', value: (analytics.summary as any)?.total },
            { label: 'Approved', value: (analytics.summary as any)?.approved },
            { label: 'Submitted', value: (analytics.summary as any)?.submitted },
            { label: 'Draft', value: (analytics.summary as any)?.draft },
            { label: 'Without', value: (analytics.summary as any)?.without },
          ]"
          :key="card.label"
          cols="6"
          md="2"
        >
          <v-sheet border rounded class="pa-3">
            <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
            <div class="text-h6">{{ card.value ?? 0 }}</div>
          </v-sheet>
        </v-col>
      </v-row>

      <v-row dense>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3">
            <div class="text-subtitle-2 mb-2">By division</div>
            <v-list density="compact" max-height="320" style="overflow:auto">
              <v-list-item v-for="(row, i) in (analytics.by_division as any[]) || []" :key="i">
                <v-list-item-title>{{ row.label }}</v-list-item-title>
                <template #append><span>{{ row.value }}</span></template>
              </v-list-item>
            </v-list>
          </v-sheet>
        </v-col>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3">
            <div class="text-subtitle-2 mb-2">Submission trend</div>
            <v-list density="compact" max-height="320" style="overflow:auto">
              <v-list-item v-for="(row, i) in (analytics.trend as any[]) || []" :key="i">
                <v-list-item-title>{{ row.date }}</v-list-item-title>
                <template #append><span>{{ row.count }}</span></template>
              </v-list-item>
              <v-list-item v-if="!((analytics.trend as any[]) || []).length">
                <v-list-item-title class="text-medium-emphasis">No trend data</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-sheet>
        </v-col>
      </v-row>
    </template>

    <template v-else-if="data && tab !== 'analytics'">
      <template v-if="tab === 'dashboard'">
        <v-row dense class="mb-4">
          <v-col
            v-for="card in [
              { label: 'Submitted+', value: data.summary.total },
              { label: 'Approved', value: data.summary.approved },
              { label: 'Submitted', value: data.summary.submitted },
              { label: 'Draft', value: data.summary.draft },
              { label: 'Without PPA', value: data.summary.without_ppa },
            ]"
            :key="card.label"
            cols="6"
            md="2"
          >
            <v-sheet border rounded class="pa-3">
              <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
              <div class="text-h6">{{ card.value }}</div>
            </v-sheet>
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="12" md="6">
            <v-sheet border rounded class="pa-3">
              <div class="text-subtitle-2 mb-2">Workflow</div>
              <div v-for="(line, key) in data.workflow_summary" :key="key" class="mb-2">
                <div class="text-caption text-uppercase text-medium-emphasis">{{ key }}</div>
                <div class="text-body-2">{{ line }}</div>
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="12" md="6">
            <v-sheet border rounded class="pa-3">
              <div class="text-subtitle-2 mb-2">Submission windows</div>
              <v-alert
                v-for="(win, key) in data.submission_windows"
                :key="key"
                class="mb-2"
                density="compact"
                :type="win.open ? 'success' : 'warning'"
                variant="tonal"
              >
                <strong>{{ win.label || key }}:</strong> {{ win.message }}
              </v-alert>
            </v-sheet>
          </v-col>
        </v-row>
      </template>

      <template v-else-if="tab === 'my'">
        <v-table density="compact" class="mb-3">
          <thead>
            <tr>
              <th>Period</th>
              <th>PPA</th>
              <th>Midterm</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in data.my_ppas?.data || []" :key="String(row.entry_id ?? i)">
              <td>{{ row.performance_period || '—' }}</td>
              <td>{{ row.draft_status_label || row.draft_status || '—' }}</td>
              <td>{{ row.midterm_status_label || '—' }}</td>
              <td>{{ row.updated_at || row.created_at || '—' }}</td>
              <td class="text-end text-no-wrap">
                <v-btn
                  v-if="row.form_url"
                  size="x-small"
                  variant="tonal"
                  class="me-1"
                  @click="openForm(row.form_url)"
                >
                  Open PPA
                </v-btn>
                <v-btn
                  v-if="row.midterm_url && Number(row.draft_status) === 2"
                  size="x-small"
                  variant="text"
                  class="me-1"
                  @click="openForm(row.midterm_url)"
                >
                  Midterm
                </v-btn>
                <v-btn
                  v-if="row.endterm_url && Number(row.draft_status) === 2"
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
                  @click="openApiPdf(`/api/v1/performance/entries/${row.entry_id}/print.pdf`, { phase: 'ppa' })"
                >
                  PDF
                </v-btn>
              </td>
            </tr>
            <tr v-if="!(data.my_ppas?.data || []).length">
              <td colspan="5" class="text-medium-emphasis">No PPAs for this period.</td>
            </tr>
          </tbody>
        </v-table>
        <div v-if="data.my_ppas" class="d-flex align-center gap-2">
          <v-btn size="small" :disabled="page <= 1" @click="page--">Prev</v-btn>
          <span class="text-caption">
            {{ data.my_ppas.meta.current_page }} / {{ data.my_ppas.meta.last_page }}
            · {{ data.my_ppas.meta.total }} total
          </span>
          <v-btn
            size="small"
            :disabled="page >= data.my_ppas.meta.last_page"
            @click="page++"
          >
            Next
          </v-btn>
        </div>
      </template>

      <template v-else>
        <v-table density="compact">
          <thead>
            <tr>
              <th>Staff</th>
              <th>Type</th>
              <th>Period</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in data.pending" :key="String(row.entry_id ?? i)">
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
            <tr v-if="!data.pending.length">
              <td colspan="5" class="text-medium-emphasis">No pending actions.</td>
            </tr>
          </tbody>
        </v-table>
      </template>
    </template>
  </div>
</template>
