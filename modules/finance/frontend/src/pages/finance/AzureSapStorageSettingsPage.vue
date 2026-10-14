<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const fetching = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const clearConnectionString = ref(false)
const clearAccountKey = ref(false)
const probeLines = ref<string[]>([])
const sync = reactive({
  last_sync_at: null as string | null,
  poll_hours: 4,
  delete_after_fetch: true,
  summary: null as Record<string, unknown> | null,
})

const form = reactive({
  azure_sap_connection_string: '',
  azure_sap_account_name: '',
  azure_sap_account_key: '',
  azure_sap_container: '',
  azure_sap_prefix: '',
})

const resolved = reactive({
  account_name: '',
  container: '',
  prefix: '',
  account_key_configured: false,
  connection_string_configured: false,
  blob_endpoint: '',
})

const sources = reactive<Record<string, string>>({})

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/azure-sap-storage')
    const d = data.data
    form.azure_sap_connection_string = ''
    form.azure_sap_account_name = d.settings?.azure_sap_account_name || ''
    form.azure_sap_account_key = ''
    form.azure_sap_container = d.settings?.azure_sap_container || ''
    form.azure_sap_prefix = d.settings?.azure_sap_prefix || ''
    Object.assign(resolved, d.resolved || {})
    Object.assign(sources, d.sources || {})
    sync.last_sync_at = d.sync?.last_sync_at || null
    sync.poll_hours = Number(d.sync?.poll_hours || 4)
    sync.delete_after_fetch = d.sync?.delete_after_fetch !== false
    sync.summary = d.sync?.summary || null
    clearConnectionString.value = false
    clearAccountKey.value = false
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load Azure SAP storage settings.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/azure-sap-storage', {
      ...form,
      clear_connection_string: clearConnectionString.value,
      clear_account_key: clearAccountKey.value,
    })
    message.value = data.message || 'Saved.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save Azure SAP storage settings.')
  } finally {
    saving.value = false
  }
}

async function testConnection() {
  testing.value = true
  error.value = null
  message.value = null
  probeLines.value = []
  try {
    const payload: Record<string, string> = {}
    if (form.azure_sap_connection_string.trim()) {
      payload.azure_sap_connection_string = form.azure_sap_connection_string.trim()
    }
    if (form.azure_sap_account_name.trim()) {
      payload.azure_sap_account_name = form.azure_sap_account_name.trim()
    }
    if (form.azure_sap_account_key.trim()) {
      payload.azure_sap_account_key = form.azure_sap_account_key
    }
    if (form.azure_sap_container.trim()) {
      payload.azure_sap_container = form.azure_sap_container.trim()
    }
    if (form.azure_sap_prefix.trim()) {
      payload.azure_sap_prefix = form.azure_sap_prefix.trim()
    }
    const { data } = await api.post('/api/v1/settings/azure-sap-storage/test', payload)
    probeLines.value = data.details || []
    if (data.success) message.value = data.message
    else error.value = data.message || 'Connection failed'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Connection test failed.')
  } finally {
    testing.value = false
  }
}

async function fetchNow() {
  fetching.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/azure-sap-storage/fetch-now', {})
    message.value = data.message || 'Fetch finished.'
    const d = data.data
    if (d?.sync) {
      sync.last_sync_at = d.sync.last_sync_at || null
      sync.poll_hours = Number(d.sync.poll_hours || 4)
      sync.delete_after_fetch = d.sync.delete_after_fetch !== false
      sync.summary = d.sync.summary || null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Fetch now failed.')
  } finally {
    fetching.value = false
  }
}

function formatSyncAt(raw: string | null): string {
  if (!raw) return 'Never'
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

onMounted(load)
</script>

<template>
  <div class="az">
    <header class="az__header">
      <div>
        <h1>Azure SAP storage</h1>
        <p class="az__sub">
          Connect Finance to the Azure Blob container that holds daily SAP dump files.
          A background job checks every {{ sync.poll_hours }} hours, imports available dumps,
          then deletes each remote file after a successful fetch.
        </p>
      </div>
      <div class="az__links">
        <RouterLink class="az__link" :to="{ name: 'budget-execution' }">Budget execution</RouterLink>
        <RouterLink class="az__link" :to="{ name: 'sap-import' }">SAP import</RouterLink>
      </div>
    </header>

    <FinSkeleton v-if="loading" variant="form" :rows="6" />
    <template v-else>
      <p v-if="error" class="az__error">{{ error }}</p>
      <p v-if="message" class="az__ok">{{ message }}</p>

      <section class="az__card">
        <h2>Resolved connection</h2>
        <ul class="az__stats">
          <li>Account: <strong>{{ resolved.account_name || '—' }}</strong>
            <span class="az__src">({{ sources.account_name || 'unset' }})</span></li>
          <li>Container: <strong>{{ resolved.container || '—' }}</strong>
            <span class="az__src">({{ sources.container || 'unset' }})</span></li>
          <li>Prefix: <strong>{{ resolved.prefix || '(none)' }}</strong></li>
          <li>Endpoint: <strong>{{ resolved.blob_endpoint || '—' }}</strong></li>
          <li>Key configured: <strong>{{ resolved.account_key_configured ? 'Yes' : 'No' }}</strong></li>
          <li>Connection string configured:
            <strong>{{ resolved.connection_string_configured ? 'Yes' : 'No' }}</strong></li>
        </ul>
      </section>

      <section class="az__card">
        <h2>Background fetch</h2>
        <ul class="az__stats">
          <li>Poll interval: <strong>every {{ sync.poll_hours }} hours</strong></li>
          <li>Delete after successful fetch:
            <strong>{{ sync.delete_after_fetch ? 'Yes' : 'No' }}</strong></li>
          <li>Last run: <strong>{{ formatSyncAt(sync.last_sync_at) }}</strong></li>
          <li v-if="sync.summary">
            Last result:
            <strong>{{ Number(sync.summary.imported_count || 0) }}</strong> imported,
            <strong>{{ Number(sync.summary.deleted_count || 0) }}</strong> deleted,
            <strong>{{ Number(sync.summary.error_count || 0) }}</strong> errors
            ({{ Number(sync.summary.files_seen || 0) }} seen)
          </li>
        </ul>
        <div class="az__actions">
          <button type="button" class="az__save" :disabled="fetching" @click="fetchNow">
            {{ fetching ? 'Fetching…' : 'Fetch now' }}
          </button>
        </div>
        <p class="az__hint">
          Scheduler command: <code>php artisan finance:fetch-azure-sap-dumps</code>
          (requires <code>schedule:run</code> every minute on the Finance API host).
        </p>
      </section>

      <section class="az__card">
        <label class="az__field">
          <span>Connection string</span>
          <textarea
            v-model="form.azure_sap_connection_string"
            class="az__textarea"
            rows="3"
            placeholder="Leave blank to keep existing / use env"
            autocomplete="off"
          />
        </label>
        <label class="az__check">
          <input v-model="clearConnectionString" type="checkbox" />
          Clear stored connection string (fall back to env)
        </label>

        <label class="az__field">
          <span>Storage account name</span>
          <input v-model="form.azure_sap_account_name" class="az__input" autocomplete="off" />
        </label>

        <label class="az__field">
          <span>Account key</span>
          <input
            v-model="form.azure_sap_account_key"
            class="az__input"
            type="password"
            placeholder="Leave blank to keep existing / use env"
            autocomplete="new-password"
          />
        </label>
        <label class="az__check">
          <input v-model="clearAccountKey" type="checkbox" />
          Clear stored account key (fall back to env)
        </label>

        <label class="az__field">
          <span>Container</span>
          <input
            v-model="form.azure_sap_container"
            class="az__input"
            placeholder="sap"
            autocomplete="off"
          />
        </label>

        <label class="az__field">
          <span>Blob prefix (optional)</span>
          <input
            v-model="form.azure_sap_prefix"
            class="az__input"
            placeholder="e.g. DEV/"
            autocomplete="off"
          />
        </label>

        <div class="az__actions">
          <button type="button" class="az__save" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <button type="button" class="az__test" :disabled="testing" @click="testConnection">
            {{ testing ? 'Testing…' : 'Test connection' }}
          </button>
        </div>

        <pre v-if="probeLines.length" class="az__probe">{{ probeLines.join('\n') }}</pre>
      </section>
    </template>
  </div>
</template>

<style scoped>
.az { width: 100%; max-width: 720px; padding-bottom: 2rem; }
.az__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;
}
.az__header h1 { margin: 0; font-size: 1.45rem; font-weight: 700; color: #0b3b5c; }
.az__sub { margin: 0.4rem 0 0; color: #64748b; line-height: 1.45; }
.az__sub code { font-size: 0.85em; }
.az__links { display: flex; flex-direction: column; gap: 0.35rem; align-items: flex-end; }
.az__link { color: #0b3b5c; font-weight: 650; text-decoration: none; white-space: nowrap; }
.az__card {
  background: #fff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 2px;
  padding: 1.1rem 1.2rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); margin-bottom: 1rem;
}
.az__card h2 { margin: 0 0 0.75rem; font-size: 1.05rem; color: #0b3b5c; }
.az__stats { margin: 0; padding-left: 1.1rem; line-height: 1.7; }
.az__src { color: #94a3b8; font-size: 0.85em; margin-left: 0.35rem; }
.az__field { display: grid; gap: 0.4rem; margin-bottom: 0.85rem; }
.az__field span { font-size: 0.85rem; font-weight: 600; color: #334155; }
.az__input, .az__textarea {
  border: 1px solid rgba(15, 23, 42, 0.15); border-radius: 2px;
  padding: 0.5rem 0.65rem; background: #fff; font: inherit;
}
.az__check {
  display: flex; align-items: center; gap: 0.45rem; margin: -0.35rem 0 0.85rem;
  font-size: 0.88rem; color: #475569;
}
.az__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
.az__save, .az__test {
  border: none; font-weight: 700; border-radius: 2px; padding: 0.5rem 1.1rem; cursor: pointer;
}
.az__save { background: #0b3b5c; color: #fff; }
.az__test { background: #fff; color: #0b3b5c; border: 1px solid #0b3b5c; }
.az__save:disabled, .az__test:disabled { opacity: 0.65; cursor: wait; }
.az__error { color: #b42318; }
.az__ok { color: #166534; }
.az__hint { margin: 0.85rem 0 0; color: #64748b; font-size: 0.85rem; line-height: 1.45; }
.az__hint code { font-size: 0.9em; }
.az__probe {
  margin: 1rem 0 0; padding: 0.75rem; background: #f8fafc; border: 1px solid rgba(15, 23, 42, 0.08);
  font-size: 0.82rem; white-space: pre-wrap; overflow: auto;
}
</style>
