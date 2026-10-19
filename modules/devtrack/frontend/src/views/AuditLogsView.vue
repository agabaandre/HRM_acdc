<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import StatCard from '@/components/dashboard/StatCard.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateTime12h } from '@/lib/formatDate'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type AuditLogRow = {
  id: number
  actor_type: string
  actor_label: string
  user_id: number | null
  user_name: string | null
  user_email: string | null
  external_integration_id: number | null
  external_integration: { id: number; name: string; slug: string } | null
  action: string
  event_type: string
  http_method: string | null
  request_uri: string | null
  target_table: string | null
  target_id: string | null
  old_values: unknown
  new_values: unknown
  ip_address: string | null
  user_agent: string | null
  is_suspicious: boolean
  suspicious_reasons: string | null
  suspicious_resolved: boolean
  suspicious_open: boolean
  suspicious_resolved_at: string | null
  suspicious_resolution_note: string | null
  suspicious_resolved_by: { id: number; name: string; email: string } | null
  created_at: string | null
}

type Filters = {
  search: string
  name: string
  email: string
  ip_address: string
  http_method: string | null
  event_type: string | null
  target_table: string | null
  actor_type: string | null
  suspicious: string | null
  resolved: string | null
  date_from: string
  date_to: string
  per_page: number
}

type StatsCard = {
  key: string
  title: string
  value: number
  subtitle?: string
  icon: string
  color: string
  filters: Record<string, string>
}

type BlockDialogMode = 'single' | 'all_suspicious'
type BlockScope = 'ip' | 'email' | 'both'

function isValidEmail(value: string | null | undefined): boolean {
  if (!value) return false
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function logEmail(row: AuditLogRow | null | undefined): string {
  if (!row) return ''
  return isValidEmail(row.user_email) ? String(row.user_email).trim() : ''
}

function canBlockLog(row: AuditLogRow): boolean {
  // Block actions only for suspicious audit events that have an IP and/or email.
  return Boolean(row.is_suspicious) && (Boolean(row.ip_address) || Boolean(logEmail(row)))
}

function defaultFilters(): Filters {
  return {
    search: '',
    name: '',
    email: '',
    ip_address: '',
    http_method: null,
    event_type: null,
    target_table: null,
    actor_type: null,
    suspicious: null,
    resolved: null,
    date_from: '',
    date_to: '',
    per_page: 50,
  }
}

const loading = ref(false)
const exporting = ref(false)
const resolving = ref(false)
const error = ref('')
const message = ref('')
const rows = ref<AuditLogRow[]>([])
const filters = ref<Filters>(defaultFilters())
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(50)
const detailsOpen = ref(false)
const selectedLog = ref<AuditLogRow | null>(null)
const statsCards = ref<StatsCard[]>([])
const statsLoading = ref(false)
const activeStatKey = ref<string | null>(null)

const resolveOpen = ref(false)
const resolveMode = ref<'single' | 'all'>('single')
const resolveTargetId = ref<number | null>(null)
const resolveNote = ref('')

const blockOpen = ref(false)
const blockMode = ref<BlockDialogMode>('single')
const blockScope = ref<BlockScope>('ip')
const blockIp = ref('')
const blockEmail = ref('')
const blockReason = ref('')
const blockAuditLogId = ref<number | null>(null)
const blocking = ref(false)

const eventTypes = ref<string[]>([])
const targetTables = ref<string[]>([])
const httpMethods = ref<string[]>(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
const actorTypeOptions = ref([
  { title: 'System user', value: 'system_user' },
  { title: 'Client', value: 'client' },
  { title: 'System', value: 'system' },
])

const rowsOnPage = computed(() => rows.value.length)
const firstRowNumber = computed(() => (page.value - 1) * perPage.value + 1)

const activeFilterCount = computed(() => {
  const f = filters.value
  return [
    f.search,
    f.name,
    f.email,
    f.ip_address,
    f.http_method,
    f.event_type,
    f.target_table,
    f.actor_type,
    f.suspicious,
    f.resolved,
    f.date_from,
    f.date_to,
  ].filter((v) => v != null && String(v).trim() !== '').length
})

const openSuspiciousCount = computed(
  () => statsCards.value.find((c) => c.key === 'suspicious_open')?.value ?? 0,
)

const suspiciousFilterItems = [
  { title: 'Suspicious only', value: '1' },
  { title: 'Not suspicious', value: '0' },
]

const resolutionFilterItems = [
  { title: 'Open (needs review)', value: '0' },
  { title: 'Resolved', value: '1' },
]

const ACTOR_LABELS: Record<string, string> = {
  system_user: 'System user',
  client: 'Client',
  system: 'System',
}

const blockDialogTitle = computed(() =>
  blockMode.value === 'all_suspicious' ? 'Block suspicious access' : 'Block from audit log',
)

const blockScopeItems = computed(() => {
  const items: { title: string; value: BlockScope; disabled?: boolean }[] = [
    { title: 'IP only', value: 'ip', disabled: blockMode.value === 'single' && !blockIp.value },
    { title: 'Email only', value: 'email', disabled: blockMode.value === 'single' && !blockEmail.value },
    {
      title: 'IP and email',
      value: 'both',
      disabled: blockMode.value === 'single' && (!blockIp.value || !blockEmail.value),
    },
  ]
  return items
})

const canSubmitBlock = computed(() => {
  if (blockReason.value.trim().length < 3) return false
  if (blockMode.value === 'all_suspicious') return true
  if (blockScope.value === 'ip') return Boolean(blockIp.value.trim())
  if (blockScope.value === 'email') return Boolean(blockEmail.value.trim())
  return Boolean(blockIp.value.trim() && blockEmail.value.trim())
})

function defaultScopeForLog(row: AuditLogRow): BlockScope {
  const hasIp = Boolean(row.ip_address)
  const hasEmail = Boolean(logEmail(row))
  if (hasIp && hasEmail) return 'both'
  if (hasEmail) return 'email'
  return 'ip'
}

async function loadFilterOptions() {
  try {
    const res = await api.get('/admin/audit-logs/filter-options')
    eventTypes.value = res.data.data.event_types ?? []
    targetTables.value = res.data.data.target_tables ?? []
    if (Array.isArray(res.data.data.http_methods) && res.data.data.http_methods.length) {
      httpMethods.value = res.data.data.http_methods
    }
    if (Array.isArray(res.data.data.actor_types) && res.data.data.actor_types.length) {
      actorTypeOptions.value = res.data.data.actor_types.map((value: string) => ({
        title: ACTOR_LABELS[value] ?? value,
        value,
      }))
    }
  } catch {
    // Keep built-in options
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    const res = await api.get('/admin/audit-logs/stats')
    statsCards.value = Array.isArray(res.data.data?.cards) ? res.data.data.cards : []
  } catch {
    statsCards.value = []
  } finally {
    statsLoading.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/audit-logs', {
      params: listParams(),
    })
    rows.value = res.data.data
    page.value = res.data.meta.current_page
    lastPage.value = res.data.meta.last_page
    total.value = res.data.meta.total
    perPage.value = res.data.meta.per_page || filters.value.per_page
  } catch (err) {
    rows.value = []
    total.value = 0
    error.value = apiErrorMessage(err, 'Could not load audit logs.')
  } finally {
    loading.value = false
  }
}

function listParams(includePage = true): Record<string, string | number | undefined> {
  return {
    search: filters.value.search.trim() || undefined,
    name: filters.value.name.trim() || undefined,
    email: filters.value.email.trim() || undefined,
    ip_address: filters.value.ip_address.trim() || undefined,
    http_method: filters.value.http_method || undefined,
    event_type: filters.value.event_type || undefined,
    event_type_exact: filters.value.event_type ? 1 : undefined,
    target_table: filters.value.target_table || undefined,
    actor_type: filters.value.actor_type || undefined,
    suspicious: filters.value.suspicious || undefined,
    resolved: filters.value.resolved || undefined,
    date_from: filters.value.date_from || undefined,
    date_to: filters.value.date_to || undefined,
    ...(includePage
      ? {
          page: page.value,
          per_page: filters.value.per_page,
        }
      : {}),
  }
}

async function drillDown(card: StatsCard) {
  const next = defaultFilters()
  next.per_page = filters.value.per_page
  const incoming = card.filters || {}
  if (incoming.search) next.search = incoming.search
  if (incoming.name) next.name = incoming.name
  if (incoming.email) next.email = incoming.email
  if (incoming.ip_address) next.ip_address = incoming.ip_address
  if (incoming.http_method) next.http_method = incoming.http_method
  if (incoming.event_type) next.event_type = incoming.event_type
  if (incoming.target_table) next.target_table = incoming.target_table
  if (incoming.actor_type) next.actor_type = incoming.actor_type
  if (incoming.suspicious) next.suspicious = incoming.suspicious
  if (incoming.resolved) next.resolved = incoming.resolved
  if (incoming.date_from) next.date_from = incoming.date_from
  if (incoming.date_to) next.date_to = incoming.date_to
  filters.value = next
  activeStatKey.value = card.key
  page.value = 1
  await load()
}

function openResolveOne(row: AuditLogRow) {
  resolveMode.value = 'single'
  resolveTargetId.value = row.id
  resolveNote.value = ''
  resolveOpen.value = true
}

function openResolveAll() {
  resolveMode.value = 'all'
  resolveTargetId.value = null
  resolveNote.value = ''
  resolveOpen.value = true
}

async function submitResolve() {
  resolving.value = true
  error.value = ''
  try {
    if (resolveMode.value === 'single' && resolveTargetId.value != null) {
      const res = await api.post(`/admin/audit-logs/${resolveTargetId.value}/resolve`, {
        note: resolveNote.value.trim() || undefined,
      })
      message.value = res.data.message || 'Suspicious event resolved.'
    } else {
      const res = await api.post('/admin/audit-logs/resolve-suspicious', {
        note: resolveNote.value.trim() || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
      })
      message.value = res.data.message || 'Open suspicious events resolved.'
    }
    resolveOpen.value = false
    await Promise.all([load(), loadStats()])
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not resolve suspicious event(s).')
  } finally {
    resolving.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/audit-logs/export', {
      params: listParams(false),
      responseType: 'blob',
    })

    const disposition = String(res.headers['content-disposition'] || '')
    const match = /filename="?([^"]+)"?/i.exec(disposition)
    const filename = match?.[1] || `audit-logs-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.xls`

    const blob = new Blob([res.data], {
      type: 'application/vnd.ms-excel',
    })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)

    const exportedRows = Number(res.headers['x-export-rows'] || 0)
    const matchedTotal = Number(res.headers['x-export-total'] || exportedRows)
    const truncated = String(res.headers['x-export-truncated'] || '') === '1'
    message.value = truncated
      ? `Exported ${exportedRows.toLocaleString()} of ${matchedTotal.toLocaleString()} matching audit logs (Excel limit applied).`
      : `Exported ${exportedRows.toLocaleString()} audit log${exportedRows === 1 ? '' : 's'} to Excel.`
  } catch (err) {
    // Axios blob errors may wrap JSON in a Blob — try to surface a readable message.
    const axiosErr = err as { response?: { data?: Blob | { message?: string }; status?: number } }
    if (axiosErr.response?.data instanceof Blob) {
      try {
        const text = await axiosErr.response.data.text()
        const parsed = JSON.parse(text) as { message?: string }
        error.value = parsed.message || 'Could not export audit logs.'
      } catch {
        error.value = apiErrorMessage(err, 'Could not export audit logs.')
      }
    } else {
      error.value = apiErrorMessage(err, 'Could not export audit logs.')
    }
  } finally {
    exporting.value = false
  }
}

function userDisplay(row: AuditLogRow): string {
  if (row.actor_type === 'client') {
    return row.external_integration?.name || row.user_name || row.user_email || 'Client'
  }
  return row.user_name || (row.user_id != null ? `User #${row.user_id}` : '—')
}

function actorColor(actorType: string | null | undefined): string {
  switch (actorType) {
    case 'client':
      return 'secondary'
    case 'system':
      return 'default'
    default:
      return 'primary'
  }
}

function targetDisplay(row: AuditLogRow): string {
  if (!row.target_table) return '—'
  return row.target_id != null && row.target_id !== '' ? `${row.target_table}#${row.target_id}` : row.target_table
}

function methodColor(method: string | null | undefined): string {
  switch ((method || '').toUpperCase()) {
    case 'GET':
      return 'info'
    case 'POST':
      return 'success'
    case 'PUT':
    case 'PATCH':
      return 'warning'
    case 'DELETE':
      return 'error'
    default:
      return 'default'
  }
}

function eventColor(eventType: string | null | undefined): string {
  const value = (eventType || '').toLowerCase()
  if (value.includes('delete')) return 'error'
  if (value.includes('create') || value.includes('insert')) return 'success'
  if (value.includes('update') || value.includes('edit') || value.includes('password')) return 'warning'
  if (value.includes('auth') || value.includes('access')) return 'info'
  return 'default'
}

function openDetails(row: AuditLogRow) {
  selectedLog.value = row
  detailsOpen.value = true
}

function openBlockIp(row: AuditLogRow) {
  if (!canBlockLog(row)) return
  blockMode.value = 'single'
  blockIp.value = row.ip_address || ''
  blockEmail.value = logEmail(row)
  blockScope.value = defaultScopeForLog(row)
  blockAuditLogId.value = row.id
  blockReason.value = row.is_suspicious
    ? `Suspicious audit activity: ${row.suspicious_reasons || row.event_type || row.action}`
    : `Blocked from audit log #${row.id} (${row.event_type || row.action})`
  blockOpen.value = true
}

function openBlockAllSuspicious() {
  blockMode.value = 'all_suspicious'
  blockIp.value = ''
  blockEmail.value = ''
  blockScope.value = 'both'
  blockAuditLogId.value = null
  blockReason.value = 'Bulk block of suspicious IPs and emails from audit logs'
  blockOpen.value = true
}

async function submitBlock() {
  if (!canSubmitBlock.value) return
  blocking.value = true
  error.value = ''
  message.value = ''
  try {
    if (blockMode.value === 'all_suspicious') {
      const res = await api.post('/admin/blocked-ips/block-suspicious', {
        reason: blockReason.value.trim(),
        scope: blockScope.value,
      })
      message.value = res.data.message || 'Suspicious access blocked.'
    } else {
      const payload: Record<string, unknown> = {
        scope: blockScope.value,
        reason: blockReason.value.trim(),
        audit_log_id: blockAuditLogId.value || undefined,
      }
      if (blockScope.value === 'ip' || blockScope.value === 'both') {
        payload.ip_address = blockIp.value.trim()
      }
      if (blockScope.value === 'email' || blockScope.value === 'both') {
        payload.email = blockEmail.value.trim()
      }
      const res = await api.post('/admin/blocked-ips', payload)
      message.value = res.data.message || 'Access blocked.'
    }
    blockOpen.value = false
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not block access.')
  } finally {
    blocking.value = false
  }
}

function parsedJson(value: unknown): string {
  if (value == null || value === '') return 'No snapshot recorded.'
  if (typeof value === 'string') {
    try {
      return JSON.stringify(JSON.parse(value), null, 2)
    } catch {
      return value
    }
  }
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

async function applyFilters() {
  activeStatKey.value = null
  page.value = 1
  await load()
}

async function clearFilters() {
  filters.value = defaultFilters()
  activeStatKey.value = null
  page.value = 1
  await load()
}

async function goToPage(nextPage: number) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) return
  page.value = nextPage
  await load()
}

onMounted(async () => {
  await Promise.all([loadFilterOptions(), loadStats()])
  await load()
})
</script>

<template>
  <div>
    <PageHeader title="Audit logs" subtitle="Admin panel activity, mutations, and authentication events">
      <template #actions>
        <v-btn
          color="warning"
          variant="tonal"
          prepend-icon="mdi-check-decagram"
          :disabled="openSuspiciousCount === 0"
          @click="openResolveAll"
        >
          Resolve open ({{ openSuspiciousCount }})
        </v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-microsoft-excel"
          :loading="exporting"
          :disabled="loading || total === 0"
          @click="exportExcel"
        >
          Export Excel
        </v-btn>
        <v-btn
          color="error"
          variant="tonal"
          prepend-icon="mdi-cancel"
          @click="openBlockAllSuspicious"
        >
          Block suspicious…
        </v-btn>
        <v-btn
          variant="text"
          prepend-icon="mdi-ip-network-outline"
          :to="{ name: 'blocked-ips' }"
        >
          View blocked access
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
      <template v-if="message.toLowerCase().includes('block')">
        —
        <router-link :to="{ name: 'blocked-ips' }">open blocked IPs</router-link>
      </template>
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <ParentCard title="Summary">
      <p class="text-caption text-medium-emphasis mb-4">
        Click a card to filter the table to those exact results (all time, today, suspicious, and more).
      </p>
      <v-row dense>
        <v-col
          v-for="card in statsCards"
          :key="card.key"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <StatCard
            :title="card.title"
            :value="statsLoading ? '…' : card.value"
            :icon="card.icon"
            :color="card.color"
            :subtitle="card.subtitle"
            :active="activeStatKey === card.key"
            clickable
            @click="drillDown(card)"
          />
        </v-col>
      </v-row>
    </ParentCard>

    <ParentCard title="Filters">
      <v-row dense>
        <v-col cols="12" md="4">
          <v-text-field
            v-model="filters.search"
            label="Search action / URI / user / IP"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            prepend-inner-icon="mdi-magnify"
            @keyup.enter="applyFilters"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.http_method"
            :items="httpMethods"
            label="Method"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.actor_type"
            :items="actorTypeOptions"
            item-title="title"
            item-value="value"
            label="Category"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.event_type"
            :items="eventTypes"
            label="Event type"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.target_table"
            :items="targetTables"
            label="Target table"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.suspicious"
            :items="suspiciousFilterItems"
            item-title="title"
            item-value="value"
            label="Suspicious"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.resolved"
            :items="resolutionFilterItems"
            item-title="title"
            item-value="value"
            label="Resolution"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-text-field
            v-model="filters.name"
            label="User name"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            @keyup.enter="applyFilters"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-text-field
            v-model="filters.email"
            label="User email"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            @keyup.enter="applyFilters"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-text-field
            v-model="filters.ip_address"
            label="IP address"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            @keyup.enter="applyFilters"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-text-field
            v-model="filters.date_from"
            label="Date from"
            type="date"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-text-field
            v-model="filters.date_to"
            label="Date to"
            type="date"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.per_page"
            :items="[25, 50, 100]"
            label="Rows"
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" md="4" class="d-flex flex-wrap ga-2 align-center">
          <v-btn color="primary" variant="tonal" :loading="loading" @click="applyFilters">Apply</v-btn>
          <v-btn variant="text" :disabled="loading || activeFilterCount === 0" @click="clearFilters">
            Clear{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}
          </v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <div class="text-caption text-medium-emphasis mb-3">
      Showing {{ rowsOnPage ? firstRowNumber : 0 }}–{{ firstRowNumber + rowsOnPage - (rowsOnPage ? 1 : 0) }}
      of {{ total }}
    </div>

    <AppDataTable
      :headers="[
        { title: 'When', key: 'created_at', sortable: false, minWidth: 180 },
        { title: 'Actor', key: 'user', sortable: false },
        { title: 'Method', key: 'http_method', sortable: false },
        { title: 'Event', key: 'event_type', sortable: false },
        { title: 'Action', key: 'action', sortable: false },
        { title: 'Target', key: 'target', sortable: false },
        { title: '', key: 'actions', sortable: false, align: 'end', width: 200 },
      ]"
      :items="rows"
      :loading="loading"
      item-value="id"
      hide-default-footer
      class="elevation-0"
      :row-props="(row) => {
        if (row.item.suspicious_open) return { class: 'audit-row--suspicious' }
        if (row.item.suspicious_resolved) return { class: 'audit-row--resolved' }
        return {}
      }"
    >
      <template #item.created_at="{ item }">
        <div class="text-no-wrap">{{ item.created_at ? formatDateTime12h(item.created_at) : '—' }}</div>
        <div class="d-flex flex-wrap ga-1 mt-1">
          <v-chip size="x-small" :color="actorColor(item.actor_type)" variant="tonal">
            {{ item.actor_label || ACTOR_LABELS[item.actor_type] || item.actor_type }}
          </v-chip>
          <v-tooltip v-if="item.suspicious_open" location="top">
            <template #activator="{ props }">
              <v-chip
                v-bind="props"
                size="x-small"
                color="error"
                variant="flat"
                prepend-icon="mdi-alert"
              >
                Open
              </v-chip>
            </template>
            <span>{{ item.suspicious_reasons || 'Flagged by security heuristics' }}</span>
          </v-tooltip>
          <v-tooltip v-else-if="item.suspicious_resolved" location="top">
            <template #activator="{ props }">
              <v-chip
                v-bind="props"
                size="x-small"
                color="success"
                variant="tonal"
                prepend-icon="mdi-check"
              >
                Resolved
              </v-chip>
            </template>
            <span>
              {{ item.suspicious_resolution_note || 'Marked resolved' }}
              <template v-if="item.suspicious_resolved_at">
                — {{ formatDateTime12h(item.suspicious_resolved_at) }}
              </template>
            </span>
          </v-tooltip>
        </div>
      </template>
      <template #item.user="{ item }">
        <div>{{ userDisplay(item) }}</div>
        <div class="text-caption text-medium-emphasis">
          {{
            item.actor_type === 'client'
              ? item.external_integration?.slug || item.user_email || ''
              : item.user_email || ''
          }}
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ item.ip_address || '—' }}
        </div>
      </template>
      <template #item.http_method="{ item }">
        <v-chip v-if="item.http_method" size="small" :color="methodColor(item.http_method)" variant="tonal">
          {{ item.http_method }}
        </v-chip>
        <span v-else>—</span>
      </template>
      <template #item.event_type="{ item }">
        <v-chip size="small" :color="eventColor(item.event_type)" variant="tonal">
          {{ item.event_type }}
        </v-chip>
      </template>
      <template #item.target="{ item }">
        {{ targetDisplay(item) }}
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex flex-wrap justify-end ga-1">
          <v-btn
            v-if="item.suspicious_open"
            size="small"
            color="warning"
            variant="text"
            @click="openResolveOne(item)"
          >
            Resolve
          </v-btn>
          <v-btn
            v-if="canBlockLog(item)"
            size="small"
            color="error"
            variant="text"
            @click="openBlockIp(item)"
          >
            Block…
          </v-btn>
          <v-btn size="small" variant="text" @click="openDetails(item)">Details</v-btn>
        </div>
      </template>
    </AppDataTable>

    <div class="d-flex justify-end align-center ga-2 mt-4">
      <v-btn size="small" variant="text" :disabled="page <= 1 || loading" @click="goToPage(page - 1)">
        Previous
      </v-btn>
      <span class="text-caption">Page {{ page }} / {{ lastPage || 1 }}</span>
      <v-btn size="small" variant="text" :disabled="page >= lastPage || loading" @click="goToPage(page + 1)">
        Next
      </v-btn>
    </div>

    <v-dialog v-model="detailsOpen" max-width="720">
      <v-card v-if="selectedLog">
        <v-card-title>Audit log #{{ selectedLog.id }}</v-card-title>
        <v-card-text>
          <p class="mb-2"><strong>Action:</strong> {{ selectedLog.action }}</p>
          <p class="mb-2">
            <strong>Category:</strong>
            {{ selectedLog.actor_label || ACTOR_LABELS[selectedLog.actor_type] || selectedLog.actor_type }}
          </p>
          <p v-if="selectedLog.suspicious_open" class="mb-2">
            <v-chip size="small" color="error" variant="flat" class="mr-2">Open suspicious</v-chip>
            {{ selectedLog.suspicious_reasons || 'Flagged by security heuristics' }}
          </p>
          <p v-else-if="selectedLog.suspicious_resolved" class="mb-2">
            <v-chip size="small" color="success" variant="tonal" class="mr-2">Resolved</v-chip>
            {{ selectedLog.suspicious_resolution_note || selectedLog.suspicious_reasons || 'Resolved' }}
            <span v-if="selectedLog.suspicious_resolved_at" class="text-medium-emphasis">
              — {{ formatDateTime12h(selectedLog.suspicious_resolved_at) }}
              <template v-if="selectedLog.suspicious_resolved_by">
                by {{ selectedLog.suspicious_resolved_by.name || selectedLog.suspicious_resolved_by.email }}
              </template>
            </span>
          </p>
          <p class="mb-2"><strong>URI:</strong> {{ selectedLog.request_uri || '—' }}</p>
          <p class="mb-2"><strong>IP:</strong> {{ selectedLog.ip_address || '—' }}</p>
          <p class="mb-2"><strong>Email:</strong> {{ selectedLog.user_email || '—' }}</p>
          <p class="mb-2"><strong>User agent:</strong> {{ selectedLog.user_agent || '—' }}</p>
          <p class="mb-1"><strong>Old values</strong></p>
          <pre class="audit-json mb-4">{{ parsedJson(selectedLog.old_values) }}</pre>
          <p class="mb-1"><strong>New values</strong></p>
          <pre class="audit-json">{{ parsedJson(selectedLog.new_values) }}</pre>
        </v-card-text>
        <v-card-actions>
          <v-btn
            v-if="selectedLog.suspicious_open"
            color="warning"
            variant="tonal"
            prepend-icon="mdi-check"
            @click="openResolveOne(selectedLog)"
          >
            Resolve
          </v-btn>
          <v-btn
            v-if="canBlockLog(selectedLog)"
            color="error"
            variant="tonal"
            prepend-icon="mdi-cancel"
            @click="openBlockIp(selectedLog)"
          >
            Block…
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="detailsOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="blockOpen" max-width="560">
      <v-card>
        <v-card-title>{{ blockDialogTitle }}</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-4">
            <template v-if="blockMode === 'all_suspicious'">
              Block every distinct IP and/or email currently flagged as suspicious in audit logs.
              IP blocks deny the API; email blocks deny login for that account.
            </template>
            <template v-else>
              Block from this suspicious audit event. Choose whether to block the IP, the email, or both. Manage the lists under
              <router-link :to="{ name: 'blocked-ips' }" @click="blockOpen = false">Blocked access</router-link>.
            </template>
          </p>

          <v-radio-group v-model="blockScope" class="mb-2" hide-details>
            <v-radio
              v-for="opt in blockScopeItems"
              :key="opt.value"
              :label="opt.title"
              :value="opt.value"
              :disabled="opt.disabled"
            />
          </v-radio-group>

          <v-text-field
            v-if="blockMode === 'single' && (blockScope === 'ip' || blockScope === 'both')"
            v-model="blockIp"
            label="IP address"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            readonly
          />
          <v-text-field
            v-if="blockMode === 'single' && (blockScope === 'email' || blockScope === 'both')"
            v-model="blockEmail"
            label="Email"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            readonly
          />
          <v-textarea
            v-model="blockReason"
            label="Reason"
            variant="outlined"
            density="comfortable"
            rows="3"
            hint="Required — stored with the block and shown on Blocked access"
            persistent-hint
            autofocus
          />
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" :to="{ name: 'blocked-ips' }" @click="blockOpen = false">
            View blocked access
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="blockOpen = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="blocking"
            :disabled="!canSubmitBlock"
            @click="submitBlock"
          >
            {{ blockMode === 'all_suspicious' ? 'Block all' : 'Block' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="resolveOpen" max-width="520">
      <v-card>
        <v-card-title>
          {{ resolveMode === 'all' ? 'Resolve open suspicious events' : 'Resolve suspicious event' }}
        </v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-4">
            <template v-if="resolveMode === 'all'">
              Mark all currently open suspicious audit events as reviewed
              <template v-if="filters.date_from || filters.date_to">
                within the active date filters
              </template>
              . They stay in the log but no longer need action.
            </template>
            <template v-else>
              Mark this suspicious event as reviewed. It remains in the audit trail.
            </template>
          </p>
          <v-textarea
            v-model="resolveNote"
            label="Resolution note (optional)"
            variant="outlined"
            density="comfortable"
            rows="3"
            autofocus
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="resolveOpen = false">Cancel</v-btn>
          <v-btn color="warning" variant="flat" :loading="resolving" @click="submitResolve">
            {{ resolveMode === 'all' ? 'Resolve all open' : 'Resolve' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.audit-json {
  margin: 0;
  padding: 0.75rem;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.04);
  font-size: 0.75rem;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 240px;
  overflow: auto;
}
:deep(.audit-row--suspicious) {
  background: rgba(176, 0, 32, 0.06);
}
:deep(.audit-row--resolved) {
  background: rgba(46, 125, 50, 0.05);
}
</style>
