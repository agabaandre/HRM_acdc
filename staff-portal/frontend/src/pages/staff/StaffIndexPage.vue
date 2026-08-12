<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import StaffColumnPicker from '@/components/molecules/StaffColumnPicker.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { downloadApiExport } from '@/lib/exportDownload'
import { useAuthStore } from '@/stores/auth'
import {
  loadStaffDirectoryColumns,
  saveStaffDirectoryColumns,
  staffDirectoryColumns,
  type StaffDirectoryColumnKey,
} from '@/lib/staffDirectoryColumns'
import {
  fetchStaffList,
  type StaffCategory,
  type StaffListRow,
  type StaffPreset,
} from '@/lib/staffApi'

const auth = useAuthStore()
const loading = ref(false)
const error = ref<string | null>(null)
const exporting = ref(false)
const rows = ref<StaffListRow[]>([])
const q = ref('')
const preset = ref<StaffPreset>('active')
const category = ref<StaffCategory>('main_staff')
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const filterCounts = ref<Record<string, number>>({})
const selectedColumns = ref<StaffDirectoryColumnKey[]>(loadStaffDirectoryColumns())

const presets: Array<{ value: StaffPreset; label: string }> = [
  { value: 'active', label: 'Active' },
  { value: 'due', label: 'Due' },
  { value: 'expired', label: 'Expired' },
  { value: 'former', label: 'Former' },
  { value: 'renewal', label: 'Renewal' },
  { value: 'all', label: 'All' },
]

const categories: Array<{ value: StaffCategory; label: string }> = [
  { value: 'main_staff', label: 'Main' },
  { value: 'other_staff', label: 'Other' },
  { value: 'all', label: 'All' },
]

const visibleColumns = computed(() =>
  staffDirectoryColumns.filter((column) => selectedColumns.value.includes(column.key)),
)

let searchTimer: number | undefined

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchStaffList({
      q: q.value || undefined,
      preset: preset.value,
      category: category.value,
      page: page.value,
      per_page: 20,
    })
    rows.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    perPage.value = res.meta.per_page
    total.value = res.meta.total
    filterCounts.value = res.meta.filter_counts || {}
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff')
  } finally {
    loading.value = false
  }
}

function setPreset(next: StaffPreset) {
  preset.value = next
  page.value = 1
}

function setCategory(next: StaffCategory) {
  category.value = next
  page.value = 1
}

function rowNumber(index: number): number {
  return (page.value - 1) * perPage.value + index + 1
}

function personName(row: StaffListRow): string {
  const lname = row.lname?.trim()
  const fname = row.fname?.trim()
  if (lname && fname) return `${lname}, ${fname}`
  if (lname) return lname
  if (fname) return fname
  return `#${row.staff_id}`
}

function personInitials(row: StaffListRow): string {
  const lname = row.lname?.trim().charAt(0) ?? ''
  const fname = row.fname?.trim().charAt(0) ?? ''
  return `${lname}${fname}`.toUpperCase() || '?'
}

function personPhotoUrl(row: StaffListRow): string | null {
  const photo = row.photo?.trim()
  if (!photo) return null
  return resolveAvatarUrl(`/staff-media/photo/${encodeURIComponent(photo)}`)
}

function categoryLabel(value: StaffListRow['category']): string {
  if (value === 'main_staff') return 'Main'
  if (value === 'other_staff') return 'Other'
  return '—'
}

function jobLabel(row: StaffListRow): string {
  return row.job_name || row.job_acting || '—'
}

function columnText(row: StaffListRow, key: StaffDirectoryColumnKey): string {
  switch (key) {
    case 'work_email':
      return row.work_email || '—'
    case 'sap_number':
      return row.SAPNO || '—'
    case 'job':
      return jobLabel(row)
    case 'division':
      return row.division_name || '—'
    case 'duty_station':
      return row.duty_station_name || '—'
    case 'contract_type':
      return row.contract_type || '—'
    case 'category':
      return categoryLabel(row.category)
    case 'status':
      return row.contract_status || '—'
    case 'grade':
      return row.grade || '—'
    case 'start_date':
      return row.start_date || '—'
    case 'end_date':
      return row.end_date || '—'
    case 'funder':
      return row.funder || '—'
    case 'nationality':
      return row.nationality || '—'
    default:
      return '—'
  }
}

async function onExportCsv() {
  exporting.value = true
  try {
    await downloadApiExport('/api/v1/staff/export.csv', 'staff-directory.csv', {
      preset: preset.value,
      category: category.value,
      q: q.value || undefined,
      columns: selectedColumns.value.length > 0 ? selectedColumns.value.join(',') : undefined,
    })
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

watch(q, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    page.value = 1
    void load()
  }, 300)
})

watch(selectedColumns, (next) => saveStaffDirectoryColumns(next))
watch([preset, category, page], () => void load())
onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome title="Staff directory" lede="Search active and historical contracts.">
      <template #actions>
        <StaffColumnPicker v-model="selectedColumns" />
        <RouterLink v-if="auth.hasPermission(71)" to="/staff/new" style="text-decoration:none">
          <v-btn size="small" color="primary">New Staff</v-btn>
        </RouterLink>
        <v-btn size="small" variant="tonal" :loading="exporting" @click="onExportCsv">CSV</v-btn>
        <RouterLink to="/staff/birthdays" style="text-decoration:none" class="ms-2">
          <v-btn size="small" variant="outlined">Birthdays</v-btn>
        </RouterLink>
        <RouterLink to="/staff/data-quality" style="text-decoration:none">
          <v-btn size="small" variant="outlined">Data quality</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>

    <v-text-field
      v-model="q"
      label="Search name, email, SAP…"
      density="compact"
      clearable
      hide-details
      class="mb-3"
      style="max-width: 360px"
    />

    <div class="d-flex flex-wrap align-center gap-2 mb-3">
      <span class="text-caption text-medium-emphasis">Category</span>
      <v-chip
        v-for="option in categories"
        :key="option.value"
        :color="category === option.value ? 'primary' : undefined"
        :variant="category === option.value ? 'flat' : 'outlined'"
        size="small"
        @click="setCategory(option.value)"
      >
        {{ option.label }}
      </v-chip>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <v-chip
        v-for="p in presets"
        :key="p.value"
        :color="preset === p.value ? 'primary' : undefined"
        :variant="preset === p.value ? 'flat' : 'outlined'"
        size="small"
        @click="setPreset(p.value)"
      >
        {{ p.label }}
        <span v-if="filterCounts[p.value] != null" class="ms-1 text-caption">({{ filterCounts[p.value] }})</span>
      </v-chip>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis mb-2">Loading…</div>

    <v-card variant="outlined" class="mb-3">
      <v-table density="compact">
        <thead>
          <tr>
            <th>#</th>
            <th v-for="column in visibleColumns" :key="column.key">{{ column.label }}</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.staff_id">
            <td>{{ rowNumber(index) }}</td>
            <td v-for="column in visibleColumns" :key="column.key">
              <template v-if="column.key === 'photo'">
                <div class="staff-photo-cell">
                  <v-avatar size="36" color="grey-lighten-2">
                    <img
                      v-if="personPhotoUrl(row)"
                      :src="personPhotoUrl(row) || undefined"
                      :alt="`${personName(row)} photo`"
                    />
                    <span v-else class="text-caption font-weight-medium">{{ personInitials(row) }}</span>
                  </v-avatar>
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
              <RouterLink :to="`/staff/${row.staff_id}`" style="text-decoration:none">
                <v-btn size="x-small" variant="tonal" color="primary">Open</v-btn>
              </RouterLink>
            </td>
          </tr>
          <tr v-if="!loading && !rows.length">
            <td :colspan="visibleColumns.length + 2" class="text-medium-emphasis">No staff found.</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <div class="d-flex align-center gap-2">
      <v-btn size="small" :disabled="page <= 1" @click="page--">Prev</v-btn>
      <span class="text-caption">{{ page }} / {{ lastPage }} · {{ total }} total</span>
      <v-btn size="small" :disabled="page >= lastPage" @click="page++">Next</v-btn>
    </div>
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
