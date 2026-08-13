<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { downloadApiExport, openApiPdf } from '@/lib/exportDownload'
import {
  bulkSaveSignatures,
  fetchSignatureManager,
  refreshSignatureApprovers,
  uploadStaffSignature,
  type SignatureManagerRow,
  type SignatureScope,
  type SignatureStatusFilter,
} from '@/lib/staffApi'
import { renderTypedSignatureDataUrl } from '@/lib/typedSignature'

const loading = ref(false)
const exporting = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const rows = ref<SignatureManagerRow[]>([])
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const stats = ref({ total: 0, valid: 0, missing: 0, broken: 0 })
const approverCount = ref(0)
const approverMeta = ref('')
const selected = ref<number[]>([])
const replaceFlags = reactive<Record<number, boolean>>({})

const filters = reactive({
  staff_name: '',
  scope: 'approvers' as SignatureScope,
  signature_status: 'all' as SignatureStatusFilter,
})

let nameTimer: number | undefined

function exportParams() {
  return {
    staff_name: filters.staff_name || undefined,
    scope: filters.scope,
    signature_status: filters.signature_status,
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchSignatureManager({
      ...exportParams(),
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = res.data
    total.value = res.meta.total
    lastPage.value = res.meta.last_page
    page.value = res.meta.current_page
    stats.value = res.meta.stats
    approverCount.value = res.meta.approver_count
    const cache = res.meta.approver_cache || {}
    approverMeta.value =
      filters.scope === 'approvers'
        ? `Approver cache: ${cache.count ?? approverCount.value} ID(s)${
            cache.updated_at ? ` · updated ${new Date(cache.updated_at).toLocaleString()}` : ''
          }`
        : 'Showing active / due / renewal contracts.'
    selected.value = selected.value.filter((id) => rows.value.some((r) => r.staff_id === id))
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load Signature Manager')
  } finally {
    loading.value = false
  }
}

async function onRefreshApprovers() {
  busy.value = true
  notice.value = null
  try {
    const res = await refreshSignatureApprovers()
    notice.value = res.message || 'Approver list refreshed.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not refresh approvers')
  } finally {
    busy.value = false
  }
}

function canAct(row: SignatureManagerRow): boolean {
  return row.signature_status !== 'valid' || !!replaceFlags[row.staff_id]
}

function toggleSelect(id: number, on: boolean | null) {
  if (on) {
    if (!selected.value.includes(id)) selected.value = [...selected.value, id]
  } else {
    selected.value = selected.value.filter((x) => x !== id)
  }
}

function selectEligible() {
  selected.value = rows.value.filter((r) => canAct(r)).map((r) => r.staff_id)
}

async function generateSelected() {
  const targets = rows.value.filter((r) => selected.value.includes(r.staff_id) && canAct(r))
  if (!targets.length) {
    notice.value = 'Select staff who are missing/broken, or enable Replace existing on valid rows.'
    return
  }
  busy.value = true
  notice.value = null
  error.value = null
  try {
    const signatures = targets.map((r) => ({
      staff_id: r.staff_id,
      signature_data_url: renderTypedSignatureDataUrl(r.signature_text || r.full_name),
      allow_override: !!replaceFlags[r.staff_id],
    }))
    const result = await bulkSaveSignatures(signatures)
    notice.value = `Saved ${result.saved}, skipped ${result.skipped}, failed ${result.failed}.`
    selected.value = []
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Bulk generate failed')
  } finally {
    busy.value = false
  }
}

async function onUpload(row: SignatureManagerRow, file: File | undefined) {
  if (!file) return
  if (!canAct(row)) {
    notice.value = 'Enable Replace existing before overwriting a valid signature.'
    return
  }
  busy.value = true
  error.value = null
  try {
    await uploadStaffSignature(row.staff_id, file, !!replaceFlags[row.staff_id])
    notice.value = `Uploaded signature for ${row.full_name}.`
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Upload failed')
  } finally {
    busy.value = false
  }
}

async function onExportCsv() {
  exporting.value = true
  try {
    await downloadApiExport('/api/v1/staff/signatures/export/csv', 'staff-signature-manager.csv', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportPdf() {
  exporting.value = true
  try {
    await openApiPdf('/api/v1/staff/signatures/export/pdf', exportParams())
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

watch(
  () => [filters.scope, filters.signature_status],
  () => {
    page.value = 1
    void load()
  },
)

watch(
  () => filters.staff_name,
  () => {
    window.clearTimeout(nameTimer)
    nameTimer = window.setTimeout(() => {
      const v = filters.staff_name.trim()
      if (v !== '' && v.length < 3) return
      page.value = 1
      void load()
    }, 350)
  },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <PortalPageChrome
      title="Signature Manager"
      lede="Generate typed signatures or upload files for APM approvers and active staff. Valid signatures require Replace existing before overwrite."
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
    <v-alert v-if="notice" type="info" variant="tonal" class="mb-3" density="compact">{{ notice }}</v-alert>

    <v-row dense class="mb-4">
      <v-col cols="6" sm="3">
        <v-sheet border rounded class="pa-3">
          <div class="text-caption text-medium-emphasis">Total</div>
          <div class="text-h5">{{ stats.total }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="3">
        <v-sheet border rounded class="pa-3">
          <div class="text-caption text-medium-emphasis">Valid</div>
          <div class="text-h5 text-success">{{ stats.valid }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="3">
        <v-sheet border rounded class="pa-3">
          <div class="text-caption text-medium-emphasis">Missing</div>
          <div class="text-h5 text-error">{{ stats.missing }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="3">
        <v-sheet border rounded class="pa-3">
          <div class="text-caption text-medium-emphasis">Broken file</div>
          <div class="text-h5 text-warning">{{ stats.broken }}</div>
        </v-sheet>
      </v-col>
    </v-row>

    <v-sheet border rounded class="pa-3 mb-4">
      <v-row dense align="end">
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.staff_name"
            label="Staff name"
            density="compact"
            hide-details
            clearable
            placeholder="Min 3 characters"
          />
        </v-col>
        <v-col cols="12" md="3">
          <div class="d-flex gap-2 align-center">
            <v-select
              v-model="filters.scope"
              :items="[
                { title: 'APM approvers only', value: 'approvers' },
                { title: 'All active staff', value: 'current' },
              ]"
              label="Staff scope"
              density="compact"
              hide-details
            />
            <v-btn
              size="small"
              variant="outlined"
              icon
              :loading="busy"
              title="Refresh approver list from APM"
              @click="onRefreshApprovers"
            >
              <i class="fa-solid fa-rotate" aria-hidden="true" />
            </v-btn>
          </div>
          <div class="text-caption text-medium-emphasis mt-1">{{ approverMeta }}</div>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="filters.signature_status"
            :items="[
              { title: 'All statuses', value: 'all' },
              { title: 'Valid', value: 'valid' },
              { title: 'Missing', value: 'missing' },
              { title: 'Broken file', value: 'broken' },
            ]"
            label="Signature status"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex flex-wrap gap-2">
          <v-btn size="small" variant="outlined" @click="selectEligible">Select eligible</v-btn>
          <v-btn size="small" color="primary" :loading="busy" @click="generateSelected">
            Generate typed
          </v-btn>
        </v-col>
      </v-row>
    </v-sheet>

    <div v-if="loading" class="text-medium-emphasis mb-3">Loading…</div>

    <v-table v-else density="compact" class="mb-3">
      <thead>
        <tr>
          <th style="width: 2.5rem"></th>
          <th>Staff</th>
          <th>SAPNO</th>
          <th>Status</th>
          <th>Signature</th>
          <th>Replace</th>
          <th>Upload</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.staff_id">
          <td>
            <v-checkbox
              :model-value="selected.includes(row.staff_id)"
              hide-details
              density="compact"
              @update:model-value="(v) => toggleSelect(row.staff_id, !!v)"
            />
          </td>
          <td>
            <div class="d-flex align-center gap-2">
              <v-avatar size="36">
                <v-img v-if="resolveAvatarUrl(row.photo_url)" :src="resolveAvatarUrl(row.photo_url)!" />
                <span v-else class="text-caption">{{ (row.full_name || '?').slice(0, 1) }}</span>
              </v-avatar>
              <div>
                <RouterLink :to="`/staff/${row.staff_id}`">{{ row.full_name }}</RouterLink>
                <div class="text-caption text-medium-emphasis">{{ row.signature_text }}</div>
              </div>
            </div>
          </td>
          <td>{{ row.SAPNO || '—' }}</td>
          <td>
            <v-chip
              size="small"
              :color="
                row.signature_status === 'valid'
                  ? 'success'
                  : row.signature_status === 'broken'
                    ? 'warning'
                    : 'error'
              "
              variant="tonal"
            >
              {{ row.signature_status_label }}
            </v-chip>
          </td>
          <td>
            <div
              v-if="row.signature_url"
              class="sig-preview"
            >
              <img :src="resolveAvatarUrl(row.signature_url) || row.signature_url" alt="Signature" />
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </td>
          <td>
            <v-checkbox
              v-if="row.signature_status === 'valid'"
              v-model="replaceFlags[row.staff_id]"
              label="Replace"
              hide-details
              density="compact"
            />
            <span v-else class="text-caption text-medium-emphasis">OK</span>
          </td>
          <td>
            <v-file-input
              density="compact"
              hide-details
              accept="image/*"
              prepend-icon=""
              prepend-inner-icon="mdi-paperclip"
              style="max-width: 11rem"
              @update:model-value="(f) => onUpload(row, Array.isArray(f) ? f[0] : f || undefined)"
            />
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="7" class="text-medium-emphasis text-center py-6">
            No staff match these filters.
          </td>
        </tr>
      </tbody>
    </v-table>

    <div class="d-flex align-center justify-space-between flex-wrap gap-2">
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
.sig-preview {
  display: inline-block;
  padding: 0.35rem 0.5rem;
  background:
    linear-gradient(45deg, #f1f3f5 25%, transparent 25%),
    linear-gradient(-45deg, #f1f3f5 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #f1f3f5 75%),
    linear-gradient(-45deg, transparent 75%, #f1f3f5 75%);
  background-size: 8px 8px;
  background-position: 0 0, 0 4px, 4px -4px, -4px 0;
  background-color: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  max-width: 220px;
}
.sig-preview img {
  max-height: 72px;
  max-width: 200px;
  display: block;
}
.staff-export-btn {
  background: #ffffff !important;
}
</style>
