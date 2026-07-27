<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import type { DataTableHeader } from 'vuetify'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { formatTableCountLabel } from '../../lib/ticketTableMeta'
import { notifyError, notifySuccess } from '../../lib/notify'

interface AuditRow {
  id: number
  action: string
  created_at: string
  staff_id: number | null
  staff_name: string | null
  staff_email: string | null
  duty_station_name: string | null
  auditable_type_short: string | null
  auditable_id: number | null
  ip_address: string | null
  correlation_id: string | null
  old_values?: Record<string, unknown> | null
  new_values?: Record<string, unknown> | null
  can_reverse: boolean
  default_reversal_action: 'restore' | 'delete' | null
}

interface AuditStats {
  total: number
  recent_24h: number
  top_action: string | null
  top_action_count: number
}

type SortItem = { key: string; order: 'asc' | 'desc' }

const rows = ref<AuditRow[]>([])
const total = ref(0)
const page = ref(1)
const itemsPerPage = ref(25)
const loading = ref(false)
const actionOptions = ref<string[]>([])
const stats = ref<AuditStats | null>(null)

const filters = reactive({
  q: '',
  action: '',
  date_from: '',
  date_to: '',
})

const appliedFilters = reactive({
  q: '',
  action: '',
  date_from: '',
  date_to: '',
})

const detailRow = ref<AuditRow | null>(null)
const detailOpen = ref(false)

const reverseRow = ref<AuditRow | null>(null)
const reverseOpen = ref(false)
const reverseBusy = ref(false)
const reverseForm = reactive({
  action_type: 'restore' as 'restore' | 'delete',
  reason: '',
})

const headers = computed((): DataTableHeader[] => [
  { title: '#', key: 'row_num', sortable: false, width: '56px', align: 'center' },
  { title: 'When (UTC)', key: 'created_at', sortable: false, width: '170px' },
  { title: 'Action', key: 'action', sortable: false, width: '160px' },
  { title: 'Entity', key: 'entity', sortable: false, minWidth: '140px' },
  { title: 'Staff member', key: 'staff', sortable: false, minWidth: '200px' },
  { title: 'Source IP', key: 'ip_address', sortable: false, width: '120px' },
  { title: 'Correlation', key: 'correlation_id', sortable: false, minWidth: '120px' },
  { title: '', key: 'actions', sortable: false, width: '64px', align: 'end' },
])

const tableCountLabel = computed(() =>
  formatTableCountLabel(rows.value.length, total.value, page.value, itemsPerPage.value),
)

const actionFilterItems = computed(() => [
  { label: 'All actions', value: '' },
  ...actionOptions.value.map((a) => ({ label: a, value: a })),
])

function rowNumber(index: number): number {
  return (page.value - 1) * itemsPerPage.value + index + 1
}

function formatWhen(iso: string): string {
  try {
    return new Date(iso).toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      timeZone: 'UTC',
      timeZoneName: 'short',
    })
  } catch {
    return iso
  }
}

function actionChipColor(action: string): string {
  if (action.includes('deleted') || action.includes('failed')) return 'error'
  if (action.includes('created') || action.includes('completed') || action.includes('sync')) return 'success'
  if (action.includes('updated') || action.includes('reversed')) return 'warning'
  return 'default'
}

function staffInitials(name: string | null): string {
  if (!name?.trim()) return 'S'
  return name
    .trim()
    .split(/\s+/)
    .map((part) => part[0] ?? '')
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

const reverseActionItems = computed(() => {
  const row = reverseRow.value
  if (!row) return []
  if (row.action === 'kb_article.created') {
    return [{ label: 'Delete created article', value: 'delete' }]
  }
  if (row.action === 'kb_article.deleted') {
    return [{ label: 'Restore deleted article', value: 'restore' }]
  }
  return [
    { label: 'Restore previous values', value: 'restore' },
    { label: 'Delete record', value: 'delete' },
  ]
})

function reversalLabel(row: AuditRow): string {
  if (row.action === 'kb_article.created') return 'Delete created article'
  if (row.action === 'kb_article.deleted') return 'Restore deleted article'
  if (row.action === 'kb_article.updated') return 'Restore previous values'
  return 'Reverse action'
}

function openDetail(row: AuditRow): void {
  detailRow.value = row
  detailOpen.value = true
}

function openReverse(row: AuditRow): void {
  reverseRow.value = row
  reverseForm.action_type = row.default_reversal_action ?? 'restore'
  reverseForm.reason = ''
  reverseOpen.value = true
  queueMicrotask(() => {
    const first = reverseActionItems.value[0]
    if (first) {
      reverseForm.action_type = first.value as 'restore' | 'delete'
    }
  })
}

function closeReverse(): void {
  reverseOpen.value = false
  reverseRow.value = null
}

async function submitReverse(): Promise<void> {
  if (!reverseRow.value || reverseBusy.value) return
  if (reverseForm.reason.trim().length < 10) {
    notifyError('Please enter a reason (at least 10 characters).')
    return
  }
  reverseBusy.value = true
  try {
    const { data } = await api.post<{ data: { message: string; detail?: string } }>(
      `/api/v1/admin/audit-logs/${reverseRow.value.id}/reverse`,
      {
        action_type: reverseForm.action_type,
        reason: reverseForm.reason.trim(),
      },
    )
    notifySuccess(data.data?.detail ?? data.data?.message ?? 'Reversed.')
    closeReverse()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Reversal failed.'))
  } finally {
    reverseBusy.value = false
  }
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get<{
      data: AuditRow[]
      meta: {
        total: number
        current_page: number
        actions?: string[]
        stats?: AuditStats
      }
    }>('/api/v1/admin/audit-logs', {
      params: {
        page: page.value,
        per_page: itemsPerPage.value,
        q: appliedFilters.q.trim() || undefined,
        action: appliedFilters.action || undefined,
        date_from: appliedFilters.date_from || undefined,
        date_to: appliedFilters.date_to || undefined,
      },
    })
    rows.value = Array.isArray(data.data) ? data.data : []
    total.value = Number(data.meta?.total ?? rows.value.length)
    page.value = Math.max(1, Number(data.meta?.current_page ?? page.value))
    actionOptions.value = Array.isArray(data.meta?.actions) ? data.meta.actions : []
    stats.value = data.meta?.stats ?? null
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load audit logs.'))
    rows.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  appliedFilters.q = filters.q
  appliedFilters.action = filters.action
  appliedFilters.date_from = filters.date_from
  appliedFilters.date_to = filters.date_to
  page.value = 1
  void load()
}

function resetFilters(): void {
  filters.q = ''
  filters.action = ''
  filters.date_from = ''
  filters.date_to = ''
  applyFilters()
}

function onUpdateOptions(options: { page: number; itemsPerPage: number; sortBy: SortItem[] }): void {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  void load()
}

function prettyJson(value: unknown): string {
  if (value == null) return '—'
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

void load()
</script>

<template>
  <div class="audit-page">
    <header class="audit-page__head">
      <h2>Audit trail</h2>
      <p class="audit-page__lede">Security and configuration events — who did what, when, and from where.</p>
    </header>

    <v-row v-if="stats" class="audit-stats" dense>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="10" class="withbg stat-card stat-card--primary">
          <v-card-text class="text-center">
            <v-icon icon="mdi-format-list-bulleted" size="32" color="primary" class="mb-2" />
            <p class="stat-label">Total events</p>
            <p class="stat-value">{{ stats.total.toLocaleString() }}</p>
            <p class="stat-sub">All time</p>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="10" class="withbg stat-card stat-card--success">
          <v-card-text class="text-center">
            <v-icon icon="mdi-clock-outline" size="32" color="success" class="mb-2" />
            <p class="stat-label">Recent activity</p>
            <p class="stat-value">{{ stats.recent_24h.toLocaleString() }}</p>
            <p class="stat-sub">Last 24 hours</p>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="10" class="withbg stat-card stat-card--info">
          <v-card-text class="text-center">
            <v-icon icon="mdi-chart-line" size="32" color="info" class="mb-2" />
            <p class="stat-label">Top action</p>
            <p class="stat-value stat-value--sm">{{ stats.top_action ?? '—' }}</p>
            <p v-if="stats.top_action_count" class="stat-sub">{{ stats.top_action_count }} times</p>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="10" class="withbg stat-card stat-card--neutral">
          <v-card-text class="text-center">
            <v-icon icon="mdi-table-eye" size="32" color="secondary" class="mb-2" />
            <p class="stat-label">Showing</p>
            <p class="stat-value stat-value--sm">{{ tableCountLabel }}</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card class="hd-data-table-card audit-filters-card" elevation="10">
      <v-card-item>
        <v-card-title class="text-subtitle-2 font-weight-bold pa-0">
          <v-icon icon="mdi-filter-outline" size="small" class="me-1" />
          Filters
        </v-card-title>
      </v-card-item>
      <v-divider />
      <v-card-text>
        <UForm :state="filters" class="hd-form hd-form--grid audit-filters" @submit="applyFilters">
          <UFormField label="Search" name="q" class="span-2">
            <UInput
              v-model="filters.q"
              type="search"
              icon="mdi-magnify"
              placeholder="Staff name, action, entity ID, correlation…"
              clearable
            />
          </UFormField>
          <UFormField label="Action" name="action">
            <USelect v-model="filters.action" :items="actionFilterItems" icon="mdi-lightning-bolt-outline" />
          </UFormField>
          <UFormField label="From date" name="date_from">
            <UDateInput
              v-model="filters.date_from"
              placeholder="Select start date"
            />
          </UFormField>
          <UFormField label="To date" name="date_to">
            <UDateInput
              v-model="filters.date_to"
              placeholder="Select end date"
            />
          </UFormField>
          <div class="full hd-form-actions audit-filter-actions">
            <v-btn type="submit" color="primary" prepend-icon="mdi-magnify" :loading="loading">
              Apply filters
            </v-btn>
            <v-btn variant="outlined" color="default" prepend-icon="mdi-close" @click="resetFilters">
              Clear
            </v-btn>
          </div>
        </UForm>
      </v-card-text>
    </v-card>

    <v-card class="hd-data-table-card" elevation="10">
      <v-card-item class="audit-table-head">
        <v-card-title class="text-subtitle-2 font-weight-bold pa-0">
          <v-icon icon="mdi-format-list-bulleted" size="small" class="me-1" />
          Audit logs
        </v-card-title>
        <template #append>
          <span class="text-caption text-medium-emphasis">{{ tableCountLabel }}</span>
        </template>
      </v-card-item>
      <v-divider />
      <v-data-table-server
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        class="hd-data-table audit-table"
        :headers="headers"
        :items="rows"
        :items-length="total"
        :items-per-page-options="[10, 25, 50, 100]"
        :loading="loading"
        density="compact"
        hover
        item-value="id"
        @update:options="onUpdateOptions"
      >
        <template #item.row_num="{ index }">
          <v-chip size="x-small" variant="tonal" color="primary" label>
            {{ rowNumber(index) }}
          </v-chip>
        </template>

        <template #item.created_at="{ item }">
          <span class="audit-when">{{ formatWhen(item.created_at) }}</span>
        </template>

        <template #item.action="{ item }">
          <v-chip size="small" variant="flat" :color="actionChipColor(item.action)" label>
            {{ item.action }}
          </v-chip>
        </template>

        <template #item.entity="{ item }">
          <div v-if="item.auditable_type_short" class="audit-entity">
            <v-chip size="x-small" variant="outlined" color="default" label class="mb-1">
              {{ item.auditable_type_short }}
            </v-chip>
            <span v-if="item.auditable_id" class="audit-entity__id">#{{ item.auditable_id }}</span>
          </div>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.staff="{ item }">
          <div class="audit-staff">
            <div class="audit-staff__row">
              <v-avatar size="28" color="primary" variant="tonal" class="me-2">
                <span class="text-caption font-weight-bold">{{ staffInitials(item.staff_name) }}</span>
              </v-avatar>
              <div>
                <strong>{{ item.staff_name ?? 'System' }}</strong>
                <span v-if="item.staff_email" class="audit-staff__meta">{{ item.staff_email }}</span>
                <span v-if="item.duty_station_name" class="audit-staff__meta">{{ item.duty_station_name }}</span>
                <span v-else-if="item.staff_id" class="audit-staff__meta">Staff ID {{ item.staff_id }}</span>
              </div>
            </div>
          </div>
        </template>

        <template #item.ip_address="{ item }">
          <span class="mono">{{ item.ip_address ?? '—' }}</span>
        </template>

        <template #item.correlation_id="{ item }">
          <span class="mono" :title="item.correlation_id ?? undefined">{{ item.correlation_id ?? '—' }}</span>
        </template>

        <template #item.actions="{ item }">
          <v-menu location="bottom end">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                icon="mdi-dots-vertical"
                variant="text"
                size="small"
                density="comfortable"
                aria-label="Row actions"
              />
            </template>
            <v-list density="compact" class="audit-actions-menu">
              <v-list-item prepend-icon="mdi-eye-outline" title="View details" @click="openDetail(item)" />
              <v-list-item
                v-if="item.can_reverse"
                prepend-icon="mdi-undo-variant"
                :title="reversalLabel(item)"
                @click="openReverse(item)"
              />
            </v-list>
          </v-menu>
        </template>

        <template #no-data>
          <div class="audit-empty">
            <v-icon icon="mdi-database-off-outline" size="40" color="disabled" class="mb-2" />
            <p class="text-medium-emphasis mb-0">No audit events match your filters.</p>
          </div>
        </template>

        <template #loading>
          <div class="hd-dt-loading">Loading audit logs…</div>
        </template>
      </v-data-table-server>
    </v-card>

    <UModal v-model:open="detailOpen" title="Audit log details" :ui="{ content: 'max-w-2xl' }">
      <template v-if="detailRow">
        <v-list density="compact" class="detail-list mb-3">
          <v-list-item title="Action">
            <template #append>
              <v-chip size="small" variant="flat" :color="actionChipColor(detailRow.action)" label>
                {{ detailRow.action }}
              </v-chip>
            </template>
          </v-list-item>
          <v-list-item title="When" :subtitle="formatWhen(detailRow.created_at)" />
          <v-list-item title="Staff" :subtitle="detailRow.staff_name ?? 'System'" />
          <v-list-item title="Email" :subtitle="detailRow.staff_email ?? '—'" />
          <v-list-item
            title="Entity"
            :subtitle="`${detailRow.auditable_type_short ?? '—'}${detailRow.auditable_id ? ` #${detailRow.auditable_id}` : ''}`"
          />
          <v-list-item title="IP" :subtitle="detailRow.ip_address ?? '—'" />
          <v-list-item title="Correlation">
            <template #subtitle>
              <span class="mono">{{ detailRow.correlation_id ?? '—' }}</span>
            </template>
          </v-list-item>
        </v-list>
        <v-card v-if="detailRow.old_values" variant="outlined" class="detail-json mb-3">
          <v-card-title class="text-subtitle-2">Old values</v-card-title>
          <v-card-text>
            <pre>{{ prettyJson(detailRow.old_values) }}</pre>
          </v-card-text>
        </v-card>
        <v-card v-if="detailRow.new_values" variant="outlined" class="detail-json">
          <v-card-title class="text-subtitle-2">New values</v-card-title>
          <v-card-text>
            <pre>{{ prettyJson(detailRow.new_values) }}</pre>
          </v-card-text>
        </v-card>
      </template>
    </UModal>

    <UModal v-model:open="reverseOpen" title="Reverse audit action">
      <template v-if="reverseRow">
        <v-alert type="warning" variant="tonal" density="comfortable" class="mb-3">
          This will undo <strong>{{ reverseRow.action }}</strong>
          <template v-if="reverseRow.auditable_type_short"> on {{ reverseRow.auditable_type_short }} #{{ reverseRow.auditable_id }}</template>.
          A new reversal entry will be appended to the audit trail.
        </v-alert>
        <UFormField v-if="reverseActionItems.length > 1" label="Reversal type" name="action_type">
          <USelect v-model="reverseForm.action_type" :items="reverseActionItems" />
        </UFormField>
        <UFormField label="Reason" name="reason" required description="Required — min 10 characters">
          <UTextarea v-model="reverseForm.reason" :rows="3" maxlength="500" placeholder="Why are you reversing this action?" />
        </UFormField>
        <v-card-actions class="reverse-actions">
          <v-spacer />
          <v-btn variant="outlined" @click="closeReverse">Cancel</v-btn>
          <v-btn color="warning" variant="flat" :loading="reverseBusy" @click="submitReverse">
            Confirm reversal
          </v-btn>
        </v-card-actions>
      </template>
    </UModal>
  </div>
</template>

<style scoped>
.audit-page__head h2 {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
  font-weight: 700;
}
.audit-page__lede {
  margin: 0 0 1rem;
  font-size: 0.88rem;
  color: #64748b;
  max-width: 42rem;
}
.audit-stats {
  margin-bottom: 1rem;
}
.stat-card {
  height: 100%;
  border: none !important;
  border-radius: 12px !important;
  border-left: 4px solid #94a3b8 !important;
}
.stat-card--primary { border-left-color: #2563eb !important; }
.stat-card--success { border-left-color: #16a34a !important; }
.stat-card--info { border-left-color: #0891b2 !important; }
.stat-card--neutral { border-left-color: #64748b !important; }
.stat-label {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.01em;
  color: #768b9e;
}
.stat-value {
  margin: 0.25rem 0 0;
  font-size: 1.65rem;
  font-weight: 700;
  color: #3a4752;
  line-height: 1.1;
}
.stat-value--sm {
  font-size: 1rem;
  font-weight: 700;
  word-break: break-word;
}
.stat-sub {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: #64748b;
}
.audit-filters-card {
  margin-bottom: 1rem;
}
.audit-filter-actions {
  justify-content: flex-start;
  gap: 0.5rem;
}
.audit-table-head {
  padding-inline: 1rem;
}
.audit-staff__row {
  display: flex;
  align-items: flex-start;
}
.audit-when {
  font-size: 0.8rem;
  color: #334155;
  white-space: nowrap;
}
.audit-entity__id {
  font-size: 0.78rem;
  color: #64748b;
}
.audit-staff strong {
  display: block;
  font-size: 0.86rem;
}
.audit-staff__meta {
  display: block;
  font-size: 0.76rem;
  color: #64748b;
}
.mono {
  font-family: ui-monospace, monospace;
  font-size: 0.74rem;
  max-width: 9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: inline-block;
}
.audit-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem 1rem;
  margin: 0;
}
.detail-list {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
.detail-json pre {
  margin: 0;
  padding: 0.75rem;
  background: rgb(var(--v-theme-surface));
  border-radius: 4px;
  font-size: 0.75rem;
  overflow: auto;
  max-height: 220px;
}
.reverse-actions {
  padding-inline: 0;
  margin-top: 0.5rem;
}
</style>
