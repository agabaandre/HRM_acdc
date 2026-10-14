<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { getStoredToken } from '@/lib/api'

const file = ref<File | null>(null)
const periodYm = ref(currentPeriodYm())
const uploading = ref(false)
const error = ref<string | null>(null)
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
    error.value = 'Choose an .xlsx SAP export first.'
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
          Import the daily SAP export workbook. All GL expense lines are stored for analysis.
          Choose the reporting month — re-uploads for the same month replace the previous file
          for that month; earlier months stay on file for history.
        </p>
      </div>
    </header>

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
        <span>SAP .xlsx file</span>
        <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="onFile" />
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
  </div>
</template>

<style scoped>
.rr-card {
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 1rem 1.1rem;
  margin-bottom: 1rem;
}
.fin-sap__file {
  display: grid;
  gap: 0.4rem;
  margin-bottom: 1rem;
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
}
.fin-sap__table th {
  background: #0b3b5c;
  color: #fff;
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
</style>
