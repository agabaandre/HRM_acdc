<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import DocumentLinkPreviewModal from '../../components/common/DocumentLinkPreviewModal.vue'
import type { StaffDirectoryRow } from '../../components/ui/UStaffDirectoryPicker.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

interface Language {
  id: number
  name: string
  slug: string
}

interface SystemModule {
  id: number
  information_system_id?: number
  name: string
  description?: string | null
  status: string
  sort_order?: number
}

interface InformationSystem {
  id: number
  name: string
  description?: string | null
  status: string
  status_label?: string
  host?: string | null
  host_name?: string | null
  ip?: string | null
  domain?: string | null
  os?: string | null
  version?: string | null
  last_update_on?: string | null
  division_id?: number | null
  division_label?: string
  focal_staff_id?: number | null
  focal_name_raw?: string | null
  mis_focal_staff_id?: number | null
  mis_focal_name_raw?: string | null
  system_profile_url?: string | null
  user_manual_users_url?: string | null
  user_manual_managers_url?: string | null
  user_manual_technical_url?: string | null
  faqs?: string | null
  sops?: string | null
  total_users?: number | null
  estimated_annual_hosting_cost?: string | number | null
  languages?: Language[]
  modules?: SystemModule[]
  modules_count?: number
}

interface Summary {
  systems_total: number
  systems_by_status: Record<string, number>
  modules_total: number
  modules_by_status: Record<string, number>
  missing_focal: number
  missing_mis_focal: number
  by_division: Record<string, number>
}

interface Division {
  id: number
  name: string
}

type TabId = 'list' | 'form'

const STATUS_ITEMS = [
  { label: 'To be Developed', value: 'to_be_developed' },
  { label: 'In development', value: 'in_development' },
  { label: 'Under Testing', value: 'under_testing' },
  { label: 'In Use', value: 'in_use' },
  { label: 'Decommissioned', value: 'decommissioned' },
]

const filterStatusItems = [{ label: 'All statuses', value: '' }, ...STATUS_ITEMS]

const NO_DIVISION_VALUE = -1
const ANY_DIVISION_VALUE = 0

const summary = ref<Summary | null>(null)
const systems = ref<InformationSystem[]>([])
const divisions = ref<Division[]>([])
const languages = ref<Language[]>([])
const loading = ref(false)
const busy = ref(false)
const moduleBusy = ref(false)
const search = ref('')
const filterStatus = ref('')
const filterDivision = ref<number>(ANY_DIVISION_VALUE)
const activeTab = ref<TabId>('list')
const editing = ref<InformationSystem | null>(null)

const form = reactive({
  name: '',
  description: '',
  status: 'to_be_developed',
  version: '1.0',
  division_id: 0 as number,
  language_ids: [] as number[],
  last_update_on: '',
  host: '',
  host_name: '',
  ip: '',
  domain: '',
  os: '',
  total_users: 0,
  estimated_annual_hosting_cost: 0,
  focal_staff_id: null as number | null,
  focal_name_raw: '',
  mis_focal_staff_id: null as number | null,
  mis_focal_name_raw: '',
  system_profile_url: '',
  user_manual_users_url: '',
  user_manual_managers_url: '',
  user_manual_technical_url: '',
  faqs: '',
  sops: '',
})

const newModule = reactive({
  name: '',
  description: '',
  status: 'to_be_developed',
})

const preview = reactive({
  open: false,
  url: '' as string | null,
  title: 'Document preview',
})

const divisionFormItems = computed(() => [
  { label: 'All (no division)', value: 0 },
  ...divisions.value.map((d) => ({ label: d.name, value: d.id })),
])

const divisionFilterItems = computed(() => [
  { label: 'All divisions', value: ANY_DIVISION_VALUE },
  { label: '— Unassigned (All) —', value: NO_DIVISION_VALUE },
  ...divisions.value.map((d) => ({ label: d.name, value: d.id })),
])

const languageItems = computed(() => languages.value.map((l) => ({ label: l.name, value: l.id })))

const formTitle = computed(() => (editing.value ? 'Edit information system' : 'Add information system'))

const focalInitialLabel = computed(() => editing.value?.focal_name_raw ?? null)
const misFocalInitialLabel = computed(() => editing.value?.mis_focal_name_raw ?? null)

type DocFieldKey =
  | 'system_profile_url'
  | 'user_manual_users_url'
  | 'user_manual_managers_url'
  | 'user_manual_technical_url'

const docFields: { key: DocFieldKey; label: string }[] = [
  { key: 'system_profile_url', label: 'System Profile' },
  { key: 'user_manual_users_url', label: 'User Manual — Users' },
  { key: 'user_manual_managers_url', label: 'User Manual — Managers' },
  { key: 'user_manual_technical_url', label: 'User Manual — Technical' },
]

function statusLabel(status: string) {
  return STATUS_ITEMS.find((s) => s.value === status)?.label ?? status
}

function statusBadgeClass(status: string) {
  if (status === 'in_use') return 'badge badge-ok'
  if (status === 'under_testing') return 'badge badge-warn'
  if (status === 'in_development') return 'badge badge-info'
  if (status === 'decommissioned') return 'badge badge-danger'
  return 'badge badge-muted'
}

function focalLabel(row: InformationSystem) {
  if (row.focal_name_raw) return row.focal_name_raw
  if (row.focal_staff_id) return `Staff #${row.focal_staff_id}`
  return '—'
}

function misFocalLabel(row: InformationSystem) {
  if (row.mis_focal_name_raw) return row.mis_focal_name_raw
  if (row.mis_focal_staff_id) return `Staff #${row.mis_focal_staff_id}`
  return '—'
}

function openPreview(url: string | null | undefined, title: string) {
  if (!url) return
  preview.url = url
  preview.title = title
  preview.open = true
}

async function loadMeta() {
  const [divRes, langRes] = await Promise.all([
    api.get<{ data: { divisions?: Division[] } }>('/api/v1/reference-data'),
    api.get<{ data: Language[] }>('/api/v1/tools/information-systems/languages'),
  ])
  divisions.value = divRes.data.data?.divisions ?? []
  languages.value = langRes.data.data ?? []
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { per_page: 100 }
    if (search.value.trim()) params.q = search.value.trim()
    if (filterStatus.value) params.status = filterStatus.value
    if (filterDivision.value === NO_DIVISION_VALUE) {
      params.division_id = 'all'
    } else if (filterDivision.value !== ANY_DIVISION_VALUE) {
      params.division_id = filterDivision.value
    }
    const [sumRes, listRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/information-systems/summary'),
      api.get<{ data: InformationSystem[] }>('/api/v1/tools/information-systems', { params }),
    ])
    summary.value = sumRes.data.data
    const paginated = listRes.data as { data?: InformationSystem[] }
    systems.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load information systems.'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editing.value = null
  Object.assign(form, {
    name: '',
    description: '',
    status: 'to_be_developed',
    version: '1.0',
    division_id: 0,
    language_ids: [],
    last_update_on: '',
    host: '',
    host_name: '',
    ip: '',
    domain: '',
    os: '',
    total_users: 0,
    estimated_annual_hosting_cost: 0,
    focal_staff_id: null,
    focal_name_raw: '',
    mis_focal_staff_id: null,
    mis_focal_name_raw: '',
    system_profile_url: '',
    user_manual_users_url: '',
    user_manual_managers_url: '',
    user_manual_technical_url: '',
    faqs: '',
    sops: '',
  })
}

function openCreate() {
  resetForm()
  activeTab.value = 'form'
}

async function openEdit(row: InformationSystem) {
  activeTab.value = 'form'
  editing.value = row
  Object.assign(form, {
    name: row.name,
    description: row.description ?? '',
    status: row.status,
    version: row.version ?? '1.0',
    division_id: row.division_id ?? 0,
    language_ids: (row.languages ?? []).map((l) => l.id),
    last_update_on: row.last_update_on?.slice(0, 10) ?? '',
    host: row.host ?? '',
    host_name: row.host_name ?? '',
    ip: row.ip ?? '',
    domain: row.domain ?? '',
    os: row.os ?? '',
    total_users: row.total_users ?? 0,
    estimated_annual_hosting_cost: Number(row.estimated_annual_hosting_cost) || 0,
    focal_staff_id: row.focal_staff_id ?? null,
    focal_name_raw: row.focal_name_raw ?? '',
    mis_focal_staff_id: row.mis_focal_staff_id ?? null,
    mis_focal_name_raw: row.mis_focal_name_raw ?? '',
    system_profile_url: row.system_profile_url ?? '',
    user_manual_users_url: row.user_manual_users_url ?? '',
    user_manual_managers_url: row.user_manual_managers_url ?? '',
    user_manual_technical_url: row.user_manual_technical_url ?? '',
    faqs: row.faqs ?? '',
    sops: row.sops ?? '',
  })
  try {
    const { data } = await api.get<{ data: InformationSystem }>(`/api/v1/tools/information-systems/${row.id}`)
    editing.value = data.data
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load system detail.'))
  }
}

function openFormTab() {
  if (activeTab.value === 'form') return
  if (editing.value) {
    activeTab.value = 'form'
    return
  }
  openCreate()
}

function cancelForm() {
  resetForm()
  activeTab.value = 'list'
}

function onFocalSelected(row: StaffDirectoryRow | null) {
  if (row) form.focal_name_raw = row.name
}

function onMisFocalSelected(row: StaffDirectoryRow | null) {
  if (row) form.mis_focal_name_raw = row.name
}

async function save() {
  busy.value = true
  try {
    const payload = {
      ...form,
      division_id: form.division_id || null,
      last_update_on: form.last_update_on || null,
      focal_staff_id: form.focal_staff_id || null,
      mis_focal_staff_id: form.mis_focal_staff_id || null,
      total_users: form.total_users || null,
      estimated_annual_hosting_cost: form.estimated_annual_hosting_cost || null,
    }
    if (editing.value) {
      const { data } = await api.put<{ data: InformationSystem }>(
        `/api/v1/tools/information-systems/${editing.value.id}`,
        payload,
      )
      editing.value = data.data
      notifySuccess('Information system updated.')
      await load()
    } else {
      const { data } = await api.post<{ data: InformationSystem }>('/api/v1/tools/information-systems', payload)
      editing.value = data.data
      notifySuccess('Information system created. You can now add modules below.')
      await load()
    }
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function remove(row: InformationSystem) {
  if (!window.confirm(`Delete information system "${row.name}"?`)) return
  try {
    await api.delete(`/api/v1/tools/information-systems/${row.id}`)
    notifySuccess('Information system deleted.')
    if (editing.value?.id === row.id) cancelForm()
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Delete failed.'))
  }
}

async function addModule() {
  if (!editing.value) return
  if (!newModule.name.trim()) {
    notifyError('Module name is required.')
    return
  }
  moduleBusy.value = true
  try {
    const { data } = await api.post<{ data: SystemModule }>(
      `/api/v1/tools/information-systems/${editing.value.id}/modules`,
      newModule,
    )
    editing.value.modules = [...(editing.value.modules ?? []), data.data]
    Object.assign(newModule, { name: '', description: '', status: 'to_be_developed' })
    notifySuccess('Module added.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to add module.'))
  } finally {
    moduleBusy.value = false
  }
}

async function updateModuleStatus(mod: SystemModule, status: string) {
  if (!editing.value) return
  try {
    await api.put(`/api/v1/tools/information-systems/${editing.value.id}/modules/${mod.id}`, { status })
    mod.status = status
    notifySuccess('Module updated.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to update module.'))
  }
}

async function removeModule(mod: SystemModule) {
  if (!editing.value) return
  if (!window.confirm(`Remove module "${mod.name}"?`)) return
  try {
    await api.delete(`/api/v1/tools/information-systems/${editing.value.id}/modules/${mod.id}`)
    editing.value.modules = (editing.value.modules ?? []).filter((m) => m.id !== mod.id)
    notifySuccess('Module removed.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to remove module.'))
  }
}

onMounted(async () => {
  try {
    await loadMeta()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load divisions/languages.'))
  }
  await load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Information Systems" back-to="/" back-label="← Overview">
      <template #lede>
        Manage the Africa CDC information systems inventory: lifecycle status, modules, focal owners, and
        documentation links.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist" aria-label="Information systems sections">
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'list' }"
          :aria-selected="activeTab === 'list'"
          @click="activeTab = 'list'"
        >
          Systems
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'form' }"
          :aria-selected="activeTab === 'form'"
          @click="openFormTab"
        >
          {{ editing ? 'Edit system' : 'Add system' }}
        </button>
      </div>

      <div v-show="activeTab === 'list'" class="tab-panel" role="tabpanel">
        <div v-if="summary" class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Systems</div>
            <div class="kpi-value">{{ summary.systems_total }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">In use</div>
            <div class="kpi-value text-success">{{ summary.systems_by_status.in_use ?? 0 }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Modules</div>
            <div class="kpi-value">{{ summary.modules_total }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Missing focal</div>
            <div class="kpi-value text-warning">{{ summary.missing_focal }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Missing MIS focal</div>
            <div class="kpi-value text-warning">{{ summary.missing_mis_focal }}</div>
          </div>
        </div>

        <div v-if="summary?.by_division && Object.keys(summary.by_division).length" class="cat-strip cbp-card">
          <span class="cat-strip-label">By division</span>
          <div class="cat-chips">
            <span v-for="(count, name) in summary.by_division" :key="name" class="cat-chip">
              {{ name }}
              <strong>{{ count }}</strong>
            </span>
          </div>
        </div>

        <div class="filters">
          <UFormField label="Status" class="filter-sm">
            <USelect v-model="filterStatus" :items="filterStatusItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Division" class="filter-sm">
            <USelect
              v-model="filterDivision"
              :items="divisionFilterItems"
              class="w-full"
              @update:model-value="load"
            />
          </UFormField>
          <UFormField label="Search" class="filter-grow">
            <UInput
              v-model="search"
              type="search"
              icon="i-lucide-search"
              placeholder="Search name, host, domain…"
              autocomplete="off"
              class="w-full"
              @keyup.enter="load()"
            />
          </UFormField>
          <UButton color="neutral" variant="outline" class="filter-btn" @click="load()">Search</UButton>
          <UButton color="primary" class="filter-btn" @click="openCreate">+ Add system</UButton>
        </div>

        <div class="table-wrap cbp-card">
          <p v-if="loading" class="muted empty">Loading information systems…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th class="num">#</th>
                <th>Name</th>
                <th>Status</th>
                <th>Version</th>
                <th>Division</th>
                <th>Languages</th>
                <th>Focal</th>
                <th>MIS Focal</th>
                <th class="num">Modules</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in systems" :key="row.id">
                <td class="num">{{ idx + 1 }}</td>
                <td><strong>{{ row.name }}</strong></td>
                <td><span :class="statusBadgeClass(row.status)">{{ row.status_label ?? statusLabel(row.status) }}</span></td>
                <td>{{ row.version || '—' }}</td>
                <td>{{ row.division_label ?? (row.division_id ? `#${row.division_id}` : 'All') }}</td>
                <td>
                  <div class="lang-chips">
                    <span v-for="l in row.languages ?? []" :key="l.id" class="lang-chip">{{ l.name }}</span>
                    <span v-if="!row.languages?.length" class="sub">—</span>
                  </div>
                </td>
                <td>{{ focalLabel(row) }}</td>
                <td>{{ misFocalLabel(row) }}</td>
                <td class="num">{{ row.modules_count ?? 0 }}</td>
                <td class="actions">
                  <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                  <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !systems.length" class="muted empty">
            No information systems match. Add one from the form tab.
          </p>
        </div>
      </div>

      <div v-show="activeTab === 'form'" class="tab-panel" role="tabpanel">
        <UCard class="form-panel">
          <template #header>
            <div class="form-header">
              <h3>{{ formTitle }}</h3>
              <p class="form-intro">
                Division "All (no division)" is the default when no specific division owns the system. Profile
                and manual fields are links only — use Preview to view them inline.
              </p>
            </div>
          </template>
          <div class="hd-form hd-form--grid">
            <UFormField label="System name" required class="span-3"><UInput v-model="form.name" class="w-full" /></UFormField>
            <UFormField label="Status" required>
              <USelect v-model="form.status" :items="STATUS_ITEMS" class="w-full" />
            </UFormField>
            <UFormField label="Version"><UInput v-model="form.version" placeholder="1.0" class="w-full" /></UFormField>
            <UFormField label="Division">
              <USelect v-model="form.division_id" :items="divisionFormItems" class="w-full" />
            </UFormField>
            <UFormField label="Languages / DB" class="span-2">
              <USelectMenu v-model="form.language_ids" multiple :items="languageItems" class="w-full" />
            </UFormField>
            <UFormField label="Last update on"><UDateInput v-model="form.last_update_on" class="w-full" /></UFormField>
            <UFormField label="Description" class="span-3"><UTextarea v-model="form.description" :rows="2" class="w-full" /></UFormField>

            <UFormField label="Host"><UInput v-model="form.host" class="w-full" /></UFormField>
            <UFormField label="Host name"><UInput v-model="form.host_name" class="w-full" /></UFormField>
            <UFormField label="IP"><UInput v-model="form.ip" class="w-full" /></UFormField>
            <UFormField label="Domain"><UInput v-model="form.domain" class="w-full" /></UFormField>
            <UFormField label="OS"><UInput v-model="form.os" class="w-full" /></UFormField>
            <UFormField label="Total users"><UInput v-model.number="form.total_users" type="number" min="0" class="w-full" /></UFormField>
            <UFormField label="Est. annual hosting cost" class="span-2">
              <UInput v-model.number="form.estimated_annual_hosting_cost" type="number" min="0" step="0.01" class="w-full" />
            </UFormField>

            <UFormField label="Focal person">
              <UStaffDirectoryPicker
                v-model="form.focal_staff_id"
                :initial-label="focalInitialLabel"
                @selected="onFocalSelected"
              />
            </UFormField>
            <UFormField label="Focal person (name, if unmatched)">
              <UInput v-model="form.focal_name_raw" class="w-full" />
            </UFormField>
            <UFormField label="MIS focal person">
              <UStaffDirectoryPicker
                v-model="form.mis_focal_staff_id"
                :initial-label="misFocalInitialLabel"
                @selected="onMisFocalSelected"
              />
            </UFormField>
            <UFormField label="MIS focal person (name, if unmatched)">
              <UInput v-model="form.mis_focal_name_raw" class="w-full" />
            </UFormField>

            <UFormField
              v-for="doc in docFields"
              :key="doc.key"
              :label="doc.label"
              class="span-2 doc-field"
            >
              <div class="doc-field-row">
                <UInput v-model="form[doc.key]" placeholder="https://…" class="w-full" />
                <UButton
                  size="xs"
                  variant="outline"
                  color="neutral"
                  :disabled="!form[doc.key]"
                  @click="openPreview(form[doc.key], doc.label)"
                >Preview</UButton>
              </div>
            </UFormField>

            <UFormField label="FAQs" class="span-3"><UTextarea v-model="form.faqs" :rows="2" class="w-full" /></UFormField>
            <UFormField label="SOPs" class="span-3"><UTextarea v-model="form.sops" :rows="2" class="w-full" /></UFormField>
          </div>
          <div class="form-actions">
            <UButton color="neutral" variant="outline" :disabled="busy" @click="cancelForm">Cancel</UButton>
            <UButton color="primary" :loading="busy" @click="save">Save system</UButton>
          </div>
        </UCard>

        <UCard v-if="editing" class="form-panel modules-panel">
          <template #header>
            <h3>Modules</h3>
            <p class="form-intro">Functional modules within this system, each with an independent lifecycle status.</p>
          </template>
          <div class="module-list">
            <div v-for="mod in editing.modules ?? []" :key="mod.id" class="module-row">
              <div class="module-main">
                <strong>{{ mod.name }}</strong>
                <p v-if="mod.description" class="sub">{{ mod.description }}</p>
              </div>
              <USelect
                :model-value="mod.status"
                :items="STATUS_ITEMS"
                class="module-status"
                @update:model-value="(v) => updateModuleStatus(mod, String(v))"
              />
              <UButton size="xs" color="error" variant="link" @click="removeModule(mod)">Remove</UButton>
            </div>
            <p v-if="!editing.modules?.length" class="muted empty-small">No modules yet.</p>
          </div>
          <div class="module-add hd-form hd-form--grid">
            <UFormField label="Module name"><UInput v-model="newModule.name" class="w-full" /></UFormField>
            <UFormField label="Status">
              <USelect v-model="newModule.status" :items="STATUS_ITEMS" class="w-full" />
            </UFormField>
            <UFormField label="Description" class="span-3"><UInput v-model="newModule.description" class="w-full" /></UFormField>
            <div class="module-add-action">
              <UButton color="primary" size="sm" :loading="moduleBusy" @click="addModule">+ Add module</UButton>
            </div>
          </div>
        </UCard>
        <p v-else class="muted hint-panel">Save the system first, then add modules here.</p>
      </div>
    </section>

    <DocumentLinkPreviewModal v-model:open="preview.open" :url="preview.url" :title="preview.title" />
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.tabs { display: flex; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; }
.tab {
  border: 0; background: transparent; padding: 0.55rem 0.9rem; cursor: pointer;
  font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab.active { color: #0d7a3a; border-bottom-color: #0d7a3a; }
.tab-panel { display: flex; flex-direction: column; gap: 1rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; }
.kpi-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  padding: 0.9rem 1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.kpi-label { font-size: 0.72rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
.kpi-value { margin-top: 0.25rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; }
.text-success { color: #047857; }
.text-warning { color: #d97706; }
.cat-strip { padding: 0.85rem 1rem; }
.cat-strip-label {
  display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; color: #64748b; margin-bottom: 0.5rem;
}
.cat-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cat-chip {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: #f0fdf4; color: #166534; padding: 0.35rem 0.7rem; border-radius: 8px;
  font-size: 0.8rem; font-weight: 600; border: 1px solid #bbf7d0;
}
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
.data-table th.num, .data-table td.num { text-align: right; }
.lang-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.lang-chip {
  display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
  background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; font-weight: 600;
}
.sub { font-size: 0.75rem; color: #64748b; }
.actions { white-space: nowrap; }
.actions-col { width: 1%; }
.badge {
  display: inline-block; padding: 0.15rem 0.5rem; border-radius: 6px;
  font-size: 0.72rem; font-weight: 700; background: #f1f5f9; color: #475569;
}
.badge-ok { background: #dcfce7; color: #166534; }
.badge-warn { background: #fef3c7; color: #92400e; }
.badge-info { background: #dbeafe; color: #1d4ed8; }
.badge-danger { background: #fee2e2; color: #b91c1c; }
.badge-muted { background: #e2e8f0; color: #475569; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; margin: 0; }
.empty-small { padding: 0.5rem 0; margin: 0; font-size: 0.85rem; }
.doc-field-row { display: flex; gap: 0.5rem; align-items: center; }
.doc-field-row :deep(.w-full) { flex: 1; }
.modules-panel { margin-top: 1rem; }
.module-list { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
.module-row {
  display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem;
  border: 1px solid #e2e8f0; border-radius: 8px;
}
.module-main { flex: 1; min-width: 0; }
.module-status { width: 12rem; flex-shrink: 0; }
.module-add { border-top: 1px dashed #e2e8f0; padding-top: 0.85rem; margin-top: 0.25rem; }
.module-add-action { display: flex; align-items: flex-end; }
.hint-panel { padding: 1.5rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 10px; }
</style>
