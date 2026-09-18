<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import AdManagerSubnav from '@/components/molecules/AdManagerSubnav.vue'
import {
  disableAdAccount,
  enableAdAccount,
  fetchDisabled,
  fetchExpired,
  type AdAccountRow,
} from '@/lib/admanagerApi'
import {
  downloadClientCsv,
  fetchAllPages,
  openClientPdfTable,
} from '@/lib/clientTableExport'

const props = defineProps<{
  mode?: 'expired' | 'disabled'
}>()

const route = useRoute()
const loading = ref(false)
const acting = ref(false)
const exporting = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<AdAccountRow[]>([])
const q = ref('')
const page = ref(1)
const perPage = ref(20)
const lastPage = ref(1)
const total = ref(0)

const confirmOpen = ref(false)
const confirmAction = ref<'disable' | 'enable'>('disable')
const selected = ref<AdAccountRow | null>(null)

const mode = computed<'expired' | 'disabled'>(() => {
  if (props.mode) return props.mode
  return route.path.includes('disabled') ? 'disabled' : 'expired'
})

const title = computed(() =>
  mode.value === 'expired' ? 'Accounts to disable' : 'Disabled accounts',
)

const lede = computed(() =>
  mode.value === 'expired'
    ? 'Expired contracts whose AD/email accounts are still active. Confirm before marking disabled.'
    : 'Accounts already marked disabled. Confirm before re-enabling.',
)

const selectedName = computed(() => {
  const row = selected.value
  if (!row) return ''
  const name = [row.lname, row.fname].filter(Boolean).join(', ')
  return name || row.work_email || `Staff #${row.staff_id}`
})

let searchTimer: number | undefined

function listFetcher() {
  return mode.value === 'expired' ? fetchExpired : fetchDisabled
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await listFetcher()({
      q: q.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load AD accounts')
  } finally {
    loading.value = false
  }
}

function openConfirm(row: AdAccountRow, action: 'disable' | 'enable') {
  selected.value = row
  confirmAction.value = action
  confirmOpen.value = true
  error.value = null
  success.value = null
}

function closeConfirm() {
  if (acting.value) return
  confirmOpen.value = false
  selected.value = null
}

async function confirmStatusChange() {
  if (!selected.value) return
  acting.value = true
  error.value = null
  success.value = null
  try {
    const staffId = Number(selected.value.staff_id)
    const res =
      confirmAction.value === 'disable'
        ? await disableAdAccount(staffId)
        : await enableAdAccount(staffId)
    success.value = res.message || (
      confirmAction.value === 'disable'
        ? 'Account marked as disabled.'
        : 'Account marked as enabled.'
    )
    confirmOpen.value = false
    selected.value = null
    await load()
  } catch (e) {
    error.value = apiErrorMessage(
      e,
      confirmAction.value === 'disable'
        ? 'Could not disable account'
        : 'Could not enable account',
    )
  } finally {
    acting.value = false
  }
}

function exportHeaders() {
  return mode.value === 'disabled'
    ? ['Staff ID', 'Name', 'Email', 'Division', 'Email status', 'Disabled at']
    : ['Staff ID', 'Name', 'Email', 'Division', 'Email status']
}

function exportCells(row: AdAccountRow): (string | number)[] {
  const name = [row.lname, row.fname].filter(Boolean).join(', ')
  const base: (string | number)[] = [
    row.staff_id,
    name || '',
    row.work_email || '',
    row.division_name || '',
    row.email_status === 0 ? 'Disabled' : 'Active',
  ]
  if (mode.value === 'disabled') base.push(row.email_disabled_at || '')
  return base
}

async function loadExportRows(): Promise<AdAccountRow[]> {
  const fetcher = listFetcher()
  return fetchAllPages((p, size) =>
    fetcher({ q: q.value || undefined, page: p, per_page: size }),
  )
}

async function onExportCsv() {
  exporting.value = true
  error.value = null
  try {
    const all = await loadExportRows()
    downloadClientCsv(
      mode.value === 'disabled' ? 'ad-disabled-accounts.csv' : 'ad-expired-accounts.csv',
      exportHeaders(),
      all.map(exportCells),
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
    openClientPdfTable(title.value, exportHeaders(), all.map(exportCells))
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

watch(q, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    page.value = 1
    void load()
  }, 300)
})

watch([mode, page, perPage], () => void load())
onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome :title="title" :lede="lede">
      <template #tabs>
        <AdManagerSubnav />
      </template>
    </PortalPageChrome>

    <div class="portal-staff-filters">
      <v-row dense>
        <v-col cols="12" sm="6" md="4">
          <v-text-field
            v-model="q"
            label="Search"
            density="compact"
            clearable
            hide-details
          />
        </v-col>
      </v-row>
    </div>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">
      {{ success }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">
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
          total-label="Total accounts"
          :exporting="exporting"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        />
      </div>
      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>
      <v-table density="compact">
        <thead>
          <tr>
            <th style="width: 3rem">#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Division</th>
            <th>Email status</th>
            <th v-if="mode === 'disabled'">Disabled at</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.staff_id">
            <td>
              <span class="portal-dt-row-num">{{ (page - 1) * perPage + index + 1 }}</span>
            </td>
            <td>
              <RouterLink :to="`/staff/${row.staff_id}`">{{ row.lname }}, {{ row.fname }}</RouterLink>
            </td>
            <td>{{ row.work_email || '—' }}</td>
            <td>{{ row.division_name || '—' }}</td>
            <td>
              <v-chip
                size="x-small"
                variant="tonal"
                :color="row.email_status === 0 ? 'error' : 'success'"
              >
                {{ row.email_status === 0 ? 'Disabled' : 'Active' }}
              </v-chip>
            </td>
            <td v-if="mode === 'disabled'">{{ row.email_disabled_at || '—' }}</td>
            <td class="text-end">
              <v-btn
                v-if="mode === 'expired'"
                size="small"
                color="error"
                variant="flat"
                @click="openConfirm(row, 'disable')"
              >
                <i class="fa-solid fa-user-slash me-1" aria-hidden="true" />
                Disable
              </v-btn>
              <v-btn
                v-else
                size="small"
                color="primary"
                variant="flat"
                @click="openConfirm(row, 'enable')"
              >
                <i class="fa-solid fa-user-check me-1" aria-hidden="true" />
                Enable
              </v-btn>
            </td>
          </tr>
          <tr v-if="!loading && !rows.length">
            <td
              :colspan="mode === 'disabled' ? 7 : 6"
              class="text-medium-emphasis text-center py-6"
            >
              No accounts.
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

    <v-dialog v-model="confirmOpen" max-width="460" persistent>
      <v-card>
        <v-card-title class="text-subtitle-1">
          {{ confirmAction === 'disable' ? 'Confirm disable account' : 'Confirm enable account' }}
        </v-card-title>
        <v-card-text>
          <p class="mb-2">
            <template v-if="confirmAction === 'disable'">
              Mark <strong>{{ selectedName }}</strong> as disabled in AD manager?
            </template>
            <template v-else>
              Mark <strong>{{ selectedName }}</strong> as enabled again?
            </template>
          </p>
          <p v-if="selected?.work_email" class="text-caption text-medium-emphasis mb-0">
            {{ selected.work_email }}
          </p>
          <v-alert
            v-if="confirmAction === 'disable'"
            class="mt-3"
            type="warning"
            variant="tonal"
            density="compact"
          >
            This records the account as disabled in the staff system. It does not change Active Directory by itself.
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="acting" @click="closeConfirm">Cancel</v-btn>
          <v-btn
            :color="confirmAction === 'disable' ? 'error' : 'primary'"
            variant="flat"
            :loading="acting"
            @click="confirmStatusChange"
          >
            {{ confirmAction === 'disable' ? 'Yes, disable' : 'Yes, enable' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
