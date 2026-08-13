<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import LeavePlanPanel from '@/components/leave/LeavePlanPanel.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import { downloadClientCsv, openClientPdfTable } from '@/lib/clientTableExport'
import { useAuthStore } from '@/stores/auth'
import { LEAVE_PERMS } from '@/lib/leavePermissions'
import {
  decideLeaveRequest,
  fetchLeaveApprovals,
  fetchLeaveBalances,
  fetchLeaveRequests,
  readLeaveApprovalsSession,
  readLeaveBalancesSession,
  type LeaveBalanceRow,
  type LeaveRequestDto,
} from '@/lib/leaveApi'

type Tab = 'balances' | 'requests' | 'approvals' | 'plan'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const tab = ref<Tab>('balances')
const loading = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const bootstrapped = ref(false)

const balances = ref<LeaveBalanceRow[]>(readLeaveBalancesSession() || [])
const requests = ref<LeaveRequestDto[]>([])
const cachedApprovals = readLeaveApprovalsSession()
const approvals = ref<LeaveRequestDto[]>(cachedApprovals?.data || [])
const approvalsHr = ref(!!cachedApprovals?.meta?.is_hr)
let loadSeq = 0
let loadTimer: number | undefined

const statusFilter = ref('')
const startDate = ref('')
const endDate = ref('')
const exporting = ref(false)
const balPage = ref(1)
const balPerPage = ref(25)
const reqPage = ref(1)
const reqPerPage = ref(25)
const apprPage = ref(1)
const apprPerPage = ref(25)

const isHr = computed(() => !!auth.me?.profile?.is_hr || auth.me?.profile?.role_id === 20)
const canViewAll = computed(
  () =>
    isHr.value ||
    auth.hasPermission(LEAVE_PERMS.VIEW_ALL) ||
    auth.hasPermission(LEAVE_PERMS.LEGACY_VIEW_ALL),
)
const canManageBalances = computed(
  () => isHr.value || auth.hasPermission(LEAVE_PERMS.MANAGE_BALANCES),
)
const canManageSettings = computed(
  () =>
    isHr.value ||
    auth.hasPermission(LEAVE_PERMS.MANAGE_SETTINGS) ||
    auth.hasPermission(15),
)

const balanceYear = computed(() => balances.value[0]?.balance.year || new Date().getFullYear())
const totalAvailable = computed(() =>
  balances.value.reduce((sum, row) => sum + Number(row.balance.available || 0), 0),
)
const totalPending = computed(() =>
  balances.value.reduce((sum, row) => sum + Number(row.balance.pending || 0), 0),
)
const totalUsed = computed(() =>
  balances.value.reduce((sum, row) => sum + Number(row.balance.used || 0), 0),
)
const totalOpening = computed(() =>
  balances.value.reduce((sum, row) => sum + Number(row.balance.opening || 0), 0),
)

const requestSnapshot = computed(() => {
  const rows = requests.value
  return {
    total: rows.length,
    pending: rows.filter((r) => r.overall_status === 'Pending').length,
    approved: rows.filter((r) => r.overall_status === 'Approved').length,
    rejected: rows.filter((r) => r.overall_status === 'Rejected').length,
    days: rows.reduce((sum, r) => sum + Number(r.requested_days || 0), 0),
  }
})

const approvalSnapshot = computed(() => ({
  total: approvals.value.length,
  days: approvals.value.reduce((sum, r) => sum + Number(r.requested_days || 0), 0),
  people: new Set(approvals.value.map((r) => r.staff_id)).size,
}))

const balanceKpis = computed(() => [
  {
    label: 'Available',
    value: formatDays(totalAvailable.value),
    icon: 'fa-solid fa-umbrella-beach',
    color: '#0f766e',
  },
  {
    label: 'Used',
    value: formatDays(totalUsed.value),
    icon: 'fa-solid fa-calendar-check',
    color: '#15803d',
  },
  {
    label: 'Pending',
    value: formatDays(totalPending.value),
    icon: 'fa-solid fa-hourglass-half',
    color: '#b45309',
  },
  {
    label: 'Opening',
    value: formatDays(totalOpening.value),
    icon: 'fa-solid fa-seedling',
    color: '#1d4ed8',
  },
  {
    label: 'Leave types',
    value: String(balances.value.length),
    icon: 'fa-solid fa-layer-group',
    color: '#334155',
  },
  {
    label: 'Year',
    value: String(balanceYear.value),
    icon: 'fa-solid fa-calendar',
    color: '#119a48',
  },
])

function formatDays(value: number | string | null | undefined): string {
  const n = Number(value ?? 0)
  if (Number.isInteger(n)) return String(n)
  return n.toFixed(1).replace(/\.0$/, '')
}

function statusColor(status: string): string {
  if (status === 'Approved') return 'success'
  if (status === 'Rejected') return 'error'
  return 'warning'
}

function pageSlice<T>(list: T[], page: number, perPage: number): T[] {
  const start = (page - 1) * perPage
  return list.slice(start, start + perPage)
}

const balTotal = computed(() => balances.value.length)
const balLastPage = computed(() => Math.max(1, Math.ceil(balTotal.value / balPerPage.value)))
const balRows = computed(() => pageSlice(balances.value, balPage.value, balPerPage.value))

const reqTotal = computed(() => requests.value.length)
const reqLastPage = computed(() => Math.max(1, Math.ceil(reqTotal.value / reqPerPage.value)))
const reqRows = computed(() => pageSlice(requests.value, reqPage.value, reqPerPage.value))

const apprTotal = computed(() => approvals.value.length)
const apprLastPage = computed(() => Math.max(1, Math.ceil(apprTotal.value / apprPerPage.value)))
const apprRows = computed(() => pageSlice(approvals.value, apprPage.value, apprPerPage.value))

const showAllStaff = computed(() => canViewAll.value && route.query.view === 'all')

function exportBalancesCsv() {
  exporting.value = true
  try {
    downloadClientCsv(
      'leave-balances.csv',
      ['Leave type', 'Available', 'Opening', 'Carried', 'Accrued', 'Used', 'Pending', 'Comp'],
      balances.value.map((row) => [
        row.type.leave_name,
        formatDays(row.balance.available),
        formatDays(row.balance.opening),
        formatDays(row.balance.carried_forward),
        formatDays(row.balance.accrued),
        formatDays(row.balance.used),
        formatDays(row.balance.pending),
        formatDays(row.balance.compensatory),
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportBalancesPdf() {
  exporting.value = true
  try {
    openClientPdfTable(
      `Leave balances ${balanceYear.value}`,
      ['Leave type', 'Available', 'Opening', 'Carried', 'Accrued', 'Used', 'Pending', 'Comp'],
      balances.value.map((row) => [
        row.type.leave_name,
        formatDays(row.balance.available),
        formatDays(row.balance.opening),
        formatDays(row.balance.carried_forward),
        formatDays(row.balance.accrued),
        formatDays(row.balance.used),
        formatDays(row.balance.pending),
        formatDays(row.balance.compensatory),
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportRequestsCsv() {
  exporting.value = true
  try {
    const headers = showAllStaff.value
      ? ['Staff', 'SAP / OCN', 'Type', 'From', 'To', 'Days', 'Status']
      : ['Type', 'From', 'To', 'Days', 'Status']
    downloadClientCsv(
      'leave-requests.csv',
      headers,
      requests.value.map((req) => {
        const cells = [
          req.leave_name,
          req.start_date,
          req.end_date,
          req.requested_days,
          req.overall_status,
        ]
        return showAllStaff.value
          ? [req.staff_name || '', req.sap_number || '', ...cells]
          : cells
      }),
    )
  } finally {
    exporting.value = false
  }
}

function exportRequestsPdf() {
  exporting.value = true
  try {
    const headers = showAllStaff.value
      ? ['Staff', 'SAP / OCN', 'Type', 'From', 'To', 'Days', 'Status']
      : ['Type', 'From', 'To', 'Days', 'Status']
    openClientPdfTable(
      'Leave requests',
      headers,
      requests.value.map((req) => {
        const cells = [
          req.leave_name,
          req.start_date,
          req.end_date,
          req.requested_days,
          req.overall_status,
        ]
        return showAllStaff.value
          ? [req.staff_name || '', req.sap_number || '', ...cells]
          : cells
      }),
    )
  } finally {
    exporting.value = false
  }
}

function exportApprovalsCsv() {
  exporting.value = true
  try {
    downloadClientCsv(
      'leave-approvals.csv',
      ['Staff', 'SAP / OCN', 'Leave', 'Days', 'From', 'To'],
      approvals.value.map((req) => [
        req.staff_name || '',
        req.sap_number || '',
        req.leave_name,
        req.requested_days,
        req.start_date,
        req.end_date,
      ]),
    )
  } finally {
    exporting.value = false
  }
}

function exportApprovalsPdf() {
  exporting.value = true
  try {
    openClientPdfTable(
      'Leave approvals',
      ['Staff', 'SAP / OCN', 'Leave', 'Days', 'From', 'To'],
      approvals.value.map((req) => [
        req.staff_name || '',
        req.sap_number || '',
        req.leave_name,
        req.requested_days,
        req.start_date,
        req.end_date,
      ]),
    )
  } finally {
    exporting.value = false
  }
}

async function loadRequests() {
  requests.value = await fetchLeaveRequests({
    scope: canViewAll.value && route.query.view === 'all' ? 'all' : 'mine',
    status: statusFilter.value || undefined,
    start_date: startDate.value || undefined,
    end_date: endDate.value || undefined,
  })
}

function hasVisibleData(): boolean {
  if (tab.value === 'balances') return balances.value.length > 0
  if (tab.value === 'requests') return requests.value.length > 0
  if (tab.value === 'plan') return true
  return approvals.value.length > 0
}

async function loadTab() {
  const seq = ++loadSeq
  const keepPaint = hasVisibleData()
  if (!keepPaint) loading.value = true
  else refreshing.value = true
  error.value = null
  try {
    if (tab.value === 'balances') {
      balances.value = await fetchLeaveBalances()
      // Warm requests after balances paint — do not race the critical path.
      if (seq === loadSeq) {
        window.setTimeout(() => {
          void loadRequests().catch(() => undefined)
        }, 150)
      }
    } else if (tab.value === 'requests') {
      await loadRequests()
    } else if (tab.value === 'plan') {
      // LeavePlanPanel loads its own data.
    } else {
      const res = await fetchLeaveApprovals()
      if (seq !== loadSeq) return
      approvals.value = res.data
      approvalsHr.value = res.meta.is_hr
    }
  } catch (e) {
    if (seq !== loadSeq) return
    error.value = apiErrorMessage(e, 'Could not load leave data')
  } finally {
    if (seq === loadSeq) {
      loading.value = false
      refreshing.value = false
    }
  }
}

function scheduleLoad(delay = 50) {
  window.clearTimeout(loadTimer)
  loadTimer = window.setTimeout(() => void loadTab(), delay)
}

function setTab(next: Tab) {
  if (tab.value === next && String(route.query.view || 'balances') === next) return
  tab.value = next
  success.value = null
  balPage.value = 1
  reqPage.value = 1
  apprPage.value = 1
  void router.replace({ query: { ...route.query, view: next } })
  // loadTab is triggered by the tab watcher once bootstrapped
}

const leaveTabItems = computed<PortalPillNavItem[]>(() => [
  {
    key: 'balances',
    label: 'My balances',
    icon: 'fa-solid fa-wallet',
    active: tab.value === 'balances',
  },
  {
    key: 'plan',
    label: 'Annual leave plan',
    icon: 'fa-solid fa-calendar-days',
    active: tab.value === 'plan',
  },
  {
    key: 'requests',
    label: canViewAll.value ? 'Requests' : 'My requests',
    icon: 'fa-solid fa-inbox',
    active: tab.value === 'requests',
  },
  {
    key: 'approvals',
    label: 'Approvals',
    icon: 'fa-solid fa-clipboard-check',
    active: tab.value === 'approvals',
  },
])

async function onDecide(id: number, role: string, action: 'approve' | 'reject') {
  success.value = null
  error.value = null
  try {
    await decideLeaveRequest(id, { role, action })
    success.value = `Leave request ${action === 'approve' ? 'Approved' : 'Rejected'}.`
    await loadTab()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update leave request')
  }
}

watch([statusFilter, startDate, endDate], () => {
  if (!bootstrapped.value || tab.value !== 'requests') return
  scheduleLoad(200)
})

watch(
  () => route.query.view,
  (view) => {
    if (!bootstrapped.value) return
    const q = String(view || 'balances')
    const next: Tab =
      q === 'all'
        ? 'requests'
        : q === 'requests' || q === 'approvals' || q === 'plan'
          ? q
          : 'balances'
    if (tab.value !== next) {
      tab.value = next
      return
    }
    if (tab.value === 'requests') scheduleLoad()
  },
)

watch(tab, (next, prev) => {
  if (!bootstrapped.value || next === prev) return
  scheduleLoad()
})

onMounted(() => {
  const q = String(route.query.view || 'balances')
  if (q === 'requests' || q === 'approvals' || q === 'balances' || q === 'plan' || q === 'all') {
    tab.value = q === 'all' ? 'requests' : (q as Tab)
  }
  bootstrapped.value = true
  void loadTab()
})
</script>

<template>
  <div class="leave-page">
    <header class="leave-page__chrome">
      <div class="leave-page__title-row">
        <CbpPageHeading title="Leave management">
          <template #lede>Balances, annual leave plan, requests, and approvals.</template>
        </CbpPageHeading>
        <div class="leave-page__actions">
          <RouterLink to="/leave/apply" class="leave-page__action-link">
            <v-btn color="primary" size="small">
              <i class="fa-solid fa-plus me-2" aria-hidden="true" />
              Apply for leave
            </v-btn>
          </RouterLink>
          <RouterLink
            v-if="canManageBalances"
            to="/leave/admin/balances"
            class="leave-page__action-link"
          >
            <v-btn variant="outlined" size="small">
              <i class="fa-solid fa-sliders me-2" aria-hidden="true" />
              Admin balances
            </v-btn>
          </RouterLink>
          <RouterLink v-if="canManageSettings" to="/settings/leave" class="leave-page__action-link">
            <v-btn variant="outlined" size="small">
              <i class="fa-solid fa-gear me-2" aria-hidden="true" />
              Leave settings
            </v-btn>
          </RouterLink>
        </div>
      </div>

      <PortalPillSubnav
        class="leave-page__tabs"
        :items="leaveTabItems"
        aria-label="Leave sections"
        @select="(key) => setTab(key as Tab)"
      />
    </header>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div
      v-if="loading && ((tab === 'balances' && !balances.length) || (tab === 'requests' && !requests.length) || (tab === 'approvals' && !approvals.length))"
      class="text-medium-emphasis"
    >
      Loading…
    </div>
    <div v-else-if="refreshing && tab !== 'plan'" class="text-caption text-medium-emphasis mb-2">Updating…</div>

    <LeavePlanPanel v-if="tab === 'plan'" />

    <div v-if="tab === 'balances' && (!loading || balances.length)" class="leave-balances">
      <template v-if="balances.length">
        <section class="leave-section" aria-label="Leave balance snapshot">
          <div class="leave-section__head">
            <div>
              <h2 class="leave-section__title">
                <i class="fa-solid fa-chart-pie" aria-hidden="true" />
                Balance snapshot
              </h2>
              <p class="leave-section__lede">Your entitlement for {{ balanceYear }} at a glance.</p>
            </div>
            <RouterLink to="/leave/apply" class="leave-page__action-link">
              <v-btn color="primary" size="small">
                <i class="fa-solid fa-plus me-2" aria-hidden="true" />
                Apply
              </v-btn>
            </RouterLink>
          </div>
          <v-row dense class="mb-3">
            <v-col v-for="card in balanceKpis" :key="card.label" cols="6" sm="4" md="2">
              <v-sheet
                rounded
                class="pa-3 perf-kpi"
                :style="{
                  '--perf-kpi-bg': `linear-gradient(135deg, ${card.color} 0%, ${card.color}dd 100%)`,
                }"
              >
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
        </section>

        <section class="leave-section" aria-label="Leave balances by type">
          <div class="leave-section__head leave-section__head--table">
            <h2 class="leave-section__title">
              <i class="fa-solid fa-table-list" aria-hidden="true" />
              Balances by type
            </h2>
          </div>
          <v-card class="portal-data-table-card leave-balances__table" variant="outlined">
            <div class="px-3 pt-1">
              <PortalTableToolbar
                placement="header"
                :page="balPage"
                :last-page="balLastPage"
                :total="balTotal"
                :per-page="balPerPage"
                total-label="Leave types"
                :exporting="exporting"
                @update:per-page="(v) => { balPerPage = v; balPage = 1 }"
                @export-csv="exportBalancesCsv"
                @export-pdf="exportBalancesPdf"
              />
            </div>
            <v-table density="compact">
              <thead>
                <tr>
                  <th style="width: 3rem">#</th>
                  <th>Leave type</th>
                  <th class="text-end">Available</th>
                  <th class="text-end d-none d-sm-table-cell">Opening</th>
                  <th class="text-end d-none d-md-table-cell">Carried</th>
                  <th class="text-end d-none d-md-table-cell">Accrued</th>
                  <th class="text-end">Used</th>
                  <th class="text-end">Pending</th>
                  <th class="text-end d-none d-lg-table-cell">Comp</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in balRows" :key="row.type.leave_id">
                  <td>
                    <span class="portal-dt-row-num">{{ (balPage - 1) * balPerPage + index + 1 }}</span>
                  </td>
                  <td>
                    <div class="leave-balances__type">
                      <i
                        class="fa-solid fa-leaf leave-balances__type-icon"
                        aria-hidden="true"
                      />
                      {{ row.type.leave_name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      <span v-if="row.type.is_accrued">Accrued</span>
                      <span v-else>Fixed entitlement</span>
                      <span v-if="row.type.leave_days"> · default {{ formatDays(row.type.leave_days) }}d</span>
                    </div>
                  </td>
                  <td class="text-end">
                    <span
                      class="leave-balances__available"
                      :class="{ 'is-low': Number(row.balance.available) <= 0 }"
                    >
                      {{ formatDays(row.balance.available) }}
                    </span>
                  </td>
                  <td class="text-end d-none d-sm-table-cell">{{ formatDays(row.balance.opening) }}</td>
                  <td class="text-end d-none d-md-table-cell">{{ formatDays(row.balance.carried_forward) }}</td>
                  <td class="text-end d-none d-md-table-cell">{{ formatDays(row.balance.accrued) }}</td>
                  <td class="text-end">{{ formatDays(row.balance.used) }}</td>
                  <td class="text-end">
                    <span :class="{ 'leave-balances__pending': Number(row.balance.pending) > 0 }">
                      {{ formatDays(row.balance.pending) }}
                    </span>
                  </td>
                  <td class="text-end d-none d-lg-table-cell">
                    {{ formatDays(row.balance.compensatory) }}
                  </td>
                </tr>
              </tbody>
            </v-table>
            <div class="px-3 pb-1">
              <PortalTableToolbar
                placement="footer"
                :page="balPage"
                :last-page="balLastPage"
                :total="balTotal"
                :per-page="balPerPage"
                :show-csv="false"
                :show-pdf="false"
                :show-per-page="false"
                @update:page="(v) => (balPage = v)"
              />
            </div>
          </v-card>
        </section>
      </template>
      <v-alert v-else type="info" variant="tonal" density="compact">
        No leave types configured. Ask HR to set up leave types in Settings.
      </v-alert>
    </div>

    <div v-if="tab === 'requests' && (!loading || requests.length)">
      <section class="leave-section" aria-label="Requests snapshot">
        <div class="leave-section__head">
          <div>
            <h2 class="leave-section__title">
              <i class="fa-solid fa-chart-simple" aria-hidden="true" />
              {{ showAllStaff ? 'Team snapshot' : 'Requests snapshot' }}
            </h2>
            <p class="leave-section__lede">
              {{ showAllStaff ? 'All-staff leave activity for the current filters.' : 'Your leave requests for the current filters.' }}
            </p>
          </div>
        </div>
        <v-row dense class="mb-3">
          <v-col cols="6" sm="4" md="2">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #0f766e 0%, #0f766edd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Total</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ requestSnapshot.total }}</div>
                </div>
                <i class="fa-solid fa-inbox perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="2">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #b45309 0%, #b45309dd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Pending</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ requestSnapshot.pending }}</div>
                </div>
                <i class="fa-solid fa-hourglass-half perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="2">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #15803d 0%, #15803ddd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Approved</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ requestSnapshot.approved }}</div>
                </div>
                <i class="fa-solid fa-circle-check perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="2">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #b91c1c 0%, #b91c1cdd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Rejected</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ requestSnapshot.rejected }}</div>
                </div>
                <i class="fa-solid fa-circle-xmark perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="2">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #1d4ed8 0%, #1d4ed8dd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Days</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ requestSnapshot.days }}</div>
                </div>
                <i class="fa-solid fa-calendar-day perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
        </v-row>
      </section>

      <div class="portal-staff-filters mb-3">
        <v-row dense>
          <v-col v-if="canViewAll" cols="12" md="3">
            <v-select
              :model-value="route.query.view === 'all' ? 'all' : 'mine'"
              :items="[
                { title: 'My requests', value: 'mine' },
                { title: 'All staff', value: 'all' },
              ]"
              label="Scope"
              density="compact"
              hide-details
              @update:model-value="
                (v) => {
                  reqPage = 1
                  router.replace({
                    query: { ...route.query, view: v === 'all' ? 'all' : 'requests' },
                  })
                }
              "
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="statusFilter"
              :items="[
                { title: 'All statuses', value: '' },
                { title: 'Pending', value: 'Pending' },
                { title: 'Approved', value: 'Approved' },
                { title: 'Rejected', value: 'Rejected' },
              ]"
              label="Status"
              density="compact"
              hide-details
              @update:model-value="reqPage = 1"
            />
          </v-col>
          <v-col cols="12" md="3">
            <UDateInput
              v-model="startDate"
              label="From"
              placeholder="From date"
              density="compact"
              hide-details
              :max="endDate || undefined"
              @update:model-value="reqPage = 1"
            />
          </v-col>
          <v-col cols="12" md="3">
            <UDateInput
              v-model="endDate"
              label="To"
              placeholder="To date"
              density="compact"
              hide-details
              :min="startDate || undefined"
              @update:model-value="reqPage = 1"
            />
          </v-col>
        </v-row>
      </div>
      <v-card class="portal-data-table-card" variant="outlined">
        <div class="px-3 pt-1">
          <PortalTableToolbar
            placement="header"
            :page="reqPage"
            :last-page="reqLastPage"
            :total="reqTotal"
            :per-page="reqPerPage"
            total-label="Total requests"
            :exporting="exporting"
            @update:per-page="(v) => { reqPerPage = v; reqPage = 1 }"
            @export-csv="exportRequestsCsv"
            @export-pdf="exportRequestsPdf"
          />
        </div>
        <v-table density="compact">
          <thead>
            <tr>
              <th style="width: 3rem">#</th>
              <th v-if="showAllStaff">Staff</th>
              <th v-if="showAllStaff">SAP / OCN</th>
              <th>Type</th>
              <th>From</th>
              <th>To</th>
              <th>Days</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(req, index) in reqRows" :key="req.request_id">
              <td>
                <span class="portal-dt-row-num">{{ (reqPage - 1) * reqPerPage + index + 1 }}</span>
              </td>
              <td v-if="showAllStaff">
                <div class="leave-staff-cell">
                  <i class="fa-solid fa-user leave-staff-cell__icon" aria-hidden="true" />
                  {{ req.staff_name }}
                </div>
              </td>
              <td v-if="showAllStaff">
                <code v-if="req.sap_number" class="leave-sap">{{ req.sap_number }}</code>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td>{{ req.leave_name }}</td>
              <td>{{ req.start_date }}</td>
              <td>{{ req.end_date }}</td>
              <td>{{ req.requested_days }}</td>
              <td>
                <v-chip size="small" :color="statusColor(req.overall_status)" variant="tonal">
                  {{ req.overall_status }}
                </v-chip>
              </td>
            </tr>
            <tr v-if="!reqRows.length">
              <td
                :colspan="showAllStaff ? 8 : 6"
                class="text-medium-emphasis text-center py-6"
              >
                No leave requests found.
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="px-3 pb-1">
          <PortalTableToolbar
            placement="footer"
            :page="reqPage"
            :last-page="reqLastPage"
            :total="reqTotal"
            :per-page="reqPerPage"
            :show-csv="false"
            :show-pdf="false"
            :show-per-page="false"
            @update:page="(v) => (reqPage = v)"
          />
        </div>
      </v-card>
    </div>

    <div v-if="tab === 'approvals' && (!loading || approvals.length)">
      <section class="leave-section" aria-label="Approvals snapshot">
        <div class="leave-section__head">
          <div>
            <h2 class="leave-section__title">
              <i class="fa-solid fa-users" aria-hidden="true" />
              Team snapshot
            </h2>
            <p class="leave-section__lede">Pending leave decisions waiting on your action.</p>
          </div>
        </div>
        <v-row dense class="mb-3">
          <v-col cols="6" sm="4" md="3">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #b45309 0%, #b45309dd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Pending</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ approvalSnapshot.total }}</div>
                </div>
                <i class="fa-solid fa-clipboard-list perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="3">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #1d4ed8 0%, #1d4ed8dd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Staff</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ approvalSnapshot.people }}</div>
                </div>
                <i class="fa-solid fa-user-group perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
          <v-col cols="6" sm="4" md="3">
            <v-sheet
              rounded
              class="pa-3 perf-kpi"
              style="--perf-kpi-bg: linear-gradient(135deg, #0f766e 0%, #0f766edd 100%)"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-uppercase perf-kpi__label">Days</div>
                  <div class="text-h6 font-weight-bold perf-kpi__value">{{ approvalSnapshot.days }}</div>
                </div>
                <i class="fa-solid fa-calendar-day perf-kpi__icon" aria-hidden="true" />
              </div>
            </v-sheet>
          </v-col>
        </v-row>
      </section>

      <v-card class="portal-data-table-card" variant="outlined">
        <div class="px-3 pt-1">
          <PortalTableToolbar
            placement="header"
            :page="apprPage"
            :last-page="apprLastPage"
            :total="apprTotal"
            :per-page="apprPerPage"
            total-label="Total approvals"
            :exporting="exporting"
            @update:per-page="(v) => { apprPerPage = v; apprPage = 1 }"
            @export-csv="exportApprovalsCsv"
            @export-pdf="exportApprovalsPdf"
          />
        </div>
        <v-table density="compact">
          <thead>
            <tr>
              <th style="width: 3rem">#</th>
              <th>Staff</th>
              <th>SAP / OCN</th>
              <th>Leave</th>
              <th>Days</th>
              <th>Period</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(req, index) in apprRows" :key="req.request_id">
              <td>
                <span class="portal-dt-row-num">{{ (apprPage - 1) * apprPerPage + index + 1 }}</span>
              </td>
              <td>
                <div class="leave-staff-cell">
                  <i class="fa-solid fa-user leave-staff-cell__icon" aria-hidden="true" />
                  {{ req.staff_name }}
                </div>
              </td>
              <td>
                <code v-if="req.sap_number" class="leave-sap">{{ req.sap_number }}</code>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td>{{ req.leave_name }}</td>
              <td>{{ req.requested_days }}</td>
              <td>{{ req.start_date }} – {{ req.end_date }}</td>
              <td class="text-no-wrap">
                <template v-if="approvalsHr || isHr">
                  <v-btn
                    size="x-small"
                    color="success"
                    class="me-1"
                    @click="onDecide(req.request_id, 'hr', 'approve')"
                  >
                    <i class="fa-solid fa-check me-1" aria-hidden="true" />
                    HR
                  </v-btn>
                  <v-btn
                    size="x-small"
                    variant="outlined"
                    color="error"
                    class="me-1"
                    @click="onDecide(req.request_id, 'hr', 'reject')"
                  >
                    <i class="fa-solid fa-xmark me-1" aria-hidden="true" />
                    HR
                  </v-btn>
                </template>
                <v-btn
                  size="x-small"
                  variant="outlined"
                  color="success"
                  class="me-1"
                  @click="onDecide(req.request_id, 'supervisor', 'approve')"
                >
                  <i class="fa-solid fa-user-check me-1" aria-hidden="true" />
                  Supervisor
                </v-btn>
                <v-btn
                  size="x-small"
                  variant="outlined"
                  color="primary"
                  @click="onDecide(req.request_id, 'hod', 'approve')"
                >
                  <i class="fa-solid fa-building-user me-1" aria-hidden="true" />
                  HOD
                </v-btn>
              </td>
            </tr>
            <tr v-if="!apprRows.length">
              <td colspan="7" class="text-medium-emphasis text-center py-6">No pending approvals.</td>
            </tr>
          </tbody>
        </v-table>
        <div class="px-3 pb-1">
          <PortalTableToolbar
            placement="footer"
            :page="apprPage"
            :last-page="apprLastPage"
            :total="apprTotal"
            :per-page="apprPerPage"
            :show-csv="false"
            :show-pdf="false"
            :show-per-page="false"
            @update:page="(v) => (apprPage = v)"
          />
        </div>
      </v-card>
    </div>
  </div>
</template>

<style scoped>
.leave-page__chrome {
  margin-bottom: 1rem;
}

.leave-page__title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem 1rem;
}

.leave-page__chrome :deep(.cbp-view-head) {
  margin-bottom: 0.15rem;
}

.leave-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding-top: 0.15rem;
}

.leave-page__action-link {
  text-decoration: none;
}

.leave-page__tabs {
  margin-top: 0.55rem;
}

.leave-section {
  margin-bottom: 0.35rem;
}

.leave-section__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.65rem 1rem;
  margin-bottom: 0.65rem;
}

.leave-section__head--table {
  margin-bottom: 0.45rem;
}

.leave-section__title {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #3a4752;
}

.leave-section__title i {
  color: #119a48;
}

.leave-section__lede {
  margin: 0.15rem 0 0;
  font-size: 0.82rem;
  color: rgba(58, 71, 82, 0.68);
}

.leave-balances__table :deep(th) {
  white-space: nowrap;
  font-size: 0.75rem;
  color: rgba(58, 71, 82, 0.72);
}

.leave-balances__table :deep(td) {
  vertical-align: middle;
}

.leave-balances__type {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
  color: #3a4752;
}

.leave-balances__type-icon {
  color: #119a48;
  font-size: 0.78rem;
}

.leave-balances__available {
  display: inline-block;
  min-width: 2.25rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  background: #e8f6ee;
  color: #0d7a3a;
  font-weight: 700;
}

.leave-balances__available.is-low {
  background: #fef3c7;
  color: #92400e;
}

.leave-balances__pending {
  color: #b45309;
  font-weight: 600;
}

.leave-staff-cell {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
}

.leave-staff-cell__icon {
  color: rgba(58, 71, 82, 0.45);
  font-size: 0.75rem;
}

.leave-sap {
  display: inline-block;
  padding: 0.1rem 0.4rem;
  border-radius: 0.3rem;
  background: #eef2f7;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 650;
}

.perf-kpi__label {
  color: rgba(255, 255, 255, 0.85);
  letter-spacing: 0.04em;
}

.perf-kpi__value {
  color: #fff;
  line-height: 1.15;
}

.perf-kpi__icon {
  color: rgba(255, 255, 255, 0.35);
  font-size: 1.35rem;
}
</style>
