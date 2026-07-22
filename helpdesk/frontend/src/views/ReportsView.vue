<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import ReportColumnHeader from '../components/reports/ReportColumnHeader.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { notifyError } from '../lib/notify'
import { formatDateTime } from '../lib/formatDateTime'
import {
  formatTableCountLabel,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'
import { normalizePageSize, type PageSize, type SelectNumberItem, type SelectStringItem } from '../lib/helpdeskForm'

import { useAuthStore } from '../stores/auth'

type SortItem = { key: string; order: 'asc' | 'desc' }

const auth = useAuthStore()
const tab = ref<'mine' | 'admin' | 'monthly' | 'infosystems'>('mine')
const itemsPerPageOptions = [10, 20, 50, 100] as const

const statusOptions = [
  { label: 'Open', value: 'open' },
  { label: 'Pending', value: 'pending' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Awaiting confirm', value: 'awaiting_requester_confirmation' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Closed', value: 'closed' },
] as const

const priorityOptions = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Urgent', value: 'urgent' },
] as const

const dateFieldOptions = [
  { label: 'Created', value: 'created_at' },
  { label: 'Resolved', value: 'resolved_at' },
  { label: 'Closed', value: 'closed_at' },
] as const

interface ReportColumnFilters {
  ticket_number: string
  subject: string
  assignee_name: string
  status: string
}

function emptyColumnFilters(): ReportColumnFilters {
  return { ticket_number: '', subject: '', assignee_name: '', status: '' }
}

const reportHeaders: DataTableHeader[] = [
  { title: '#', key: 'row_num', sortable: false, width: '52px', align: 'center' },
  { title: 'Ticket', key: 'ticket_number', sortable: false, minWidth: '120px' },
  { title: 'Subject', key: 'subject', sortable: false, minWidth: '200px' },
  { title: 'Assigned to', key: 'assignee_name', sortable: false, minWidth: '150px' },
  { title: 'Status', key: 'status', sortable: false, width: '130px' },
  { title: 'Created', key: 'created_at', sortable: false, minWidth: '140px' },
]

/** Ticket rows from report APIs (aligned with ticket API resource fields). */
interface ReportTicket {
  id: number
  ticket_number: string
  subject?: string
  status?: string
  created_at?: string | null
  assignee?: { name: string; avatar_url?: string | null } | null
}

interface PaginatedTickets {
  current_page: number
  data: ReportTicket[]
  last_page: number
  per_page: number
  total: number
}

const myStats = ref<{ total_received: number; pending: number; resolved: number } | null>(null)
const myTickets = ref<ReportTicket[]>([])
const myPage = ref(1)
const myTotal = ref(0)
const myItemsPerPage = ref<PageSize>(20)
const mySortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const mySearchState = reactive<{
  q: string
  statuses: string[]
  categoryIds: number[]
  priorities: string[]
  dateField: string
  dateFrom: string
  dateTo: string
}>({ q: '', statuses: [], categoryIds: [], priorities: [], dateField: 'created_at', dateFrom: '', dateTo: '' })
const myColumnFilters = reactive<ReportColumnFilters>(emptyColumnFilters())
const myLoading = ref(false)

const adminCounts = ref<Record<string, number> | null>(null)
const adminRecent = ref<ReportTicket[]>([])
const adminPage = ref(1)
const adminTotal = ref(0)
const adminItemsPerPage = ref<PageSize>(20)
const adminSortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const adminSearchState = reactive<{
  q: string
  agentIds: number[]
  groupIds: number[]
  categoryIds: number[]
  statuses: string[]
  priorities: string[]
  dateField: string
  dateFrom: string
  dateTo: string
}>({ q: '', agentIds: [], groupIds: [], categoryIds: [], statuses: [], priorities: [], dateField: 'created_at', dateFrom: '', dateTo: '' })
const adminColumnFilters = reactive<ReportColumnFilters>(emptyColumnFilters())
const adminLoading = ref(false)
const adminAgents = ref<{ id: number; name: string; email: string }[]>([])
const adminGroups = ref<{ id: number; name: string }[]>([])
const categories = ref<{ id: number; name: string }[]>([])

const adminFilterCount = computed(() => {
  let n = 0
  if (adminSearchState.q.trim()) n += 1
  if (adminSearchState.agentIds.length) n += 1
  if (adminSearchState.groupIds.length) n += 1
  if (adminSearchState.categoryIds.length) n += 1
  if (adminSearchState.statuses.length) n += 1
  if (adminSearchState.priorities.length) n += 1
  if (adminSearchState.dateFrom) n += 1
  if (adminSearchState.dateTo) n += 1
  if (adminSearchState.dateField !== 'created_at') n += 1
  if (adminColumnFilters.ticket_number.trim()) n += 1
  if (adminColumnFilters.subject.trim()) n += 1
  if (adminColumnFilters.assignee_name.trim()) n += 1
  if (adminColumnFilters.status.trim()) n += 1
  return n
})

const myFilterCount = computed(() => {
  let n = 0
  if (mySearchState.q.trim()) n += 1
  if (mySearchState.statuses.length) n += 1
  if (mySearchState.categoryIds.length) n += 1
  if (mySearchState.priorities.length) n += 1
  if (mySearchState.dateFrom) n += 1
  if (mySearchState.dateTo) n += 1
  if (mySearchState.dateField !== 'created_at') n += 1
  if (myColumnFilters.ticket_number.trim()) n += 1
  if (myColumnFilters.subject.trim()) n += 1
  if (myColumnFilters.assignee_name.trim()) n += 1
  if (myColumnFilters.status.trim()) n += 1
  return n
})

const adminAgentItems = computed((): SelectNumberItem[] =>
  adminAgents.value.map((a) => ({ label: a.name, value: a.id })),
)
const adminGroupItems = computed((): SelectNumberItem[] =>
  adminGroups.value.map((g) => ({ label: g.name, value: g.id })),
)
const categoryItems = computed((): SelectNumberItem[] =>
  categories.value.map((c) => ({ label: c.name, value: c.id })),
)
const statusSelectItems = computed((): SelectStringItem[] =>
  statusOptions.map((s) => ({ label: s.label, value: s.value })),
)
const prioritySelectItems = computed((): SelectStringItem[] =>
  priorityOptions.map((p) => ({ label: p.label, value: p.value })),
)

const isAdmin = computed(
  () => !!auth.me?.profile?.is_helpdesk_admin || auth.me?.profile?.role === 'admin',
)
const canManageInformationSystems = computed(
  () => isAdmin.value || !!auth.me?.profile?.can_manage_information_systems,
)
const isStaff = computed(() => {
  const role = auth.me?.profile?.role
  return ['agent', 'supervisor', 'admin', 'auditor'].includes(role ?? '')
})

interface MonthlyReportRow {
  id: number
  user_id: number
  user_name?: string
  period_year: number
  period_month: number
  period_label: string
  tickets_worked?: number | null
  tickets_resolved?: number | null
  avg_first_response_minutes?: number | null
  emailed_at?: string | null
  created_at?: string | null
}

interface MonthlyReportDetail extends MonthlyReportRow {
  ai_summary?: string
  ai_model?: string | null
  metrics?: Record<string, unknown>
}

const monthlyReports = ref<MonthlyReportRow[]>([])
const monthlyDetail = ref<MonthlyReportDetail | null>(null)
const monthlyLoading = ref(false)
const monthlyYear = ref(new Date().getFullYear())
const monthlyMonth = ref(new Date().getMonth() || 12)
const monthlyAgentId = ref<number | null>(null)

interface InfoSystemsSummary {
  systems_total: number
  systems_by_status: Record<string, number>
  modules_total: number
  modules_by_status: Record<string, number>
  missing_focal: number
  missing_mis_focal: number
  by_division: Record<string, number>
}

interface InfoSystemsTrendRow {
  date: string
  to_status: string
  count: number
}

const isSummary = ref<InfoSystemsSummary | null>(null)
const isTrends = ref<InfoSystemsTrendRow[]>([])
const isLoading = ref(false)
const isDateFrom = ref('')
const isDateTo = ref('')
const isExporting = ref(false)
const myTableCountLabel = computed(() =>
  formatTableCountLabel(myTickets.value.length, myTotal.value, myPage.value, myItemsPerPage.value),
)
const adminTableCountLabel = computed(() =>
  formatTableCountLabel(adminRecent.value.length, adminTotal.value, adminPage.value, adminItemsPerPage.value),
)

function myCounter(idx: number): number {
  return rowIndex(myPage.value, myItemsPerPage.value, idx)
}

function adminCounter(idx: number): number {
  return rowIndex(adminPage.value, adminItemsPerPage.value, idx)
}

function reportFilterParams(state: {
  statuses: string[]
  categoryIds: number[]
  priorities: string[]
  dateField: string
  dateFrom: string
  dateTo: string
  agentIds?: number[]
  groupIds?: number[]
}) {
  return {
    statuses: state.statuses.length ? state.statuses : undefined,
    category_ids: state.categoryIds.length ? state.categoryIds : undefined,
    priorities: state.priorities.length ? state.priorities : undefined,
    date_field: state.dateField !== 'created_at' ? state.dateField : undefined,
    date_from: state.dateFrom || undefined,
    date_to: state.dateTo || undefined,
    agent_ids: state.agentIds?.length ? state.agentIds : undefined,
    group_ids: state.groupIds?.length ? state.groupIds : undefined,
  }
}

function columnFilterParams(state: ReportColumnFilters) {
  return {
    col_ticket: state.ticket_number.trim() || undefined,
    col_subject: state.subject.trim() || undefined,
    col_assignee: state.assignee_name.trim() || undefined,
    col_status: state.status.trim() || undefined,
  }
}

/** Laravel-friendly array query encoding for report filters. */
function serializeReportQueryParams(params: Record<string, unknown>): string {
  const parts: string[] = []
  for (const [key, raw] of Object.entries(params)) {
    if (raw === undefined || raw === null || raw === '') continue
    if (Array.isArray(raw)) {
      if (raw.length === 0) continue
      for (const item of raw) {
        parts.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(String(item))}`)
      }
      continue
    }
    parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(raw))}`)
  }
  return parts.join('&')
}

async function loadMine() {
  myLoading.value = true
  try {
    const params = {
      q: mySearchState.q.trim() || undefined,
      page: myPage.value,
      per_page: myItemsPerPage.value,
      ...reportFilterParams(mySearchState),
      ...columnFilterParams(myColumnFilters),
    }
    const { data } = await api.get('/api/v1/reports/my-requester', {
      params,
      paramsSerializer: () => serializeReportQueryParams(params),
    })
    myStats.value = data.data.stats
    const tickets = (data.data.tickets ?? {}) as Partial<PaginatedTickets>
    myTickets.value = (tickets.data ?? []) as ReportTicket[]
    myPage.value = Number(tickets.current_page ?? myPage.value)
    myItemsPerPage.value = normalizePageSize(Number(tickets.per_page ?? myItemsPerPage.value))
    myTotal.value = Number(tickets.total ?? myTickets.value.length)
  } finally {
    myLoading.value = false
  }
}

function adminReportParams() {
  return {
    q: adminSearchState.q.trim() || undefined,
    page: adminPage.value,
    per_page: adminItemsPerPage.value,
    ...reportFilterParams(adminSearchState),
    ...columnFilterParams(adminColumnFilters),
  }
}

async function loadAdmin() {
  adminLoading.value = true
  try {
    const params = adminReportParams()
    const { data } = await api.get('/api/v1/reports/admin-summary', {
      params,
      paramsSerializer: () => serializeReportQueryParams(params),
    })
    adminCounts.value = data.data.counts
    const recent = (data.data.recent ?? {}) as Partial<PaginatedTickets>
    adminRecent.value = (recent.data ?? []) as ReportTicket[]
    adminPage.value = Number(recent.current_page ?? adminPage.value)
    adminItemsPerPage.value = normalizePageSize(Number(recent.per_page ?? adminItemsPerPage.value))
    adminTotal.value = Number(recent.total ?? adminRecent.value.length)
  } finally {
    adminLoading.value = false
  }
}

async function loadMonthly() {
  monthlyLoading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/agent-monthly', {
      params: {
        year: monthlyYear.value,
        month: monthlyMonth.value || undefined,
        user_id: isAdmin.value && monthlyAgentId.value ? monthlyAgentId.value : undefined,
      },
    })
    monthlyReports.value = Array.isArray(data.data) ? data.data : []
    if (monthlyReports.value.length && !monthlyDetail.value) {
      await openMonthlyReport(monthlyReports.value[0].id)
    }
  } finally {
    monthlyLoading.value = false
  }
}

async function openMonthlyReport(id: number) {
  try {
    const { data } = await api.get(`/api/v1/reports/agent-monthly/${id}`)
    monthlyDetail.value = data.data as MonthlyReportDetail
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load monthly report'))
  }
}

async function loadInfoSystems() {
  if (!canManageInformationSystems.value) return
  isLoading.value = true
  try {
    const params: Record<string, string> = {}
    if (isDateFrom.value) params.date_from = isDateFrom.value
    if (isDateTo.value) params.date_to = isDateTo.value
    const [sumRes, trendRes] = await Promise.all([
      api.get<{ data: InfoSystemsSummary }>('/api/v1/tools/information-systems/summary'),
      api.get<{ data: InfoSystemsTrendRow[] }>('/api/v1/tools/information-systems/reports/trends', { params }),
    ])
    isSummary.value = sumRes.data.data
    isTrends.value = Array.isArray(trendRes.data.data) ? trendRes.data.data : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load information systems report'))
    isSummary.value = null
    isTrends.value = []
  } finally {
    isLoading.value = false
  }
}

async function exportInfoSystems() {
  isExporting.value = true
  try {
    const { data } = await api.get('/api/v1/tools/information-systems/export', {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'information-systems.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Export failed'))
  } finally {
    isExporting.value = false
  }
}

async function switchTab(next: 'mine' | 'admin' | 'monthly' | 'infosystems') {
  tab.value = next
  await load()
}

async function load() {
  try {
    if (tab.value === 'infosystems' && canManageInformationSystems.value) {
      await loadInfoSystems()
    } else if (tab.value === 'monthly' && isStaff.value) {
      await loadMonthly()
    } else if (tab.value === 'admin' && isAdmin.value) {
      await loadAdmin()
    } else {
      await loadMine()
    }
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load report'))
    myLoading.value = false
    adminLoading.value = false
    monthlyLoading.value = false
    isLoading.value = false
  }
}

function mySearch() {
  myPage.value = 1
  loadMine()
}
function myClear() {
  mySearchState.q = ''
  mySearchState.statuses = []
  mySearchState.categoryIds = []
  mySearchState.priorities = []
  mySearchState.dateField = 'created_at'
  mySearchState.dateFrom = ''
  mySearchState.dateTo = ''
  Object.assign(myColumnFilters, emptyColumnFilters())
  myPage.value = 1
  loadMine()
}
function adminSearch() {
  adminPage.value = 1
  loadAdmin()
}
function adminClear() {
  adminSearchState.q = ''
  adminSearchState.agentIds = []
  adminSearchState.groupIds = []
  adminSearchState.categoryIds = []
  adminSearchState.statuses = []
  adminSearchState.priorities = []
  adminSearchState.dateField = 'created_at'
  adminSearchState.dateFrom = ''
  adminSearchState.dateTo = ''
  Object.assign(adminColumnFilters, emptyColumnFilters())
  adminPage.value = 1
  loadAdmin()
}

function onMyColumnFilter() {
  myPage.value = 1
  void loadMine()
}

function onAdminColumnFilter() {
  adminPage.value = 1
  void loadAdmin()
}

function onMyUpdateOptions(options: { page: number; itemsPerPage: number; sortBy: SortItem[] }) {
  myPage.value = options.page
  myItemsPerPage.value = options.itemsPerPage as PageSize
  mySortBy.value = options.sortBy
  void loadMine()
}

function onAdminUpdateOptions(options: { page: number; itemsPerPage: number; sortBy: SortItem[] }) {
  adminPage.value = options.page
  adminItemsPerPage.value = options.itemsPerPage as PageSize
  adminSortBy.value = options.sortBy
  void loadAdmin()
}

async function downloadExcel(scope: 'assigned' | 'all' | 'mine') {
  try {
    const params: Record<string, unknown> = { scope }
    if (scope === 'all' && tab.value === 'admin') {
      Object.assign(params, {
        q: adminSearchState.q.trim() || undefined,
        ...reportFilterParams(adminSearchState),
        ...columnFilterParams(adminColumnFilters),
      })
    } else if (scope === 'mine') {
      Object.assign(params, {
        q: mySearchState.q.trim() || undefined,
        ...reportFilterParams(mySearchState),
        ...columnFilterParams(myColumnFilters),
      })
    }
    const res = await api.get('/api/v1/reports/export', {
      params,
      paramsSerializer: () => serializeReportQueryParams(params),
      responseType: 'blob',
    })
    const blob = new Blob([res.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `helpdesk-export-${scope}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    notifyError('Export failed (check you are signed in as staff).')
  }
}

async function bootstrapReports(): Promise<void> {
  if (!auth.me && auth.isAuthenticated) {
    try {
      await auth.fetchMe()
    } catch {
      // keep going with whatever role state we have
    }
  }

  try {
    const { data } = await api.get<{ data: { id: number; name: string }[] }>('/api/v1/categories')
    categories.value = Array.isArray(data.data) ? data.data : []
  } catch {
    categories.value = []
  }

  if (isAdmin.value) {
    tab.value = 'admin'
    try {
      const { data } = await api.get('/api/v1/admin/agents')
      adminAgents.value = Array.isArray(data.data) ? data.data : []
    } catch {
      adminAgents.value = []
    }
    try {
      const { data } = await api.get('/api/v1/admin/support-groups')
      adminGroups.value = (Array.isArray(data.data) ? data.data : []).map((g: { id: number; name: string }) => ({
        id: g.id,
        name: g.name,
      }))
    } catch {
      adminGroups.value = []
    }
  } else if (isStaff.value) {
    tab.value = 'monthly'
    const prev = new Date()
    prev.setMonth(prev.getMonth() - 1)
    monthlyYear.value = prev.getFullYear()
    monthlyMonth.value = prev.getMonth() + 1
  } else {
    tab.value = 'mine'
  }
  await load()
}

onMounted(() => {
  void bootstrapReports()
})

watch(
  () => auth.me?.profile?.role,
  (role, prev) => {
    if (!role || role === prev) return
    // Profile arrived after first paint — re-bootstrap once so admin/staff tabs populate.
    if (!adminCounts.value && !myStats.value && !monthlyReports.value.length) {
      void bootstrapReports()
    }
  },
)
</script>

<template>
  <div>
    <CbpPageHeading title="Reports" back-to="/" back-label="← Overview" />
    <div class="cbp-card">
      <div
        v-if="isAdmin || isStaff || canManageInformationSystems"
        class="report-tabs"
        :class="{
          'report-tabs--three': (isAdmin && isStaff) || canManageInformationSystems,
          'report-tabs--four': isAdmin && isStaff && canManageInformationSystems,
        }"
        role="tablist"
        aria-label="Report views"
      >
        <button
          v-if="isAdmin"
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'admin' }"
          :aria-selected="tab === 'admin'"
          @click="switchTab('admin')"
        >
          Admin overview
        </button>
        <button
          v-if="isStaff"
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'monthly' }"
          :aria-selected="tab === 'monthly'"
          @click="switchTab('monthly')"
        >
          Monthly agent report
        </button>
        <button
          v-if="canManageInformationSystems"
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'infosystems' }"
          :aria-selected="tab === 'infosystems'"
          @click="switchTab('infosystems')"
        >
          Information systems
        </button>
        <button
          type="button"
          role="tab"
          class="report-tab"
          :class="{ 'report-tab--on': tab === 'mine' }"
          :aria-selected="tab === 'mine'"
          @click="switchTab('mine')"
        >
          My issues
        </button>
      </div>

      <template v-if="tab === 'mine' && myStats">
      <section class="reports-filters">
        <header class="reports-filters__head">
          <h2 class="reports-filters__title">
            <i class="bx bx-filter-alt" aria-hidden="true" />
            Filters
          </h2>
          <span v-if="myFilterCount" class="hd-filter-panel__badge">{{ myFilterCount }} active</span>
        </header>
        <UForm :state="mySearchState" class="hd-form hd-form--grid reports-filters__body" @submit="mySearch">
          <UFormField label="Search" name="q" class="full">
            <UInput
              v-model="mySearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Ticket #, subject, status, assignee…"
              aria-label="Search my tickets"
              clearable
            />
          </UFormField>
          <UFormField label="Status" name="statuses">
            <USelectMenu
              v-model="mySearchState.statuses"
              :items="statusSelectItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-flag-outline"
              placeholder="All statuses"
            />
          </UFormField>
          <UFormField label="Category" name="categoryIds">
            <USelectMenu
              v-model="mySearchState.categoryIds"
              :items="categoryItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-tag-outline"
              placeholder="All categories"
            />
          </UFormField>
          <UFormField label="Priority" name="priorities">
            <USelectMenu
              v-model="mySearchState.priorities"
              :items="prioritySelectItems"
              value-key="value"
              multiple
              icon="mdi-alert-circle-outline"
              placeholder="All priorities"
            />
          </UFormField>
          <UFormField label="Date field" name="dateField">
            <USelect v-model="mySearchState.dateField" :items="[...dateFieldOptions]" icon="mdi-calendar-clock" />
          </UFormField>
          <UFormField label="From date" name="dateFrom">
            <UDateInput v-model="mySearchState.dateFrom" placeholder="Select start date" />
          </UFormField>
          <UFormField label="To date" name="dateTo">
            <UDateInput v-model="mySearchState.dateTo" placeholder="Select end date" />
          </UFormField>
          <div class="hd-form-actions full">
            <UButton type="submit" color="primary">Apply filters</UButton>
            <UButton type="button" color="neutral" variant="outline" @click="myClear">
              Clear
            </UButton>
          </div>
        </UForm>
      </section>

      <div class="tiles">
        <article class="tile tile-total">
          <header>
            <span class="tile-icon" aria-hidden="true">📥</span>
            <span class="l">Total received</span>
          </header>
          <span class="n">{{ myStats.total_received }}</span>
          <small class="tile-sub">All tickets logged for you</small>
        </article>
        <article class="tile tile-pending">
          <header>
            <span class="tile-icon" aria-hidden="true">⏳</span>
            <span class="l">Pending resolution</span>
          </header>
          <span class="n">{{ myStats.pending }}</span>
          <small class="tile-sub">Still being worked on</small>
        </article>
        <article class="tile tile-resolved">
          <header>
            <span class="tile-icon" aria-hidden="true">✅</span>
            <span class="l">Resolved</span>
          </header>
          <span class="n">{{ myStats.resolved }}</span>
          <small class="tile-sub">Completed tickets</small>
        </article>
      </div>
      <div class="report-tools">
        <UButton type="button" color="primary" class="report-tools__btn" @click="downloadExcel('mine')">
          Export my issues (Excel)
        </UButton>
      </div>
      <h2>My tickets &amp; assignees</h2>
      <v-card class="hd-data-table-card" elevation="10">
        <v-card-text class="hd-data-table-card__head">
          <p class="table-count" role="status">
            Showing <strong>{{ myTableCountLabel }}</strong>
          </p>
        </v-card-text>
        <v-data-table-server
          v-model:page="myPage"
          v-model:items-per-page="myItemsPerPage"
          v-model:sort-by="mySortBy"
          class="hd-data-table"
          :headers="reportHeaders"
          :items="myTickets"
          :items-length="myTotal"
          :items-per-page-options="[...itemsPerPageOptions]"
          :loading="myLoading"
          density="compact"
          hover
          item-value="id"
          @update:options="onMyUpdateOptions"
        >
          <template #header.ticket_number>
            <ReportColumnHeader
              v-model="myColumnFilters.ticket_number"
              title="Ticket"
              placeholder="Ticket #…"
              ariaLabel="Filter by ticket number"
              @filter="onMyColumnFilter"
            />
          </template>
          <template #header.subject>
            <ReportColumnHeader
              v-model="myColumnFilters.subject"
              title="Subject"
              placeholder="Subject…"
              ariaLabel="Filter by subject"
              @filter="onMyColumnFilter"
            />
          </template>
          <template #header.assignee_name>
            <ReportColumnHeader
              v-model="myColumnFilters.assignee_name"
              title="Assigned to"
              placeholder="Assignee…"
              ariaLabel="Filter by assignee"
              @filter="onMyColumnFilter"
            />
          </template>
          <template #header.status>
            <ReportColumnHeader
              v-model="myColumnFilters.status"
              title="Status"
              placeholder="Status…"
              ariaLabel="Filter by status"
              @filter="onMyColumnFilter"
            />
          </template>
          <template #item.row_num="{ index }">
            <span class="hd-dt-row-num">{{ myCounter(index) }}</span>
          </template>
          <template #item.ticket_number="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-ticket-link">
              {{ item.ticket_number }}
            </RouterLink>
            <span v-else class="hd-dt-ticket-link">{{ item.ticket_number }}</span>
          </template>
          <template #item.subject="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-subject-link">
              {{ item.subject }}
            </RouterLink>
            <span v-else class="hd-dt-subject-link">{{ item.subject }}</span>
          </template>
          <template #item.assignee_name="{ item }">
            <div v-if="item.assignee" class="hd-dt-person">
              <CbpAvatar size="sm" :name="item.assignee.name" :image-url="item.assignee.avatar_url ?? null" />
              <span class="hd-dt-person-name">{{ item.assignee.name }}</span>
            </div>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.status="{ item }">
            <span
              v-if="item.status"
              class="hd-dt-pill"
              :style="{ background: statusMeta(item.status).bg, color: statusMeta(item.status).color }"
            >
              {{ statusMeta(item.status).label }}
            </span>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.created_at="{ item }">
            <time
              v-if="item.created_at"
              class="hd-dt-created"
              :datetime="item.created_at"
              :title="formatDateTime(item.created_at)"
            >
              {{ formatDateTime(item.created_at) }}
            </time>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #no-data>
            <div class="hd-dt-empty-msg">No matching tickets.</div>
          </template>
          <template #loading>
            <div class="hd-dt-loading">Loading…</div>
          </template>
        </v-data-table-server>
      </v-card>
    </template>

    <template v-else-if="tab === 'admin' && adminCounts">
      <section class="reports-filters">
        <header class="reports-filters__head">
          <h2 class="reports-filters__title">
            <i class="bx bx-filter-alt" aria-hidden="true" />
            Filters
          </h2>
          <span v-if="adminFilterCount" class="hd-filter-panel__badge">{{ adminFilterCount }} active</span>
        </header>
        <UForm :state="adminSearchState" class="hd-form hd-form--grid reports-filters__body" @submit="adminSearch">
          <UFormField label="Search" name="q" class="full">
            <UInput
              v-model="adminSearchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Ticket #, subject, requester, assignee…"
              aria-label="Search admin recent activity"
              clearable
            />
          </UFormField>
          <UFormField label="Agents" name="agentIds">
            <USelectMenu
              v-model="adminSearchState.agentIds"
              :items="adminAgentItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-account-group"
              placeholder="All agents"
            />
          </UFormField>
          <UFormField label="Support groups" name="groupIds">
            <USelectMenu
              v-model="adminSearchState.groupIds"
              :items="adminGroupItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-account-multiple"
              placeholder="All groups"
            />
          </UFormField>
          <UFormField label="Category" name="categoryIds">
            <USelectMenu
              v-model="adminSearchState.categoryIds"
              :items="categoryItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-tag-outline"
              placeholder="All categories"
            />
          </UFormField>
          <UFormField label="Status" name="statuses">
            <USelectMenu
              v-model="adminSearchState.statuses"
              :items="statusSelectItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-flag-outline"
              placeholder="All statuses"
            />
          </UFormField>
          <UFormField label="Priority" name="priorities">
            <USelectMenu
              v-model="adminSearchState.priorities"
              :items="prioritySelectItems"
              value-key="value"
              multiple
              icon="mdi-alert-circle-outline"
              placeholder="All priorities"
            />
          </UFormField>
          <UFormField label="Date field" name="dateField">
            <USelect v-model="adminSearchState.dateField" :items="[...dateFieldOptions]" icon="mdi-calendar-clock" />
          </UFormField>
          <UFormField label="From date" name="dateFrom">
            <UDateInput v-model="adminSearchState.dateFrom" placeholder="Select start date" />
          </UFormField>
          <UFormField label="To date" name="dateTo">
            <UDateInput v-model="adminSearchState.dateTo" placeholder="Select end date" />
          </UFormField>
          <div class="hd-form-actions full">
            <UButton type="submit" color="primary">Apply filters</UButton>
            <UButton type="button" color="neutral" variant="outline" @click="adminClear">
              Clear all
            </UButton>
          </div>
        </UForm>
      </section>

      <div class="tiles">
        <article class="tile tile-total">
          <header>
            <span class="tile-icon" aria-hidden="true">🎫</span>
            <span class="l">Total</span>
          </header>
          <span class="n">{{ adminCounts.total ?? 0 }}</span>
          <small class="tile-sub">All helpdesk tickets</small>
        </article>
        <article class="tile tile-open">
          <header>
            <span class="tile-icon" aria-hidden="true">🗂</span>
            <span class="l">Open</span>
          </header>
          <span class="n">{{ adminCounts.open ?? 0 }}</span>
          <small class="tile-sub">Open + pending + in progress</small>
        </article>
        <article class="tile tile-awaiting">
          <header>
            <span class="tile-icon" aria-hidden="true">⌛</span>
            <span class="l">Awaiting requester</span>
          </header>
          <span class="n">{{ adminCounts.awaiting_requester_confirmation ?? 0 }}</span>
          <small class="tile-sub">Resolution shared, waiting confirmation</small>
        </article>
        <article class="tile tile-resolved">
          <header>
            <span class="tile-icon" aria-hidden="true">✅</span>
            <span class="l">Resolved</span>
          </header>
          <span class="n">{{ adminCounts.resolved ?? 0 }}</span>
          <small class="tile-sub">Marked resolved</small>
        </article>
        <article class="tile tile-closed">
          <header>
            <span class="tile-icon" aria-hidden="true">🔒</span>
            <span class="l">Closed</span>
          </header>
          <span class="n">{{ adminCounts.closed ?? 0 }}</span>
          <small class="tile-sub">Finalized after confirmation</small>
        </article>
      </div>
      <div class="report-tools">
        <UButton type="button" color="primary" class="report-tools__btn" @click="downloadExcel('all')">
          Export all tickets (Excel)
        </UButton>
        <UButton
          type="button"
          color="neutral"
          variant="outline"
          class="report-tools__btn"
          @click="downloadExcel('assigned')"
        >
          Export my assigned (Excel)
        </UButton>
      </div>
      <h2>Recent activity</h2>
      <v-card class="hd-data-table-card" elevation="10">
        <v-card-text class="hd-data-table-card__head">
          <p class="table-count" role="status">
            Showing <strong>{{ adminTableCountLabel }}</strong>
          </p>
        </v-card-text>
        <v-data-table-server
          v-model:page="adminPage"
          v-model:items-per-page="adminItemsPerPage"
          v-model:sort-by="adminSortBy"
          class="hd-data-table"
          :headers="reportHeaders"
          :items="adminRecent"
          :items-length="adminTotal"
          :items-per-page-options="[...itemsPerPageOptions]"
          :loading="adminLoading"
          density="compact"
          hover
          item-value="id"
          @update:options="onAdminUpdateOptions"
        >
          <template #header.ticket_number>
            <ReportColumnHeader
              v-model="adminColumnFilters.ticket_number"
              title="Ticket"
              placeholder="Ticket #…"
              ariaLabel="Filter by ticket number"
              @filter="onAdminColumnFilter"
            />
          </template>
          <template #header.subject>
            <ReportColumnHeader
              v-model="adminColumnFilters.subject"
              title="Subject"
              placeholder="Subject…"
              ariaLabel="Filter by subject"
              @filter="onAdminColumnFilter"
            />
          </template>
          <template #header.assignee_name>
            <ReportColumnHeader
              v-model="adminColumnFilters.assignee_name"
              title="Assigned to"
              placeholder="Assignee…"
              ariaLabel="Filter by assignee"
              @filter="onAdminColumnFilter"
            />
          </template>
          <template #header.status>
            <ReportColumnHeader
              v-model="adminColumnFilters.status"
              title="Status"
              placeholder="Status…"
              ariaLabel="Filter by status"
              @filter="onAdminColumnFilter"
            />
          </template>
          <template #item.row_num="{ index }">
            <span class="hd-dt-row-num">{{ adminCounter(index) }}</span>
          </template>
          <template #item.ticket_number="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-ticket-link">
              {{ item.ticket_number }}
            </RouterLink>
            <span v-else class="hd-dt-ticket-link">{{ item.ticket_number }}</span>
          </template>
          <template #item.subject="{ item }">
            <RouterLink v-if="item.id" :to="`/tickets/${item.id}`" class="hd-dt-subject-link">
              {{ item.subject }}
            </RouterLink>
            <span v-else class="hd-dt-subject-link">{{ item.subject }}</span>
          </template>
          <template #item.assignee_name="{ item }">
            <div v-if="item.assignee" class="hd-dt-person">
              <CbpAvatar size="sm" :name="item.assignee.name" :image-url="item.assignee.avatar_url ?? null" />
              <span class="hd-dt-person-name">{{ item.assignee.name }}</span>
            </div>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.status="{ item }">
            <span
              v-if="item.status"
              class="hd-dt-pill"
              :style="{ background: statusMeta(item.status).bg, color: statusMeta(item.status).color }"
            >
              {{ statusMeta(item.status).label }}
            </span>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #item.created_at="{ item }">
            <time
              v-if="item.created_at"
              class="hd-dt-created"
              :datetime="item.created_at"
              :title="formatDateTime(item.created_at)"
            >
              {{ formatDateTime(item.created_at) }}
            </time>
            <span v-else class="hd-dt-empty">—</span>
          </template>
          <template #no-data>
            <div class="hd-dt-empty-msg">No matching tickets.</div>
          </template>
          <template #loading>
            <div class="hd-dt-loading">Loading…</div>
          </template>
        </v-data-table-server>
      </v-card>
    </template>

    <template v-else-if="tab === 'monthly' && isStaff">
      <div class="monthly-toolbar">
        <div class="hd-form hd-form--grid">
          <UFormField label="Year" name="monthlyYear">
            <UInput v-model.number="monthlyYear" type="number" min="2020" max="2100" icon="mdi-calendar" />
          </UFormField>
          <UFormField label="Month" name="monthlyMonth">
            <UInput v-model.number="monthlyMonth" type="number" min="1" max="12" icon="mdi-calendar-month" />
          </UFormField>
          <UFormField v-if="isAdmin" label="Agent" name="monthlyAgentId">
            <USelectMenu
              v-model="monthlyAgentId"
              :items="adminAgentItems"
              value-key="value"
              searchable
              clearable
              icon="mdi-account"
              placeholder="All agents"
            />
          </UFormField>
        </div>
        <div class="report-tools">
          <UButton type="button" color="primary" :loading="monthlyLoading" @click="loadMonthly()">Load reports</UButton>
        </div>
        <p class="monthly-hint muted">
          Reports are generated automatically at month end and emailed to agents. Adjust retention under Settings → General.
        </p>
      </div>

      <div v-if="monthlyLoading" class="muted">Loading monthly reports…</div>
      <div v-else class="monthly-layout">
        <aside v-if="monthlyReports.length" class="monthly-list">
          <button
            v-for="row in monthlyReports"
            :key="row.id"
            type="button"
            class="monthly-list-item"
            :class="{ 'monthly-list-item--on': monthlyDetail?.id === row.id }"
            @click="openMonthlyReport(row.id)"
          >
            <strong>{{ row.period_label }}</strong>
            <span v-if="row.user_name">{{ row.user_name }}</span>
            <span class="muted">{{ row.tickets_worked ?? 0 }} worked · {{ row.tickets_resolved ?? 0 }} resolved</span>
          </button>
        </aside>
        <p v-else class="muted">No saved reports for this period yet. Reports appear after the scheduled month-end run.</p>

        <article v-if="monthlyDetail" class="monthly-detail">
          <header class="monthly-detail-head">
            <h2>{{ monthlyDetail.period_label }} — {{ monthlyDetail.user_name ?? 'Agent' }}</h2>
            <p class="muted">
              {{ monthlyDetail.tickets_worked ?? 0 }} tickets worked ·
              {{ monthlyDetail.tickets_resolved ?? 0 }} resolved ·
              Avg response: {{ monthlyDetail.avg_first_response_minutes ?? 'n/a' }} min
              <span v-if="monthlyDetail.emailed_at"> · Emailed {{ new Date(monthlyDetail.emailed_at).toLocaleDateString() }}</span>
            </p>
          </header>
          <div class="monthly-summary">{{ monthlyDetail.ai_summary }}</div>
        </article>
      </div>
    </template>

    <template v-else-if="tab === 'infosystems' && canManageInformationSystems">
      <div class="is-toolbar">
        <UFormField label="Trends from" name="isDateFrom">
          <UInput v-model="isDateFrom" type="date" />
        </UFormField>
        <UFormField label="to" name="isDateTo">
          <UInput v-model="isDateTo" type="date" />
        </UFormField>
        <UButton type="button" color="primary" :loading="isLoading" @click="loadInfoSystems">Refresh</UButton>
        <UButton type="button" color="neutral" variant="outline" :loading="isExporting" @click="exportInfoSystems">
          Export Excel
        </UButton>
      </div>
      <div v-if="isLoading" class="muted">Loading information systems…</div>
      <template v-else-if="isSummary">
        <div class="kpi-row">
          <div class="kpi"><div class="kpi-label">Systems</div><div class="kpi-value">{{ isSummary.systems_total }}</div></div>
          <div class="kpi"><div class="kpi-label">Modules</div><div class="kpi-value">{{ isSummary.modules_total }}</div></div>
          <div class="kpi"><div class="kpi-label">Missing focal</div><div class="kpi-value">{{ isSummary.missing_focal }}</div></div>
          <div class="kpi"><div class="kpi-label">Missing MIS focal</div><div class="kpi-value">{{ isSummary.missing_mis_focal }}</div></div>
        </div>
        <div class="is-grid">
          <section>
            <h3 class="h3">Systems by status</h3>
            <ul class="is-list">
              <li v-for="(count, status) in isSummary.systems_by_status" :key="'s-' + status">
                <span>{{ status }}</span><strong>{{ count }}</strong>
              </li>
            </ul>
          </section>
          <section>
            <h3 class="h3">By division</h3>
            <ul class="is-list">
              <li v-for="(count, div) in isSummary.by_division" :key="'d-' + div">
                <span>{{ div }}</span><strong>{{ count }}</strong>
              </li>
            </ul>
          </section>
        </div>
        <section class="is-trends">
          <h3 class="h3">Status change trends</h3>
          <p v-if="!isTrends.length" class="muted">No status changes in this date range.</p>
          <table v-else class="is-table">
            <thead>
              <tr><th>Date</th><th>To status</th><th>Count</th></tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in isTrends" :key="idx">
                <td>{{ row.date }}</td>
                <td>{{ row.to_status }}</td>
                <td>{{ row.count }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>
    </template>

    <p v-else-if="tab === 'admin' && adminLoading" class="muted">Loading admin overview…</p>
    <p v-else-if="tab === 'mine' && myLoading" class="muted">Loading your issues…</p>
    <p v-else-if="tab !== 'monthly' && tab !== 'infosystems'" class="muted">Loading…</p>
    </div>
  </div>
</template>

<style scoped>
.report-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.report-tabs--three {
  grid-template-columns: repeat(3, 1fr);
}
.report-tabs--four {
  grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 900px) {
  .report-tabs--four {
    grid-template-columns: repeat(4, 1fr);
  }
}
.is-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: flex-end;
  margin-bottom: 1rem;
}
.kpi-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.kpi {
  border: 1px solid var(--hd-line);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  background: #fff;
}
.kpi-label {
  font-size: 0.8rem;
  color: #64748b;
}
.kpi-value {
  font-size: 1.4rem;
  font-weight: 700;
}
.is-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}
.is-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.is-list li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--hd-line);
}
.is-table {
  width: 100%;
  border-collapse: collapse;
}
.is-table th,
.is-table td {
  text-align: left;
  padding: 0.4rem 0.5rem;
  border-bottom: 1px solid var(--hd-line);
  font-size: 0.9rem;
}
.h3 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}

.reports-filters {
  margin: 0 0 1rem;
  border: 1px solid var(--hd-line);
  border-radius: 8px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.reports-filters__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--hd-line);
}

.reports-filters__title {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
}

.reports-filters__body {
  padding: 1rem;
}

.reports-filters :deep(.hd-v-input .v-field--variant-outlined .v-field__outline),
.reports-filters :deep(.hd-v-select-menu .v-field--variant-outlined .v-field__outline),
.reports-filters :deep(.hd-v-select .v-field--variant-outlined .v-field__outline) {
  color: #94a3b8 !important;
  opacity: 1 !important;
}

.monthly-hint {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
}

.report-tab {
  padding: 0.65rem 0.85rem;
  border-radius: 6px;
  border: 1px solid var(--hd-line);
  background: #fff;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9rem;
  text-align: center;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.report-tab--on {
  background: #e8f5ee;
  border-color: #119a48;
  color: #065f2c;
}

h2 {
  font-size: 1.05rem;
  margin: 1rem 0 0.5rem;
  color: #2c3e50;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.1rem;
}
.tile {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  padding: 0.85rem 0.9rem 0.8rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.tile::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--tile-accent, #334155);
}
.tile header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}
.tile-icon {
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.82rem;
  background: var(--tile-soft, #e2e8f0);
}
.n {
  font-size: 2rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: #0f172a;
  line-height: 1;
  display: block;
}
.l {
  font-size: 0.74rem;
  color: #64748b;
  text-transform: capitalize;
  font-weight: 700;
}
.tile-sub {
  margin-top: 0.35rem;
  display: block;
  font-size: 0.73rem;
  color: #64748b;
}
.tile-total {
  --tile-accent: #334155;
  --tile-soft: #e2e8f0;
}
.tile-open,
.tile-pending {
  --tile-accent: #3b82f6;
  --tile-soft: #dbeafe;
}
.tile-awaiting {
  --tile-accent: #a855f7;
  --tile-soft: #f3e8ff;
}
.tile-resolved {
  --tile-accent: #16a34a;
  --tile-soft: #dcfce7;
}
.tile-closed {
  --tile-accent: #64748b;
  --tile-soft: #e2e8f0;
}

.report-tools {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0 0 1rem;
}

@media (min-width: 640px) {
  .report-tools {
    flex-direction: row;
    flex-wrap: wrap;
  }

  .report-tools__btn {
    width: auto;
    flex: 0 1 auto;
  }
}

.table-wrap {
  margin-bottom: 0.5rem;
}
.muted {
  color: #64748b;
  font-size: 0.85rem;
}
.err {
  color: #b91c1c;
}
.pager {
  margin-top: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
  align-items: center;
}

@media (min-width: 640px) {
  .pager {
    justify-content: flex-end;
  }
}

.pager__label {
  font-size: 0.88rem;
  color: #475569;
  text-align: center;
  width: 100%;
}

.monthly-toolbar {
  margin-bottom: 1rem;
}
.monthly-layout {
  display: grid;
  grid-template-columns: minmax(220px, 280px) 1fr;
  gap: 1rem;
  align-items: start;
}
@media (max-width: 900px) {
  .monthly-layout {
    grid-template-columns: 1fr;
  }
}
.monthly-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.monthly-list-item {
  text-align: left;
  border: 1px solid var(--hd-line);
  border-radius: 6px;
  background: #fff;
  padding: 0.65rem 0.75rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.monthly-list-item--on {
  border-color: #119a48;
  background: #f0fdf4;
}
.monthly-detail {
  border: 1px solid var(--hd-line);
  border-radius: 6px;
  padding: 1rem;
  background: #fff;
}
.monthly-detail-head h2 {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
}
.monthly-summary {
  line-height: 1.55;
  color: #334155;
  white-space: pre-wrap;
}

@media (min-width: 640px) {
  .pager__label {
    width: auto;
  }
}
</style>
