<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import { useAuthStore } from '@/stores/auth'
import {
  fetchDashboard,
  fetchDashboardJobs,
  readDashboardSession,
  type DashboardData,
  type DashboardResponse,
} from '@/lib/dashboardApi'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'

const auth = useAuthStore()
const loading = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)
const exporting = ref(false)
const payload = ref<DashboardResponse | null>(readDashboardSession())
const jobOptions = ref<Array<{ job_id: number; job_name: string }>>([])

const divisionId = ref<number | null>(null)
const dutyStationId = ref<number | null>(null)
const funderId = ref<number | null>(null)
const jobId = ref<number | null>(null)

const data = computed<DashboardData | null>(() => payload.value?.data ?? null)

const dashboardTabItems = computed<PortalPillNavItem[]>(() => [
  {
    key: 'staff',
    label: 'Staff',
    icon: 'fa-solid fa-users',
    to: '/dashboard',
    active: true,
  },
  {
    key: 'ppa',
    label: 'PPA',
    icon: 'fa-solid fa-flag',
    to: { path: '/performance', query: { tab: 'analytics', phase: 'ppa' } },
  },
  {
    key: 'midterm',
    label: 'Midterm',
    icon: 'fa-solid fa-chart-simple',
    to: { path: '/performance', query: { tab: 'analytics', phase: 'midterm' } },
  },
  {
    key: 'endterm',
    label: 'Endterm',
    icon: 'fa-solid fa-flag-checkered',
    to: { path: '/performance', query: { tab: 'analytics', phase: 'endterm' } },
  },
])
const filters = computed(() => {
  const base = payload.value?.meta.filters
  if (!base) return null
  return {
    ...base,
    jobs: jobOptions.value.length ? jobOptions.value : base.jobs || [],
  }
})
const birthdays = computed(() => data.value?.birthdays || [])
let filterTimer: number | undefined
let loadSeq = 0

function zipChart(labels: string[] = [], values: number[] = []) {
  return labels.map((label, i) => ({ label, value: values[i] ?? 0 }))
}

const byDivision = computed(() =>
  zipChart(data.value?.staff_by_division?.division, data.value?.staff_by_division?.value),
)
const byContract = computed(() =>
  zipChart(data.value?.staff_by_contract?.contract_type, data.value?.staff_by_contract?.value),
)
const byFunder = computed(() =>
  zipChart(data.value?.staff_by_funder?.funder, data.value?.staff_by_funder?.value),
)
const byMemberState = computed(() =>
  zipChart(data.value?.staff_by_member_state?.member_states, data.value?.staff_by_member_state?.value),
)
const byGender = computed(() =>
  (data.value?.staff_by_gender || []).map((r) => ({ label: String(r.name || 'Unknown'), value: Number(r.y) || 0 })),
)

const genderSeries = computed(() => [
  {
    name: 'Gender',
    data: byGender.value.map((r) => ({ name: r.label, y: r.value })),
  },
])

function columnSeries(rows: Array<{ label: string; value: number }>) {
  return {
    categories: rows.slice(0, 15).map((r) => r.label),
    series: [{ name: 'Staff', data: rows.slice(0, 15).map((r) => r.value) }],
  }
}

function exportParams() {
  return {
    division_id: divisionId.value,
    duty_station_id: dutyStationId.value,
    funder_id: funderId.value,
    job_id: jobId.value,
  }
}

async function onPrintPdf() {
  exporting.value = true
  try {
    await openApiPdf('/api/v1/dashboard/export/pdf', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportCsv() {
  exporting.value = true
  try {
    await downloadApiExport('/api/v1/dashboard/export/csv', 'staff-dashboard.csv', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function loadJobs() {
  try {
    jobOptions.value = await fetchDashboardJobs()
  } catch {
    /* jobs filter is optional for first paint */
  }
}

async function load() {
  const seq = ++loadSeq
  // Keep previous snapshot visible while refreshing (SPA feel).
  const hasData = !!payload.value
  if (!hasData) loading.value = true
  else refreshing.value = true
  error.value = null
  try {
    const next = await fetchDashboard({
      division_id: divisionId.value,
      duty_station_id: dutyStationId.value,
      funder_id: funderId.value,
      job_id: jobId.value,
    })
    if (seq !== loadSeq) return
    payload.value = next
  } catch (e) {
    if (seq !== loadSeq) return
    error.value = apiErrorMessage(e, 'Could not load dashboard')
  } finally {
    if (seq === loadSeq) {
      loading.value = false
      refreshing.value = false
    }
  }
}

watch([divisionId, dutyStationId, funderId, jobId], () => {
  window.clearTimeout(filterTimer)
  filterTimer = window.setTimeout(() => void load(), 200)
})
onMounted(() => {
  void load()
  void loadJobs()
})
</script>

<template>
  <div>
    <PortalPageChrome title="Dashboard" lede="Staff analytics — same KPIs and breakdowns as the CI Staff Tracker.">
      <template #actions>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-printer" :loading="exporting" @click="onPrintPdf">
          Print PDF
        </v-btn>
        <v-btn size="small" variant="outlined" class="ms-2" prepend-icon="mdi-file-delimited" :loading="exporting" @click="onExportCsv">
          CSV
        </v-btn>
      </template>
      <template v-if="auth.hasPermission(74)" #tabs>
        <PortalPillSubnav :items="dashboardTabItems" aria-label="Dashboard analytics" />
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div class="portal-staff-filters">
      <v-row dense>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="divisionId"
            :items="[{ title: 'All divisions', value: null }, ...(filters?.divisions || []).map((d) => ({ title: d.division_name, value: d.division_id }))]"
            label="Division"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="dutyStationId"
            :items="[{ title: 'All duty stations', value: null }, ...(filters?.duty_stations || []).map((d) => ({ title: d.duty_station_name, value: d.duty_station_id }))]"
            label="Duty station"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="funderId"
            :items="[{ title: 'All funders', value: null }, ...(filters?.funders || []).map((f) => ({ title: f.funder, value: f.funder_id }))]"
            label="Funder"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="jobId"
            :items="[{ title: 'All jobs', value: null }, ...(filters?.jobs || []).map((j) => ({ title: j.job_name, value: j.job_id }))]"
            label="Job"
            density="compact"
            hide-details
          />
        </v-col>
      </v-row>
    </div>

    <div v-if="loading && !data" class="text-medium-emphasis">Loading…</div>
    <div v-else-if="refreshing" class="text-caption text-medium-emphasis mb-2">Updating…</div>

    <template v-if="data">
      <v-row dense class="mb-4">
        <v-col
          v-for="card in [
            { label: 'Main staff', value: data.staff, icon: 'fa-solid fa-users', color: '#119a48', to: '/staff' },
            { label: 'Contracts due', value: data.two_months, icon: 'fa-solid fa-clock', color: '#f0ad4e', to: '/staff' },
            { label: 'Under renewal', value: data.staff_renewal, icon: 'fa-solid fa-rotate', color: '#5bc0de', to: '/staff' },
            { label: 'Expired contracts', value: data.expired, icon: 'fa-solid fa-triangle-exclamation', color: '#d9534f', to: '/staff' },
          ]"
          :key="card.label"
          cols="6"
          md="3"
        >
          <RouterLink :to="card.to" style="text-decoration:none;color:inherit">
            <v-sheet border rounded class="pa-3 dash-kpi" :style="{ borderTop: `3px solid ${card.color}` }">
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
                  <div class="text-h5" :style="{ color: card.color }">{{ card.value }}</div>
                </div>
                <i :class="card.icon" :style="{ fontSize: '1.75rem', color: card.color, opacity: 0.85 }" aria-hidden="true" />
              </div>
            </v-sheet>
          </RouterLink>
        </v-col>
      </v-row>

      <v-row dense>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="byGender.length"
              title="Staff gender distribution"
              type="pie"
              :series="genderSeries"
            />
            <div v-else class="text-medium-emphasis text-body-2">No gender data</div>
          </v-sheet>
        </v-col>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="byContract.length"
              title="Staff by contract type"
              type="column"
              :categories="columnSeries(byContract).categories"
              :series="columnSeries(byContract).series"
            />
            <div v-else class="text-medium-emphasis text-body-2">No contract data</div>
          </v-sheet>
        </v-col>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="byDivision.length"
              title="Staff by division"
              type="bar"
              :categories="columnSeries(byDivision).categories"
              :series="columnSeries(byDivision).series"
              :height="320"
            />
            <div v-else class="text-medium-emphasis text-body-2">No division data</div>
          </v-sheet>
        </v-col>
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="byFunder.length"
              title="Active staff by funder"
              type="column"
              :categories="columnSeries(byFunder).categories"
              :series="columnSeries(byFunder).series"
            />
            <div v-else class="text-medium-emphasis text-body-2">No funder data</div>
          </v-sheet>
        </v-col>
        <v-col cols="12">
          <v-sheet border rounded class="pa-3 mb-3">
            <PortalHighchart
              v-if="byMemberState.length"
              title="Staff by member state"
              type="column"
              :categories="columnSeries(byMemberState).categories"
              :series="columnSeries(byMemberState).series"
              :height="300"
            />
            <div v-else class="text-medium-emphasis text-body-2">No member state data</div>
          </v-sheet>
        </v-col>

        <v-col cols="12">
          <v-sheet border rounded class="pa-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="text-subtitle-2">
                <i class="fa-solid fa-cake-candles me-2" style="color:#119a48" aria-hidden="true" />
                Upcoming birthdays
              </div>
              <RouterLink to="/staff/birthdays" class="text-caption">View all</RouterLink>
            </div>
            <v-table density="compact">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Staff</th>
                  <th>Age</th>
                  <th>Division</th>
                  <th>Job</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ev in birthdays.slice(0, 15)" :key="String(ev.id) + ev.start">
                  <td>{{ ev.start }}</td>
                  <td>
                    <RouterLink :to="`/staff/${ev.id}`">{{ ev.title }}</RouterLink>
                  </td>
                  <td>{{ ev.age ?? '—' }}</td>
                  <td>{{ ev.division_name || '—' }}</td>
                  <td>{{ ev.job_name || '—' }}</td>
                </tr>
                <tr v-if="!birthdays.length">
                  <td colspan="5" class="text-medium-emphasis">No upcoming birthdays for this filter.</td>
                </tr>
              </tbody>
            </v-table>
          </v-sheet>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<style scoped>
.dash-kpi:hover {
  box-shadow: 0 4px 14px rgba(17, 154, 72, 0.12);
}
</style>
