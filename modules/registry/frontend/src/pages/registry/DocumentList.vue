<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError, notifySuccess } from '@/lib/notify'

export interface RegistryDocument {
  id: number
  ref_no: string
  division_id: number
  document_type_id: number
  status_id: number
  addressed_to: string
  document_date: string | null
  date_recorded: string | null
  description: string
  signed_by: string
  recorded_by: string
  comments: string | null
  division: string | null
  division_abbrev: string | null
  document_type: string | null
  status: string | null
}

interface Option { id: number; label: string }

const columns = [
  { key: 'ref_no', label: 'Reference' },
  { key: 'document_date', label: 'Document date' },
  { key: 'date_recorded', label: 'Recorded' },
  { key: 'document_type', label: 'Type' },
  { key: 'division', label: 'Division' },
  { key: 'addressed_to', label: 'Addressed to' },
  { key: 'description', label: 'Description' },
  { key: 'signed_by', label: 'Signed by' },
  { key: 'status', label: 'Status' },
  { key: 'recorded_by', label: 'Recorded by' },
] as const

const props = defineProps<{
  scope: 'week' | 'year' | 'archive'
  year?: number
}>()

const rows = ref<RegistryDocument[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(50)
const pageSize = ref<'10' | '20' | '30' | '50' | '100' | 'all'>('50')
const search = ref('')
const divisionId = ref(0)
const typeId = ref(0)
const statusId = ref(0)
const sort = ref('date_recorded')
const dir = ref<'asc' | 'desc'>('desc')
const loading = ref(false)
const exporting = ref('')
const weekStart = ref('')
const weekEnd = ref('')

const emit = defineEmits<{
  loaded: [meta: { total: number; week_start: string; week_end: string; year: number }]
}>()

const editing = ref<RegistryDocument | null>(null)
const busy = ref(false)
const divisions = ref<Option[]>([])
const types = ref<Option[]>([])
const statuses = ref<Option[]>([])
const form = reactive({
  division_id: 0,
  document_type_id: 0,
  status_id: 0,
  addressed_to: '',
  document_date: '',
  description: '',
  signed_by: '',
  comments: '',
})

const fromRow = computed(() => (total.value === 0 ? 0 : (page.value - 1) * perPage.value + 1))
const toRow = computed(() => (total.value === 0 ? 0 : Math.min((page.value - 1) * perPage.value + rows.value.length, total.value)))
const pageCount = computed(() => (pageSize.value === 'all' || perPage.value <= 0 ? 1 : Math.max(1, Math.ceil(total.value / perPage.value))))

function fmt(value: string | null) {
  if (!value) return '—'
  return value.slice(0, 10)
}

function listParams() {
  return {
    scope: props.scope,
    year: props.year,
    q: search.value.trim() || undefined,
    division_id: divisionId.value || undefined,
    document_type_id: typeId.value || undefined,
    status_id: statusId.value || undefined,
    sort: sort.value,
    dir: dir.value,
    per_page: pageSize.value,
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: RegistryDocument[]; meta: { total: number; page: number; per_page: number; week_start: string; week_end: string; year: number } }>(
      '/api/v1/registry/documents',
      {
        params: { ...listParams(), page: page.value },
        timeout: pageSize.value === 'all' ? 120000 : 30000,
      },
    )
    rows.value = data.data
    total.value = data.meta.total
    page.value = data.meta.page
    perPage.value = data.meta.per_page || data.data.length || 50
    weekStart.value = data.meta.week_start
    weekEnd.value = data.meta.week_end
    emit('loaded', data.meta)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load documents.'))
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  void load()
}

function toggleSort(key: string) {
  if (sort.value === key) {
    dir.value = dir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = key
    dir.value = key === 'date_recorded' || key === 'document_date' ? 'desc' : 'asc'
  }
  page.value = 1
  void load()
}

async function download(format: 'csv' | 'excel' | 'pdf') {
  exporting.value = format
  try {
    const res = await api.get('/api/v1/registry/documents/export', {
      params: { ...listParams(), format },
      responseType: 'blob',
      timeout: 120000,
    })
    const ext = format === 'excel' ? 'xls' : format
    const url = URL.createObjectURL(res.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `registry-${props.scope}.${ext}`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not export the documents.'))
  } finally {
    exporting.value = ''
  }
}

async function ensureLookups() {
  if (divisions.value.length) return
  const { data } = await api.get<{
    data: {
      divisions: Array<{ division_id: number; division: string; abbrev: string }>
      document_types: Array<{ document_type_id: number; document_type: string }>
      statuses: Array<{ status_id: number; status: string }>
    }
  }>('/api/v1/registry/settings')
  divisions.value = data.data.divisions.map((row) => ({
    id: row.division_id,
    label: row.abbrev ? `${row.abbrev} — ${row.division}` : row.division,
  }))
  types.value = data.data.document_types.map((row) => ({ id: row.document_type_id, label: row.document_type }))
  statuses.value = data.data.statuses.map((row) => ({ id: row.status_id, label: row.status }))
}

async function openEdit(row: RegistryDocument) {
  try {
    await ensureLookups()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not open the document.'))
    return
  }
  editing.value = row
  form.division_id = row.division_id
  form.document_type_id = row.document_type_id
  form.status_id = row.status_id
  form.addressed_to = row.addressed_to
  form.document_date = (row.document_date || '').slice(0, 10)
  form.description = row.description
  form.signed_by = row.signed_by
  form.comments = row.comments || ''
}

async function saveEdit() {
  if (!editing.value) return
  busy.value = true
  try {
    const { data } = await api.put<{ data: { ref_no: string } }>(`/api/v1/registry/documents/${editing.value.id}`, { ...form })
    notifySuccess(`Document ${data.data.ref_no} updated.`)
    editing.value = null
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not update the document.'))
  } finally {
    busy.value = false
  }
}

watch(() => props.year, () => {
  page.value = 1
  void load()
})

onMounted(() => {
  void ensureLookups().catch(() => {})
  void load()
})

defineExpose({ weekStart, weekEnd, total, reload: load })
</script>

<template>
  <div class="tools-page">
    <div class="filters">
      <label>
        <span class="kpi-label">Division</span>
        <select v-model.number="divisionId" @change="applyFilters">
          <option :value="0">All divisions</option>
          <option v-for="row in divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
        </select>
      </label>
      <label>
        <span class="kpi-label">Document type</span>
        <select v-model.number="typeId" @change="applyFilters">
          <option :value="0">All types</option>
          <option v-for="row in types" :key="row.id" :value="row.id">{{ row.label }}</option>
        </select>
      </label>
      <label>
        <span class="kpi-label">Status</span>
        <select v-model.number="statusId" @change="applyFilters">
          <option :value="0">All statuses</option>
          <option v-for="row in statuses" :key="row.id" :value="row.id">{{ row.label }}</option>
        </select>
      </label>
      <label class="filter-grow">
        <span class="kpi-label">Search</span>
        <input v-model="search" type="search" placeholder="Reference, description, addressee, signatory" @keyup.enter="applyFilters" />
      </label>
      <button type="button" class="filter-btn" @click="applyFilters">Search</button>
    </div>

    <div class="toolbar-row">
      <p class="counter">Showing {{ fromRow.toLocaleString() }}–{{ toRow.toLocaleString() }} of {{ total.toLocaleString() }}</p>
      <div class="exports">
        <label class="show">
          <span class="kpi-label">Show</span>
          <select v-model="pageSize" @change="applyFilters">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="all">All</option>
          </select>
        </label>
        <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="download('excel')">{{ exporting === 'excel' ? 'Excel…' : 'Excel' }}</button>
        <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="download('csv')">{{ exporting === 'csv' ? 'CSV…' : 'CSV' }}</button>
        <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="download('pdf')">{{ exporting === 'pdf' ? 'PDF…' : 'PDF' }}</button>
      </div>
    </div>

    <form v-if="editing" class="doc-form" @submit.prevent="saveEdit">
      <h2>Edit {{ editing.ref_no }}</h2>
      <p class="hint">The reference number stays the same. Changing the division or type only updates the letters in that reference.</p>
      <div class="grid">
        <label>
          <span>Division <em class="req">*</em></span>
          <select v-model.number="form.division_id" required>
            <option v-for="row in divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Document type <em class="req">*</em></span>
          <select v-model.number="form.document_type_id" required>
            <option v-for="row in types" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Status <em class="req">*</em></span>
          <select v-model.number="form.status_id" required>
            <option v-for="row in statuses" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Document date <em class="req">*</em></span>
          <input v-model="form.document_date" type="date" required />
        </label>
        <label>
          <span>Addressed to <em class="req">*</em></span>
          <input v-model="form.addressed_to" required maxlength="100" />
        </label>
        <label>
          <span>Signed by <em class="req">*</em></span>
          <input v-model="form.signed_by" required maxlength="100" />
        </label>
        <label class="wide">
          <span>Description <em class="req">*</em></span>
          <textarea v-model="form.description" required rows="3" />
        </label>
        <label class="wide">
          <span>Comments</span>
          <textarea v-model="form.comments" rows="2" />
        </label>
      </div>
      <div class="actions">
        <button type="button" @click="editing = null">Cancel</button>
        <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save changes' }}</button>
      </div>
    </form>

    <p v-if="loading" class="muted">Loading documents…</p>
    <div v-else class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th class="num">No.</th>
            <th v-for="column in columns" :key="column.key">
              <button type="button" class="sort-btn" @click="toggleSort(column.key)">
                {{ column.label }}
                <span class="arrows" aria-hidden="true">
                  <i :class="{ on: sort === column.key && dir === 'asc' }">▲</i>
                  <i :class="{ on: sort === column.key && dir === 'desc' }">▼</i>
                </span>
              </button>
            </th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.id">
            <td class="num">{{ (fromRow + index).toLocaleString() }}</td>
            <td>{{ row.ref_no }}</td>
            <td>{{ fmt(row.document_date) }}</td>
            <td>{{ fmt(row.date_recorded) }}</td>
            <td>{{ row.document_type || '—' }}</td>
            <td :title="row.division || ''">{{ row.division_abbrev || row.division || '—' }}</td>
            <td>{{ row.addressed_to || '—' }}</td>
            <td class="desc">{{ row.description }}</td>
            <td>{{ row.signed_by || '—' }}</td>
            <td><span class="status-pill status-active">{{ row.status || '—' }}</span></td>
            <td>{{ row.recorded_by || '—' }}</td>
            <td><button type="button" class="edit-btn" @click="openEdit(row)">Edit</button></td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="12" class="empty">No documents match this view.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="total > 0" class="pager">
      <button type="button" :disabled="page <= 1" @click="page -= 1; load()">Previous</button>
      <span>Page {{ page }} of {{ pageCount }}</span>
      <button type="button" :disabled="page >= pageCount" @click="page += 1; load()">Next</button>
    </div>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.filters, .exports { display: flex; gap: 0.65rem; align-items: flex-end; flex-wrap: wrap; }
.filters label, .show { display: flex; flex-direction: column; gap: 0.3rem; min-width: 10rem; }
.filter-grow { flex: 1; min-width: 16rem; }
.filters select, .filters input, .show select, .filter-btn, .pager button {
  border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.75rem; background: #fff; font: inherit;
}
.filter-btn, .pager button { cursor: pointer; font-weight: 600; color: #0d7a3a; }
.filter-btn:hover, .pager button:hover { background: #f0fdf4; }
.filter-btn:disabled { opacity: 0.55; cursor: default; }
.kpi-label { font-size: 0.72rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
.toolbar-row { display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
.counter { margin: 0; font-weight: 700; color: #0f172a; }
.table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.data-table thead th { background: #f8fafc; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; color: #64748b; }
.data-table tbody tr:hover { background: #f8fafc; }
.num { width: 3.2rem; color: #64748b; font-variant-numeric: tabular-nums; }
.sort-btn {
  display: inline-flex; align-items: center; gap: 0.35rem; border: 0; background: transparent; padding: 0;
  font: inherit; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
  color: #64748b; cursor: pointer; white-space: nowrap;
}
.arrows { display: inline-flex; flex-direction: column; font-style: normal; font-size: 0.55rem; line-height: 0.75; color: #cbd5e1; }
.arrows .on { color: #0d7a3a; }
.desc { max-width: 22rem; }
.status-pill { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.status-active { background: #ecfdf5; color: #047857; }
.muted, .empty { color: #64748b; }
.empty { text-align: center; padding: 2rem; }
.pager { display: flex; gap: 0.75rem; align-items: center; justify-content: flex-end; }
.pager button:disabled { opacity: 0.45; cursor: default; }
.edit-btn {
  border: 1px solid #0d7a3a; border-radius: 8px; background: #fff; color: #0d7a3a;
  padding: 0.35rem 0.65rem; font: inherit; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.doc-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.1rem; }
.doc-form h2 { margin: 0; font-size: 1rem; }
.hint { margin: 0.35rem 0 0.9rem; color: #64748b; font-size: 0.85rem; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.wide { grid-column: 1 / -1; }
.doc-form label { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.doc-form input, .doc-form select, .doc-form textarea {
  border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.65rem; font: inherit; font-weight: 500;
  color: #0f172a; text-transform: none; letter-spacing: 0; background: #fff;
}
.req { color: #dc2626; font-style: normal; }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.9rem; }
.actions button { border: 1px solid #0d7a3a; border-radius: 8px; background: #fff; color: #0d7a3a; padding: 0.5rem 0.9rem; font: inherit; font-weight: 700; cursor: pointer; }
.actions .primary { background: #0d7a3a; color: #fff; }
.actions button:disabled { opacity: 0.6; cursor: default; }
@media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
</style>
