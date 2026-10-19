<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateTime12h } from '@/lib/formatDate'
import { useAuthStore } from '@/stores/auth'
import AppDataTableServer from '@/components/shared/AppDataTableServer.vue'

type EmailLog = {
  id: number
  to: string
  subject: string
  status: string
  driver: string | null
  error_message: string | null
  sending_system: string
  source: string
  sender_ip: string | null
  can_retry?: boolean
  attachment_count?: number
  external_integration_id: number | null
  email_provider?: { id: number; name: string } | null
  created_at: string
  updated_at?: string | null
}

type FilterOption = { value: string; label: string }
type ClientOption = { id: number; name: string }

const items = ref<EmailLog[]>([])
const loading = ref(true)
const page = ref(1)
const itemsPerPage = ref(25)
const total = ref(0)
const error = ref('')
const message = ref('')
const retryingId = ref<number | null>(null)
const retryingAll = ref(false)
const detailsOpen = ref(false)
const selectedLog = ref<EmailLog | null>(null)

const statusFilter = ref<string | null>(null)
const driverFilter = ref<string | null>(null)
const clientFilter = ref<string | null>(null)
const search = ref('')
const auth = useAuthStore()
const canResend = computed(() => auth.isAdmin)

const statuses = ref<FilterOption[]>([
  { value: 'pending', label: 'Pending' },
  { value: 'sent', label: 'Sent' },
  { value: 'failed', label: 'Failed' },
])
const drivers = ref<FilterOption[]>([
  { value: 'exchange', label: 'Microsoft Exchange (Graph API)' },
  { value: 'smtp', label: 'SMTP' },
  { value: 'ses', label: 'Amazon SES' },
  { value: 'log', label: 'Log (development)' },
])
const clients = ref<ClientOption[]>([])
const canViewInternal = ref(true)

const clientItems = computed(() => [
  ...(canViewInternal.value ? [{ value: 'none', title: 'Admin / internal' }] : []),
  ...clients.value.map((c) => ({ value: String(c.id), title: c.name })),
])

async function loadFilters() {
  try {
    const res = await api.get('/admin/email-logs/filter-options')
    statuses.value = res.data.data.statuses ?? statuses.value
    drivers.value = res.data.data.drivers ?? drivers.value
    clients.value = res.data.data.clients ?? []
    canViewInternal.value = res.data.data.can_view_internal !== false
    if (!canViewInternal.value && clientFilter.value === 'none') {
      clientFilter.value = null
    }
  } catch {
    // Keep built-in status / driver options
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/email-logs', {
      params: {
        page: page.value,
        per_page: itemsPerPage.value,
        status: statusFilter.value || undefined,
        driver: driverFilter.value || undefined,
        external_integration_id: clientFilter.value || undefined,
        q: search.value.trim() || undefined,
      },
    })
    items.value = res.data.data
    total.value = res.data.total
  } catch (err) {
    items.value = []
    total.value = 0
    error.value = apiErrorMessage(err, 'Could not load email logs.')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  load()
}

function clearFilters() {
  statusFilter.value = null
  driverFilter.value = null
  clientFilter.value = null
  search.value = ''
  page.value = 1
  load()
}

function openDetails(item: EmailLog) {
  selectedLog.value = item
  detailsOpen.value = true
}

async function retry(item: EmailLog) {
  if (!item.can_retry) return
  retryingId.value = item.id
  message.value = ''
  error.value = ''
  try {
    const res = await api.post(`/admin/email-logs/${item.id}/retry`)
    message.value = res.data.message || 'Email queued for resend.'
    detailsOpen.value = false
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not resend email.')
  } finally {
    retryingId.value = null
  }
}

async function retryAllFailed() {
  const scope = clientFilter.value
    ? 'failed emails for the selected client'
    : 'all failed emails'
  if (!confirm(`Resend ${scope} that still have a stored body?`)) return

  retryingAll.value = true
  message.value = ''
  error.value = ''
  try {
    const res = await api.post('/admin/email-logs/retry-failed', null, {
      params: {
        external_integration_id: clientFilter.value || undefined,
      },
    })
    message.value = res.data.message || 'Failed emails queued for resend.'
    statusFilter.value = 'pending'
    page.value = 1
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not resend failed emails.')
  } finally {
    retryingAll.value = false
  }
}

watch([statusFilter, driverFilter, clientFilter], () => {
  page.value = 1
  load()
})

onMounted(async () => {
  await loadFilters()
  await load()
})
</script>

<template>
  <div>
    <PageHeader
      title="Email logs"
      subtitle="Delivery history — filter by client, status, or sending driver"
    >
      <template #actions>
        <v-btn
          v-if="canResend"
          color="error"
          variant="tonal"
          prepend-icon="mdi-email-sync-outline"
          :loading="retryingAll"
          @click="retryAllFailed"
        >
          Resend all failed
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <ParentCard title="Filters">
      <v-row dense>
        <v-col cols="12" md="2">
          <v-select
            v-model="statusFilter"
            :items="statuses"
            item-title="label"
            item-value="value"
            label="Status"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="driverFilter"
            :items="drivers"
            item-title="label"
            item-value="value"
            label="Sending driver"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="clientFilter"
            :items="clientItems"
            item-title="title"
            item-value="value"
            label="Client"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="search"
            label="Search to / subject / IP"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            prepend-inner-icon="mdi-magnify"
            @keyup.enter="applyFilters"
          />
        </v-col>
        <v-col cols="12" md="2" class="d-flex ga-2 align-center">
          <v-btn color="primary" variant="tonal" @click="applyFilters">Apply</v-btn>
          <v-btn variant="text" @click="clearFilters">Clear</v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <AppDataTableServer
      :loading="loading"
      :items="items"
      :items-length="total"
      :page="page"
      :items-per-page="itemsPerPage"
      :headers="[
        { title: 'To / Subject', key: 'to_subject', sortable: false },
        { title: 'Client', key: 'source' },
        { title: 'IP address', key: 'sender_ip' },
        { title: 'Sending system', key: 'sending_system' },
        { title: 'Status', key: 'status' },
        { title: 'Driver', key: 'driver' },
        { title: 'When', key: 'created_at' },
        { title: 'Actions', key: 'actions', sortable: false, width: 200 },
      ]"
      @update:page="(p: number) => { page = p; load() }"
      @update:items-per-page="(n: number) => { itemsPerPage = n; page = 1; load() }"
    >
      <template #item.to_subject="{ item }">
        <div class="to-subject-cell">
          <div class="to-subject-cell__to">{{ item.to || '—' }}</div>
          <div class="to-subject-cell__subject">{{ item.subject || '—' }}</div>
        </div>
      </template>
      <template #item.sending_system="{ item }">
        <span class="font-weight-medium">{{ item.sending_system }}</span>
      </template>
      <template #item.source="{ item }">
        <v-chip size="small" variant="tonal" color="primary">
          {{ item.source }}
        </v-chip>
      </template>
      <template #item.sender_ip="{ item }">
        <span class="text-no-wrap text-caption">{{ item.sender_ip || '—' }}</span>
      </template>
      <template #item.status="{ item }">
        <v-chip
          size="small"
          class="text-capitalize"
          :color="item.status === 'sent' ? 'success' : item.status === 'failed' ? 'error' : 'warning'"
        >
          {{ item.status }}
        </v-chip>
      </template>
      <template #item.created_at="{ item }">
        <span class="text-no-wrap">{{ formatDateTime12h(item.created_at) }}</span>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex ga-1 flex-wrap">
          <v-btn
            size="small"
            variant="text"
            prepend-icon="mdi-eye-outline"
            @click="openDetails(item)"
          >
            Details
          </v-btn>
          <v-btn
            v-if="canResend && item.can_retry"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="mdi-email-sync"
            :loading="retryingId === item.id"
            @click="retry(item)"
          >
            Resend
          </v-btn>
        </div>
      </template>
      <template #no-data>
        <div class="text-medium-emphasis pa-6 text-center">
          No email logs match the current filters.
        </div>
      </template>
    </AppDataTableServer>

    <v-dialog v-model="detailsOpen" max-width="640">
      <v-card v-if="selectedLog">
        <v-card-title>Email log #{{ selectedLog.id }}</v-card-title>
        <v-card-text class="details-body">
          <p><strong>To:</strong> {{ selectedLog.to || '—' }}</p>
          <p><strong>Subject:</strong> {{ selectedLog.subject || '—' }}</p>
          <p>
            <strong>Status:</strong>
            <v-chip
              size="small"
              class="text-capitalize ml-1"
              :color="
                selectedLog.status === 'sent'
                  ? 'success'
                  : selectedLog.status === 'failed'
                    ? 'error'
                    : 'warning'
              "
            >
              {{ selectedLog.status }}
            </v-chip>
          </p>
          <p><strong>Client:</strong> {{ selectedLog.source || '—' }}</p>
          <p><strong>Sending system:</strong> {{ selectedLog.sending_system || '—' }}</p>
          <p><strong>Driver:</strong> {{ selectedLog.driver || '—' }}</p>
          <p><strong>Provider:</strong> {{ selectedLog.email_provider?.name || '—' }}</p>
          <p><strong>IP address:</strong> {{ selectedLog.sender_ip || '—' }}</p>
          <p>
            <strong>Attachments:</strong>
            {{
              (selectedLog.attachment_count ?? 0) > 0
                ? selectedLog.attachment_count
                : 'None'
            }}
          </p>
          <p><strong>When:</strong> {{ formatDateTime12h(selectedLog.created_at) }}</p>
          <p v-if="selectedLog.updated_at">
            <strong>Updated:</strong> {{ formatDateTime12h(selectedLog.updated_at) }}
          </p>
          <div class="mt-3">
            <strong>Error</strong>
            <pre class="error-block">{{ selectedLog.error_message || '—' }}</pre>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn
            v-if="canResend && selectedLog.can_retry"
            color="primary"
            variant="tonal"
            :loading="retryingId === selectedLog.id"
            @click="retry(selectedLog)"
          >
            Resend
          </v-btn>
          <v-btn variant="text" @click="detailsOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.to-subject-cell {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 12rem;
  max-width: 28rem;
  padding: 0.15rem 0;
  line-height: 1.35;
}

.to-subject-cell__to {
  font-weight: 600;
  word-break: break-word;
}

.to-subject-cell__subject {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8125rem;
  word-break: break-word;
}

.details-body p {
  margin-bottom: 0.5rem;
}

.error-block {
  margin: 0.35rem 0 0;
  padding: 0.75rem;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.8125rem;
  max-height: 12rem;
  overflow: auto;
}
</style>
