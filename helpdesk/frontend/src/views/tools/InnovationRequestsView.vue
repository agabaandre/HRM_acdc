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

interface InnovationRequest {
  id: number
  request_number: string
  status: string
  title: string
  description?: string | null
  innovation_type?: string | null
  requester_name: string
  requester_division_name?: string | null
  process_notes?: string | null
}

type TabId = 'new' | 'requests'

const auth = useAuthStore()
const route = useRoute()
const activeTab = ref<TabId>('requests')
const rows = ref<InnovationRequest[]>([])
const loading = ref(true)
const selected = ref<InnovationRequest | null>(null)
const busy = ref(false)
const filterQ = ref('')
const filterStatus = ref('')
const notes = ref('')

const canProcess = computed(
  () =>
    !!auth.me?.profile?.is_helpdesk_admin ||
    !!auth.me?.profile?.can_process_innovation_requests,
)

const form = reactive({
  title: '',
  description: '',
  innovation_type: '',
})

const statusItems = [
  { label: 'All statuses', value: '' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Draft', value: 'draft' },
]

function statusBadge(status: string): string {
  return `status-badge status-${status}`
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/tools/innovation-requests', {
      params: {
        q: filterQ.value || undefined,
        status: filterStatus.value || undefined,
        per_page: 50,
      },
    })
    const page = data as { data?: InnovationRequest[] }
    rows.value = Array.isArray(page.data) ? page.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load innovation requests.'))
  } finally {
    loading.value = false
  }
}

async function openDetail(row: InnovationRequest) {
  try {
    const { data } = await api.get<{ data: InnovationRequest }>(`/api/v1/tools/innovation-requests/${row.id}`)
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
  busy.value = true
  try {
    await api.post('/api/v1/tools/innovation-requests', { ...form, submit })
    notifySuccess(submit ? 'Innovation request submitted.' : 'Draft saved.')
    form.title = ''
    form.description = ''
    form.innovation_type = ''
    activeTab.value = 'requests'
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function processRequest() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: InnovationRequest }>(
      `/api/v1/tools/innovation-requests/${selected.value.id}/process`,
      { notes: notes.value || null },
    )
    selected.value = data.data
    notifySuccess('Processing started.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Process failed.'))
  } finally {
    busy.value = false
  }
}

async function completeRequest() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: InnovationRequest }>(
      `/api/v1/tools/innovation-requests/${selected.value.id}/complete`,
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

async function rejectRequest() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: InnovationRequest }>(
      `/api/v1/tools/innovation-requests/${selected.value.id}/reject`,
      { notes: notes.value || null },
    )
    selected.value = data.data
    notifySuccess('Request rejected.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Reject failed.'))
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
    <CbpPageHeading title="Innovations" subtitle="Submit innovation ideas. No HoD approval required — processors can act after submit." />

    <div class="tabs" role="tablist">
      <button type="button" class="tab" :class="{ active: activeTab === 'requests' }" @click="activeTab = 'requests'">Requests</button>
      <button type="button" class="tab" :class="{ active: activeTab === 'new' }" @click="activeTab = 'new'">New request</button>
    </div>

    <div v-show="activeTab === 'new'" class="cbp-card panel">
      <div class="hd-form hd-form--grid">
        <UFormField label="Title" required class="span-3">
          <UInput v-model="form.title" class="w-full" />
        </UFormField>
        <UFormField label="Type" class="span-3">
          <UInput v-model="form.innovation_type" class="w-full" placeholder="Optional classification" />
        </UFormField>
        <UFormField label="Description" class="span-3 hd-rich-field">
          <CbpRichTextEditor v-model="form.description" :min-rows="5" placeholder="Describe the innovation…" />
        </UFormField>
      </div>
      <div class="actions">
        <UButton color="neutral" variant="outline" :loading="busy" @click="save(false)">Save draft</UButton>
        <UButton color="primary" :loading="busy" @click="save(true)">Submit</UButton>
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
                <td>{{ row.requester_name }}</td>
                <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !rows.length" class="muted">No innovation requests yet.</p>
        </div>

        <UCard v-if="selected" class="detail">
          <template #header>
            <strong>{{ selected.request_number }}</strong>
            <span :class="statusBadge(selected.status)">{{ selected.status }}</span>
          </template>
          <dl class="detail-dl">
            <dt>Title</dt><dd>{{ selected.title }}</dd>
            <dt>Type</dt><dd>{{ selected.innovation_type || '—' }}</dd>
            <dt>Requester</dt><dd>{{ selected.requester_name }} · {{ selected.requester_division_name || '—' }}</dd>
            <dt>Description</dt><dd><div class="rich-text-content" v-html="selected.description || '—'" /></dd>
          </dl>

          <UFormField v-if="canProcess && ['submitted', 'in_progress'].includes(selected.status)" label="Notes">
            <UTextarea v-model="notes" :rows="2" class="w-full" />
          </UFormField>

          <div class="actions">
            <UButton
              v-if="canProcess && selected.status === 'submitted'"
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
            <UButton
              v-if="canProcess && ['submitted', 'in_progress'].includes(selected.status)"
              color="error"
              variant="outline"
              size="sm"
              :loading="busy"
              @click="rejectRequest"
            >
              Reject
            </UButton>
          </div>
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
.muted { color: #64748b; font-size: 0.9rem; }
.status-badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 999px; background: #e2e8f0; font-size: 0.75rem; }
@media (max-width: 900px) {
  .split.has-detail, .filters { grid-template-columns: 1fr; }
}
</style>
