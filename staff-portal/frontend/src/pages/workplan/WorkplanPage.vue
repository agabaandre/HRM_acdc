<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import { downloadClientCsv, openClientPdfTable } from '@/lib/clientTableExport'
import { useAuthStore } from '@/stores/auth'
import { fetchWorkplans, syncPraWorkplan, type WorkplanDivisionOption, type WorkplanRow } from '@/lib/workplanApi'

const auth = useAuthStore()

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const canConfigurePra = computed(() => auth.hasPermission(15))
const syncing = ref(false)
const exporting = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<WorkplanRow[]>([])
const divisions = ref<WorkplanDivisionOption[]>([])
const praConfigured = ref(false)
const q = ref('')
const divisionId = ref<number | null>(null)
const year = ref<number | null>(new Date().getFullYear())
const metaMessage = ref<string | null>(null)
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const lastPage = ref(1)
const metaReady = ref(false)

let searchTimer: number | undefined
let loadSeq = 0
let suppressPageWatch = false

const divisionItems = computed(() => [
  { title: 'All divisions', value: null as number | null },
  ...divisions.value.map((d) => ({
    title: d.division_short_name
      ? `${d.division_short_name} — ${d.division_name}`
      : d.division_name,
    value: d.division_id,
  })),
])

const queryShowId = computed(() => Number(route.query.id || route.query.show) || null)

const exportHeaders = ['Activity', 'Broad activity', 'Division', 'Code', 'Year', 'Outcome', 'Sub-activities']

function exportCells(row: WorkplanRow): (string | number)[] {
  return [
    row.activity_name || '',
    row.broad_activity || '',
    row.division_name || '',
    row.division_short_name || row.pra_division_code || '',
    row.year || '',
    row.intermediate_outcome || '',
    row.sub_activity_count ?? 0,
  ]
}

async function load(opts: { includeMeta?: boolean } = {}) {
  const seq = ++loadSeq
  const includeMeta = opts.includeMeta ?? !metaReady.value
  loading.value = true
  error.value = null
  try {
    const res = await fetchWorkplans({
      q: q.value || undefined,
      division_id: divisionId.value,
      year: year.value,
      page: page.value,
      per_page: perPage.value,
      include_meta: includeMeta,
    })
    if (seq !== loadSeq) return
    rows.value = res.data
    suppressPageWatch = true
    page.value = res.meta.current_page || page.value
    suppressPageWatch = false
    lastPage.value = res.meta.last_page || 1
    total.value = res.meta.total ?? res.data.length
    praConfigured.value = !!res.meta.pra_configured
    metaMessage.value = res.meta.message || null
    if (includeMeta && res.meta.divisions) {
      divisions.value = res.meta.divisions
      metaReady.value = true
    }
  } catch (e) {
    if (seq !== loadSeq) return
    error.value = apiErrorMessage(e, 'Could not load workplans')
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

function resetToFirstPageAndLoad() {
  if (page.value !== 1) {
    suppressPageWatch = true
    page.value = 1
    suppressPageWatch = false
  }
  void load()
}

async function onSyncPra() {
  syncing.value = true
  error.value = null
  success.value = null
  try {
    const selected = divisions.value.find((d) => d.division_id === divisionId.value)
    // Prefer PRA code alias source when filtering DHIS (MIS→DHIS).
    let divisionCode = selected?.division_short_name || null
    if (divisionCode && String(divisionCode).toUpperCase() === 'DHIS') {
      divisionCode = 'MIS'
    }
    const res = await syncPraWorkplan({
      year: year.value,
      division: divisionCode,
    })
    success.value = res.message
    metaReady.value = false
    await load({ includeMeta: true })
  } catch (e) {
    error.value = apiErrorMessage(e, 'PRA sync failed')
  } finally {
    syncing.value = false
  }
}

function openShow(id: number) {
  void router.push({ path: `/workplan/${id}` })
}

function onPerPage(v: number) {
  perPage.value = v
  resetToFirstPageAndLoad()
}

function onExportCsv() {
  exporting.value = true
  try {
    downloadClientCsv('workplan-activities.csv', exportHeaders, rows.value.map(exportCells))
  } finally {
    exporting.value = false
  }
}

function onExportPdf() {
  exporting.value = true
  try {
    openClientPdfTable('Workplan activities', exportHeaders, rows.value.map(exportCells))
  } finally {
    exporting.value = false
  }
}

watch(queryShowId, (id) => {
  if (id) void router.replace({ path: `/workplan/${id}` })
})

watch(q, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => resetToFirstPageAndLoad(), 250)
})

watch([divisionId, year], () => resetToFirstPageAndLoad())

watch(page, () => {
  if (suppressPageWatch) return
  void load()
})

onMounted(() => {
  if (queryShowId.value) {
    void router.replace({ path: `/workplan/${queryShowId.value}` })
    return
  }
  void load({ includeMeta: true })
})
</script>

<template>
  <div>
    <PortalPageChrome
      title="Workplan"
      lede="Synced from Africa CDC PRA (tier 3/4 indicators) into workplan_tasks — mapped by division short code."
    >
      <template #actions>
        <RouterLink v-if="canConfigurePra" to="/settings/workplan" style="text-decoration: none">
          <v-btn size="small" variant="outlined" color="secondary">
            <i class="fa-solid fa-gear me-2" aria-hidden="true" />
            PRA settings
          </v-btn>
        </RouterLink>
        <v-btn
          v-if="praConfigured"
          size="small"
          color="primary"
          variant="flat"
          :loading="syncing"
          @click="onSyncPra"
        >
          <i class="fa-solid fa-cloud-arrow-down me-2" aria-hidden="true" />
          Sync from PRA
        </v-btn>
        <v-btn
          v-else-if="canConfigurePra"
          size="small"
          color="primary"
          variant="flat"
          to="/settings/workplan"
        >
          Configure PRA
        </v-btn>
      </template>
    </PortalPageChrome>

    <v-alert
      v-if="!loading && !praConfigured"
      type="warning"
      variant="tonal"
      class="mb-3"
      density="compact"
    >
      PRA sync is not configured.
      <RouterLink v-if="canConfigurePra" to="/settings/workplan">Add the API URL and key in Settings → Workplan / PRA.</RouterLink>
      <span v-else> Ask an administrator to configure Settings → Workplan / PRA.</span>
    </v-alert>
    <v-alert v-if="metaMessage" type="info" variant="tonal" class="mb-3" density="compact">{{ metaMessage }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div class="portal-staff-filters">
      <v-row dense>
        <v-col cols="12" sm="4">
          <v-text-field v-model="q" label="Search activities" density="compact" hide-details clearable />
        </v-col>
        <v-col cols="12" sm="4">
          <v-select
            v-model="divisionId"
            :items="divisionItems"
            label="Division (short code)"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="4">
          <v-text-field v-model.number="year" type="number" label="Fiscal year" density="compact" hide-details clearable />
        </v-col>
      </v-row>
    </div>

    <v-card class="portal-data-table-card mb-3" variant="outlined">
      <div class="px-3 pt-1">
        <PortalTableToolbar
          placement="header"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          total-label="Total activities"
          :exporting="exporting"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        />
      </div>
      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>
      <v-table v-else density="compact" class="workplan-table">
        <thead>
          <tr>
            <th class="workplan-table__count">#</th>
            <th>Activity</th>
            <th>Division</th>
            <th>Year</th>
            <th>Outcome</th>
            <th>Sub-activities</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="row.id"
            style="cursor:pointer"
            @click="openShow(row.id)"
          >
            <td class="workplan-table__count text-medium-emphasis">
              <span class="portal-dt-row-num">{{ (page - 1) * perPage + index + 1 }}</span>
            </td>
            <td>
              <RouterLink :to="`/workplan/${row.id}`" @click.stop>{{ row.activity_name || '—' }}</RouterLink>
              <div class="text-caption text-medium-emphasis">{{ row.broad_activity }}</div>
            </td>
            <td>
              <div>{{ row.division_name || '—' }}</div>
              <div v-if="row.division_short_name || row.pra_division_code" class="text-caption text-medium-emphasis">
                {{ row.pra_division_code || row.division_short_name }}
              </div>
            </td>
            <td>{{ row.year || '—' }}</td>
            <td>{{ row.intermediate_outcome || '—' }}</td>
            <td>{{ row.sub_activity_count ?? 0 }}</td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="text-medium-emphasis text-center py-6">No workplan activities.</td>
          </tr>
        </tbody>
      </v-table>
      <div class="px-3 pb-1">
        <PortalTableToolbar
          placement="footer"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          :show-csv="false"
          :show-pdf="false"
          :show-per-page="false"
          @update:page="(v) => (page = v)"
        />
      </div>
    </v-card>
  </div>
</template>

<style scoped>
.workplan-table__count {
  width: 3rem;
  white-space: nowrap;
}
</style>
