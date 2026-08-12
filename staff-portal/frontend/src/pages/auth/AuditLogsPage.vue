<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { fetchAuditLogs, revertAuditLog, type AuditLogRow } from '@/lib/authAdminApi'

interface AuditLogFilters {
  search: string
  name: string
  email: string
  http_method: string
  event_type: string
  date_from: string
  date_to: string
  per_page: number
}

const HTTP_METHOD_OPTIONS = ['', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE']

function defaultFilters(): AuditLogFilters {
  return {
    search: '',
    name: '',
    email: '',
    http_method: '',
    event_type: '',
    date_from: '',
    date_to: '',
    per_page: 50,
  }
}

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<AuditLogRow[]>([])
const filters = ref<AuditLogFilters>(defaultFilters())
const draftFilters = ref<AuditLogFilters>(defaultFilters())
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(50)
const extended = ref(false)
const metaMessage = ref<string | null>(null)
const detailsOpen = ref(false)
const selectedLog = ref<AuditLogRow | null>(null)
const revertingId = ref<number | string | null>(null)

const rowsOnPage = computed(() => rows.value.length)
const firstRowNumber = computed(() => (page.value - 1) * perPage.value + 1)

async function load(options: { preserveSelection?: boolean } = {}) {
  loading.value = true
  error.value = null
  try {
    const res = await fetchAuditLogs({
      search: filters.value.search || undefined,
      name: filters.value.name || undefined,
      email: filters.value.email || undefined,
      http_method: filters.value.http_method || undefined,
      event_type: filters.value.event_type || undefined,
      date_from: filters.value.date_from || undefined,
      date_to: filters.value.date_to || undefined,
      page: page.value,
      per_page: filters.value.per_page,
    })
    rows.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
    perPage.value = res.meta.per_page || filters.value.per_page
    extended.value = !!res.meta.extended
    metaMessage.value = res.meta.message || null

    if (options.preserveSelection && selectedLog.value) {
      const currentId = auditLogId(selectedLog.value)
      selectedLog.value = rows.value.find((row) => auditLogId(row) === currentId) ?? selectedLog.value
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load audit logs')
  } finally {
    loading.value = false
  }
}

function auditLogId(row: AuditLogRow): number | string {
  return row.id ?? row.log_id ?? 'unknown'
}

function userDisplay(row: AuditLogRow): string {
  return row.user_name || (row.user_id != null ? `User #${row.user_id}` : '—')
}

function targetDisplay(row: AuditLogRow): string {
  if (!row.target_table) return '—'
  return row.target_id != null && row.target_id !== '' ? `${row.target_table}#${row.target_id}` : row.target_table
}

function formatTimestamp(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleString()
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
  if (value.includes('update') || value.includes('edit') || value.includes('revert')) return 'warning'
  if (value.includes('view') || value.includes('read')) return 'info'
  return 'default'
}

function canRevert(row: AuditLogRow): boolean {
  if (typeof row.can_revert === 'boolean') {
    return row.can_revert
  }

  return Boolean(row.old_values && !row.reverted_at && row.target_table === 'user')
}

function openDetails(row: AuditLogRow) {
  selectedLog.value = row
  detailsOpen.value = true
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
  success.value = null
  filters.value = { ...draftFilters.value }
  page.value = 1
  await load()
}

async function resetFilters() {
  draftFilters.value = defaultFilters()
  filters.value = defaultFilters()
  page.value = 1
  success.value = null
  await load()
}

async function goToPage(nextPage: number) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) return
  page.value = nextPage
  await load()
}

async function onRevert(row: AuditLogRow) {
  const id = auditLogId(row)
  if (!canRevert(row) || id === 'unknown' || id === '') return
  if (!window.confirm(`Revert audit snapshot #${id}? This restores the captured old values.`)) return

  revertingId.value = id
  error.value = null
  success.value = null

  try {
    const res = await revertAuditLog(id)
    success.value = res.message
    await load({ preserveSelection: true })
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not revert audit snapshot')
  } finally {
    revertingId.value = null
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome title="Audit logs" lede="Recent user activity from user_logs.">
      <template #actions>
        <RouterLink to="/auth/users" style="text-decoration:none">
          <v-btn size="small" variant="outlined">Users</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>

    <v-card variant="outlined" class="mb-4">
      <v-card-title>Filters</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="draftFilters.search"
              label="Search"
              density="compact"
              clearable
              hide-details
              placeholder="Action, URI, table, user..."
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="draftFilters.name" label="Name" density="compact" clearable hide-details />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="draftFilters.email" label="Email" density="compact" clearable hide-details />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select
              v-model="draftFilters.http_method"
              :items="HTTP_METHOD_OPTIONS"
              label="Method"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field v-model="draftFilters.event_type" label="Event" density="compact" clearable hide-details />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-text-field v-model="draftFilters.date_from" label="Date from" type="date" density="compact" hide-details />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-text-field v-model="draftFilters.date_to" label="Date to" type="date" density="compact" hide-details />
          </v-col>
          <v-col cols="12" sm="6" md="1">
            <v-select
              v-model="draftFilters.per_page"
              :items="[25, 50, 100]"
              label="Rows"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center ga-2">
            <v-btn color="primary" :loading="loading" @click="applyFilters">Apply</v-btn>
            <v-btn variant="text" :disabled="loading" @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-alert v-if="metaMessage" type="info" variant="tonal" class="mb-3" density="compact">{{ metaMessage }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-row class="mb-1">
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">Matching rows</div>
            <div class="text-h5">{{ total }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">Rows on page</div>
            <div class="text-h5">{{ rowsOnPage }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">Integrity and retention</div>
            <div class="text-body-2">
              {{ extended ? 'Extended audit columns are available.' : 'Legacy audit rows are still supported.' }}
              Snapshots and retention remain governed by backend audit storage policy.
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="outlined">
      <v-card-text>
        <div v-if="loading" class="text-medium-emphasis mb-3">Loading…</div>

        <v-table density="compact" class="mb-3">
          <thead>
            <tr>
              <th>#</th>
              <th>ID</th>
              <th>User</th>
              <th>When</th>
              <th>Method</th>
              <th>Event</th>
              <th>URI</th>
              <th>Target</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in rows" :key="String(auditLogId(row))">
              <td>{{ firstRowNumber + index }}</td>
              <td>{{ auditLogId(row) }}</td>
              <td>
                <div>{{ userDisplay(row) }}</div>
                <div class="text-caption text-medium-emphasis">{{ row.user_email || '—' }}</div>
              </td>
              <td class="text-no-wrap">{{ formatTimestamp(row.created_at) }}</td>
              <td>
                <v-chip size="small" :color="methodColor(row.http_method)" variant="tonal" class="text-uppercase">
                  {{ row.http_method || '—' }}
                </v-chip>
              </td>
              <td>
                <v-chip size="small" :color="eventColor(row.event_type)" variant="tonal">
                  {{ row.event_type || '—' }}
                </v-chip>
              </td>
              <td class="text-medium-emphasis">{{ row.request_uri || '—' }}</td>
              <td>{{ targetDisplay(row) }}</td>
              <td class="text-no-wrap">
                <v-btn size="x-small" variant="text" @click="openDetails(row)">Details</v-btn>
                <v-btn
                  v-if="canRevert(row)"
                  size="x-small"
                  variant="tonal"
                  color="warning"
                  :loading="revertingId === auditLogId(row)"
                  @click="onRevert(row)"
                >
                  Revert
                </v-btn>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td colspan="9" class="text-medium-emphasis">No audit logs.</td>
            </tr>
          </tbody>
        </v-table>

        <div class="d-flex flex-wrap align-center ga-2">
          <v-btn size="small" :disabled="page <= 1 || loading" @click="goToPage(page - 1)">Prev</v-btn>
          <span class="text-caption">Page {{ page }} / {{ lastPage }} · {{ total }} total · {{ perPage }} per page</span>
          <v-btn size="small" :disabled="page >= lastPage || loading" @click="goToPage(page + 1)">Next</v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="detailsOpen" max-width="980">
      <v-card v-if="selectedLog">
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Audit log #{{ auditLogId(selectedLog) }}</span>
          <v-chip v-if="selectedLog.reverted_at" size="small" color="success" variant="tonal">
            Reverted {{ formatTimestamp(selectedLog.reverted_at) }}
          </v-chip>
        </v-card-title>
        <v-card-text>
          <v-alert
            v-if="selectedLog.reverted_at"
            type="success"
            variant="tonal"
            class="mb-3"
            density="compact"
          >
            This snapshot has already been reverted.
          </v-alert>

          <v-row class="mb-1">
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">User</div>
              <div>{{ userDisplay(selectedLog) }}</div>
              <div class="text-caption">{{ selectedLog.user_email || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">When</div>
              <div>{{ formatTimestamp(selectedLog.created_at) }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Method</div>
              <div>{{ selectedLog.http_method || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Event</div>
              <div>{{ selectedLog.event_type || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Target</div>
              <div>{{ targetDisplay(selectedLog) }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Action</div>
              <div>{{ selectedLog.action || selectedLog.activity || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">IP</div>
              <div>{{ selectedLog.ip_address || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">URI</div>
              <div>{{ selectedLog.request_uri || '—' }}</div>
            </v-col>
            <v-col cols="12">
              <div class="text-caption text-medium-emphasis">User agent</div>
              <div>{{ selectedLog.user_agent || '—' }}</div>
            </v-col>
          </v-row>

          <v-row>
            <v-col cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title>Old values</v-card-title>
                <v-card-text>
                  <pre style="white-space: pre-wrap; word-break: break-word">{{ parsedJson(selectedLog.old_values) }}</pre>
                </v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title>New values</v-card-title>
                <v-card-text>
                  <pre style="white-space: pre-wrap; word-break: break-word">{{ parsedJson(selectedLog.new_values) }}</pre>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="detailsOpen = false">Close</v-btn>
          <v-btn
            v-if="canRevert(selectedLog)"
            color="warning"
            variant="tonal"
            :loading="revertingId === auditLogId(selectedLog)"
            @click="onRevert(selectedLog)"
          >
            Revert snapshot
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
