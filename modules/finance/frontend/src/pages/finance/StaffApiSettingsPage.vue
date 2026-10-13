<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { RouterLink } from 'vue-router'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const loading = ref(true)
const savingStaff = ref(false)
const savingApm = ref(false)
const testingStaff = ref(false)
const testingApm = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const clearPassword = ref(false)
const clearToken = ref(false)
const clearApmPassword = ref(false)
const probeLines = ref<string[]>([])
const resolved = reactive({
  base_url: '',
  username: '',
  password_configured: false,
  token_configured: false,
  token_preview: '',
})
const sources = reactive<Record<string, string>>({})
const form = reactive({
  staff_api_base_url: '',
  staff_api_username: '',
  staff_api_password: '',
  staff_api_token: '',
})
const apmResolved = reactive({
  base_url: '',
  email: '',
  password_configured: false,
  api_prefix: '',
})
const apmSources = reactive<Record<string, string>>({})
const apmForm = reactive({
  apm_api_base_url: '',
  apm_api_email: '',
  apm_api_password: '',
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: any }>('/api/v1/settings/staff-api')
    const d = data.data
    form.staff_api_base_url = d.settings.staff_api_base_url || ''
    form.staff_api_username = d.settings.staff_api_username || ''
    form.staff_api_password = ''
    form.staff_api_token = ''
    Object.assign(resolved, d.resolved)
    Object.assign(sources, d.sources)

    const apm = d.apm || {}
    apmForm.apm_api_base_url = apm.settings?.apm_api_base_url || ''
    apmForm.apm_api_email = apm.settings?.apm_api_email || ''
    apmForm.apm_api_password = ''
    Object.assign(apmResolved, apm.resolved || {})
    Object.assign(apmSources, apm.sources || {})
    clearApmPassword.value = false
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load Staff API settings')
  } finally {
    loading.value = false
  }
}

async function saveStaff() {
  savingStaff.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/settings/staff-api', {
      ...form,
      clear_password: clearPassword.value,
      clear_token: clearToken.value,
      section: 'staff',
    })
    message.value = data.message || 'Staff API settings saved.'
    clearPassword.value = false
    clearToken.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save Staff API settings')
  } finally {
    savingStaff.value = false
  }
}

async function saveApm() {
  savingApm.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/settings/staff-api', {
      ...apmForm,
      clear_apm_password: clearApmPassword.value,
      section: 'apm',
    })
    message.value = data.message || 'APM API settings saved.'
    clearApmPassword.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save APM API settings')
  } finally {
    savingApm.value = false
  }
}

async function testStaffConnection() {
  testingStaff.value = true
  error.value = null
  message.value = null
  probeLines.value = []
  try {
    const payload: Record<string, string> = { target: 'staff' }
    if (form.staff_api_base_url.trim()) payload.staff_api_base_url = form.staff_api_base_url.trim()
    if (form.staff_api_username.trim()) payload.staff_api_username = form.staff_api_username.trim()
    if (form.staff_api_password.trim()) payload.staff_api_password = form.staff_api_password
    if (form.staff_api_token.trim()) payload.staff_api_token = form.staff_api_token.trim()
    const { data } = await api.post<{ success: boolean; message: string; details?: string[] }>(
      '/api/v1/settings/staff-api/test',
      payload,
    )
    probeLines.value = data.details || []
    if (data.success) message.value = data.message
    else error.value = data.message || 'Connection failed'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Connection test failed')
  } finally {
    testingStaff.value = false
  }
}

async function testApmConnection() {
  testingApm.value = true
  error.value = null
  message.value = null
  probeLines.value = []
  try {
    const payload: Record<string, string> = { target: 'apm' }
    if (apmForm.apm_api_base_url.trim()) payload.apm_api_base_url = apmForm.apm_api_base_url.trim()
    if (apmForm.apm_api_email.trim()) payload.apm_api_email = apmForm.apm_api_email.trim()
    if (apmForm.apm_api_password.trim()) payload.apm_api_password = apmForm.apm_api_password
    const { data } = await api.post<{ success: boolean; message: string; details?: string[] }>(
      '/api/v1/settings/staff-api/test',
      payload,
    )
    probeLines.value = data.details || []
    if (data.success) message.value = data.message
    else error.value = data.message || 'APM connection failed'
  } catch (e) {
    error.value = apiErrorMessage(e, 'APM connection test failed')
  } finally {
    testingApm.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="fn-page">
    <header class="fn-page__header">
      <div>
        <h1>API credentials</h1>
        <p class="fn-page__sub">
          Staff Share and APM share the same synced users. Leave APM email/password blank to reuse
          Staff Share credentials for SAP budget sync. DB values override env.
        </p>
      </div>
      <RouterLink class="fn-link" :to="{ name: 'dashboard' }">Back to portfolio</RouterLink>
    </header>

    <p v-if="error" class="fn-error">{{ error }}</p>
    <p v-if="message" class="fn-ok">{{ message }}</p>
    <FinSkeleton v-if="loading" variant="form" :rows="5" />

    <template v-else>
      <section class="fn-card">
        <h2>Staff Share API — resolved</h2>
        <p class="fn-muted">
          Base: <code>{{ resolved.base_url }}</code> ({{ sources.base_url }})
        </p>
        <p class="fn-muted">User: {{ resolved.username || '—' }} ({{ sources.username }})</p>
        <p class="fn-muted">
          Password: {{ resolved.password_configured ? 'set' : 'empty' }} ({{ sources.password }})
        </p>
        <p class="fn-muted">Token: {{ resolved.token_preview || '—' }} ({{ sources.token }})</p>
      </section>

      <section class="fn-card">
        <h2>Staff Share API — DB overrides</h2>
        <label class="fn-field">
          <span>Base URL</span>
          <input v-model="form.staff_api_base_url" type="url" placeholder="http://127.0.0.1/staff/backend" />
        </label>
        <label class="fn-field">
          <span>Username</span>
          <input v-model="form.staff_api_username" type="text" autocomplete="username" />
        </label>
        <label class="fn-field">
          <span>Password (blank = keep)</span>
          <input v-model="form.staff_api_password" type="password" autocomplete="new-password" />
        </label>
        <label class="fn-check">
          <input v-model="clearPassword" type="checkbox" />
          Clear DB password
        </label>
        <label class="fn-field">
          <span>Static token (blank = keep)</span>
          <input v-model="form.staff_api_token" type="password" autocomplete="new-password" />
        </label>
        <label class="fn-check">
          <input v-model="clearToken" type="checkbox" />
          Clear DB token
        </label>
        <div class="fn-actions">
          <button type="button" class="fn-btn fn-btn--ghost" :disabled="testingStaff" @click="testStaffConnection">
            {{ testingStaff ? 'Testing…' : 'Test Staff API' }}
          </button>
          <button type="button" class="fn-btn" :disabled="savingStaff" @click="saveStaff">
            {{ savingStaff ? 'Saving…' : 'Save Staff API' }}
          </button>
        </div>
      </section>

      <section class="fn-card">
        <h2>APM API — resolved (SAP sync)</h2>
        <p class="fn-muted">
          Used by SAP budget import to update APM fund-code budgets via
          <code>POST /api/apm/v1/auth/login</code>. Prefer an
          <code>apm_api_users</code> account with email login enabled — typically the same
          Staff Share username/password (synced from staff).
        </p>
        <p class="fn-muted">
          Base: <code>{{ apmResolved.base_url || '—' }}</code> ({{ apmSources.base_url || 'unset' }})
        </p>
        <p class="fn-muted">Email: {{ apmResolved.email || '—' }} ({{ apmSources.email || 'unset' }})</p>
        <p class="fn-muted">
          Password: {{ apmResolved.password_configured ? 'set' : 'empty' }} ({{ apmSources.password || 'unset' }})
        </p>
        <p class="fn-muted">API prefix: <code>{{ apmResolved.api_prefix || '/api/apm/v1' }}</code></p>
      </section>

      <section class="fn-card">
        <h2>APM API — DB overrides</h2>
        <label class="fn-field">
          <span>APM base URL</span>
          <input v-model="apmForm.apm_api_base_url" type="url" placeholder="http://localhost/staff/apm" />
        </label>
        <label class="fn-field">
          <span>APM API email (blank = Staff Share username)</span>
          <input v-model="apmForm.apm_api_email" type="email" autocomplete="username" placeholder="Same as Staff Share username" />
        </label>
        <label class="fn-field">
          <span>APM API password (blank = keep / Staff Share)</span>
          <input v-model="apmForm.apm_api_password" type="password" autocomplete="new-password" />
        </label>
        <label class="fn-check">
          <input v-model="clearApmPassword" type="checkbox" />
          Clear DB APM password
        </label>
        <div class="fn-actions">
          <button type="button" class="fn-btn fn-btn--ghost" :disabled="testingApm" @click="testApmConnection">
            {{ testingApm ? 'Testing…' : 'Test APM login' }}
          </button>
          <button type="button" class="fn-btn" :disabled="savingApm" @click="saveApm">
            {{ savingApm ? 'Saving…' : 'Save APM API' }}
          </button>
        </div>
      </section>

      <section v-if="probeLines.length" class="fn-card">
        <h2>Probe</h2>
        <div v-for="(line, i) in probeLines" :key="i" class="fn-mono">{{ line }}</div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.fn-page { width: 100%; max-width: none; margin: 0; padding: 1rem 0 2rem; }
.fn-page__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.fn-page__sub { color: #64748b; margin: 0.35rem 0 0; }
.fn-card { background: #fff; border: 1px solid rgba(58,71,82,.12); border-radius: .75rem; padding: 1rem 1.15rem; margin-bottom: 1rem; }
.fn-card h2 { margin: 0 0 .75rem; font-size: 1.05rem; }
.fn-field { display: flex; flex-direction: column; gap: .35rem; margin-bottom: .85rem; }
.fn-field span { font-size: .85rem; font-weight: 600; }
.fn-field input { border: 1px solid rgba(58,71,82,.2); border-radius: .45rem; padding: .5rem .65rem; font: inherit; }
.fn-check { display: flex; align-items: center; gap: .5rem; margin-bottom: .75rem; font-size: .9rem; }
.fn-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .5rem; }
.fn-btn { background: #0d7a3a; color: #fff; border: 0; border-radius: .45rem; padding: .55rem 1rem; font-weight: 600; cursor: pointer; }
.fn-btn--ghost { background: #fff; color: #0d7a3a; border: 1px solid #0d7a3a; }
.fn-btn:disabled { opacity: .6; cursor: wait; }
.fn-link { color: #0d7a3a; font-weight: 600; text-decoration: none; }
.fn-error { color: #b91c1c; }
.fn-ok { color: #0d7a3a; }
.fn-muted { color: #64748b; margin: .25rem 0; }
.fn-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; margin: .2rem 0; }
@media (max-width: 720px) {
  .fn-page__header { flex-direction: column; }
}
</style>
