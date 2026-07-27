<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'
import { useAuthStore } from '../../stores/auth'

const CbpRichTextEditor = defineAsyncComponent(
  () => import('../../components/common/CbpRichTextEditor.vue'),
)

interface HostingRequest {
  id: number
  request_number: string
  status: string
  category: string
  title: string
  description?: string | null
  cloud_provider?: string | null
  environment_notes?: string | null
  requester_name: string
  requester_division_name?: string | null
  hod_staff_id?: number | null
  hod_name?: string | null
  hod_decision_notes?: string | null
  process_notes?: string | null
}

type TabId = 'new' | 'requests'

const auth = useAuthStore()
const route = useRoute()
const activeTab = ref<TabId>('requests')
const rows = ref<HostingRequest[]>([])
const loading = ref(true)
const selected = ref<HostingRequest | null>(null)
const busy = ref(false)
const filterQ = ref('')
const filterStatus = ref('')
const notes = ref('')

const canProcess = computed(
  () =>
    !!auth.me?.profile?.is_helpdesk_admin ||
    !!auth.me?.profile?.can_process_hosting_requests,
)

const isHodForSelected = computed(() => {
  const staffId = auth.me?.profile?.staff_id
  if (!selected.value || !staffId) return false
  return selected.value.status === 'pending_hod' && Number(selected.value.hod_staff_id) === Number(staffId)
})

const form = reactive({
  title: '',
  description: '',
  category: 'cloud',
  cloud_provider: 'Azure',
  environment_notes: '',
})

const categoryItems = [
  { label: 'Cloud (Azure / CDC-approved online)', value: 'cloud' },
  { label: 'On Premises (Africa CDC servers)', value: 'on_premises' },
]

const statusItems = [
  { label: 'All statuses', value: '' },
  { label: 'Pending HoD', value: 'pending_hod' },
  { label: 'HoD approved', value: 'hod_approved' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'HoD rejected', value: 'hod_rejected' },
  { label: 'Draft', value: 'draft' },
]

function statusBadge(status: string): string {
  return `status-badge status-${status}`
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: HostingRequest[] }>('/api/v1/tools/hosting-requests', {
      params: {
        q: filterQ.value || undefined,
        status: filterStatus.value || undefined,
        per_page: 50,
      },
    })
    rows.value = Array.isArray(data.data) ? data.data : ((data as unknown as { data?: HostingRequest[] }).data ?? [])
    // Laravel paginator: { data: [...], current_page }
    const page = data as unknown as { data?: HostingRequest[] }
    if (Array.isArray(page.data)) rows.value = page.data
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load hosting requests.'))
  } finally {
    loading.value = false
  }
}

async function openDetail(row: HostingRequest) {
  try {
    const { data } = await api.get<{ data: HostingRequest }>(`/api/v1/tools/hosting-requests/${row.id}`)
    selected.value = data.data
    notes.value = ''
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to open request.'))
  }
}

async function save(submit: boolean) {
  if (!form.title.trim()) {
    notifyError('Title is required.')
    return
  }
  if (form.category === 'cloud' && !form.cloud_provider.trim()) {
    notifyError('Cloud provider is required for cloud requests.')
    return
  }
  busy.value = true
  try {
    await api.post('/api/v1/tools/hosting-requests', { ...form, submit })
    notifySuccess(submit ? 'Hosting request submitted for HoD approval.' : 'Draft saved.')
    form.title = ''
    form.description = ''
    form.environment_notes = ''
    activeTab.value = 'requests'
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function hodAction(approve: boolean) {
  if (!selected.value) return
  busy.value = true
  try {
    const path = approve ? 'hod-approve' : 'hod-reject'
    const { data } = await api.post<{ data: HostingRequest }>(
      `/api/v1/tools/hosting-requests/${selected.value.id}/${path}`,
      { notes: notes.value || null },
    )
    selected.value = data.data
    notifySuccess(approve ? 'Approved by HoD.' : 'Rejected by HoD.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'HoD action failed.'))
  } finally {
    busy.value = false
  }
}

async function processRequest() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: HostingRequest }>(
      `/api/v1/tools/hosting-requests/${selected.value.id}/process`,
      { notes: notes.value || null },
    )
    selected.value = data.data
    notifySuccess('Processing started.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Process failed. HoD approval is required first.'))
  } finally {
    busy.value = false
  }
}

async function completeRequest() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: HostingRequest }>(
      `/api/v1/tools/hosting-requests/${selected.value.id}/complete`,
      { notes: notes.value || null },
    )
    selected.value = data.data
    notifySuccess('Marked completed.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Complete failed.'))
  } finally {
    busy.value = false
  }
}

watch(
  () => route.query.tab,
  (tab) => {
    if (tab === 'new') activeTab.value = 'new'
  },
  { immediate: true },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="tools-page">
    <CbpPageHeading title="Hosting requests" subtitle="Cloud or on-premises hosting — HoD approval required before processing." />

    <div class="tabs" role="tablist">
      <button type="button" class="tab" :class="{ active: activeTab === 'requests' }" @click="activeTab = 'requests'">Requests</button>
      <button type="button" class="tab" :class="{ active: activeTab === 'new' }" @click="activeTab = 'new'">New request</button>
    </div>

    <div v-show="activeTab === 'new'" class="cbp-card panel">
      <div class="hd-form hd-form--grid">
        <UFormField label="Title" required class="span-3">
          <UInput v-model="form.title" class="w-full" />
        </UFormField>
        <UFormField label="Category" required>
          <USelect v-model="form.category" :items="categoryItems" class="w-full" />
        </UFormField>
        <UFormField v-if="form.category === 'cloud'" label="Cloud provider" required>
          <UInput v-model="form.cloud_provider" class="w-full" placeholder="Azure or other CDC-approved" />
        </UFormField>
        <UFormField label="Description" class="span-3 hd-rich-field">
          <CbpRichTextEditor v-model="form.description" :min-rows="4" placeholder="Describe the hosting need…" />
        </UFormField>
        <UFormField label="Environment notes" class="span-3 hd-rich-field">
          <CbpRichTextEditor v-model="form.environment_notes" :min-rows="3" placeholder="Capacity, URLs, constraints…" />
        </UFormField>
      </div>
      <div class="actions">
        <UButton color="neutral" variant="outline" :loading="busy" @click="save(false)">Save draft</UButton>
        <UButton color="primary" :loading="busy" @click="save(true)">Submit for HoD approval</UButton>
      </div>
    </div>

    <div v-show="activeTab === 'requests'" class="requests-layout">
      <div class="filters">
        <UInput v-model="filterQ" type="search" placeholder="Search…" class="w-full" @keyup.enter="load()" />
        <USelect v-model="filterStatus" :items="statusItems" class="w-full" @update:model-value="load" />
        <UButton color="primary" variant="soft" @click="load()">Apply</UButton>
      </div>

      <div class="split" :class="{ 'has-detail': !!selected }">
        <div class="cbp-card table-wrap">
          <p v-if="loading" class="muted">Loading…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Category</th>
                <th>Requester</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="clickable"
                :class="{ selected: selected?.id === row.id }"
                @click="openDetail(row)"
              >
                <td><strong>{{ row.request_number }}</strong></td>
                <td>{{ row.title }}</td>
                <td>{{ row.category === 'cloud' ? 'Cloud' : 'On premises' }}</td>
                <td>{{ row.requester_name }}</td>
                <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !rows.length" class="muted">No hosting requests yet.</p>
        </div>

        <UCard v-if="selected" class="detail">
          <template #header>
            <strong>{{ selected.request_number }}</strong>
            <span :class="statusBadge(selected.status)">{{ selected.status }}</span>
          </template>
          <dl class="detail-dl">
            <dt>Title</dt><dd>{{ selected.title }}</dd>
            <dt>Category</dt><dd>{{ selected.category === 'cloud' ? `Cloud (${selected.cloud_provider || '—'})` : 'On premises' }}</dd>
            <dt>Requester</dt><dd>{{ selected.requester_name }} · {{ selected.requester_division_name || '—' }}</dd>
            <dt>HoD</dt><dd>{{ selected.hod_name || '—' }}</dd>
            <dt>Description</dt><dd><div class="rich-text-content" v-html="selected.description || '—'" /></dd>
          </dl>

          <UFormField v-if="isHodForSelected || (canProcess && ['hod_approved', 'in_progress'].includes(selected.status))" label="Notes">
            <UTextarea v-model="notes" :rows="2" class="w-full" />
          </UFormField>

          <div class="actions">
            <template v-if="isHodForSelected">
              <UButton color="primary" size="sm" :loading="busy" @click="hodAction(true)">HoD approve</UButton>
              <UButton color="error" variant="outline" size="sm" :loading="busy" @click="hodAction(false)">HoD reject</UButton>
            </template>
            <UButton
              v-if="canProcess && selected.status === 'hod_approved'"
              color="primary"
              size="sm"
              :loading="busy"
              @click="processRequest"
            >
              Start processing
            </UButton>
            <UButton
              v-if="canProcess && selected.status === 'in_progress'"
              color="primary"
              size="sm"
              :loading="busy"
              @click="completeRequest"
            >
              Mark completed
            </UButton>
          </div>
          <p v-if="canProcess && selected.status === 'pending_hod'" class="hint">
            Waiting for Head of Division approval before processing.
          </p>
        </UCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.tabs { display: flex; gap: 0.5rem; }
.tab { border: 1px solid #cbd5e1; background: #fff; padding: 0.45rem 0.9rem; border-radius: 8px; cursor: pointer; }
.tab.active { background: #0f766e; color: #fff; border-color: #0f766e; }
.panel, .table-wrap, .detail { padding: 1rem; }
.actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; }
.filters { display: grid; grid-template-columns: 1fr 12rem auto; gap: 0.75rem; align-items: end; }
.split { display: grid; gap: 1rem; }
.split.has-detail { grid-template-columns: 1.2fr 1fr; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
.clickable { cursor: pointer; }
.clickable.selected { background: #f0fdfa; }
.detail-dl { display: grid; grid-template-columns: 7rem 1fr; gap: 0.35rem 0.75rem; margin: 0 0 1rem; }
.detail-dl dt { color: #64748b; }
.muted, .hint { color: #64748b; font-size: 0.9rem; }
.status-badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 999px; background: #e2e8f0; font-size: 0.75rem; }
@media (max-width: 900px) {
  .split.has-detail, .filters { grid-template-columns: 1fr; }
}
</style>
