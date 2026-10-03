<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
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
  try {
    const { data } = await api.get<{ data: any }>('/api/v1/admin/settings/staff-api')
    const d = data.data
    form.staff_api_base_url = d.settings.staff_api_base_url || ''
    form.staff_api_username = d.settings.staff_api_username || ''
    form.staff_api_password = ''
    form.staff_api_token = ''
    Object.assign(resolved, d.resolved)
    Object.assign(sources, d.sources)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not load Staff API settings'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const { data } = await api.put<{ message: string }>('/api/v1/admin/settings/staff-api', {
      ...form,
      clear_password: clearPassword.value,
      clear_token: clearToken.value,
    })
    notifySuccess(data.message || 'Saved.')
    clearPassword.value = false
    clearToken.value = false
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not save'))
  } finally {
    saving.value = false
  }
}

async function testConnection() {
  testing.value = true
  probeLines.value = []
  try {
    const payload: Record<string, string> = {}
    if (form.staff_api_base_url.trim()) payload.staff_api_base_url = form.staff_api_base_url.trim()
    if (form.staff_api_username.trim()) payload.staff_api_username = form.staff_api_username.trim()
    if (form.staff_api_password.trim()) payload.staff_api_password = form.staff_api_password
    if (form.staff_api_token.trim()) payload.staff_api_token = form.staff_api_token.trim()
    const { data } = await api.post<{ success: boolean; message: string; details?: string[] }>(
      '/api/v1/admin/settings/staff-api/test',
      payload,
    )
    probeLines.value = data.details || []
    if (data.success) notifySuccess(data.message)
    else notifyError(data.message || 'Connection failed')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Connection test failed'))
  } finally {
    testing.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-if="loading">Loading…</div>
  <div v-else>
    <v-alert type="info" variant="tonal" class="mb-4">
      DB values override env. Empty DB fields fall back to env / Staff Portal defaults.
      Effective: <code>{{ resolved.base_url }}</code>
    </v-alert>
    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">Resolved</v-card-title>
      <v-card-text class="text-body-2">
        <div>Base: <code>{{ resolved.base_url }}</code> ({{ sources.base_url }})</div>
        <div>User: {{ resolved.username || '—' }} ({{ sources.username }})</div>
        <div>Password: {{ resolved.password_configured ? 'set' : 'empty' }} ({{ sources.password }})</div>
        <div>Token: {{ resolved.token_preview || '—' }} ({{ sources.token }})</div>
      </v-card-text>
    </v-card>
    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">DB overrides</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.staff_api_base_url" label="Base URL" density="compact" class="mb-2" />
        <v-text-field v-model="form.staff_api_username" label="Username" density="compact" class="mb-2" />
        <v-text-field v-model="form.staff_api_password" label="Password (blank = keep)" type="password" density="compact" class="mb-2" autocomplete="new-password" />
        <v-checkbox v-model="clearPassword" label="Clear DB password" density="compact" hide-details class="mb-2" />
        <v-text-field v-model="form.staff_api_token" label="Static token (blank = keep)" type="password" density="compact" class="mb-2" autocomplete="new-password" />
        <v-checkbox v-model="clearToken" label="Clear DB token" density="compact" hide-details />
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" variant="outlined" :loading="testing" @click="testConnection">Test connection</v-btn>
        <v-spacer />
        <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
      </v-card-actions>
    </v-card>
    <v-card v-if="probeLines.length" variant="outlined">
      <v-card-title class="text-subtitle-1">Probe</v-card-title>
      <v-card-text>
        <div v-for="(line, i) in probeLines" :key="i" class="font-mono text-body-2">{{ line }}</div>
      </v-card-text>
    </v-card>
  </div>
</template>
