<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

type Entry = { timestamp: string; level: string; env: string; message: string }

const loading = ref(true)
const saving = ref(false)
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
  try {
    const { data } = await api.get<{ data: any }>('/api/v1/admin/settings/app-logs', {
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
    notifyError(apiErrorMessage(e, 'Could not load application logs'))
  } finally {
    loading.value = false
  }
}

async function saveTelemetry() {
  saving.value = true
  try {
    const { data } = await api.put<{ success: boolean; message: string; data: any }>(
      '/api/v1/admin/settings/app-logs/telemetry',
      { ...telemetryForm, ...criticalForm },
    )
    notifySuccess(data.message || 'Telemetry settings saved')
    const payload = data.data || {}
    applyTelemetry(payload.telemetry || payload)
    applyCritical(payload.critical_alerts)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not save telemetry settings'))
  } finally {
    saving.value = false
  }
}

async function testCriticalAlert() {
  testingAlert.value = true
  try {
    const { data } = await api.post<{ success: boolean; message: string }>(
      '/api/v1/admin/settings/app-logs/critical-alert/test',
    )
    notifySuccess(data.message || 'Test alert sent')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not send test alert'))
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
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      Laravel daily logs + telemetry. Channel <code>{{ meta.channel }}</code> · stack <code>{{ meta.stack }}</code>
      · LOG_LEVEL <code>{{ meta.log_level }}</code>
      · file <code>{{ meta.file || '—' }}</code>{{ meta.file_exists ? ` · ${meta.file_size_human}` : ' (missing)' }}
    </v-alert>

    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">Telemetry / APM</v-card-title>
      <v-card-subtitle>
        Resolved: <code>{{ telemetryResolved.provider }}</code>
        · <code>{{ telemetryResolved.service || '—' }}</code>
        · <code>{{ telemetryResolved.environment || '—' }}</code>
      </v-card-subtitle>
      <v-card-text class="d-flex flex-wrap ga-3">
        <v-select v-model="telemetryForm.telemetry_provider" :items="providers" label="Provider" density="compact" hide-details style="min-width: 10rem" />
        <v-text-field v-model="telemetryForm.telemetry_service" label="Service name" density="compact" hide-details style="min-width: 12rem" />
        <v-text-field v-model="telemetryForm.telemetry_environment" label="Environment" density="compact" hide-details style="min-width: 10rem" />
        <v-text-field v-model="telemetryForm.telemetry_datadog_agent_host" label="Datadog agent host" density="compact" hide-details style="min-width: 12rem" />
        <v-select
          v-model="telemetryForm.telemetry_datadog_trace_enabled"
          :items="[{ title: 'Tracing on', value: 'true' }, { title: 'Tracing off', value: 'false' }]"
          item-title="title"
          item-value="value"
          label="Datadog tracing"
          density="compact"
          hide-details
          style="min-width: 10rem"
        />
        <v-text-field
          v-model="telemetryForm.telemetry_sentry_dsn"
          :label="telemetryResolved.sentry_dsn_configured ? 'Sentry DSN (configured)' : 'Sentry DSN'"
          type="password"
          autocomplete="off"
          density="compact"
          hide-details
          style="min-width: 16rem"
        />
        <v-checkbox v-model="telemetryForm.clear_sentry_dsn" label="Clear Sentry DSN" density="compact" hide-details />
        <v-text-field v-model="telemetryForm.telemetry_sentry_traces_sample_rate" label="Sentry sample rate" density="compact" hide-details style="min-width: 10rem" />
        <v-text-field v-model="telemetryForm.telemetry_newrelic_app_name" label="New Relic app" density="compact" hide-details style="min-width: 12rem" />
        <v-text-field
          v-model="telemetryForm.telemetry_newrelic_license_key"
          :label="telemetryResolved.newrelic_license_configured ? 'New Relic license (configured)' : 'New Relic license'"
          type="password"
          autocomplete="off"
          density="compact"
          hide-details
          style="min-width: 14rem"
        />
        <v-checkbox v-model="telemetryForm.clear_newrelic_license" label="Clear New Relic license" density="compact" hide-details />
        <v-text-field v-model="telemetryForm.telemetry_otel_endpoint" label="OTLP endpoint" density="compact" hide-details style="min-width: 16rem" />
        <v-btn color="primary" :loading="saving" @click="saveTelemetry">Save telemetry &amp; alerts</v-btn>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">Critical error admin emails</v-card-title>
      <v-card-subtitle>
        {{ criticalResolved.enabled ? 'Enabled' : 'Disabled' }}
        · min {{ criticalResolved.min_level }}
        · cooldown {{ criticalResolved.cooldown_minutes }}m
        · {{ criticalResolved.emails_display || 'no recipients' }}
      </v-card-subtitle>
      <v-card-text class="d-flex flex-wrap ga-3 align-center">
        <v-select
          v-model="criticalForm.critical_alert_enabled"
          :items="[{ title: 'Enabled', value: 'true' }, { title: 'Disabled', value: 'false' }]"
          item-title="title"
          item-value="value"
          label="Alerts"
          density="compact"
          hide-details
          style="min-width: 9rem"
        />
        <v-text-field
          v-model="criticalForm.critical_alert_emails"
          label="Admin emails (comma-separated)"
          density="compact"
          hide-details
          style="min-width: 20rem; flex: 1"
        />
        <v-select
          v-model="criticalForm.critical_alert_min_level"
          :items="[
            { title: 'ERROR+', value: 'error' },
            { title: 'CRITICAL+', value: 'critical' },
            { title: 'EMERGENCY only', value: 'emergency' },
          ]"
          item-title="title"
          item-value="value"
          label="Min level"
          density="compact"
          hide-details
          style="min-width: 11rem"
        />
        <v-text-field
          v-model="criticalForm.critical_alert_cooldown_minutes"
          label="Cooldown (min)"
          type="number"
          density="compact"
          hide-details
          style="min-width: 8rem"
        />
        <v-btn color="primary" :loading="saving" @click="saveTelemetry">Save</v-btn>
        <v-btn variant="outlined" :loading="testingAlert" @click="testCriticalAlert">Send test</v-btn>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="mb-4">
      <v-card-text class="d-flex flex-wrap ga-3 align-center">
        <v-select v-model="filters.date" :items="dates" label="Date" density="compact" hide-details style="min-width: 10rem" />
        <v-select
          v-model="filters.level"
          :items="[{ title: 'All levels', value: '' }, ...levels.map((l) => ({ title: `${l.toUpperCase()}${levelCounts[l] != null ? ` (${levelCounts[l]})` : ''}`, value: l }))]"
          item-title="title"
          item-value="value"
          label="Exact level"
          density="compact"
          hide-details
          style="min-width: 10rem"
        />
        <v-select
          v-model="filters.min_level"
          :items="[
            { title: 'No min', value: '' },
            { title: 'ERROR+', value: 'error' },
            { title: 'WARNING+', value: 'warning' },
            { title: 'INFO+', value: 'info' },
            { title: 'DEBUG+', value: 'debug' },
          ]"
          item-title="title"
          item-value="value"
          label="Min severity"
          density="compact"
          hide-details
          style="min-width: 10rem"
        />
        <v-text-field v-model="filters.q" label="Contains" density="compact" hide-details clearable style="min-width: 14rem" @keyup.enter="load(1)" />
        <v-btn color="primary" :loading="loading" @click="load(1)">Filter</v-btn>
      </v-card-text>
    </v-card>
    <div v-if="loading">Loading…</div>
    <template v-else>
      <div class="d-flex justify-space-between align-center mb-2">
        <div>{{ meta.total }} matching entries</div>
        <div class="d-flex ga-2">
          <v-btn size="small" variant="outlined" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Prev</v-btn>
          <span class="text-caption align-self-center">{{ meta.page }} / {{ meta.last_page }}</span>
          <v-btn size="small" variant="outlined" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">Next</v-btn>
        </div>
      </div>
      <v-card v-for="(entry, i) in entries" :key="i" variant="outlined" class="mb-2">
        <v-card-text class="py-2">
          <div class="d-flex ga-2 align-center mb-1">
            <v-chip size="x-small" :color="['error', 'critical', 'alert', 'emergency'].includes(entry.level) ? 'error' : entry.level === 'warning' ? 'warning' : 'info'">
              {{ entry.level.toUpperCase() }}
            </v-chip>
            <code class="text-caption">{{ entry.timestamp }}</code>
          </div>
          <pre class="text-body-2 mb-0" style="white-space: pre-wrap; word-break: break-word">{{ entry.message }}</pre>
        </v-card-text>
      </v-card>
      <div v-if="!entries.length" class="text-medium-emphasis">No log entries for this filter.</div>
    </template>
  </div>
</template>
