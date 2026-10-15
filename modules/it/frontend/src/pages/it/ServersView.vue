<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError, notifySuccess } from '@/lib/notify'

type Kind = 'azure_vm' | 'azure_landing_zone' | 'azure_storage' | 'datacenter_vm'
type TabId = 'inventory' | 'form'

interface ServerDetails {
  subscription?: string
  resource_group?: string
  vm_size?: string
  public_ip?: string
  management_group?: string
  connected_networks?: string
  storage_account?: string
  container_name?: string
  access_tier?: string
  replication?: string
  datacenter?: string
  cluster?: string
  cpu?: string
  memory_gb?: number | string | null
  disk_gb?: number | string | null
}

interface ServerRow {
  id: number
  kind: Kind
  name: string
  status: string
  environment?: string | null
  location?: string | null
  hostname?: string | null
  ip_address?: string | null
  operating_system?: string | null
  hosts?: string | null
  running_details?: string | null
  owner_name?: string | null
  notes?: string | null
  details?: ServerDetails | null
}

interface Summary {
  total: number
  running: number
  by_kind: Record<Kind, number>
}

const kinds: { id: Kind; label: string }[] = [
  { id: 'azure_vm', label: 'Azure VMs' },
  { id: 'azure_landing_zone', label: 'Azure Landing Zones' },
  { id: 'azure_storage', label: 'Azure Storage Containers' },
  { id: 'datacenter_vm', label: 'Data Center VMs' },
]

const statusItems = [
  { label: 'Running', value: 'running' },
  { label: 'Stopped', value: 'stopped' },
  { label: 'Decommissioned', value: 'decommissioned' },
]

const environmentItems = [
  { label: 'Production', value: 'production' },
  { label: 'Staging', value: 'staging' },
  { label: 'Development', value: 'development' },
  { label: 'Test', value: 'test' },
]

const tierItems = [
  { label: 'Hot', value: 'hot' },
  { label: 'Cool', value: 'cool' },
  { label: 'Archive', value: 'archive' },
]

const kind = ref<Kind>('azure_vm')
const activeTab = ref<TabId>('inventory')
const summary = ref<Summary | null>(null)
const rows = ref<ServerRow[]>([])
const loading = ref(false)
const busy = ref(false)
const search = ref('')
const filterStatus = ref('')
const editing = ref<ServerRow | null>(null)

const emptyDetails = (): ServerDetails => ({
  subscription: '',
  resource_group: '',
  vm_size: '',
  public_ip: '',
  management_group: '',
  connected_networks: '',
  storage_account: '',
  container_name: '',
  access_tier: '',
  replication: '',
  datacenter: '',
  cluster: '',
  cpu: '',
  memory_gb: null,
  disk_gb: null,
})

const form = reactive({
  name: '',
  status: 'running',
  environment: 'production',
  location: '',
  hostname: '',
  ip_address: '',
  operating_system: '',
  hosts: '',
  running_details: '',
  owner_name: '',
  notes: '',
  details: emptyDetails(),
})

const filterStatusItems = [{ label: 'All statuses', value: '' }, ...statusItems]
const kindLabel = computed(() => kinds.find((item) => item.id === kind.value)?.label ?? 'Servers')
const formTitle = computed(() => (editing.value ? `Edit ${kindLabel.value.replace(/s$/, '')}` : `Add ${kindLabel.value.replace(/s$/, '')}`))

function statusLabel(value: string) {
  return statusItems.find((item) => item.value === value)?.label ?? value
}

function statusBadgeClass(value: string) {
  if (value === 'running') return 'badge badge-ok'
  if (value === 'stopped') return 'badge badge-warn'
  return 'badge badge-muted'
}

function resetForm() {
  editing.value = null
  form.name = ''
  form.status = 'running'
  form.environment = 'production'
  form.location = ''
  form.hostname = ''
  form.ip_address = ''
  form.operating_system = ''
  form.hosts = ''
  form.running_details = ''
  form.owner_name = ''
  form.notes = ''
  form.details = emptyDetails()
}

function openCreate() {
  resetForm()
  activeTab.value = 'form'
}

function openEdit(row: ServerRow) {
  editing.value = row
  form.name = row.name
  form.status = row.status
  form.environment = row.environment || 'production'
  form.location = row.location || ''
  form.hostname = row.hostname || ''
  form.ip_address = row.ip_address || ''
  form.operating_system = row.operating_system || ''
  form.hosts = row.hosts || ''
  form.running_details = row.running_details || ''
  form.owner_name = row.owner_name || ''
  form.notes = row.notes || ''
  form.details = { ...emptyDetails(), ...(row.details || {}) }
  activeTab.value = 'form'
}

function cancelForm() {
  resetForm()
  activeTab.value = 'inventory'
}

async function load() {
  loading.value = true
  try {
    const [sumRes, listRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/servers/summary'),
      api.get<{ data: ServerRow[] }>('/api/v1/tools/servers', {
        params: {
          kind: kind.value,
          status: filterStatus.value || undefined,
          q: search.value || undefined,
        },
      }),
    ])
    summary.value = sumRes.data.data
    rows.value = Array.isArray(listRes.data.data) ? listRes.data.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load servers.'))
  } finally {
    loading.value = false
  }
}

async function save() {
  busy.value = true
  const payload = {
    kind: kind.value,
    name: form.name,
    status: form.status,
    environment: form.environment || null,
    location: form.location || null,
    hostname: form.hostname || null,
    ip_address: form.ip_address || null,
    operating_system: form.operating_system || null,
    hosts: form.hosts || null,
    running_details: form.running_details || null,
    owner_name: form.owner_name || null,
    notes: form.notes || null,
    details: { ...form.details },
  }
  try {
    if (editing.value) {
      await api.put(`/api/v1/tools/servers/${editing.value.id}`, payload)
      notifySuccess('Server updated.')
    } else {
      await api.post('/api/v1/tools/servers', payload)
      notifySuccess('Server added.')
    }
    cancelForm()
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to save server.'))
  } finally {
    busy.value = false
  }
}

async function remove(row: ServerRow) {
  if (!window.confirm(`Delete ${row.name}?`)) return
  try {
    await api.delete(`/api/v1/tools/servers/${row.id}`)
    notifySuccess('Server deleted.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to delete server.'))
  }
}

function locationLabel(row: ServerRow) {
  if (row.kind === 'azure_storage') {
    return row.details?.storage_account || row.location || '—'
  }
  if (row.kind === 'datacenter_vm') {
    return row.details?.datacenter || row.location || '—'
  }
  return row.location || row.details?.resource_group || '—'
}

watch(kind, () => {
  cancelForm()
  void load()
})

onMounted(load)
</script>

<template>
  <div>
    <CbpPageHeading title="Servers" back-to="/" back-label="← Overview">
      <template #lede>
        Record Azure and data-center servers, what each one hosts, and the workloads it is running.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist" aria-label="Server types">
        <button
          v-for="item in kinds"
          :key="item.id"
          type="button"
          role="tab"
          class="tab"
          :class="{ active: kind === item.id }"
          :aria-selected="kind === item.id"
          @click="kind = item.id"
        >
          {{ item.label }}
          <span v-if="summary" class="tab-count">{{ summary.by_kind[item.id] }}</span>
        </button>
      </div>

      <div class="tabs inner-tabs" role="tablist" aria-label="Server records">
        <button type="button" role="tab" class="tab" :class="{ active: activeTab === 'inventory' }" :aria-selected="activeTab === 'inventory'" @click="activeTab = 'inventory'">
          Inventory
        </button>
        <button type="button" role="tab" class="tab" :class="{ active: activeTab === 'form' }" :aria-selected="activeTab === 'form'" @click="editing ? (activeTab = 'form') : openCreate()">
          {{ editing ? 'Edit' : 'Add' }}
        </button>
      </div>

      <div v-show="activeTab === 'inventory'" class="tab-panel" role="tabpanel">
        <div class="filters">
          <UFormField label="Status" class="filter-sm">
            <USelect v-model="filterStatus" :items="filterStatusItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Search" class="filter-grow">
            <UInput
              v-model="search"
              type="search"
              placeholder="Name, host, IP, what it hosts…"
              class="w-full"
              @keyup.enter="load"
            />
          </UFormField>
          <UButton color="neutral" variant="outline" class="filter-btn" @click="load">Search</UButton>
          <UButton color="primary" class="filter-btn" @click="openCreate">+ Add</UButton>
        </div>

        <div class="table-wrap cbp-card">
          <p v-if="loading" class="muted empty">Loading {{ kindLabel.toLowerCase() }}…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Where</th>
                <th>Hosting</th>
                <th>Running</th>
                <th>Owner</th>
                <th>Status</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>
                  <strong>{{ row.name }}</strong>
                  <div v-if="row.hostname" class="sub mono">{{ row.hostname }}</div>
                  <div v-else-if="row.details?.container_name" class="sub mono">{{ row.details.container_name }}</div>
                </td>
                <td class="wrap">{{ locationLabel(row) }}</td>
                <td class="wrap">{{ row.hosts || '—' }}</td>
                <td class="wrap">{{ row.running_details || '—' }}</td>
                <td>{{ row.owner_name || '—' }}</td>
                <td><span :class="statusBadgeClass(row.status)">{{ statusLabel(row.status) }}</span></td>
                <td class="actions">
                  <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                  <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !rows.length" class="muted empty">No {{ kindLabel.toLowerCase() }} yet.</p>
        </div>
      </div>

      <div v-show="activeTab === 'form'" class="tab-panel" role="tabpanel">
        <UCard class="form-panel">
          <template #header>
            <div class="form-header">
              <h3>{{ formTitle }}</h3>
              <p class="form-intro">Describe what this {{ kindLabel.replace(/s$/, '') }} hosts and the services or workloads it is running.</p>
            </div>
          </template>
          <div class="hd-form hd-form--grid">
            <UFormField label="Name" required class="span-2"><UInput v-model="form.name" class="w-full" /></UFormField>
            <UFormField label="Status">
              <USelect v-model="form.status" :items="statusItems" class="w-full" />
            </UFormField>
            <UFormField label="Environment">
              <USelect v-model="form.environment" :items="environmentItems" class="w-full" />
            </UFormField>
            <UFormField label="Owner"><UInput v-model="form.owner_name" class="w-full" /></UFormField>
            <UFormField label="Region / location"><UInput v-model="form.location" class="w-full" /></UFormField>

            <template v-if="kind === 'azure_vm'">
              <UFormField label="Subscription"><UInput v-model="form.details.subscription" class="w-full" /></UFormField>
              <UFormField label="Resource group"><UInput v-model="form.details.resource_group" class="w-full" /></UFormField>
              <UFormField label="VM size"><UInput v-model="form.details.vm_size" placeholder="Standard_D4s_v5" class="w-full" /></UFormField>
              <UFormField label="Hostname"><UInput v-model="form.hostname" class="w-full" /></UFormField>
              <UFormField label="Private IP"><UInput v-model="form.ip_address" class="w-full" /></UFormField>
              <UFormField label="Public IP"><UInput v-model="form.details.public_ip" class="w-full" /></UFormField>
              <UFormField label="Operating system" class="span-2"><UInput v-model="form.operating_system" class="w-full" /></UFormField>
            </template>

            <template v-else-if="kind === 'azure_landing_zone'">
              <UFormField label="Subscription"><UInput v-model="form.details.subscription" class="w-full" /></UFormField>
              <UFormField label="Management group"><UInput v-model="form.details.management_group" class="w-full" /></UFormField>
              <UFormField label="Connected networks" class="span-3">
                <UTextarea v-model="form.details.connected_networks" :rows="2" class="w-full" />
              </UFormField>
            </template>

            <template v-else-if="kind === 'azure_storage'">
              <UFormField label="Storage account"><UInput v-model="form.details.storage_account" class="w-full" /></UFormField>
              <UFormField label="Container name"><UInput v-model="form.details.container_name" class="w-full" /></UFormField>
              <UFormField label="Access tier">
                <USelect v-model="form.details.access_tier" :items="tierItems" class="w-full" />
              </UFormField>
              <UFormField label="Replication"><UInput v-model="form.details.replication" placeholder="LRS, ZRS, GRS" class="w-full" /></UFormField>
            </template>

            <template v-else>
              <UFormField label="Data center"><UInput v-model="form.details.datacenter" class="w-full" /></UFormField>
              <UFormField label="Cluster"><UInput v-model="form.details.cluster" class="w-full" /></UFormField>
              <UFormField label="Hostname"><UInput v-model="form.hostname" class="w-full" /></UFormField>
              <UFormField label="IP address"><UInput v-model="form.ip_address" class="w-full" /></UFormField>
              <UFormField label="Operating system"><UInput v-model="form.operating_system" class="w-full" /></UFormField>
              <UFormField label="CPU"><UInput v-model="form.details.cpu" placeholder="8 vCPU" class="w-full" /></UFormField>
              <UFormField label="Memory (GB)"><UInput v-model.number="form.details.memory_gb" type="number" class="w-full" /></UFormField>
              <UFormField label="Disk (GB)"><UInput v-model.number="form.details.disk_gb" type="number" class="w-full" /></UFormField>
            </template>

            <UFormField label="What it hosts" class="span-3">
              <UTextarea v-model="form.hosts" :rows="3" placeholder="Applications, sites, databases, or file shares this resource hosts" class="w-full" />
            </UFormField>
            <UFormField label="What it is running" class="span-3">
              <UTextarea v-model="form.running_details" :rows="3" placeholder="Services, agents, runtimes, and workloads currently running" class="w-full" />
            </UFormField>
            <UFormField label="Notes" class="span-3"><UTextarea v-model="form.notes" :rows="2" class="w-full" /></UFormField>
          </div>
          <div class="form-actions">
            <UButton color="neutral" variant="outline" :disabled="busy" @click="cancelForm">Cancel</UButton>
            <UButton color="primary" :loading="busy" @click="save">Save</UButton>
          </div>
        </UCard>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; }
.inner-tabs { margin-top: -0.25rem; }
.tab {
  border: 0; background: transparent; padding: 0.55rem 0.9rem; cursor: pointer;
  font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab.active { color: #0d7a3a; border-bottom-color: #0d7a3a; }
.tab-count {
  margin-left: 0.35rem; background: #f1f5f9; color: #475569; border-radius: 999px;
  padding: 0.05rem 0.4rem; font-size: 0.72rem;
}
.tab-panel { display: flex; flex-direction: column; gap: 1rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; }
.filter-grow { flex: 1; min-width: 14rem; }
.filter-sm { min-width: 10rem; }
.filter-btn { margin-bottom: 0.15rem; }
.form-panel { border-style: solid; }
.form-header h3 { margin: 0; font-size: 1rem; }
.form-intro { margin: 0.25rem 0 0; color: #64748b; font-size: 0.85rem; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.85rem; flex-wrap: wrap; }
.table-wrap { overflow-x: auto; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.65rem 0.85rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.data-table thead th {
  background: #f8fafc; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em;
  color: #64748b; font-weight: 700;
}
.data-table tbody tr:hover { background: #f8fafc; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8rem; }
.sub { color: #64748b; margin-top: 0.15rem; }
.wrap { max-width: 240px; word-wrap: break-word; }
.actions { white-space: nowrap; }
.actions-col { width: 1%; }
.badge {
  display: inline-block; padding: 0.15rem 0.5rem; border-radius: 6px;
  font-size: 0.72rem; font-weight: 700; background: #f1f5f9; color: #475569;
}
.badge-ok { background: #dcfce7; color: #166534; }
.badge-warn { background: #fef3c7; color: #92400e; }
.badge-muted { background: #e2e8f0; color: #475569; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; margin: 0; }
</style>
