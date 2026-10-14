<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, getStoredToken } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type SapDumpFile = {
  name: string
  size: number
  last_modified: string | null
  content_type: string | null
  is_directory?: boolean
}

type CsvPreview = {
  name: string
  headers: string[]
  rows: string[][]
  row_count: number
  truncated: boolean
  limit: number
  size?: number
}

const tab = ref<'upload' | 'dump'>('upload')
const file = ref<File | null>(null)
const periodYm = ref(currentPeriodYm())
const uploading = ref(false)
const error = ref<string | null>(null)
const uploadPreview = ref<CsvPreview | null>(null)
const uploadPreviewError = ref<string | null>(null)
const result = ref<{
  import_id: number
  rows: number
  totals: number
  apm_updated: number
  sync_status: string
  budget_year: number
  period_ym?: string
} | null>(null)
const latest = ref<Record<string, unknown> | null>(null)
const history = ref<Array<Record<string, unknown>>>([])

const dumpLoading = ref(false)
const dumpError = ref<string | null>(null)
const dumpConfigured = ref(false)
const dumpMeta = ref<{ account_name?: string; container?: string; prefix?: string }>({})
const dumpFiles = ref<SapDumpFile[]>([])
const dumpBusy = ref<string | null>(null)
const dumpPreview = ref<CsvPreview | null>(null)
const dumpPreviewError = ref<string | null>(null)
const dumpFetching = ref(false)
const dumpFetchMessage = ref<string | null>(null)

function currentPeriodYm(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const periodOptions = computed(() => {
  const opts: Array<{ title: string; value: string }> = []
  const now = new Date()
  for (let i = 0; i < 18; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const title = d.toLocaleString('en-GB', { month: 'long', year: 'numeric' })
    opts.push({ title, value })
  }
  return opts
})

function onFile(e: Event) {
  const input = e.target as HTMLInputElement
  file.value = input.files?.[0] || null
  uploadPreview.value = null
  uploadPreviewError.value = null
  if (file.value && isCsvName(file.value.name)) {
    void previewLocalCsv(file.value)
  }
}

function isCsvName(name: string): boolean {
  const ext = name.split('.').pop()?.toLowerCase() || ''
  return ext === 'csv' || ext === 'txt'
}

function parseCsvText(text: string, limit = 50): Omit<CsvPreview, 'name' | 'size'> {
  const cleaned = text.replace(/^\uFEFF/, '')
  const lines: string[][] = []
  let row: string[] = []
  let cell = ''
  let inQuotes = false
  for (let i = 0; i < cleaned.length; i++) {
    const ch = cleaned[i]
    const next = cleaned[i + 1]
    if (inQuotes) {
      if (ch === '"' && next === '"') {
        cell += '"'
        i++
      } else if (ch === '"') {
        inQuotes = false
      } else {
        cell += ch
      }
      continue
    }
    if (ch === '"') {
      inQuotes = true
      continue
    }
    if (ch === ',') {
      row.push(cell)
      cell = ''
      continue
    }
    if (ch === '\n' || (ch === '\r' && next === '\n')) {
      row.push(cell)
      cell = ''
      if (row.some((c) => c.trim() !== '') || row.length > 1) lines.push(row)
      row = []
      if (ch === '\r') i++
      continue
    }
    if (ch === '\r') {
      row.push(cell)
      cell = ''
      if (row.some((c) => c.trim() !== '') || row.length > 1) lines.push(row)
      row = []
      continue
    }
    cell += ch
  }
  if (cell !== '' || row.length) {
    row.push(cell)
    if (row.some((c) => c.trim() !== '') || row.length > 1) lines.push(row)
  }
  const headers = lines[0] || []
  const dataRows = lines.slice(1)
  return {
    headers,
    rows: dataRows.slice(0, limit),
    row_count: dataRows.length,
    truncated: dataRows.length > limit,
    limit,
  }
}

async function previewLocalCsv(f: File) {
  try {
    const text = await f.text()
    const parsed = parseCsvText(text, 50)
    uploadPreview.value = {
      name: f.name,
      size: f.size,
      ...parsed,
    }
  } catch {
    uploadPreviewError.value = 'Could not preview this CSV.'
    uploadPreview.value = null
  }
}

async function loadLatest() {
  try {
    const { data } = await api.get('/api/v1/sap/imports/latest')
    latest.value = data.data
  } catch {
    latest.value = null
  }
}

async function loadHistory() {
  try {
    const { data } = await api.get('/api/v1/sap/imports/history')
    history.value = Array.isArray(data.data) ? data.data : []
  } catch {
    history.value = []
  }
}

async function upload() {
  if (!file.value) {
    error.value = 'Choose an .xlsx or .csv SAP export first.'
    return
  }
  uploading.value = true
  error.value = null
  result.value = null
  try {
    const body = new FormData()
    body.append('file', file.value)
    body.append('period_ym', periodYm.value)
    const { data } = await api.post('/api/v1/sap/import', body, {
      headers: { 'Content-Type': 'multipart/form-data', Authorization: `Bearer ${getStoredToken() || ''}` },
    })
    result.value = data.data
    await Promise.all([loadLatest(), loadHistory()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'SAP upload failed.')
  } finally {
    uploading.value = false
  }
}

async function retrySync() {
  const id = Number(result.value?.import_id || latest.value?.id)
  if (!id) return
  uploading.value = true
  error.value = null
  try {
    const { data } = await api.post(`/api/v1/sap/imports/${id}/retry-sync`)
    result.value = {
      ...(result.value || {
        import_id: id,
        rows: 0,
        totals: 0,
        budget_year: new Date().getFullYear(),
        period_ym: periodYm.value,
      }),
      ...data.data,
    }
    await Promise.all([loadLatest(), loadHistory()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'Retry sync failed.')
  } finally {
    uploading.value = false
  }
}

async function loadDumpFiles() {
  dumpLoading.value = true
  dumpError.value = null
  try {
    const { data } = await api.get('/api/v1/sap/dump/files')
    dumpConfigured.value = Boolean(data.data?.configured)
    dumpMeta.value = {
      account_name: data.data?.account_name,
      container: data.data?.container,
      prefix: data.data?.prefix,
    }
    dumpFiles.value = Array.isArray(data.data?.files) ? data.data.files : []
  } catch (e: any) {
    const payload = e?.response?.data
    dumpConfigured.value = Boolean(payload?.data?.configured)
    dumpFiles.value = []
    dumpError.value = apiErrorMessage(e, payload?.message || 'Could not list Azure SAP dumps.')
  } finally {
    dumpLoading.value = false
  }
}

function selectTab(next: 'upload' | 'dump') {
  tab.value = next
  if (next === 'dump' && dumpFiles.value.length === 0 && !dumpLoading.value) {
    void loadDumpFiles()
  }
}

function formatBytes(n: number): string {
  if (!n || n < 0) return '0 B'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

function formatDumpDate(raw: string | null): string {
  if (!raw) return '—'
  const d = new Date(raw)
  if (Number.isNaN(d.getTime())) return raw
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function canImportDump(name: string): boolean {
  const ext = name.split('.').pop()?.toLowerCase() || ''
  return ['xlsx', 'csv', 'txt'].includes(ext)
}

function canPreviewDump(name: string): boolean {
  return isCsvName(name)
}

async function previewDump(name: string) {
  dumpBusy.value = `pv:${name}`
  dumpPreviewError.value = null
  dumpError.value = null
  try {
    const { data } = await api.get('/api/v1/sap/dump/preview', {
      params: { name, limit: 50 },
    })
    dumpPreview.value = {
      name: data.data?.name || name,
      headers: Array.isArray(data.data?.headers) ? data.data.headers : [],
      rows: Array.isArray(data.data?.rows) ? data.data.rows : [],
      row_count: Number(data.data?.row_count || 0),
      truncated: Boolean(data.data?.truncated),
      limit: Number(data.data?.limit || 50),
      size: data.data?.size != null ? Number(data.data.size) : undefined,
    }
  } catch (e) {
    dumpPreview.value = null
    dumpPreviewError.value = apiErrorMessage(e, 'CSV preview failed.')
  } finally {
    dumpBusy.value = null
  }
}

function closeDumpPreview() {
  dumpPreview.value = null
  dumpPreviewError.value = null
}

async function downloadDump(name: string) {
  dumpBusy.value = `dl:${name}`
  dumpError.value = null
  try {
    const res = await api.get('/api/v1/sap/dump/download', {
      params: { name },
      responseType: 'blob',
    })
    const blob = new Blob([res.data])
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = name.split('/').pop() || 'sap-dump'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    dumpError.value = apiErrorMessage(e, 'Download failed.')
  } finally {
    dumpBusy.value = null
  }
}

async function importDump(name: string) {
  dumpBusy.value = `im:${name}`
  dumpError.value = null
  dumpFetchMessage.value = null
  error.value = null
  try {
    const { data } = await api.post('/api/v1/sap/dump/import', {
      name,
      period_ym: periodYm.value,
    })
    result.value = data.data
    if (data.data?.remote_deleted) {
      dumpFetchMessage.value = `Imported ${name} and removed it from Azure storage.`
      dumpPreview.value = null
      await loadDumpFiles()
    }
    tab.value = 'upload'
    await Promise.all([loadLatest(), loadHistory()])
  } catch (e) {
    dumpError.value = apiErrorMessage(e, 'Import from Azure failed.')
  } finally {
    dumpBusy.value = null
  }
}

async function fetchAllDumps() {
  dumpFetching.value = true
  dumpError.value = null
  dumpFetchMessage.value = null
  try {
    const { data } = await api.post('/api/v1/sap/dump/fetch', {})
    const run = data.data || {}
    const imported = Array.isArray(run.imported) ? run.imported.length : 0
    const deleted = Array.isArray(run.deleted) ? run.deleted.length : 0
    dumpFetchMessage.value = data.message || `Fetched ${imported} file(s); deleted ${deleted} remote.`
    dumpPreview.value = null
    await Promise.all([loadDumpFiles(), loadLatest(), loadHistory()])
    if (imported > 0) {
      tab.value = 'upload'
    }
  } catch (e) {
    dumpError.value = apiErrorMessage(e, 'Background fetch failed.')
    await loadDumpFiles()
  } finally {
    dumpFetching.value = false
  }
}

function formatPeriod(ym: unknown): string {
  const s = String(ym || '')
  if (!/^\d{4}-\d{2}$/.test(s)) return s || '—'
  const [y, m] = s.split('-').map(Number)
  return new Date(y, m - 1, 1).toLocaleString('en-GB', { month: 'short', year: 'numeric' })
}

onMounted(() => {
  void loadLatest()
  void loadHistory()
})
</script>

<template>
  <div class="fin-sap">
    <header class="rr-page__header">
      <div>
        <h1 class="rr-page__title">SAP budget import</h1>
        <p class="rr-page__sub">
          Import the daily SAP export workbook, or pull dumps from Azure Blob storage.
          Re-uploads for the same month replace the previous file for that month.
        </p>
      </div>
      <RouterLink class="fin-sap__settings" :to="{ name: 'settings-azure-sap-storage' }">
        Azure storage settings
      </RouterLink>
    </header>

    <nav class="fin-sap__tabs" aria-label="SAP import sections">
      <button
        type="button"
        class="fin-sap__tab"
        :class="{ 'fin-sap__tab--active': tab === 'upload' }"
        @click="selectTab('upload')"
      >
        Upload
      </button>
      <button
        type="button"
        class="fin-sap__tab"
        :class="{ 'fin-sap__tab--active': tab === 'dump' }"
        @click="selectTab('dump')"
      >
        SAP dump
      </button>
    </nav>

    <template v-if="tab === 'upload'">
      <section class="rr-card">
        <label class="fin-sap__file">
          <span>Reporting month</span>
          <select v-model="periodYm" class="fin-sap__select">
            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
              {{ opt.title }}
            </option>
          </select>
        </label>
        <label class="fin-sap__file">
          <span>SAP .xlsx / .csv file</span>
          <input
            type="file"
            accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
            @change="onFile"
          />
        </label>
        <div class="fin-sap__actions">
          <button type="button" class="btn btn-primary" :disabled="uploading || !file" @click="upload">
            {{ uploading ? 'Uploading…' : 'Upload & queue APM sync' }}
          </button>
          <button
            v-if="(result?.sync_status || latest?.sync_status) === 'sync_failed'"
            type="button"
            class="btn"
            :disabled="uploading"
            @click="retrySync"
          >
            Retry APM sync
          </button>
          <button
            v-if="(result?.sync_status || latest?.sync_status) === 'pending'"
            type="button"
            class="btn"
            :disabled="uploading"
            @click="loadLatest"
          >
            Check sync status
          </button>
        </div>
        <p v-if="error" class="rr-error">{{ error }}</p>
        <p v-if="uploadPreviewError" class="rr-error">{{ uploadPreviewError }}</p>
      </section>

      <section v-if="uploadPreview" class="rr-card">
        <div class="fin-sap__preview-head">
          <div>
            <h2>CSV preview</h2>
            <p class="fin-sap__hint">
              {{ uploadPreview.name }}
              · {{ uploadPreview.row_count.toLocaleString() }} data row{{ uploadPreview.row_count === 1 ? '' : 's' }}
              <template v-if="uploadPreview.truncated">
                · showing first {{ uploadPreview.limit }}
              </template>
            </p>
          </div>
          <button type="button" class="btn" @click="uploadPreview = null">Close</button>
        </div>
        <div class="fin-sap__preview-wrap">
          <table class="fin-sap__table fin-sap__preview-table">
            <thead>
              <tr>
                <th v-for="(h, i) in uploadPreview.headers" :key="`uh-${i}`">{{ h || `Col ${i + 1}` }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, ri) in uploadPreview.rows" :key="`ur-${ri}`">
                <td v-for="(cell, ci) in row" :key="`uc-${ri}-${ci}`">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="result" class="rr-card">
        <h2>Last upload result</h2>
        <ul class="fin-sap__stats">
          <li>Period: <strong>{{ formatPeriod(result.period_ym) }}</strong></li>
          <li>Budget year: <strong>{{ result.budget_year }}</strong></li>
          <li>Rows imported: <strong>{{ result.rows }}</strong></li>
          <li>Total fund centers: <strong>{{ result.totals }}</strong></li>
          <li>APM codes updated: <strong>{{ result.apm_updated }}</strong></li>
          <li>Sync status: <strong>{{ result.sync_status }}</strong></li>
        </ul>
      </section>

      <section v-if="latest" class="rr-card">
        <h2>Current dashboard snapshot</h2>
        <ul class="fin-sap__stats">
          <li>Period: <strong>{{ formatPeriod(latest.period_ym) }}</strong></li>
          <li>File: <strong>{{ latest.original_filename }}</strong></li>
          <li>Status: <strong>{{ latest.sync_status }}</strong></li>
          <li>APM updated: <strong>{{ latest.apm_updated_count }}</strong></li>
          <li v-if="latest.sync_error">Error: <strong class="rr-error">{{ latest.sync_error }}</strong></li>
        </ul>
      </section>

      <section v-if="history.length" class="rr-card">
        <h2>Monthly history</h2>
        <p class="fin-sap__hint">One retained snapshot per month (last upload wins).</p>
        <div class="fin-sap__table-wrap">
          <table class="fin-sap__table">
            <thead>
              <tr>
                <th>Period</th>
                <th>File</th>
                <th>Rows</th>
                <th>Fund centers</th>
                <th>Sync</th>
                <th>Current</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in history" :key="String(row.id)">
                <td>{{ formatPeriod(row.period_ym) }}</td>
                <td>{{ row.original_filename }}</td>
                <td>{{ row.row_count }}</td>
                <td>{{ row.total_row_count }}</td>
                <td>{{ row.sync_status }}</td>
                <td>{{ row.is_latest ? 'Yes' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <template v-else>
      <section class="rr-card">
        <div class="fin-sap__dump-head">
          <div>
            <h2>Azure SAP dumps</h2>
            <p class="fin-sap__hint">
              Background job checks Azure every 4 hours, imports new dumps, then deletes them remotely after a successful fetch.
              <template v-if="dumpConfigured">
                Account <strong>{{ dumpMeta.account_name }}</strong>
                · container <strong>{{ dumpMeta.container }}</strong>
                <template v-if="dumpMeta.prefix"> · prefix <strong>{{ dumpMeta.prefix }}</strong></template>
              </template>
              <template v-else>
                Configure credentials under
                <RouterLink :to="{ name: 'settings-azure-sap-storage' }">Azure SAP storage</RouterLink>.
              </template>
            </p>
          </div>
          <div class="fin-sap__actions">
            <label class="fin-sap__file fin-sap__file--inline">
              <span>Import month</span>
              <select v-model="periodYm" class="fin-sap__select">
                <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                  {{ opt.title }}
                </option>
              </select>
            </label>
            <button type="button" class="btn" :disabled="dumpLoading || dumpFetching" @click="loadDumpFiles">
              {{ dumpLoading ? 'Refreshing…' : 'Refresh' }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="dumpFetching || !dumpConfigured"
              @click="fetchAllDumps"
            >
              {{ dumpFetching ? 'Fetching…' : 'Fetch now' }}
            </button>
          </div>
        </div>
        <p v-if="dumpFetchMessage" class="fin-sap__ok">{{ dumpFetchMessage }}</p>
        <p v-if="dumpError" class="rr-error">{{ dumpError }}</p>
        <div v-if="dumpLoading && !dumpFiles.length" class="fin-sap__hint">Loading dumps…</div>
        <div v-else-if="!dumpFiles.length && dumpConfigured" class="fin-sap__hint">
          No files waiting in Azure (already fetched, or none uploaded yet).
        </div>
        <div v-else-if="dumpFiles.length" class="fin-sap__table-wrap">
          <table class="fin-sap__table">
            <thead>
              <tr>
                <th>File</th>
                <th>Size</th>
                <th>Last modified</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in dumpFiles" :key="f.name">
                <td>{{ f.name }}</td>
                <td>{{ formatBytes(f.size) }}</td>
                <td>{{ formatDumpDate(f.last_modified) }}</td>
                <td>
                  <div class="fin-sap__row-actions">
                    <button
                      v-if="canPreviewDump(f.name)"
                      type="button"
                      class="btn"
                      :disabled="dumpBusy === `pv:${f.name}`"
                      @click="previewDump(f.name)"
                    >
                      {{ dumpBusy === `pv:${f.name}` ? '…' : 'Preview' }}
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :disabled="dumpBusy === `dl:${f.name}`"
                      @click="downloadDump(f.name)"
                    >
                      {{ dumpBusy === `dl:${f.name}` ? '…' : 'Download' }}
                    </button>
                    <button
                      v-if="canImportDump(f.name)"
                      type="button"
                      class="btn btn-primary"
                      :disabled="dumpBusy === `im:${f.name}`"
                      @click="importDump(f.name)"
                    >
                      {{ dumpBusy === `im:${f.name}` ? 'Importing…' : 'Import' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="dumpPreviewError" class="rr-card">
        <p class="rr-error">{{ dumpPreviewError }}</p>
        <button type="button" class="btn" @click="dumpPreviewError = null">Dismiss</button>
      </section>

      <section v-if="dumpPreview" class="rr-card">
        <div class="fin-sap__preview-head">
          <div>
            <h2>CSV preview</h2>
            <p class="fin-sap__hint">
              {{ dumpPreview.name }}
              <template v-if="dumpPreview.size != null"> · {{ formatBytes(dumpPreview.size) }}</template>
              · {{ dumpPreview.row_count.toLocaleString() }} data row{{ dumpPreview.row_count === 1 ? '' : 's' }}
              <template v-if="dumpPreview.truncated">
                · showing first {{ dumpPreview.limit }}
              </template>
            </p>
          </div>
          <div class="fin-sap__actions">
            <button
              v-if="canImportDump(dumpPreview.name)"
              type="button"
              class="btn btn-primary"
              :disabled="dumpBusy === `im:${dumpPreview.name}`"
              @click="importDump(dumpPreview.name)"
            >
              {{ dumpBusy === `im:${dumpPreview.name}` ? 'Importing…' : 'Import this file' }}
            </button>
            <button type="button" class="btn" @click="closeDumpPreview">Close</button>
          </div>
        </div>
        <div class="fin-sap__preview-wrap">
          <table class="fin-sap__table fin-sap__preview-table">
            <thead>
              <tr>
                <th v-for="(h, i) in dumpPreview.headers" :key="`dh-${i}`">{{ h || `Col ${i + 1}` }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, ri) in dumpPreview.rows" :key="`dr-${ri}`">
                <td v-for="(cell, ci) in row" :key="`dc-${ri}-${ci}`">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page__header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}
.fin-sap__settings {
  color: #0b3b5c;
  font-weight: 650;
  text-decoration: none;
  white-space: nowrap;
}
.fin-sap__tabs {
  display: flex;
  gap: 0.35rem;
  margin: 0 0 1rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
.fin-sap__tab {
  border: none;
  background: transparent;
  padding: 0.55rem 0.9rem;
  cursor: pointer;
  font-weight: 650;
  color: #64748b;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.fin-sap__tab--active {
  color: #0b3b5c;
  border-bottom-color: #0b3b5c;
}
.rr-card {
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 1rem 1.1rem;
  margin-bottom: 1rem;
}
.fin-sap__dump-head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.fin-sap__dump-head h2 { margin: 0 0 0.35rem; font-size: 1.1rem; color: #0b3b5c; }
.fin-sap__file {
  display: grid;
  gap: 0.4rem;
  margin-bottom: 1rem;
}
.fin-sap__file--inline {
  margin-bottom: 0;
}
.fin-sap__select {
  max-width: 280px;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 8px;
  padding: 0.45rem 0.65rem;
  background: #fff;
}
.fin-sap__actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: flex-end;
}
.fin-sap__row-actions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.fin-sap__stats {
  margin: 0;
  padding-left: 1.1rem;
  line-height: 1.7;
}
.fin-sap__hint {
  margin: 0 0 0.75rem;
  color: #64748b;
  font-size: 0.9rem;
}
.fin-sap__table-wrap { overflow: auto; }
.fin-sap__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.fin-sap__table th,
.fin-sap__table td {
  text-align: left;
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  vertical-align: middle;
}
.fin-sap__table th {
  background: #0b3b5c;
  color: #fff;
}
.fin-sap__preview-head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.fin-sap__preview-head h2 {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
  color: #0b3b5c;
}
.fin-sap__preview-wrap {
  overflow: auto;
  max-height: min(60vh, 520px);
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 8px;
}
.fin-sap__preview-table {
  font-size: 0.78rem;
}
.fin-sap__preview-table th,
.fin-sap__preview-table td {
  white-space: nowrap;
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.fin-sap__preview-table th {
  position: sticky;
  top: 0;
  z-index: 1;
}
.btn {
  border: 1px solid rgba(0, 0, 0, 0.15);
  background: #fff;
  border-radius: 8px;
  padding: 0.45rem 0.8rem;
  cursor: pointer;
}
.btn-primary {
  background: #0b3b5c;
  color: #fff;
  border-color: #0b3b5c;
}
.rr-error {
  color: #b42318;
  margin-top: 0.75rem;
}
.fin-sap__ok {
  color: #166534;
  margin: 0 0 0.75rem;
}
</style>
