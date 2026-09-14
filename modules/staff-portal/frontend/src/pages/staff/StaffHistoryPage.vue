<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import StaffColumnPicker from '@/components/molecules/StaffColumnPicker.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'
import { personAvatarName, toAbsoluteMediaUrl } from '@/lib/personAvatar'
import {
  loadStaffDirectoryColumns,
  saveStaffDirectoryColumns,
  staffDirectoryColumns,
  type StaffDirectoryColumnKey,
} from '@/lib/staffDirectoryColumns'
import {
  fetchStaffFilterOptions,
  fetchStaffHistory,
  type StaffFilterOptions,
  type StaffListRow,
} from '@/lib/staffApi'

function isoDate(value: Date): string {
  const y = value.getFullYear()
  const m = String(value.getMonth() + 1).padStart(2, '0')
  const d = String(value.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const today = new Date()
const loading = ref(false)
const error = ref<string | null>(null)
const exporting = ref(false)
const rows = ref<StaffListRow[]>([])
const periodFrom = ref(`${today.getFullYear()}-01-01`)
const periodTo = ref(isoDate(today))
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const selectedColumns = ref<StaffDirectoryColumnKey[]>(loadStaffDirectoryColumns())
const filterOptions = ref<StaffFilterOptions | null>(null)

const filters = reactive({
  name: '',
  sapno: '',
  gender: '' as string | null,
  region_id: null as number | null,
  nationality_id: null as number | null,
  division_id: [] as number[],
  duty_station_id: [] as number[],
  funder_id: [] as number[],
  job_id: [] as number[],
  grade_id: [] as number[],
})

const visibleColumns = computed(() =>
  staffDirectoryColumns.filter((column) => selectedColumns.value.includes(column.key)),
)

const nationalityItems = computed(() => {
  const all = filterOptions.value?.nationalities || []
  if (filters.region_id == null) return all
  if (filters.region_id === 0) {
    return all.filter((n) => n.region_id == null || Number(n.region_id) === 0)
  }
  return all.filter((n) => Number(n.region_id) === Number(filters.region_id))
})

const regionItems = computed(() => [
  { title: 'All regions', value: null },
  { title: 'Rest of World', value: 0 },
  ...(filterOptions.value?.regions || []).map((r) => ({ title: r.name, value: Number(r.id) })),
])

const periodLabel = computed(() => `${periodFrom.value} → ${periodTo.value}`)

let nameTimer: number | undefined
let sapTimer: number | undefined
let skipWatch = false

function exportParams() {
  const columns =
    visibleColumns.value.length > 0
      ? visibleColumns.value.map((column) => column.key).join(',')
      : undefined

  return {
    period_from: periodFrom.value,
    period_to: periodTo.value,
    name: filters.name || undefined,
    sapno: filters.sapno || undefined,
    gender: filters.gender || undefined,
    region_id: filters.region_id,
    nationality_id: filters.nationality_id,
    division_id: filters.division_id,
    duty_station_id: filters.duty_station_id,
    funder_id: filters.funder_id,
    job_id: filters.job_id,
    grade_id: filters.grade_id,
    columns,
  }
}

function textFilterReady(value: string): boolean {
  const v = value.trim()
  return v === '' || v.length >= 3
}

async function load(): Promise<void> {
  if (!textFilterReady(filters.name) || !textFilterReady(filters.sapno)) {
    return
  }
  loading.value = true
  error.value = null
  try {
    const res = await fetchStaffHistory({
      page: page.value,
      per_page: perPage.value,
      ...exportParams(),
    })
    rows.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    perPage.value = res.meta.per_page
    total.value = res.meta.total
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff history')
  } finally {
    loading.value = false
  }
}

function rowNumber(index: number): number {
  return (page.value - 1) * perPage.value + index + 1
}

function personName(row: StaffListRow): string {
  const parts = [row.lname, row.fname, row.oname]
    .map((part) => (typeof part === 'string' ? part.trim() : ''))
    .filter(Boolean)
  if (parts.length > 0) return parts.join(' ')
  return `#${row.staff_id}`
}

function yearsFromDate(value: string | null | undefined): string {
  if (!value) return '—'
  const raw = String(value).slice(0, 10)
  const birth = new Date(`${raw}T00:00:00`)
  if (Number.isNaN(birth.getTime())) return '—'
  const now = new Date()
  let years = now.getFullYear() - birth.getFullYear()
  const monthDiff = now.getMonth() - birth.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
    years -= 1
  }
  return years >= 0 ? String(years) : '—'
}

function telephoneText(row: StaffListRow): string {
  const parts = [row.tel_1, row.tel_2]
    .map((part) => (typeof part === 'string' ? part.trim() : ''))
    .filter(Boolean)
  return parts.length > 0 ? parts.join(' ') : '—'
}

function avatarName(row: StaffListRow): string {
  return personAvatarName({
    fname: row.fname,
    oname: row.oname,
    lname: row.lname,
    name: personName(row),
  })
}

function personPhotoUrl(row: StaffListRow): string | null {
  const photo = row.photo?.trim()
  if (!photo) return null
  return toAbsoluteMediaUrl(resolveAvatarUrl(`/staff-media/photo/${encodeURIComponent(photo)}`))
}

function categoryLabel(value: StaffListRow['category']): string {
  if (value === 'main_staff') return 'Main'
  if (value === 'other_staff') return 'Other'
  return '—'
}

function columnText(row: StaffListRow, key: StaffDirectoryColumnKey): string {
  switch (key) {
    case 'sap_number':
      return row.SAPNO || '—'
    case 'title':
      return row.title || '—'
    case 'gender':
      return row.gender || '—'
    case 'date_of_birth':
      return row.date_of_birth || '—'
    case 'age':
      return yearsFromDate(row.date_of_birth)
    case 'nationality':
      return row.nationality || '—'
    case 'region':
      return row.region_name || '—'
    case 'duty_station':
      return row.duty_station_name || '—'
    case 'division':
      return row.division_name || '—'
    case 'grade':
      return row.grade || '—'
    case 'job':
      return row.job_name || '—'
    case 'initiation_date':
      return row.initiation_date || '—'
    case 'start_date':
      return row.start_date || '—'
    case 'end_date':
      return row.end_date || '—'
    case 'years_of_tenure':
      return yearsFromDate(row.initiation_date)
    case 'job_acting':
      return row.job_acting || '—'
    case 'first_supervisor':
      return row.first_supervisor_name?.trim() || '—'
    case 'second_supervisor':
      return row.second_supervisor_name?.trim() || '—'
    case 'funder':
      return row.funder || '—'
    case 'work_email':
      return row.work_email || '—'
    case 'telephone':
      return telephoneText(row)
    case 'whatsapp':
      return row.whatsapp || '—'
    case 'contract_type':
      return row.contract_type || '—'
    case 'category':
      return categoryLabel(row.category)
    case 'status':
      return row.contract_status || '—'
    default:
      return '—'
  }
}

async function onExportCsv(): Promise<void> {
  exporting.value = true
  error.value = null
  try {
    await downloadApiExport('/api/v1/staff/history/export/csv', 'staff-history.csv', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportPdf(): Promise<void> {
  exporting.value = true
  error.value = null
  try {
    await openApiPdf('/api/v1/staff/history/export/pdf', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

function onPerPage(next: number): void {
  perPage.value = next
  page.value = 1
}

watch(
  () => filters.name,
  () => {
    window.clearTimeout(nameTimer)
    nameTimer = window.setTimeout(() => {
      if (!textFilterReady(filters.name)) return
      page.value = 1
      void load()
    }, 350)
  },
)

watch(
  () => filters.sapno,
  () => {
    window.clearTimeout(sapTimer)
    sapTimer = window.setTimeout(() => {
      if (!textFilterReady(filters.sapno)) return
      page.value = 1
      void load()
    }, 350)
  },
)

watch(
  () => [
    periodFrom.value,
    periodTo.value,
    filters.gender,
    filters.region_id,
    filters.nationality_id,
    filters.division_id,
    filters.duty_station_id,
    filters.funder_id,
    filters.job_id,
    filters.grade_id,
  ],
  () => {
    if (skipWatch) return
    page.value = 1
    void load()
  },
  { deep: true },
)

watch(
  () => filters.region_id,
  () => {
    if (
      filters.nationality_id != null &&
      !nationalityItems.value.some((n) => Number(n.id) === Number(filters.nationality_id))
    ) {
      skipWatch = true
      filters.nationality_id = null
      skipWatch = false
    }
  },
)

watch(selectedColumns, (next) => saveStaffDirectoryColumns(next))
watch([page, perPage], () => void load())

onMounted(() => {
  void Promise.all([
    fetchStaffFilterOptions()
      .then((opts) => {
        filterOptions.value = opts
      })
      .catch(() => {
        /* filters still usable without option lists */
      }),
    load(),
  ])
})
</script>

<template>
  <div>
    <PortalPageChrome
      title="Staff history"
      :lede="`Who had a contract overlapping ${periodLabel}. Includes separated staff.`"
    >
      <template #tabs>
        <StaffSubnav />
      </template>
    </PortalPageChrome>

    <v-alert type="info" variant="tonal" density="compact" class="mb-3">
      Staff are included if any contract overlaps these dates (start on or before the period end, and end on or after
      the period start, or open-ended). If several contracts overlap, the row shows the one with the
      <strong>largest overlap</strong> (tie-break: most recent contract).
    </v-alert>

    <div class="portal-staff-filters">
      <v-row dense>
        <v-col cols="12" sm="6" md="3">
          <UDateInput
            v-model="periodFrom"
            label="Report period from"
            placeholder="Select start date"
            density="comfortable"
            hide-details="auto"
            :max="periodTo || undefined"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <UDateInput
            v-model="periodTo"
            label="Report period to"
            placeholder="Select end date"
            density="comfortable"
            hide-details="auto"
            :min="periodFrom || undefined"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-text-field v-model="filters.name" label="Name" placeholder="Enter Name" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.gender"
            :items="[{ title: 'Select Gender', value: null }, ...(filterOptions?.genders || []).map((g) => ({ title: g.name, value: String(g.id) }))]"
            label="Gender"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-text-field v-model="filters.sapno" label="SAP NO" placeholder="SAP Number" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select v-model="filters.region_id" :items="regionItems" label="Region" hide-details clearable />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.nationality_id"
            :items="[{ title: 'Select Nationality', value: null }, ...nationalityItems.map((n) => ({ title: n.name, value: Number(n.id) }))]"
            label="Nationality"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.division_id"
            :items="(filterOptions?.divisions || []).map((d) => ({ title: d.name, value: Number(d.id) }))"
            label="Division(s)"
            multiple
            chips
            closable-chips
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.duty_station_id"
            :items="(filterOptions?.duty_stations || []).map((d) => ({ title: d.name, value: Number(d.id) }))"
            label="Duty Station(s)"
            multiple
            chips
            closable-chips
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.funder_id"
            :items="(filterOptions?.funders || []).map((f) => ({ title: f.name, value: Number(f.id) }))"
            label="Funder(s)"
            multiple
            chips
            closable-chips
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.job_id"
            :items="(filterOptions?.jobs || []).map((j) => ({ title: j.name, value: Number(j.id) }))"
            label="Job(s)"
            multiple
            chips
            closable-chips
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.grade_id"
            :items="(filterOptions?.grades || []).map((g) => ({ title: g.name, value: Number(g.id) }))"
            label="Grade(s)"
            multiple
            chips
            closable-chips
            hide-details
          />
        </v-col>
      </v-row>
      <p class="portal-staff-filters__hint">
        Filters apply when you change a date or dropdown, or when Name / SAP NO is cleared or has at least
        <strong>3</strong> characters.
      </p>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact" closable>
      {{ error }}
    </v-alert>

    <v-card class="portal-data-table-card mb-3" variant="outlined">
      <div class="px-3 pt-1">
        <PortalTableToolbar
          placement="header"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          total-label="Total Staff"
          :exporting="exporting"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        >
          <template #actions>
            <StaffColumnPicker v-model="selectedColumns" />
          </template>
        </PortalTableToolbar>
      </div>

      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>

      <v-table density="compact" class="portal-data-table">
        <thead>
          <tr>
            <th style="width: 3rem">#</th>
            <th v-for="column in visibleColumns" :key="column.key">{{ column.label }}</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.staff_id">
            <td><span class="portal-dt-row-num">{{ rowNumber(index) }}</span></td>
            <td v-for="column in visibleColumns" :key="column.key">
              <template v-if="column.key === 'photo'">
                <div class="staff-photo-cell">
                  <CbpAvatar size="sm" :name="avatarName(row)" :image-url="personPhotoUrl(row)" />
                </div>
              </template>
              <template v-else-if="column.key === 'name'">
                <RouterLink :to="`/staff/${row.staff_id}`">
                  {{ personName(row) }}
                </RouterLink>
              </template>
              <template v-else>
                {{ columnText(row, column.key) }}
              </template>
            </td>
            <td class="text-end text-no-wrap">
              <RouterLink :to="`/staff/${row.staff_id}`" style="text-decoration: none">
                <v-btn size="x-small" variant="tonal" color="primary">
                  <i class="fa-solid fa-folder-open me-1" aria-hidden="true" />
                  Open
                </v-btn>
              </RouterLink>
            </td>
          </tr>
          <tr v-if="!loading && !rows.length">
            <td :colspan="visibleColumns.length + 2" class="text-medium-emphasis text-center py-6">
              No staff found for this period.
            </td>
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
.staff-photo-cell {
  display: flex;
  align-items: center;
}

.staff-photo-cell :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
