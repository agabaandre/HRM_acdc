<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { FormSubmitEvent } from '@nuxt/ui'
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

async function onSaveSources(_event: FormSubmitEvent<{ sources: FaqSourceRow[] }>) {
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

    <UCard>
      <UForm :state="{ sources }" class="hd-form" :disabled="busy" @submit="onSaveSources">
        <div v-for="(row, idx) in sources" :key="row.id" class="source-row">
          <div class="source-row-head">
            <strong>Source {{ idx + 1 }}</strong>
            <UButton
              v-if="sources.length > 1"
              type="button"
              color="error"
              variant="link"
              size="xs"
              @click="removeSource(idx)"
            >
              Remove
            </UButton>
          </div>
          <UFormField label="Label" :name="`label-${idx}`">
            <UInput v-model="row.label" type="text" placeholder="APM & Staff Portal FAQs" class="w-full" />
          </UFormField>
          <UFormField label="Export URL" :name="`url-${idx}`" :description="`Leave blank to use the default APM export URL when this source id is apm.`">
            <UInput v-model="row.url" type="url" :placeholder="defaultApmUrl || 'https://…/api/apm/v1/faqs/export'" class="w-full" />
          </UFormField>
          <UFormField label="Source id (unique)" :name="`id-${idx}`">
            <UInput v-model="row.id" type="text" placeholder="apm" class="w-full" />
          </UFormField>
          <UFormField :name="`enabled-${idx}`">
            <UCheckbox v-model="row.enabled" label="Enabled" />
          </UFormField>
          <UFormField :name="`deactivate-${idx}`">
            <UCheckbox v-model="row.deactivate_missing" label="Deactivate KB articles removed from source" />
          </UFormField>
        </div>

        <div class="hd-form-actions">
          <UButton type="button" color="neutral" variant="outline" :disabled="busy" @click="addSource()">Add URL source</UButton>
          <UButton type="submit" color="primary" :loading="busy">Save sources</UButton>
          <UButton
            type="button"
            color="primary"
            variant="soft"
            :disabled="ingestBusy || busy || !exportConfigured"
            :loading="ingestBusy"
            @click="runIngest()"
          >
            Run ingest now
          </UButton>
        </div>
      </UForm>
    </UCard>

    <UCard v-if="lastResult" class="last-run">
      <template #header>
        <h3>Last ingest</h3>
      </template>
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
    </UCard>
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
  margin: 0;
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
.last-run h3 {
  margin: 0;
  font-size: 0.95rem;
}
.last-run {
  margin-top: 1rem;
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
