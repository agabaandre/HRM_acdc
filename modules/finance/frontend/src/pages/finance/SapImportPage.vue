<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { getStoredToken } from '@/lib/api'

const file = ref<File | null>(null)
const uploading = ref(false)
const error = ref<string | null>(null)
const result = ref<{
  import_id: number
  rows: number
  totals: number
  apm_updated: number
  sync_status: string
  budget_year: number
} | null>(null)
const latest = ref<Record<string, unknown> | null>(null)

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
    const { data } = await api.post('/api/v1/sap/import', body, {
      headers: { 'Content-Type': 'multipart/form-data', Authorization: `Bearer ${getStoredToken() || ''}` },
    })
    result.value = data.data
    await loadLatest()
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
    result.value = { ...(result.value || { import_id: id, rows: 0, totals: 0, budget_year: new Date().getFullYear() }), ...data.data }
    await loadLatest()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Retry sync failed.')
  } finally {
    uploading.value = false
  }
}

onMounted(loadLatest)
</script>

<template>
  <div class="fin-sap">
    <header class="rr-page__header">
      <div>
        <h1 class="rr-page__title">SAP budget import</h1>
        <p class="rr-page__sub">
          Import the SAP export workbook. Total rows update APM
          <code>approved_budget</code>, <code>uploaded_budget</code>, and <code>budget_balance</code>.
        </p>
      </div>
    </header>

    <section class="rr-card">
      <label class="fin-sap__file">
        <span>SAP .xlsx file</span>
        <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="onFile" />
      </label>
      <div class="fin-sap__actions">
        <button type="button" class="btn btn-primary" :disabled="uploading || !file" @click="upload">
          {{ uploading ? 'Uploading…' : 'Upload & sync to APM' }}
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
      </div>
      <p v-if="error" class="rr-error">{{ error }}</p>
    </section>

    <section v-if="result" class="rr-card">
      <h2>Last upload result</h2>
      <ul class="fin-sap__stats">
        <li>Budget year: <strong>{{ result.budget_year }}</strong></li>
        <li>Rows imported: <strong>{{ result.rows }}</strong></li>
        <li>Total fund centers: <strong>{{ result.totals }}</strong></li>
        <li>APM codes updated: <strong>{{ result.apm_updated }}</strong></li>
        <li>Sync status: <strong>{{ result.sync_status }}</strong></li>
      </ul>
    </section>

    <section v-if="latest" class="rr-card">
      <h2>Latest import on file</h2>
      <ul class="fin-sap__stats">
        <li>File: <strong>{{ latest.original_filename }}</strong></li>
        <li>Status: <strong>{{ latest.sync_status }}</strong></li>
        <li>APM updated: <strong>{{ latest.apm_updated_count }}</strong></li>
      </ul>
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
.fin-sap__actions {
  display: flex;
  gap: 0.5rem;
}
.fin-sap__stats {
  margin: 0;
  padding-left: 1.1rem;
  line-height: 1.7;
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
