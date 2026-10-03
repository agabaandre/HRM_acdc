<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type Entry = { timestamp: string; level: string; env: string; message: string }

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const ok = ref<string | null>(null)
const dates = ref<string[]>([])
const levels = ref<string[]>([])
const entries = ref<Entry[]>([])
const levelCounts = ref<Record<string, number>>({})
const providers = ref<string[]>(['none', 'datadog', 'sentry', 'newrelic', 'otel'])
const meta = reactive({
  channel: '',
  stack: '',
  log_level: '',
  file: '',
  file_exists: false,
  file_size_human: '',
  total: 0,
  page: 1,
  last_page: 1,
})
const filters = reactive({ date: '', level: '', min_level: '', q: '' })
const telemetryForm = reactive({
  telemetry_provider: 'none',
  telemetry_service: '',
  telemetry_environment: '',
  telemetry_datadog_agent_host: '127.0.0.1',
  telemetry_datadog_trace_enabled: 'true',
  telemetry_datadog_logs_injection: 'true',
  telemetry_sentry_dsn: '',
  telemetry_sentry_traces_sample_rate: '0.0',
  telemetry_newrelic_app_name: '',
  telemetry_newrelic_license_key: '',
  telemetry_otel_endpoint: '',
  clear_sentry_dsn: false,
  clear_newrelic_license: false,
})
const telemetryResolved = reactive({
  provider: 'none',
  service: '',
  environment: '',
  sentry_dsn_configured: false,
  newrelic_license_configured: false,
})
const testingAlert = ref(false)
const criticalForm = reactive({
  critical_alert_enabled: 'false',
  critical_alert_emails: '',
  critical_alert_min_level: 'error',
  critical_alert_cooldown_minutes: '30',
})
const criticalResolved = reactive({
  enabled: false,
  emails_display: '',
  min_level: 'error',
  cooldown_minutes: 30,
})

function applyCritical(c: any) {
  if (!c) return
  const s = c.settings || {}
  const r = c.resolved || {}
  Object.assign(criticalResolved, {
    enabled: !!r.enabled,
    emails_display: r.emails_display || '',
    min_level: r.min_level || 'error',
    cooldown_minutes: r.cooldown_minutes || 30,
  })
  Object.assign(criticalForm, {
    critical_alert_enabled: s.critical_alert_enabled || (r.enabled ? 'true' : 'false'),
    critical_alert_emails: s.critical_alert_emails || r.emails_display || '',
    critical_alert_min_level: s.critical_alert_min_level || r.min_level || 'error',
    critical_alert_cooldown_minutes: s.critical_alert_cooldown_minutes || String(r.cooldown_minutes ?? 30),
  })
}

function applyTelemetry(t: any) {
  if (!t) return
  providers.value = t.providers || providers.value
  const s = t.settings || {}
  const r = t.resolved || {}
  Object.assign(telemetryResolved, {
    provider: r.provider || 'none',
    service: r.service || '',
    environment: r.environment || '',
    sentry_dsn_configured: !!r.sentry_dsn_configured,
    newrelic_license_configured: !!r.newrelic_license_configured,
  })
  Object.assign(telemetryForm, {
    telemetry_provider: s.telemetry_provider || r.provider || 'none',
    telemetry_service: s.telemetry_service || r.service || '',
    telemetry_environment: s.telemetry_environment || r.environment || '',
    telemetry_datadog_agent_host: s.telemetry_datadog_agent_host || r.datadog_agent_host || '127.0.0.1',
    telemetry_datadog_trace_enabled: s.telemetry_datadog_trace_enabled || (r.datadog_trace_enabled ? 'true' : 'false'),
    telemetry_datadog_logs_injection: s.telemetry_datadog_logs_injection || (r.datadog_logs_injection ? 'true' : 'false'),
    telemetry_sentry_dsn: '',
    telemetry_sentry_traces_sample_rate: s.telemetry_sentry_traces_sample_rate || r.sentry_traces_sample_rate || '0.0',
    telemetry_newrelic_app_name: s.telemetry_newrelic_app_name || r.newrelic_app_name || '',
    telemetry_newrelic_license_key: '',
    telemetry_otel_endpoint: s.telemetry_otel_endpoint || r.otel_endpoint || '',
    clear_sentry_dsn: false,
    clear_newrelic_license: false,
  })
}

async function load(page = 1) {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: any }>('/api/v1/settings/app-logs', {
      params: {
        date: filters.date || undefined,
        level: filters.level || undefined,
        min_level: filters.min_level || undefined,
        q: filters.q || undefined,
        page,
      },
    })
    const d = data.data
    dates.value = d.dates || []
    levels.value = d.levels || []
    entries.value = d.entries || []
    levelCounts.value = d.level_counts || {}
    if (!filters.date) filters.date = d.date || d.default_date || ''
    Object.assign(meta, {
      channel: d.channel || '',
      stack: d.stack || '',
      log_level: d.log_level || '',
      file: d.file || '',
      file_exists: !!d.file_exists,
      file_size_human: d.file_size_human || '',
      total: d.total || 0,
      page: d.page || 1,
      last_page: d.last_page || 1,
    })
    applyTelemetry(d.telemetry)
    applyCritical(d.critical_alerts)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load application logs')
  } finally {
    loading.value = false
  }
}

async function saveTelemetry() {
  saving.value = true
  error.value = null
  ok.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string; data: any }>(
      '/api/v1/settings/app-logs/telemetry',
      { ...telemetryForm, ...criticalForm },
    )
    ok.value = data.message || 'Telemetry settings saved'
    const payload = data.data || {}
    applyTelemetry(payload.telemetry || payload)
    applyCritical(payload.critical_alerts)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save telemetry settings')
  } finally {
    saving.value = false
  }
}

async function testCriticalAlert() {
  testingAlert.value = true
  error.value = null
  ok.value = null
  try {
    const { data } = await api.post<{ success: boolean; message: string }>(
      '/api/v1/settings/app-logs/critical-alert/test',
    )
    ok.value = data.message || 'Test alert sent'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send test alert')
  } finally {
    testingAlert.value = false
  }
}

watch(
  () => [filters.date, filters.level, filters.min_level],
  () => {
    void load(1)
  },
)

onMounted(() => {
  void load(1)
})
</script>

<template>
  <div class="fn-page">
    <header class="fn-page__header">
      <div>
        <h1>Application logs &amp; telemetry</h1>
        <p class="fn-page__sub">
          Channel <code>{{ meta.channel }}</code> · stack <code>{{ meta.stack }}</code>
          · LOG_LEVEL <code>{{ meta.log_level }}</code>
          · file <code>{{ meta.file || '—' }}</code>{{ meta.file_exists ? ` · ${meta.file_size_human}` : ' (missing)' }}
        </p>
      </div>
      <RouterLink class="fn-link" :to="{ name: 'dashboard' }">Back</RouterLink>
    </header>
    <p v-if="error" class="fn-error">{{ error }}</p>
    <p v-if="ok" class="fn-ok">{{ ok }}</p>

    <section class="fn-card">
      <h2>Telemetry / APM</h2>
      <p class="fn-muted">
        Resolved: <code>{{ telemetryResolved.provider }}</code>
        · <code>{{ telemetryResolved.service || '—' }}</code>
        · <code>{{ telemetryResolved.environment || '—' }}</code>
      </p>
      <div class="fn-filters">
        <label class="fn-field">
          <span>Provider</span>
          <select v-model="telemetryForm.telemetry_provider">
            <option v-for="p in providers" :key="p" :value="p">{{ p }}</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Service</span>
          <input v-model="telemetryForm.telemetry_service" type="text" />
        </label>
        <label class="fn-field">
          <span>Environment</span>
          <input v-model="telemetryForm.telemetry_environment" type="text" />
        </label>
        <label class="fn-field">
          <span>Datadog agent host</span>
          <input v-model="telemetryForm.telemetry_datadog_agent_host" type="text" />
        </label>
        <label class="fn-field">
          <span>Datadog tracing</span>
          <select v-model="telemetryForm.telemetry_datadog_trace_enabled">
            <option value="true">enabled</option>
            <option value="false">disabled</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Sentry DSN{{ telemetryResolved.sentry_dsn_configured ? ' (configured)' : '' }}</span>
          <input v-model="telemetryForm.telemetry_sentry_dsn" type="password" autocomplete="off" placeholder="Leave blank to keep" />
        </label>
        <label class="fn-field fn-field--check">
          <input v-model="telemetryForm.clear_sentry_dsn" type="checkbox" />
          <span>Clear Sentry DSN</span>
        </label>
        <label class="fn-field">
          <span>Sentry sample rate</span>
          <input v-model="telemetryForm.telemetry_sentry_traces_sample_rate" type="text" />
        </label>
        <label class="fn-field">
          <span>New Relic app</span>
          <input v-model="telemetryForm.telemetry_newrelic_app_name" type="text" />
        </label>
        <label class="fn-field">
          <span>New Relic license{{ telemetryResolved.newrelic_license_configured ? ' (configured)' : '' }}</span>
          <input v-model="telemetryForm.telemetry_newrelic_license_key" type="password" autocomplete="off" placeholder="Leave blank to keep" />
        </label>
        <label class="fn-field fn-field--check">
          <input v-model="telemetryForm.clear_newrelic_license" type="checkbox" />
          <span>Clear New Relic license</span>
        </label>
        <label class="fn-field">
          <span>OTLP endpoint</span>
          <input v-model="telemetryForm.telemetry_otel_endpoint" type="text" placeholder="http://localhost:4318" />
        </label>
        <button type="button" class="fn-btn" :disabled="saving" @click="saveTelemetry">{{ saving ? 'Saving…' : 'Save telemetry & alerts' }}</button>
      </div>
    </section>

    <section class="fn-card">
      <h2>Critical error admin emails</h2>
      <p class="fn-muted">
        {{ criticalResolved.enabled ? 'Enabled' : 'Disabled' }}
        · min {{ criticalResolved.min_level }}
        · cooldown {{ criticalResolved.cooldown_minutes }}m
        · {{ criticalResolved.emails_display || 'no recipients' }}
      </p>
      <div class="fn-filters">
        <label class="fn-field">
          <span>Alerts</span>
          <select v-model="criticalForm.critical_alert_enabled">
            <option value="true">enabled</option>
            <option value="false">disabled</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Admin emails</span>
          <input v-model="criticalForm.critical_alert_emails" type="text" placeholder="ops@example.org, oncall@example.org" />
        </label>
        <label class="fn-field">
          <span>Min level</span>
          <select v-model="criticalForm.critical_alert_min_level">
            <option value="error">ERROR+</option>
            <option value="critical">CRITICAL+</option>
            <option value="emergency">EMERGENCY only</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Cooldown (min)</span>
          <input v-model="criticalForm.critical_alert_cooldown_minutes" type="number" min="1" max="1440" />
        </label>
        <button type="button" class="fn-btn" :disabled="saving" @click="saveTelemetry">{{ saving ? 'Saving…' : 'Save' }}</button>
        <button type="button" class="fn-btn fn-btn--ghost" :disabled="testingAlert" @click="testCriticalAlert">{{ testingAlert ? 'Sending…' : 'Send test' }}</button>
      </div>
    </section>

    <section class="fn-card">
      <div class="fn-filters">
        <label class="fn-field">
          <span>Date</span>
          <select v-model="filters.date">
            <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Exact level</span>
          <select v-model="filters.level">
            <option value="">All levels</option>
            <option v-for="l in levels" :key="l" :value="l">{{ l.toUpperCase() }}{{ levelCounts[l] != null ? ` (${levelCounts[l]})` : '' }}</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Min severity</span>
          <select v-model="filters.min_level">
            <option value="">—</option>
            <option value="error">ERROR+</option>
            <option value="warning">WARNING+</option>
            <option value="info">INFO+</option>
            <option value="debug">DEBUG+</option>
          </select>
        </label>
        <label class="fn-field">
          <span>Contains</span>
          <input v-model="filters.q" type="text" @keyup.enter="load(1)" />
        </label>
        <button type="button" class="fn-btn" :disabled="loading" @click="load(1)">{{ loading ? 'Loading…' : 'Filter' }}</button>
      </div>
    </section>
    <p class="fn-muted">{{ meta.total }} matching entries · page {{ meta.page }} / {{ meta.last_page }}</p>
    <div class="fn-pager">
      <button type="button" class="fn-btn fn-btn--ghost" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Prev</button>
      <button type="button" class="fn-btn fn-btn--ghost" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">Next</button>
    </div>
    <section v-for="(entry, i) in entries" :key="i" class="fn-card">
      <div class="fn-entry-meta">
        <strong class="fn-level" :data-level="entry.level">{{ entry.level.toUpperCase() }}</strong>
        <code>{{ entry.timestamp }}</code>
      </div>
      <pre class="fn-msg">{{ entry.message }}</pre>
    </section>
    <p v-if="!loading && !entries.length" class="fn-muted">No log entries for this filter.</p>
  </div>
</template>

<style scoped>
.fn-page { max-width: 960px; margin: 0 auto; padding: 1rem 1.25rem 2rem; }
.fn-page__header { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.fn-page__sub { color: #64748b; margin: 0.35rem 0 0; }
.fn-card { background: #fff; border: 1px solid rgba(58,71,82,.12); border-radius: .75rem; padding: 1rem 1.15rem; margin-bottom: .75rem; }
.fn-card h2 { margin: 0 0 .5rem; font-size: 1.05rem; }
.fn-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
.fn-field { display: flex; flex-direction: column; gap: .35rem; }
.fn-field--check { flex-direction: row; align-items: center; gap: .4rem; }
.fn-field span { font-size: .85rem; font-weight: 600; }
.fn-field input, .fn-field select { border: 1px solid rgba(58,71,82,.2); border-radius: .45rem; padding: .5rem .65rem; font: inherit; }
.fn-btn { background: #0d7a3a; color: #fff; border: 0; border-radius: .45rem; padding: .55rem 1rem; font-weight: 600; cursor: pointer; }
.fn-btn--ghost { background: #fff; color: #0d7a3a; border: 1px solid #0d7a3a; }
.fn-btn:disabled { opacity: .6; }
.fn-link { color: #0d7a3a; font-weight: 600; text-decoration: none; }
.fn-error { color: #b91c1c; }
.fn-ok { color: #0d7a3a; }
.fn-muted { color: #64748b; }
.fn-pager { display: flex; gap: .5rem; margin-bottom: .75rem; }
.fn-entry-meta { display: flex; gap: .75rem; align-items: center; margin-bottom: .35rem; font-size: .85rem; }
.fn-level[data-level="error"], .fn-level[data-level="critical"] { color: #b91c1c; }
.fn-level[data-level="warning"] { color: #b45309; }
.fn-msg { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: .85rem; }
</style>
