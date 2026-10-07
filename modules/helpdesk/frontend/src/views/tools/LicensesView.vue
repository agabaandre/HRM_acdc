<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

interface LicenseExpiry {
  days_until_expiry: number | null
  expiry_status: string
  is_expiring_soon: boolean
  is_expired: boolean
}

interface ResponsiblePerson {
  staff_id: number
  name: string
  email: string
}

interface License {
  id: number
  name: string
  vendor?: string
  license_key?: string
  purchase_date?: string
  duration_months?: number
  expiry_date?: string
  warning_days_before?: number
  seats_total?: number
  seats_used?: number
  cost?: string | number
  notes?: string
  responsible_staff_id?: number | null
  responsible_person?: ResponsiblePerson | null
  expiry?: LicenseExpiry
}

interface Summary {
  license_count: number
  expiring_soon: number
  expired: number
  total_seats: number
  seats_used: number
  annual_cost: number
}

type TabId = 'list' | 'form'

type SelectStringItem = { label: string; value: string }
type SelectNumberItem = { label: string; value: number }

const summary = ref<Summary | null>(null)
const licenses = ref<License[]>([])
const loading = ref(false)
const busy = ref(false)
const exportBusy = ref(false)
const search = ref('')
const filterVendor = ref('')
const filterResponsibleId = ref(0)
const activeTab = ref<TabId>('list')
const editing = ref<License | null>(null)

const form = reactive({
  name: '',
  vendor: '',
  license_key: '',
  purchase_date: '',
  expiry_date: '',
  seats_total: 1,
  seats_used: 0,
  cost: 0,
  notes: '',
  warning_days_before: 30,
  responsible_staff_id: null as number | null,
})

const responsibleInitialLabel = computed(() => {
  const p = editing.value?.responsible_person
  if (!p) return null
  return p.email ? `${p.name} · ${p.email}` : p.name
})

const vendorFilterItems = computed((): SelectStringItem[] => {
  const set = new Set<string>()
  for (const l of licenses.value) {
    const v = (l.vendor || '').trim()
    if (v) set.add(v)
  }
  return [
    { label: 'All vendors', value: '' },
    ...[...set].sort((a, b) => a.localeCompare(b)).map((v) => ({ label: v, value: v })),
  ]
})

const responsibleFilterItems = computed((): SelectNumberItem[] => {
  const map = new Map<number, string>()
  for (const l of licenses.value) {
    const id = l.responsible_staff_id
    const name = l.responsible_person?.name?.trim()
    if (id && id > 0 && name) map.set(id, name)
  }
  return [
    { label: 'All responsible', value: 0 },
    { label: 'Unassigned', value: -1 },
    ...[...map.entries()]
      .sort((a, b) => a[1].localeCompare(b[1]))
      .map(([value, label]) => ({ label, value })),
  ]
})

const filteredLicenses = computed(() => {
  let rows = licenses.value
  const q = search.value.trim().toLowerCase()
  if (q) {
    rows = rows.filter((l) => {
      const hay = [
        l.name,
        l.vendor,
        l.license_key,
        l.responsible_person?.name,
        l.responsible_person?.email,
        l.responsible_staff_id != null ? String(l.responsible_staff_id) : '',
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      return hay.includes(q)
    })
  }
  const vendor = filterVendor.value.trim()
  if (vendor) {
    rows = rows.filter((l) => (l.vendor || '').trim() === vendor)
  }
  if (filterResponsibleId.value === -1) {
    rows = rows.filter((l) => !l.responsible_staff_id)
  } else if (filterResponsibleId.value > 0) {
    rows = rows.filter((l) => l.responsible_staff_id === filterResponsibleId.value)
  }
  return rows
})

function listParams(includeFacetFilters = false): Record<string, string | number> {
  const params: Record<string, string | number> = { per_page: 100 }
  if (search.value.trim()) params.q = search.value.trim()
  // Facet filters stay client-side on the list so dropdown options remain complete;
  // export applies them on the server.
  if (includeFacetFilters) {
    if (filterVendor.value.trim()) params.vendor = filterVendor.value.trim()
    if (filterResponsibleId.value !== 0) params.responsible_staff_id = filterResponsibleId.value
  }
  return params
}

const expiringList = computed(() =>
  licenses.value.filter((l) => l.expiry?.is_expiring_soon || l.expiry?.is_expired),
)

const activeCount = computed(() =>
  licenses.value.filter((l) => !l.expiry?.is_expired && l.expiry?.expiry_status !== 'no_expiry').length,
)

const formTitle = computed(() => (editing.value ? 'Edit license' : 'Add license'))

function parseYmd(value: string | undefined | null): Date | null {
  const ymd = String(value || '').slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return null
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d))
}

/** Calendar days between start and end (inclusive of span; end − start). */
function daysBetween(startYmd: string | undefined | null, endYmd: string | undefined | null): number | null {
  const start = parseYmd(startYmd)
  const end = parseYmd(endYmd)
  if (!start || !end) return null
  return Math.round((end.getTime() - start.getTime()) / 86_400_000)
}

/** Duration in days from user-entered start/end dates. */
const computedDurationDays = computed(() => daysBetween(form.purchase_date, form.expiry_date))

const computedDurationMonths = computed(() => {
  const days = computedDurationDays.value
  if (days == null || days < 0) return null
  return Math.max(1, Math.round(days / 30.4375))
})

function durationLabel(row: License): string {
  const days = daysBetween(row.purchase_date, row.expiry_date)
  if (days != null && days >= 0) {
    return `${days} day${days === 1 ? '' : 's'}`
  }
  return row.duration_months ? `${row.duration_months} mo` : '—'
}

function statusClass(l: License) {
  if (l.expiry?.is_expired) return 'status-expired'
  if (l.expiry?.is_expiring_soon) return 'status-warning'
  return 'status-active'
}

function statusLabel(l: License) {
  if (l.expiry?.is_expired) {
    const days = l.expiry.days_until_expiry ?? 0
    return `Expired ${Math.abs(days)}d ago`
  }
  if (l.expiry?.is_expiring_soon) {
    return `Expires in ${l.expiry.days_until_expiry ?? 0}d`
  }
  if (l.expiry?.days_until_expiry != null) {
    return `Active · ${l.expiry.days_until_expiry}d left`
  }
  return 'No expiry date'
}

const fmtMoney = (n: number | string | undefined) =>
  n != null ? '$' + (parseFloat(String(n)) || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'

async function load() {
  loading.value = true
  try {
    const [sumRes, listRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/licenses/summary'),
      api.get<{ data: License[] }>('/api/v1/tools/licenses', { params: listParams() }),
    ])
    summary.value = sumRes.data.data
    const paginated = listRes.data as { data?: License[] }
    licenses.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load licenses.'))
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  const hadSearch = !!search.value.trim()
  search.value = ''
  filterVendor.value = ''
  filterResponsibleId.value = 0
  if (hadSearch) void load()
}

function resetForm() {
  editing.value = null
  Object.assign(form, {
    name: '',
    vendor: '',
    license_key: '',
    purchase_date: '',
    expiry_date: '',
    seats_total: 1,
    seats_used: 0,
    cost: 0,
    notes: '',
    warning_days_before: 30,
    responsible_staff_id: null,
  })
}

function openCreate() {
  resetForm()
  activeTab.value = 'form'
}

function openEdit(row: License) {
  editing.value = row
  Object.assign(form, {
    name: row.name,
    vendor: row.vendor ?? '',
    license_key: row.license_key ?? '',
    purchase_date: row.purchase_date?.slice(0, 10) ?? '',
    expiry_date: row.expiry_date?.slice(0, 10) ?? '',
    seats_total: row.seats_total ?? 1,
    seats_used: row.seats_used ?? 0,
    cost: Number(row.cost) || 0,
    notes: row.notes ?? '',
    warning_days_before: row.warning_days_before ?? 30,
    responsible_staff_id: row.responsible_staff_id ?? null,
  })
  activeTab.value = 'form'
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

async function save() {
  if (form.purchase_date && form.expiry_date) {
    const days = daysBetween(form.purchase_date, form.expiry_date)
    if (days != null && days < 0) {
      notifyError('End date must be on or after the start date.')
      return
    }
  }
  busy.value = true
  try {
    const payload = {
      ...form,
      purchase_date: form.purchase_date || null,
      expiry_date: form.expiry_date || null,
      duration_months: computedDurationMonths.value,
      responsible_staff_id: form.responsible_staff_id || null,
    }
    if (editing.value) {
      await api.put(`/api/v1/tools/licenses/${editing.value.id}`, payload)
      notifySuccess('License updated.')
    } else {
      await api.post('/api/v1/tools/licenses', payload)
      notifySuccess('License created.')
    }
    resetForm()
    activeTab.value = 'list'
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function remove(row: License) {
  if (!window.confirm(`Delete license "${row.name}"?`)) return
  try {
    await api.delete(`/api/v1/tools/licenses/${row.id}`)
    notifySuccess('License deleted.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Delete failed.'))
  }
}

async function exportExcel() {
  exportBusy.value = true
  try {
    const res = await api.get('/api/v1/tools/licenses/export', {
      params: listParams(true),
      responseType: 'blob',
    })
    const contentType = String(res.headers['content-type'] || '')
    if (contentType.includes('application/json')) {
      const text = await (res.data as Blob).text()
      throw new Error(text || 'Export failed')
    }
    const blob = new Blob([res.data], {
      type: contentType || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `licenses-${new Date().toISOString().slice(0, 10)}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
    notifySuccess('Licenses exported.')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Export failed.'))
  } finally {
    exportBusy.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <CbpPageHeading title="Licenses" back-to="/" back-label="← Overview">
      <template #lede>
        Track renewals, seats, and responsible owners. Add or edit licenses on a separate form tab.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist" aria-label="Licenses sections">
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'list' }"
          :aria-selected="activeTab === 'list'"
          @click="activeTab = 'list'"
        >
          Licenses
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'form' }"
          :aria-selected="activeTab === 'form'"
          @click="openFormTab"
        >
          {{ editing ? 'Edit license' : 'Add license' }}
        </button>
      </div>

      <div v-show="activeTab === 'list'" class="tab-panel" role="tabpanel">
        <div v-if="summary" class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Total</div>
            <div class="kpi-value">{{ summary.license_count }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Active</div>
            <div class="kpi-value text-success">{{ activeCount }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Expiring soon</div>
            <div class="kpi-value text-warning">{{ summary.expiring_soon }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Expired</div>
            <div class="kpi-value text-danger">{{ summary.expired }}</div>
          </div>
        </div>

        <div v-if="expiringList.length" class="alert-card cbp-card">
          <div class="alert-title">Expiry attention</div>
          <ul class="warn-list">
            <li v-for="l in expiringList" :key="l.id" :class="statusClass(l)">
              <strong>{{ l.name }}</strong> — {{ statusLabel(l) }}
              <span v-if="l.expiry_date"> ({{ l.expiry_date.slice(0, 10) }})</span>
              <span v-if="l.responsible_person"> · {{ l.responsible_person.name }}</span>
            </li>
          </ul>
        </div>

        <div class="filters">
          <UFormField label="Vendor" class="filter-sm">
            <USelect v-model="filterVendor" :items="vendorFilterItems" class="w-full" />
          </UFormField>
          <UFormField label="Responsible person" class="filter-md">
            <USelect v-model="filterResponsibleId" :items="responsibleFilterItems" class="w-full" />
          </UFormField>
          <UFormField label="Search" class="filter-grow">
            <UInput
              v-model="search"
              type="search"
              icon="i-lucide-search"
              placeholder="Search name, vendor, owner…"
              autocomplete="off"
              class="w-full"
              @keyup.enter="load()"
            />
          </UFormField>
          <UButton color="neutral" variant="outline" class="filter-btn" @click="load()">Search</UButton>
          <UButton
            v-if="filterVendor || filterResponsibleId || search.trim()"
            color="neutral"
            variant="ghost"
            class="filter-btn"
            @click="clearFilters"
          >
            Clear
          </UButton>
          <UButton
            color="neutral"
            variant="outline"
            class="filter-btn"
            icon="mdi-file-excel"
            :loading="exportBusy"
            @click="exportExcel"
          >
            Export Excel
          </UButton>
          <UButton color="primary" class="filter-btn" @click="openCreate">+ Add license</UButton>
        </div>

        <div class="table-wrap cbp-card">
          <p v-if="loading" class="muted empty">Loading licenses…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th class="num-col">#</th>
                <th>Name</th>
                <th>Vendor</th>
                <th>Responsible</th>
                <th>Start</th>
                <th>Duration</th>
                <th>End</th>
                <th>Status</th>
                <th class="num">Cost</th>
                <th>Seats</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in filteredLicenses" :key="row.id">
                <td class="num-col">{{ index + 1 }}</td>
                <td><strong>{{ row.name }}</strong></td>
                <td>{{ row.vendor || '—' }}</td>
                <td>
                  <template v-if="row.responsible_person">
                    <div>{{ row.responsible_person.name }}</div>
                    <div class="sub">{{ row.responsible_person.email || '—' }}</div>
                  </template>
                  <template v-else>—</template>
                </td>
                <td>{{ row.purchase_date?.slice(0, 10) ?? '—' }}</td>
                <td>{{ durationLabel(row) }}</td>
                <td>{{ row.expiry_date?.slice(0, 10) ?? '—' }}</td>
                <td><span class="status-pill" :class="statusClass(row)">{{ statusLabel(row) }}</span></td>
                <td class="num">{{ fmtMoney(row.cost) }}</td>
                <td>{{ row.seats_used ?? 0 }}/{{ row.seats_total ?? 1 }}</td>
                <td class="actions">
                  <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                  <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !filteredLicenses.length" class="muted empty">No licenses match. Add one from the form tab.</p>
        </div>
      </div>

      <div v-show="activeTab === 'form'" class="tab-panel" role="tabpanel">
        <UCard class="form-panel">
          <template #header>
            <div class="form-header">
              <h3>{{ formTitle }}</h3>
              <p class="form-intro">Enter start and end dates; duration in days is calculated automatically. Set warning days for renewal alerts.</p>
            </div>
          </template>
          <div class="hd-form hd-form--grid">
            <UFormField label="License name" required class="span-3"><UInput v-model="form.name" class="w-full" /></UFormField>
            <UFormField label="Vendor"><UInput v-model="form.vendor" class="w-full" /></UFormField>
            <UFormField label="License key"><UInput v-model="form.license_key" class="w-full" /></UFormField>
            <UFormField label="Start date"><UDateInput v-model="form.purchase_date" class="w-full" /></UFormField>
            <UFormField label="End date"><UDateInput v-model="form.expiry_date" class="w-full" /></UFormField>
            <UFormField label="Duration (days)">
              <UInput
                :model-value="computedDurationDays == null ? '—' : String(computedDurationDays)"
                readonly
                class="w-full"
                :class="{ 'text-danger': computedDurationDays != null && computedDurationDays < 0 }"
              />
            </UFormField>
            <UFormField label="Warn days before expiry"><UInput v-model.number="form.warning_days_before" type="number" min="1" max="365" class="w-full" /></UFormField>
            <UFormField label="Seats total"><UInput v-model.number="form.seats_total" type="number" class="w-full" /></UFormField>
            <UFormField label="Seats used"><UInput v-model.number="form.seats_used" type="number" class="w-full" /></UFormField>
            <UFormField label="Cost"><UInput v-model.number="form.cost" type="number" class="w-full" /></UFormField>
            <UFormField label="Responsible person" class="span-3">
              <UStaffDirectoryPicker
                v-model="form.responsible_staff_id"
                :initial-label="responsibleInitialLabel"
              />
            </UFormField>
            <UFormField label="Notes" class="span-3"><UTextarea v-model="form.notes" :rows="3" class="w-full" /></UFormField>
          </div>
          <div class="form-actions">
            <UButton color="neutral" variant="outline" :disabled="busy" @click="cancelForm">Cancel</UButton>
            <UButton color="primary" :loading="busy" @click="save">Save license</UButton>
          </div>
        </UCard>
      </div>
    </section>
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
.text-danger { color: #dc2626; }
.alert-card { padding: 0.85rem 1rem; border-left: 4px solid #f59e0b; }
.alert-title {
  font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  color: #92400e; margin-bottom: 0.45rem;
}
.warn-list { margin: 0; padding-left: 1.15rem; }
.warn-list li { margin-bottom: 0.35rem; font-size: 0.88rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; }
.filter-grow { flex: 1; min-width: 12rem; }
.filter-sm { min-width: 10rem; max-width: 14rem; }
.filter-md { min-width: 12rem; max-width: 18rem; }
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
.data-table th.num-col, .data-table td.num-col { width: 3rem; text-align: right; color: #64748b; font-variant-numeric: tabular-nums; }
.status-pill { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; }
.status-active { background: #ecfdf5; color: #047857; }
.status-warning { background: #fffbeb; color: #b45309; }
.status-expired { background: #fef2f2; color: #b91c1c; }
.sub { font-size: 0.75rem; color: #64748b; }
.actions { white-space: nowrap; }
.actions-col { width: 1%; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; margin: 0; }
</style>
