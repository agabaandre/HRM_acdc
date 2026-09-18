<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'
import { personAvatarName, toAbsoluteMediaUrl } from '@/lib/personAvatar'
import {
  fetchNextOfKinReport,
  fetchStaffFilterOptions,
  type NextOfKinRow,
  type StaffFilterOptions,
} from '@/lib/staffApi'

function avatarName(row: NextOfKinRow): string {
  return personAvatarName({
    fname: row.fname,
    oname: row.oname,
    lname: row.lname,
    name: row.full_name,
  })
}

/** Same staff-media photo URL pattern as the staff directory table. */
function personPhotoUrl(row: NextOfKinRow): string | null {
  const photo = row.photo?.trim()
  if (photo) {
    return toAbsoluteMediaUrl(resolveAvatarUrl(`/staff-media/photo/${encodeURIComponent(photo)}`))
  }
  return toAbsoluteMediaUrl(resolveAvatarUrl(row.photo_url))
}

const loading = ref(false)
const exporting = ref(false)
const error = ref<string | null>(null)
const rows = ref<NextOfKinRow[]>([])
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const filterOptions = ref<StaffFilterOptions | null>(null)

const filters = reactive({
  name: '',
  sapno: '',
  division_id: [] as number[],
  duty_station_id: [] as number[],
  funder_id: [] as number[],
  job_id: [] as number[],
  grade_id: [] as number[],
  region_id: null as number | null,
})

let nameTimer: number | undefined

function exportParams() {
  return {
    name: filters.name || undefined,
    sapno: filters.sapno || undefined,
    division_id: filters.division_id,
    duty_station_id: filters.duty_station_id,
    funder_id: filters.funder_id,
    job_id: filters.job_id,
    grade_id: filters.grade_id,
    region_id: filters.region_id,
  }
}

async function load() {
  const name = filters.name.trim()
  if (name !== '' && name.length < 3) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchNextOfKinReport({
      ...exportParams(),
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = res.data
    total.value = res.meta.total
    lastPage.value = res.meta.last_page
    page.value = res.meta.current_page
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load next of kin report')
  } finally {
    loading.value = false
  }
}

async function onExportCsv() {
  exporting.value = true
  try {
    await downloadApiExport('/api/v1/staff/next-of-kin/export/csv', 'staff-next-of-kin.csv', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportPdf() {
  exporting.value = true
  try {
    await openApiPdf('/api/v1/staff/next-of-kin/export/pdf', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

watch(
  () => [
    filters.division_id,
    filters.duty_station_id,
    filters.funder_id,
    filters.job_id,
    filters.grade_id,
    filters.region_id,
    filters.sapno,
  ],
  () => {
    page.value = 1
    void load()
  },
  { deep: true },
)

watch(
  () => filters.name,
  () => {
    window.clearTimeout(nameTimer)
    nameTimer = window.setTimeout(() => {
      page.value = 1
      void load()
    }, 350)
  },
)

onMounted(async () => {
  try {
    filterOptions.value = await fetchStaffFilterOptions()
  } catch {
    /* filters optional */
  }
  void load()
})
</script>

<template>
  <div>
    <PortalPageChrome
      title="Staff Next of Kin"
      lede="Contact and next-of-kin details for staff on Active, Due, or Under renewal contracts."
    >
      <template #tabs>
        <StaffSubnav />
      </template>
      <template #actions>
        <v-btn size="small" variant="outlined" class="staff-export-btn" :loading="exporting" @click="onExportCsv">
          CSV
        </v-btn>
        <v-btn size="small" variant="outlined" class="staff-export-btn" :loading="exporting" @click="onExportPdf">
          PDF
        </v-btn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-sheet border rounded class="pa-3 mb-4">
      <v-row dense>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.name"
            label="Staff name"
            density="compact"
            hide-details
            clearable
            placeholder="Min 3 characters"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-text-field v-model="filters.sapno" label="SAPNO" density="compact" hide-details clearable />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="filters.division_id"
            :items="(filterOptions?.divisions || []).map((d) => ({ title: d.name, value: Number(d.id) }))"
            label="Division"
            density="compact"
            hide-details
            multiple
            chips
            clearable
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="filters.duty_station_id"
            :items="(filterOptions?.duty_stations || []).map((d) => ({ title: d.name, value: Number(d.id) }))"
            label="Duty station"
            density="compact"
            hide-details
            multiple
            chips
            clearable
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="filters.job_id"
            :items="(filterOptions?.jobs || []).map((d) => ({ title: d.name, value: Number(d.id) }))"
            label="Job"
            density="compact"
            hide-details
            multiple
            chips
            clearable
          />
        </v-col>
      </v-row>
    </v-sheet>

    <div v-if="loading" class="text-medium-emphasis mb-3">Loading…</div>

    <v-row v-else dense>
      <v-col v-for="row in rows" :key="row.staff_id" cols="12" md="6" lg="4">
        <v-sheet border rounded class="pa-3 h-100">
          <div class="d-flex align-start ga-3 mb-3">
            <div class="nok-photo-cell">
              <CbpAvatar size="md" :name="avatarName(row)" :image-url="personPhotoUrl(row)" />
            </div>
            <div class="flex-grow-1">
              <RouterLink :to="`/staff/${row.staff_id}`" class="text-subtitle-2 text-decoration-none">
                {{ row.full_name }}
              </RouterLink>
              <div class="text-caption text-medium-emphasis">
                {{ row.SAPNO || '—' }} · {{ row.contract_status_label || '—' }}
              </div>
              <div class="text-caption">
                {{ row.job_name || '—' }} · {{ row.division_name || '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">{{ row.duty_station_name || '—' }}</div>
            </div>
          </div>

          <div class="text-caption mb-2">
            <div><strong>Work:</strong> {{ row.work_email || '—' }} · {{ row.tel_1 || '—' }}</div>
            <div v-if="row.whatsapp || row.tel_2">
              <strong>Alt:</strong> {{ row.whatsapp || row.tel_2 || '—' }}
            </div>
            <div v-if="row.physical_location">
              <strong>Location:</strong> {{ row.physical_location }}
            </div>
            <div v-if="row.residential_address_duty_station">
              <strong>Residence:</strong> {{ row.residential_address_duty_station }}
            </div>
            <div v-if="row.number_of_dependants != null && row.number_of_dependants !== ''">
              <strong>Dependants:</strong> {{ row.number_of_dependants }}
            </div>
          </div>

          <div class="text-subtitle-2 mb-1">Next of kin</div>
          <div v-if="!row.next_of_kin?.length" class="text-caption text-medium-emphasis">Not recorded</div>
          <div v-for="(nok, idx) in row.next_of_kin" :key="idx" class="text-body-2 mb-2">
            <div>
              <strong>{{ nok.name || '—' }}</strong>
              <span v-if="nok.relationship_name" class="text-medium-emphasis">
                ({{ nok.relationship_name }})
              </span>
            </div>
            <div class="text-caption">
              {{ nok.phone || '—' }} · {{ nok.email || '—' }}
            </div>
          </div>
        </v-sheet>
      </v-col>
      <v-col v-if="!rows.length" cols="12">
        <v-sheet border rounded class="pa-6 text-center text-medium-emphasis">
          No staff match these filters.
        </v-sheet>
      </v-col>
    </v-row>

    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mt-4">
      <div class="text-caption text-medium-emphasis">{{ total }} record(s)</div>
      <div class="d-flex align-center gap-2">
        <v-btn size="small" variant="outlined" :disabled="page <= 1 || loading" @click="page--; load()">
          Prev
        </v-btn>
        <span class="text-caption">Page {{ page }} / {{ lastPage }}</span>
        <v-btn
          size="small"
          variant="outlined"
          :disabled="page >= lastPage || loading"
          @click="page++; load()"
        >
          Next
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.staff-export-btn {
  background: #ffffff !important;
}

.nok-photo-cell {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.nok-photo-cell :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
