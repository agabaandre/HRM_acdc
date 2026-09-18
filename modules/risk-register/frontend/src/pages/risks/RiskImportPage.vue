<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'

type UnmatchedBu = { business_unit: string; count: number }
type Division = { division_id: number; division_short_name: string; division_name: string; directorate_id: number | null }

const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const importEnabled = ref(true)
const batchId = ref<number | null>(null)
const preview = ref<{ matched: number; unmatched: number; skipped: number; unmatched_bus: UnmatchedBu[] } | null>(null)
const commitResult = ref<{ imported: number; staged: number } | null>(null)
const pending = ref<UnmatchedBu[]>([])
const divisions = ref<Division[]>([])
const mappings = reactive<Record<string, number | null>>({})
const fileInput = ref<HTMLInputElement | null>(null)

async function loadStatus() {
  loading.value = true
  error.value = null
  try {
    const [{ data: status }, { data: org }] = await Promise.all([
      api.get<{ import_enabled: boolean; pending_unmatched: UnmatchedBu[]; latest_batch: { id: number; status: string } | null }>('/api/v1/import/status'),
      api.get<{ data: { divisions: Division[] } }>('/api/v1/org'),
    ])
    importEnabled.value = status.import_enabled
    pending.value = status.pending_unmatched || []
    if (status.latest_batch && status.latest_batch.status === 'matched_committed') {
      batchId.value = status.latest_batch.id
    }
    divisions.value = org.data.divisions || []
    for (const row of pending.value) {
      if (!(row.business_unit in mappings)) mappings[row.business_unit] = null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load import status')
  } finally {
    loading.value = false
  }
}

async function onFileChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  busy.value = true
  error.value = null
  preview.value = null
  commitResult.value = null
  try {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post<{ data: { batch_id: number; matched: number; unmatched: number; skipped: number; unmatched_bus: UnmatchedBu[] } }>(
      '/api/v1/import/preview',
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    batchId.value = data.data.batch_id
    preview.value = data.data
    for (const row of data.data.unmatched_bus) {
      mappings[row.business_unit] = null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Preview failed')
  } finally {
    busy.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function commitMatched() {
  if (batchId.value == null) return
  busy.value = true
  error.value = null
  try {
    const { data } = await api.post<{ data: { imported: number; staged: number } }>(`/api/v1/import/${batchId.value}/commit-matched`)
    commitResult.value = data.data
    await loadStatus()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Commit failed')
  } finally {
    busy.value = false
  }
}

async function applyMappings() {
  if (batchId.value == null) return
  const payload = Object.entries(mappings)
    .filter(([, div]) => div != null && div > 0)
    .map(([business_unit, division_id]) => ({ business_unit, division_id: division_id as number }))
  if (payload.length === 0) {
    error.value = 'Select at least one division mapping.'
    return
  }
  busy.value = true
  error.value = null
  try {
    await api.post(`/api/v1/import/${batchId.value}/apply-mappings`, { mappings: payload })
    preview.value = null
    commitResult.value = null
    await loadStatus()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Apply mappings failed')
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  void loadStatus()
})
</script>

<template>
  <div class="rr-page">
    <h1>Excel import</h1>
    <p class="rr-muted">One-time workbook import. Matched rows import immediately; unmatched business units are mapped here, then imported.</p>

    <p v-if="loading">Loading…</p>
    <p v-else-if="!importEnabled" class="rr-warn">Excel import is disabled in Settings.</p>
    <template v-else>
      <p v-if="error" class="rr-error">{{ error }}</p>

      <section class="rr-card">
        <h2>1. Upload workbook</h2>
        <input ref="fileInput" type="file" accept=".xlsx,.xls" :disabled="busy" @change="onFileChange" />
      </section>

      <section v-if="preview" class="rr-card">
        <h2>2. Preview</h2>
        <p>Matched: <strong>{{ preview.matched }}</strong> · Unmatched: <strong>{{ preview.unmatched }}</strong> · Skipped empty: {{ preview.skipped }}</p>
        <ul v-if="preview.unmatched_bus.length">
          <li v-for="u in preview.unmatched_bus" :key="u.business_unit">{{ u.business_unit }} ({{ u.count }})</li>
        </ul>
        <button type="button" class="rr-btn" :disabled="busy" @click="commitMatched">Import matched rows</button>
      </section>

      <section v-if="commitResult" class="rr-card">
        <p>Imported {{ commitResult.imported }} matched · staged {{ commitResult.staged }} unmatched</p>
      </section>

      <section v-if="pending.length" class="rr-card">
        <h2>3. Map unmatched business units</h2>
        <table class="rr-table">
          <thead>
            <tr><th>Excel BU</th><th>Count</th><th>Division</th></tr>
          </thead>
          <tbody>
            <tr v-for="u in pending" :key="u.business_unit">
              <td>{{ u.business_unit }}</td>
              <td>{{ u.count }}</td>
              <td>
                <select v-model="mappings[u.business_unit]">
                  <option :value="null">— select —</option>
                  <option v-for="d in divisions" :key="d.division_id" :value="d.division_id">
                    {{ d.division_short_name }} — {{ d.division_name }}
                  </option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
        <button type="button" class="rr-btn" :disabled="busy" @click="applyMappings">Apply mappings &amp; import</button>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page { width: 100%; max-width: none; margin: 0; padding: 1.5rem 0; }
.rr-card { background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
.rr-btn { background: #0b6e4f; color: #fff; border: 0; border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600; cursor: pointer; margin-top: 0.75rem; }
.rr-table { width: 100%; border-collapse: collapse; }
.rr-table th, .rr-table td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #e8edf2; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
.rr-warn { color: #9a6700; background: #fff8e6; padding: 0.75rem 1rem; border-radius: 6px; }
</style>
