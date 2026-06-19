<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

interface FaqSourceRow {
  id: string
  label: string
  url: string
  format: string
  enabled: boolean
  deactivate_missing?: boolean
  category_map?: Record<string, string>
}

interface IngestSourceResult {
  id: string
  label: string
  url: string
  created: number
  updated: number
  deactivated: number
  status: string
  error?: string
}

interface IngestResult {
  created: number
  updated: number
  deactivated: number
  errors: Array<{ source: string; message: string }>
  sources: IngestSourceResult[]
  finished_at?: string
}

const busy = ref(false)
const ingestBusy = ref(false)
const sources = ref<FaqSourceRow[]>([])
const lastResult = ref<IngestResult | null>(null)
const exportConfigured = ref(true)
const defaultApmUrl = ref('')

function blankSource(): FaqSourceRow {
  return {
    id: `source-${Date.now()}`,
    label: 'Custom FAQ export',
    url: '',
    format: 'apm_export',
    enabled: true,
    deactivate_missing: true,
    category_map: {},
  }
}

function addSource() {
  sources.value.push(blankSource())
}

function removeSource(index: number) {
  sources.value.splice(index, 1)
}

async function load() {
  busy.value = true
  try {
    const { data } = await api.get<{
      data: {
        sources: FaqSourceRow[]
        last_result: IngestResult | null
        export_client_configured: boolean
        default_apm_export_url: string
      }
    }>('/api/v1/admin/faq-ingest')
    sources.value = data.data.sources?.length ? data.data.sources : [blankSource()]
    lastResult.value = data.data.last_result
    exportConfigured.value = data.data.export_client_configured
    defaultApmUrl.value = data.data.default_apm_export_url
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load FAQ sources.'))
  } finally {
    busy.value = false
  }
}

async function saveSources() {
  busy.value = true
  try {
    await api.put('/api/v1/admin/settings', {
      faq_sources_json: JSON.stringify(sources.value),
    })
    notifySuccess('FAQ sources saved.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function runIngest() {
  ingestBusy.value = true
  try {
    const { data } = await api.post<{ data: IngestResult }>('/api/v1/admin/faq-ingest')
    lastResult.value = data.data
    notifySuccess(
      `Ingest complete: ${data.data.created} created, ${data.data.updated} updated, ${data.data.deactivated} deactivated.`,
    )
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'FAQ ingest failed.'))
  } finally {
    ingestBusy.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel" aria-labelledby="faq-sources-heading">
    <h2 id="faq-sources-heading">FAQ knowledge-base sources</h2>
    <p class="hint">
      Pull FAQs from <strong>APM</strong> (Approvals Management &amp; Staff Portal categories at
      <a href="https://cbp.africacdc.org/staff/apm/faq" target="_blank" rel="noopener noreferrer">/staff/apm/faq</a>)
      and from other systems that expose the same JSON export format. Ingested articles feed
      <strong>Ask Helpdesk</strong> and the home-page knowledge base.
    </p>

    <p v-if="!exportConfigured" class="warn" role="status">
      Staff Share API credentials are not configured. Set <code>STAFF_API_USERNAME</code> and
      <code>STAFF_API_PASSWORD</code> in Helpdesk <code>backend/.env</code> (same as APM) so ingest can authenticate to export URLs.
    </p>

    <div class="card">
      <div v-for="(row, idx) in sources" :key="row.id" class="source-row">
        <div class="source-row-head">
          <strong>Source {{ idx + 1 }}</strong>
          <button v-if="sources.length > 1" type="button" class="linkish" @click="removeSource(idx)">Remove</button>
        </div>
        <label>Label
          <input v-model="row.label" type="text" placeholder="APM & Staff Portal FAQs" />
        </label>
        <label>Export URL
          <input v-model="row.url" type="url" :placeholder="defaultApmUrl || 'https://…/api/apm/v1/faqs/export'" />
        </label>
        <p class="field-hint">Leave blank to use the default APM export URL when this source id is <code>apm</code>.</p>
        <label>Source id (unique)
          <input v-model="row.id" type="text" placeholder="apm" />
        </label>
        <label class="row">
          <input v-model="row.enabled" type="checkbox" />
          Enabled
        </label>
        <label class="row">
          <input v-model="row.deactivate_missing" type="checkbox" />
          Deactivate KB articles removed from source
        </label>
      </div>

      <div class="actions">
        <button type="button" class="ghost" :disabled="busy" @click="addSource()">Add URL source</button>
        <button type="button" class="primary" :disabled="busy" @click="saveSources()">
          {{ busy ? 'Saving…' : 'Save sources' }}
        </button>
        <button
          type="button"
          class="primary"
          :disabled="ingestBusy || busy || !exportConfigured"
          @click="runIngest()"
        >
          {{ ingestBusy ? 'Ingesting…' : 'Run ingest now' }}
        </button>
      </div>
    </div>

    <div v-if="lastResult" class="card last-run">
      <h3>Last ingest</h3>
      <p class="field-hint">
        {{ lastResult.created }} created · {{ lastResult.updated }} updated ·
        {{ lastResult.deactivated }} deactivated
        <span v-if="lastResult.finished_at"> · {{ lastResult.finished_at }}</span>
      </p>
      <ul v-if="lastResult.sources?.length" class="run-list">
        <li v-for="s in lastResult.sources" :key="s.id">
          <strong>{{ s.label }}</strong>
          — {{ s.status }}
          <span v-if="s.status === 'ok'">
            ({{ s.created }} new, {{ s.updated }} updated, {{ s.deactivated }} deactivated)
          </span>
          <span v-else class="err-inline">{{ s.error }}</span>
        </li>
      </ul>
      <ul v-if="lastResult.errors?.length" class="run-errors">
        <li v-for="(err, i) in lastResult.errors" :key="i">{{ err.source }}: {{ err.message }}</li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.hint a {
  color: #0d7a3a;
  font-weight: 600;
}
.field-hint {
  font-size: 0.78rem;
  color: #64748b;
  margin: -0.35rem 0 0.25rem;
  line-height: 1.45;
}
.warn {
  font-size: 0.86rem;
  line-height: 1.55;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 4px;
  padding: 0.65rem 0.75rem;
  margin: 0 0 0.75rem;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem 1.35rem;
  border-radius: 4px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-paper, #fafbf9);
}
.source-row {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px dashed #e2e8f0;
}
.source-row:last-of-type {
  border-bottom: 0;
  padding-bottom: 0;
}
.source-row-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
}
label.row {
  flex-direction: row;
  align-items: center;
  gap: 0.45rem;
  font-weight: 600;
}
input[type='text'],
input[type='url'] {
  font-weight: 400;
  padding: 0.5rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-family: inherit;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.35rem;
}
.primary,
.ghost {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}
.primary {
  border: none;
  background: linear-gradient(135deg, #119a48, #0d7a3a);
  color: #fff;
}
.ghost {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}
.primary:disabled,
.ghost:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.linkish {
  border: 0;
  background: transparent;
  color: #b91c1c;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}
.last-run h3 {
  margin: 0;
  font-size: 0.95rem;
}
.run-list,
.run-errors {
  margin: 0.35rem 0 0;
  padding-left: 1.2rem;
  font-size: 0.86rem;
  line-height: 1.5;
}
.run-errors {
  color: #b91c1c;
}
.err-inline {
  color: #b91c1c;
}
code {
  font-size: 0.82em;
}
</style>
