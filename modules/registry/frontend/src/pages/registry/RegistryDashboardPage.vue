<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError } from '@/lib/notify'
import type { RegistryDocument } from './DocumentList.vue'

interface Slice { label: string; total: number }

interface Dashboard {
  year: number
  week_start: string
  week_end: string
  total: number
  this_week: number
  by_status: Slice[]
  by_type: Slice[]
  by_division: Slice[]
  recent: RegistryDocument[]
}

const data = ref<Dashboard | null>(null)
const loading = ref(true)
const divisionFilter = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const sortKey = ref<'ref_no' | 'date_recorded' | 'division' | 'description' | 'status'>('date_recorded')
const sortDir = ref<'asc' | 'desc'>('desc')
const exporting = ref('')

const maxDivision = computed(() => Math.max(1, ...(data.value?.by_division.map((row) => row.total) ?? [1])))

function unique(rows: RegistryDocument[], pick: (row: RegistryDocument) => string) {
  return [...new Set(rows.map(pick).filter(Boolean))].sort((a, b) => a.localeCompare(b))
}

const divisionOptions = computed(() => unique(data.value?.recent ?? [], (row) => row.division_abbrev || row.division || ''))
const typeOptions = computed(() => unique(data.value?.recent ?? [], (row) => row.document_type || ''))
const statusOptions = computed(() => unique(data.value?.recent ?? [], (row) => row.status || ''))

const visibleRecent = computed(() => {
  const filtered = (data.value?.recent ?? []).filter((row) => {
    const division = row.division_abbrev || row.division || ''
    if (divisionFilter.value && division !== divisionFilter.value) return false
    if (typeFilter.value && (row.document_type || '') !== typeFilter.value) return false
    if (statusFilter.value && (row.status || '') !== statusFilter.value) return false
    return true
  })
  const key = sortKey.value
  const dir = sortDir.value
  return [...filtered].sort((a, b) => {
    const left = key === 'division' ? (a.division_abbrev || a.division || '') : String(a[key] ?? '')
    const right = key === 'division' ? (b.division_abbrev || b.division || '') : String(b[key] ?? '')
    return dir === 'asc' ? left.localeCompare(right) : right.localeCompare(left)
  })
})

function toggleSort(key: typeof sortKey.value) {
  if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else {
    sortKey.value = key
    sortDir.value = key === 'date_recorded' ? 'desc' : 'asc'
  }
}

async function exportRecent(format: 'csv' | 'excel' | 'pdf') {
  exporting.value = format
  try {
    const res = await api.post('/api/v1/registry/export-table', {
      format,
      title: 'Latest documents',
      headings: ['No.', 'Reference', 'Recorded', 'Division', 'Type', 'Description', 'Status'],
      rows: visibleRecent.value.map((row, index) => [
        String(index + 1),
        row.ref_no,
        fmt(row.date_recorded),
        row.division_abbrev || row.division || '',
        row.document_type || '',
        row.description,
        row.status || '',
      ]),
    }, { responseType: 'blob', timeout: 60000 })
    const ext = format === 'excel' ? 'xls' : format
    const url = URL.createObjectURL(res.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `registry-latest.${ext}`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not export the latest documents.'))
  } finally {
    exporting.value = ''
  }
}

function fmt(value: string | null | undefined) {
  if (!value) return '—'
  return value.slice(0, 10)
}

onMounted(async () => {
  try {
    const res = await api.get<{ data: Dashboard }>('/api/v1/registry/dashboard')
    data.value = res.data.data
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load the registry dashboard.'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <CbpPageHeading title="Dashboard" back-to="/" back-label="← Registry" />

    <section v-if="loading" class="muted">Loading this year’s registry…</section>
    <section v-else-if="data" class="tools-page">
      <div class="kpi-grid">
        <article class="kpi-card">
          <div class="kpi-label">{{ data.year }} documents</div>
          <div class="kpi-value">{{ data.total.toLocaleString() }}</div>
        </article>
        <article class="kpi-card">
          <div class="kpi-label">This week</div>
          <div class="kpi-value">{{ data.this_week.toLocaleString() }}</div>
          <p class="sub">{{ fmt(data.week_start) }} – {{ fmt(data.week_end) }}</p>
        </article>
        <article v-for="row in data.by_status" :key="row.label" class="kpi-card">
          <div class="kpi-label">{{ row.label }}</div>
          <div class="kpi-value">{{ Number(row.total).toLocaleString() }}</div>
        </article>
      </div>

      <div class="split">
        <article class="panel">
          <h2>By division</h2>
          <div v-for="row in data.by_division" :key="row.label" class="bar-row">
            <span>{{ row.label }}</span>
            <div class="bar"><span :style="{ width: `${(Number(row.total) / maxDivision) * 100}%` }" /></div>
            <strong>{{ Number(row.total).toLocaleString() }}</strong>
          </div>
        </article>
        <article class="panel">
          <h2>By document type</h2>
          <ul>
            <li v-for="row in data.by_type" :key="row.label">
              <span>{{ row.label }}</span>
              <strong>{{ Number(row.total).toLocaleString() }}</strong>
            </li>
          </ul>
        </article>
      </div>

      <article class="panel">
        <div class="panel-head">
          <h2>Latest documents this year</h2>
          <RouterLink to="/documents/year">Open current year</RouterLink>
        </div>
        <div class="filters">
          <label>
            <span class="kpi-label">Division</span>
            <select v-model="divisionFilter">
              <option value="">All divisions</option>
              <option v-for="label in divisionOptions" :key="label" :value="label">{{ label }}</option>
            </select>
          </label>
          <label>
            <span class="kpi-label">Document type</span>
            <select v-model="typeFilter">
              <option value="">All types</option>
              <option v-for="label in typeOptions" :key="label" :value="label">{{ label }}</option>
            </select>
          </label>
          <label>
            <span class="kpi-label">Status</span>
            <select v-model="statusFilter">
              <option value="">All statuses</option>
              <option v-for="label in statusOptions" :key="label" :value="label">{{ label }}</option>
            </select>
          </label>
        </div>
        <div class="toolbar-row">
          <p class="counter">Showing {{ visibleRecent.length ? 1 : 0 }}–{{ visibleRecent.length }} of {{ visibleRecent.length }}</p>
          <div class="exports">
            <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="exportRecent('excel')">Excel</button>
            <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="exportRecent('csv')">CSV</button>
            <button type="button" class="filter-btn" :disabled="exporting !== ''" @click="exportRecent('pdf')">PDF</button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>No.</th>
                <th><button type="button" class="sort-btn" @click="toggleSort('ref_no')">Reference <span class="arrows"><i :class="{ on: sortKey === 'ref_no' && sortDir === 'asc' }">▲</i><i :class="{ on: sortKey === 'ref_no' && sortDir === 'desc' }">▼</i></span></button></th>
                <th><button type="button" class="sort-btn" @click="toggleSort('date_recorded')">Recorded <span class="arrows"><i :class="{ on: sortKey === 'date_recorded' && sortDir === 'asc' }">▲</i><i :class="{ on: sortKey === 'date_recorded' && sortDir === 'desc' }">▼</i></span></button></th>
                <th><button type="button" class="sort-btn" @click="toggleSort('division')">Division <span class="arrows"><i :class="{ on: sortKey === 'division' && sortDir === 'asc' }">▲</i><i :class="{ on: sortKey === 'division' && sortDir === 'desc' }">▼</i></span></button></th>
                <th><button type="button" class="sort-btn" @click="toggleSort('description')">Description <span class="arrows"><i :class="{ on: sortKey === 'description' && sortDir === 'asc' }">▲</i><i :class="{ on: sortKey === 'description' && sortDir === 'desc' }">▼</i></span></button></th>
                <th><button type="button" class="sort-btn" @click="toggleSort('status')">Status <span class="arrows"><i :class="{ on: sortKey === 'status' && sortDir === 'asc' }">▲</i><i :class="{ on: sortKey === 'status' && sortDir === 'desc' }">▼</i></span></button></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in visibleRecent" :key="row.id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.ref_no }}</td>
                <td>{{ fmt(row.date_recorded) }}</td>
                <td>{{ row.division_abbrev || row.division || '—' }}</td>
                <td>{{ row.description }}</td>
                <td>{{ row.status || '—' }}</td>
              </tr>
              <tr v-if="!visibleRecent.length">
                <td colspan="6" class="muted">No documents match these filters.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; }
.kpi-card, .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.9rem 1rem; }
.kpi-label { font-size: 0.72rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
.kpi-value { margin-top: 0.25rem; font-size: 1.45rem; font-weight: 800; color: #0f172a; }
.sub, .muted { color: #64748b; font-size: 0.8rem; }
.split { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; }
.panel h2 { margin: 0 0 0.75rem; font-size: 1rem; }
.panel-head { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
.panel-head a { color: #0d7a3a; font-weight: 700; font-size: 0.85rem; }
.bar-row, .panel li { display: grid; grid-template-columns: 7rem 1fr auto; gap: 0.6rem; align-items: center; margin-bottom: 0.45rem; font-size: 0.85rem; }
.panel ul { list-style: none; margin: 0; padding: 0; }
.panel li { grid-template-columns: 1fr auto; }
.bar { height: 0.55rem; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
.bar span { display: block; height: 100%; background: #0d7a3a; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.55rem 0.4rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.data-table thead th { font-size: 0.72rem; text-transform: uppercase; color: #64748b; }
.filters, .exports { display: flex; gap: 0.55rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 0.7rem; }
.filters label { display: flex; flex-direction: column; gap: 0.25rem; min-width: 9rem; }
.filters select, .filter-btn { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.4rem 0.6rem; background: #fff; font: inherit; }
.filter-btn { cursor: pointer; font-weight: 700; color: #0d7a3a; }
.filter-btn:disabled { opacity: 0.55; cursor: default; }
.toolbar-row { display: flex; justify-content: space-between; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.6rem; }
.counter { margin: 0; font-weight: 700; }
.sort-btn { display: inline-flex; align-items: center; gap: 0.3rem; border: 0; background: transparent; padding: 0; font: inherit; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: #64748b; cursor: pointer; }
.arrows { display: inline-flex; flex-direction: column; font-size: 0.55rem; line-height: 0.75; color: #cbd5e1; }
.arrows .on { color: #0d7a3a; }
@media (max-width: 800px) { .split { grid-template-columns: 1fr; } }
</style>
