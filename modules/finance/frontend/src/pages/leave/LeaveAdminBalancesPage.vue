<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import {
  downloadClientCsv,
  fetchAllPages,
  openClientPdfTable,
} from '@/lib/clientTableExport'
import { useAuthStore } from '@/stores/auth'
import { canManageLeaveBalances } from '@/lib/leavePermissions'
import {
  bulkFillLeaveBalances,
  fetchLeaveAdminDirectory,
  fetchLeaveAdminStaffBalances,
  saveLeaveAdminStaffBalances,
  type LeaveAdminBalanceEditRow,
  type LeaveAdminDirectoryRow,
} from '@/lib/leaveApi'

const auth = useAuthStore()
const router = useRouter()

const year = ref(new Date().getFullYear())
const search = ref('')
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const lastPage = ref(1)
const rows = ref<LeaveAdminDirectoryRow[]>([])
const loading = ref(false)
const exporting = ref(false)
const loadingEditor = ref(false)
const saving = ref(false)
const filling = ref(false)
const overwriteFill = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const dialogOpen = ref(false)
const selectedStaffId = ref<number | null>(null)
const selectedName = ref('')
const selectedEmail = ref('')
const editRows = ref<
  Array<
    LeaveAdminBalanceEditRow & {
      opening_days: number
      carried_forward_days: number
      compensatory_days: number
    }
  >
>([])

const canAdmin = computed(() =>
  canManageLeaveBalances({
    hasPermission: (code) => auth.hasPermission(code),
  }),
)

const incompleteCount = computed(() => rows.value.filter((r) => !r.balances_complete).length)

function formatDays(value: number | string | null | undefined): string {
  const n = Number(value ?? 0)
  if (Number.isInteger(n)) return String(n)
  return n.toFixed(1).replace(/\.0$/, '')
}

async function loadDirectory() {
  if (!canAdmin.value) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchLeaveAdminDirectory({
      q: search.value.trim() || undefined,
      year: year.value,
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = res.data
    total.value = res.meta.total
    lastPage.value = Math.max(1, Math.ceil(res.meta.total / perPage.value))
    year.value = res.meta.year
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff leave balances')
  } finally {
    loading.value = false
  }
}

async function openStaff(row: LeaveAdminDirectoryRow) {
  selectedStaffId.value = row.staff_id
  selectedName.value = row.name
  selectedEmail.value = String(row.work_email || '')
  dialogOpen.value = true
  success.value = null
  error.value = null
  loadingEditor.value = true
  try {
    const data = await fetchLeaveAdminStaffBalances(row.staff_id, year.value)
    selectedName.value = data.staff.name
    selectedEmail.value = String(data.staff.work_email || row.work_email || '')
    editRows.value = data.balances.map((b) => ({
      ...b,
      opening_days: Number(b.opening_days ?? b.balance.opening ?? 0),
      carried_forward_days: Number(b.carried_forward_days ?? b.balance.carried_forward ?? 0),
      compensatory_days: Number(b.compensatory_days ?? 0),
    }))
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff balances')
    dialogOpen.value = false
  } finally {
    loadingEditor.value = false
  }
}

async function saveSelected() {
  if (!selectedStaffId.value) return
  saving.value = true
  success.value = null
  error.value = null
  try {
    await saveLeaveAdminStaffBalances(selectedStaffId.value, {
      year: year.value,
      rows: editRows.value.map((r) => ({
        leave_id: r.type.leave_id,
        opening_days: Number(r.opening_days) || 0,
        carried_forward_days: Number(r.carried_forward_days) || 0,
        compensatory_days: Number(r.compensatory_days) || 0,
      })),
    })
    success.value = `Balances saved for ${selectedName.value}.`
    dialogOpen.value = false
    closeEditor()
    await loadDirectory()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save balances')
  } finally {
    saving.value = false
  }
}

async function runBulkFill() {
  if (
    !confirm(
      overwriteFill.value
        ? `Overwrite opening balances for all active staff for ${year.value}?`
        : `Fill missing opening balances for all active staff for ${year.value}?`,
    )
  ) {
    return
  }
  filling.value = true
  success.value = null
  error.value = null
  try {
    const res = await bulkFillLeaveBalances({
      year: year.value,
      overwrite: overwriteFill.value,
    })
    success.value = res.message
    await loadDirectory()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Bulk fill failed')
  } finally {
    filling.value = false
  }
}

function closeEditor() {
  selectedStaffId.value = null
  selectedName.value = ''
  selectedEmail.value = ''
  editRows.value = []
}

function onDialogUpdate(open: boolean) {
  dialogOpen.value = open
  if (!open) closeEditor()
}

async function loadExportRows(): Promise<LeaveAdminDirectoryRow[]> {
  return fetchAllPages((p, size) =>
    fetchLeaveAdminDirectory({
      q: search.value.trim() || undefined,
      year: year.value,
      page: p,
      per_page: size,
    }).then((res) => ({
      data: res.data,
      meta: { last_page: Math.max(1, Math.ceil(res.meta.total / size)) },
    })),
  )
}

async function onExportCsv() {
  exporting.value = true
  error.value = null
  try {
    const all = await loadExportRows()
    downloadClientCsv(
      `leave-admin-balances-${year.value}.csv`,
      ['Staff ID', 'Name', 'Email', 'SAP', 'Configured', 'Active types'],
      all.map((row) => [
        row.staff_id,
        row.name,
        row.work_email || '',
        row.sap_number || '',
        row.opening_types_configured,
        row.active_leave_types,
      ]),
    )
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportPdf() {
  exporting.value = true
  error.value = null
  try {
    const all = await loadExportRows()
    openClientPdfTable(
      `Leave balances directory ${year.value}`,
      ['Staff ID', 'Name', 'Email', 'SAP', 'Configured', 'Active types'],
      all.map((row) => [
        row.staff_id,
        row.name,
        row.work_email || '',
        row.sap_number || '',
        row.opening_types_configured,
        row.active_leave_types,
      ]),
    )
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

function onPerPage(v: number) {
  perPage.value = v
  page.value = 1
}

watch([search, year, perPage], () => {
  page.value = 1
  void loadDirectory()
})

watch(page, () => void loadDirectory())

onMounted(() => {
  if (!canAdmin.value) {
    void router.replace({ name: 'home' })
    return
  }
  void loadDirectory()
})
</script>

<template>
  <div class="leave-admin">
    <header class="leave-admin__chrome">
      <CbpPageHeading title="Leave balances">
        <template #lede>Compact opening-balance management for active staff.</template>
      </CbpPageHeading>
      <div class="leave-admin__actions">
        <RouterLink to="/leave" style="text-decoration: none">
          <v-btn variant="outlined" size="small">Back to Leave</v-btn>
        </RouterLink>
        <RouterLink to="/settings/leave" style="text-decoration: none">
          <v-btn variant="text" size="small">Leave settings</v-btn>
        </RouterLink>
      </div>
    </header>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div class="portal-staff-filters leave-admin__filters">
      <v-row dense>
        <v-col cols="12" sm="3" md="2">
          <v-text-field
            v-model.number="year"
            type="number"
            label="Year"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="5" md="4">
          <v-text-field
            v-model="search"
            label="Search staff"
            density="compact"
            clearable
            hide-details
            prepend-inner-icon="mdi-magnify"
          />
        </v-col>
        <v-col cols="12" sm="4" md="6" class="d-flex flex-wrap align-center ga-3">
          <v-checkbox
            v-model="overwriteFill"
            label="Overwrite"
            hide-details
            density="compact"
          />
          <v-btn color="primary" size="small" :loading="filling" @click="runBulkFill">
            Fill all
          </v-btn>
          <span v-if="incompleteCount" class="text-caption text-medium-emphasis">
            {{ incompleteCount }} incomplete on this page
          </span>
        </v-col>
      </v-row>
    </div>

    <v-card class="portal-data-table-card" variant="outlined">
      <div class="px-3 pt-1">
        <PortalTableToolbar
          placement="header"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          total-label="Total staff"
          :exporting="exporting"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        />
      </div>
      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>
      <v-table v-else density="compact" class="leave-admin__table">
        <thead>
          <tr>
            <th style="width: 3rem">#</th>
            <th>Staff</th>
            <th>SAP</th>
            <th>Configured</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="row.staff_id"
            class="leave-admin__row"
            @click="openStaff(row)"
          >
            <td>
              <span class="portal-dt-row-num">{{ (page - 1) * perPage + index + 1 }}</span>
            </td>
            <td>
              <div class="font-weight-medium">{{ row.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ row.work_email || '—' }}</div>
            </td>
            <td>{{ row.sap_number || '—' }}</td>
            <td>
              <v-chip
                size="x-small"
                :color="row.balances_complete ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ row.opening_types_configured }}/{{ row.active_leave_types }}
              </v-chip>
            </td>
            <td class="text-end">
              <v-btn
                size="small"
                color="primary"
                variant="tonal"
                @click.stop="openStaff(row)"
              >
                Edit
              </v-btn>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="5" class="text-center text-medium-emphasis py-6">No active staff found.</td>
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

    <v-dialog :model-value="dialogOpen" max-width="760" persistent @update:model-value="onDialogUpdate">
      <v-card class="leave-admin__dialog">
        <v-card-title class="leave-admin__dialog-title">
          <div>
            <div class="text-subtitle-1 font-weight-medium">{{ selectedName || 'Staff balances' }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ selectedEmail || '—' }} · Year {{ year }}
            </div>
          </div>
          <v-btn icon variant="text" size="small" :disabled="saving" @click="onDialogUpdate(false)">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </v-btn>
        </v-card-title>

        <v-card-text class="pt-2">
          <p class="text-caption text-medium-emphasis mb-3">
            Opening days set the administered entitlement for non-accrued types. Available updates after save.
          </p>
          <div v-if="loadingEditor" class="text-medium-emphasis py-4">Loading balances…</div>
          <v-table v-else density="compact" class="leave-admin__edit-table">
            <thead>
              <tr>
                <th>Leave type</th>
                <th style="width: 6rem">Opening</th>
                <th style="width: 6rem">Carried</th>
                <th style="width: 6rem">Comp</th>
                <th class="text-end" style="width: 5rem">Avail.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in editRows" :key="row.type.leave_id">
                <td>
                  <div class="font-weight-medium">{{ row.type.leave_name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    default {{ formatDays(row.type.leave_days) }}
                    <span v-if="row.type.is_accrued"> · accrued</span>
                  </div>
                </td>
                <td>
                  <v-text-field
                    v-model.number="row.opening_days"
                    type="number"
                    min="0"
                    step="0.5"
                    density="compact"
                    hide-details
                    variant="outlined"
                  />
                </td>
                <td>
                  <v-text-field
                    v-model.number="row.carried_forward_days"
                    type="number"
                    min="0"
                    step="0.5"
                    density="compact"
                    hide-details
                    variant="outlined"
                  />
                </td>
                <td>
                  <v-text-field
                    v-model.number="row.compensatory_days"
                    type="number"
                    min="0"
                    step="0.5"
                    density="compact"
                    hide-details
                    variant="outlined"
                  />
                </td>
                <td class="text-end">
                  <span class="leave-admin__avail">{{ formatDays(row.balance.available) }}</span>
                </td>
              </tr>
              <tr v-if="!editRows.length">
                <td colspan="5" class="text-medium-emphasis text-center py-4">
                  No active leave types.
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>

        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" :disabled="saving" @click="onDialogUpdate(false)">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" :disabled="loadingEditor" @click="saveSelected">
            Save balances
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.leave-admin__chrome {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.leave-admin__chrome :deep(.cbp-view-head) {
  margin-bottom: 0;
}

.leave-admin__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.leave-admin__filters {
  margin-bottom: 1rem;
}

.leave-admin__table :deep(th) {
  white-space: nowrap;
  font-size: 0.75rem;
}

.leave-admin__row {
  cursor: pointer;
}

.leave-admin__row:hover {
  background: #f3faf5;
}

.leave-admin__dialog-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding-top: 1rem;
}

.leave-admin__edit-table :deep(.v-field) {
  --v-input-control-height: 2rem;
}

.leave-admin__avail {
  display: inline-block;
  min-width: 2rem;
  padding: 0.12rem 0.4rem;
  border-radius: 0.3rem;
  background: #e8f6ee;
  color: #0d7a3a;
  font-weight: 700;
}

</style>
