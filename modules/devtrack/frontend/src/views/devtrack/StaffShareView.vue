<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateTime12h } from '@/lib/formatDate'

type StaffShareSettings = {
  enabled: boolean
  base_url: string
  docs_url: string
  has_api_token: boolean
  api_token_hint: string | null
  auth_method: string
  staff_count: number
  last_synced_at: string | null
  last_sync_total: number | null
  last_sync_message: string | null
  updated_at: string | null
}

const loading = ref(true)
const saving = ref(false)
const syncing = ref(false)
const message = ref('')
const error = ref('')
const settings = ref<StaffShareSettings | null>(null)

const form = ref({
  enabled: true,
  base_url: 'https://cbp.africacdc.org/staff/backend',
  api_token: '',
  clear_api_token: false,
})

const showToken = ref(false)
const canSync = computed(() => !!settings.value?.has_api_token || form.value.api_token.trim() !== '')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<{ data: StaffShareSettings }>('/admin/staff-share/settings')
    settings.value = res.data.data
    form.value.enabled = res.data.data.enabled
    form.value.base_url = res.data.data.base_url
    form.value.api_token = ''
    form.value.clear_api_token = false
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load Staff Share settings.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  message.value = ''
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      enabled: form.value.enabled,
      base_url: form.value.base_url.trim(),
    }
    if (form.value.clear_api_token) {
      payload.clear_api_token = true
    } else if (form.value.api_token.trim()) {
      payload.api_token = form.value.api_token.trim()
    }
    const res = await api.put<{ message: string; data: StaffShareSettings }>(
      '/admin/staff-share/settings',
      payload,
    )
    settings.value = res.data.data
    form.value.api_token = ''
    form.value.clear_api_token = false
    message.value = res.data.message || 'Credentials saved.'
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save Staff Share credentials.')
  } finally {
    saving.value = false
  }
}

async function syncNow() {
  if (form.value.api_token.trim() && !settings.value?.has_api_token) {
    await save()
    if (error.value) return
  }

  syncing.value = true
  message.value = ''
  error.value = ''
  try {
    const res = await api.post<{
      message: string
      data: { stats: { staff: Record<string, number> }; settings: StaffShareSettings }
    }>('/admin/staff-share/sync')
    settings.value = res.data.data.settings
    const staff = res.data.data.stats.staff
    message.value =
      res.data.message +
      ` Fetched ${staff.total}, created ${staff.created}, updated ${staff.updated}.`
  } catch (err) {
    error.value = apiErrorMessage(err, 'Staff Share sync failed.')
    await load()
  } finally {
    syncing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Staff Share"
      subtitle="Connect to the Africa CDC Staff Share API with a Bearer token and sync into the staff table"
    />

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <template v-else>
      <v-row class="mb-2">
        <v-col cols="12" sm="4">
          <v-card variant="tonal" color="primary">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Staff rows</div>
              <div class="text-h4 font-weight-bold">{{ settings?.staff_count ?? 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="4">
          <v-card variant="tonal">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Token</div>
              <div class="text-h6 font-weight-bold">
                {{ settings?.has_api_token ? 'Configured' : 'Not set' }}
              </div>
              <div v-if="settings?.api_token_hint" class="text-caption font-mono mt-1">
                {{ settings.api_token_hint }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="4">
          <v-card variant="tonal">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Last sync</div>
              <div class="text-body-1 font-weight-medium">
                {{ settings?.last_synced_at ? formatDateTime12h(settings.last_synced_at) : 'Never' }}
              </div>
              <div v-if="settings?.last_sync_message" class="text-caption mt-1">
                {{ settings.last_sync_message }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="API credentials (Bearer token)">
        <p class="text-body-2 text-medium-emphasis mb-4">
          Per the
          <a :href="settings?.docs_url || 'https://cbp.africacdc.org/staff/backend/share/docs'" target="_blank" rel="noopener">
            Staff Share API docs
          </a>,
          directory sync uses a static API token:
          <code>Authorization: Bearer {STAFF_API_TOKEN}</code>
          against <code>GET /share/get_current_staff</code>.
        </p>

        <v-row>
          <v-col cols="12" md="8" class="form-stack">
            <FormField label="Enabled">
              <v-switch
                v-model="form.enabled"
                color="primary"
                hide-details
                density="compact"
                :label="form.enabled ? 'Staff Share sync enabled' : 'Staff Share sync disabled'"
              />
            </FormField>

            <FormField label="Base URL" required>
              <v-text-field
                v-model="form.base_url"
                variant="outlined"
                hide-details
                placeholder="https://cbp.africacdc.org/staff/backend"
              />
            </FormField>

            <FormField label="Bearer token (STAFF_API_TOKEN)">
              <v-text-field
                v-model="form.api_token"
                variant="outlined"
                :type="showToken ? 'text' : 'password'"
                hide-details
                autocomplete="off"
                placeholder="Paste token — leave blank to keep the current one"
                :append-inner-icon="showToken ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                @click:append-inner="showToken = !showToken"
              />
            </FormField>

            <v-checkbox
              v-if="settings?.has_api_token"
              v-model="form.clear_api_token"
              label="Clear stored Bearer token"
              hide-details
              density="compact"
              class="mb-2"
            />
          </v-col>
        </v-row>

        <div class="d-flex flex-wrap ga-3 mt-4">
          <v-btn color="primary" :loading="saving" :disabled="syncing" @click="save">
            Save credentials
          </v-btn>
          <v-btn
            color="secondary"
            variant="tonal"
            :loading="syncing"
            :disabled="saving || !canSync"
            prepend-icon="mdi-sync"
            @click="syncNow"
          >
            Sync staff now
          </v-btn>
          <v-btn
            v-if="settings?.docs_url"
            variant="text"
            :href="settings.docs_url"
            target="_blank"
            rel="noopener"
            prepend-icon="mdi-open-in-new"
          >
            API docs
          </v-btn>
        </div>
      </ParentCard>
    </template>
  </div>
</template>
