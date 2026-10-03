<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const clearPassword = ref(false)
const clearToken = ref(false)
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
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load Staff API settings')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/settings/staff-api', {
      ...form,
      clear_password: clearPassword.value,
      clear_token: clearToken.value,
    })
    message.value = data.message || 'Staff API settings saved.'
    clearPassword.value = false
    clearToken.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save Staff API settings')
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
    testing.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>Staff API credentials</h1>
        <p class="rr-page__sub">
          Share API base URL, username/password (JWT), and static token. DB values override env; leave blank to use env / Staff Portal defaults.
        </p>
      </div>
      <RouterLink class="rr-btn rr-btn--ghost" :to="{ name: 'risk-settings' }">Back to settings</RouterLink>
    </header>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>
    <p v-if="loading" class="rr-muted">Loading…</p>

    <template v-else>
      <section class="rr-card">
        <h2>Resolved (effective)</h2>
        <p class="rr-muted">
          Base: <code>{{ resolved.base_url }}</code> ({{ sources.base_url }})
        </p>
        <p class="rr-muted">User: {{ resolved.username || '—' }} ({{ sources.username }})</p>
        <p class="rr-muted">
          Password: {{ resolved.password_configured ? 'set' : 'empty' }} ({{ sources.password }})
        </p>
        <p class="rr-muted">Token: {{ resolved.token_preview || '—' }} ({{ sources.token }})</p>
      </section>

      <section class="rr-card">
        <h2>DB overrides</h2>
        <label class="rr-field">
          <span>Base URL</span>
          <input v-model="form.staff_api_base_url" type="url" placeholder="http://127.0.0.1/staff/backend" />
        </label>
        <label class="rr-field">
          <span>Username</span>
          <input v-model="form.staff_api_username" type="text" autocomplete="username" />
        </label>
        <label class="rr-field">
          <span>Password (blank = keep)</span>
          <input v-model="form.staff_api_password" type="password" autocomplete="new-password" />
        </label>
        <label class="rr-check">
          <input v-model="clearPassword" type="checkbox" />
          Clear DB password
        </label>
        <label class="rr-field">
          <span>Static token (blank = keep)</span>
          <input v-model="form.staff_api_token" type="password" autocomplete="new-password" />
        </label>
        <label class="rr-check">
          <input v-model="clearToken" type="checkbox" />
          Clear DB token
        </label>
        <div class="rr-actions">
          <button type="button" class="rr-btn rr-btn--ghost" :disabled="testing" @click="testConnection">
            {{ testing ? 'Testing…' : 'Test connection' }}
          </button>
          <button type="button" class="rr-btn" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </section>

      <section v-if="probeLines.length" class="rr-card">
        <h2>Probe</h2>
        <div v-for="(line, i) in probeLines" :key="i" class="rr-mono">{{ line }}</div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.rr-field span {
  font-size: 0.85rem;
  font-weight: 600;
}
.rr-field input {
  border: 1px solid rgba(58, 71, 82, 0.2);
  border-radius: 0.45rem;
  padding: 0.5rem 0.65rem;
  font: inherit;
}
.rr-check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
}
.rr-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.5rem;
}
.rr-btn--ghost {
  text-decoration: none;
  background: #fff;
  color: #0d7a3a;
  border: 1px solid #0d7a3a;
}
.rr-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.85rem;
  margin: 0.2rem 0;
}
</style>
