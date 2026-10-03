<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import SettingsSubnav from '@/components/molecules/SettingsSubnav.vue'
import { api } from '@/lib/api'

type Snapshot = {
  settings: {
    staff_api_base_url: string
    staff_api_username: string
    staff_api_password: string
    staff_api_token: string
  }
  resolved: {
    base_url: string
    username: string
    password_configured: boolean
    token_configured: boolean
    token_preview: string
  }
  sources: Record<string, string>
  db_overrides: Record<string, boolean>
  docs_url: string
}

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const probeLines = ref<string[]>([])
const clearPassword = ref(false)
const clearToken = ref(false)
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
    const { data } = await api.get<{ data: Snapshot }>('/api/v1/settings/staff-api')
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
  success.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/settings/staff-api', {
      ...form,
      clear_password: clearPassword.value,
      clear_token: clearToken.value,
    })
    success.value = data.message || 'Saved.'
    clearPassword.value = false
    clearToken.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save')
  } finally {
    saving.value = false
  }
}

async function testConnection() {
  testing.value = true
  error.value = null
  success.value = null
  probeLines.value = []
  try {
    const payload: Record<string, string> = {}
    if (form.staff_api_base_url.trim()) payload.staff_api_base_url = form.staff_api_base_url.trim()
    if (form.staff_api_username.trim()) payload.staff_api_username = form.staff_api_username.trim()
    if (form.staff_api_password.trim()) payload.staff_api_password = form.staff_api_password
    if (form.staff_api_token.trim()) payload.staff_api_token = form.staff_api_token.trim()
    const { data } = await api.post<{
      success: boolean
      message: string
      details?: string[]
      status?: string
      mode?: string
    }>('/api/v1/settings/staff-api/test', payload)
    probeLines.value = data.details || []
    if (data.success) success.value = data.message
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
  <div>
    <PortalPageChrome
      title="Staff API credentials"
      lede="Share API base URL, username/password (JWT), and static token. DB values override env; leave blank to use env / defaults. Modules inherit when their own DB overrides are empty."
    />
    <SettingsSubnav />
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>
    <template v-else>
      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1">Resolved (effective)</v-card-title>
        <v-card-text>
          <div><strong>Base:</strong> <code>{{ resolved.base_url }}</code> <span class="text-caption">({{ sources.base_url }})</span></div>
          <div><strong>User:</strong> {{ resolved.username || '—' }} <span class="text-caption">({{ sources.username }})</span></div>
          <div><strong>Password:</strong> {{ resolved.password_configured ? 'set' : 'empty' }} <span class="text-caption">({{ sources.password }})</span></div>
          <div><strong>Token:</strong> {{ resolved.token_preview || '—' }} <span class="text-caption">({{ sources.token }})</span></div>
        </v-card-text>
      </v-card>

      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1">DB overrides</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.staff_api_base_url" label="STAFF_API base URL (optional DB override)" density="compact" class="mb-2" hint="Empty = use env / default …/staff/backend" persistent-hint />
          <v-text-field v-model="form.staff_api_username" label="Username (optional)" density="compact" class="mb-2" />
          <v-text-field v-model="form.staff_api_password" label="Password (leave blank to keep)" type="password" density="compact" class="mb-2" autocomplete="new-password" />
          <v-checkbox v-model="clearPassword" label="Clear DB password (fall back to env)" density="compact" hide-details class="mb-2" />
          <v-text-field v-model="form.staff_api_token" label="Static token (leave blank to keep)" type="password" density="compact" class="mb-2" autocomplete="new-password" />
          <v-checkbox v-model="clearToken" label="Clear DB token (fall back to env / default)" density="compact" hide-details />
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" variant="outlined" :loading="testing" @click="testConnection">Test connection</v-btn>
          <v-spacer />
          <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
        </v-card-actions>
      </v-card>

      <v-card v-if="probeLines.length" variant="outlined">
        <v-card-title class="text-subtitle-1">Connection test</v-card-title>
        <v-card-text>
          <div v-for="(line, i) in probeLines" :key="i" class="text-body-2 font-mono">{{ line }}</div>
        </v-card-text>
      </v-card>
    </template>
  </div>
</template>
