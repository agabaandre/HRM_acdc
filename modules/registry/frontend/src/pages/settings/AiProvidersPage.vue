<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  createAiProvider,
  deleteAiProvider,
  fetchAiDrivers,
  fetchAiProviders,
  setDefaultAiProvider,
  testAiProvider,
  updateAiProvider,
  type AiDriverDef,
  type AiProvider,
  type AiTestResult,
} from '@/lib/aiProvidersApi'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()
const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const drivers = ref<AiDriverDef[]>([])
const providers = ref<AiProvider[]>([])
const dialog = ref(false)
const editing = ref<AiProvider | null>(null)
const testResult = ref<AiTestResult | null>(null)

const form = reactive({
  name: '',
  driver: 'openai',
  api_endpoint: '',
  model: '',
  api_key: '',
  description: '',
  is_default: false,
  is_active: true,
})

const activeDriver = computed(
  () => drivers.value.find((d) => d.key === form.driver) || drivers.value[0],
)

function applyPreset(driverKey: string, force = false) {
  const driver = drivers.value.find((d) => d.key === driverKey)
  if (!driver) return
  if (force || !form.api_endpoint) form.api_endpoint = driver.api_endpoint
  if (force || !form.model) form.model = driver.model
}

function openCreate() {
  editing.value = null
  form.name = 'OpenAI'
  form.driver = 'openai'
  form.api_endpoint = ''
  form.model = ''
  form.api_key = ''
  form.description = ''
  form.is_default = false
  form.is_active = true
  applyPreset('openai', true)
  testResult.value = null
  dialog.value = true
}

function openEdit(row: AiProvider) {
  editing.value = row
  form.name = row.name
  form.driver = row.driver
  form.api_endpoint = row.api_endpoint
  form.model = row.model
  form.api_key = ''
  form.description = row.description || ''
  form.is_default = row.is_default
  form.is_active = row.is_active
  testResult.value = null
  dialog.value = true
}

function onDriverChange(driver: string) {
  form.driver = driver
  applyPreset(driver, true)
  const preset = drivers.value.find((d) => d.key === driver)
  if (preset && !form.name) form.name = preset.label
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [d, p] = await Promise.all([fetchAiDrivers(), fetchAiProviders()])
    drivers.value = d
    providers.value = p
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load AI providers')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    const payload = {
      name: form.name.trim(),
      driver: form.driver,
      api_endpoint: form.api_endpoint.trim(),
      model: form.model.trim(),
      description: form.description.trim(),
      is_default: form.is_default,
      is_active: form.is_active,
      ...(form.api_key.trim() ? { api_key: form.api_key.trim() } : {}),
    }
    if (editing.value) {
      await updateAiProvider(editing.value.uuid, {
        name: payload.name,
        api_endpoint: payload.api_endpoint,
        model: payload.model,
        description: payload.description,
        is_default: payload.is_default,
        is_active: payload.is_active,
        ...(form.api_key.trim() ? { api_key: form.api_key.trim() } : {}),
      })
      success.value = 'AI provider updated.'
    } else {
      await createAiProvider(payload)
      success.value = 'AI provider created.'
    }
    dialog.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save AI provider')
  } finally {
    saving.value = false
  }
}

async function makeDefault(row: AiProvider) {
  try {
    await setDefaultAiProvider(row.uuid)
    success.value = `${row.name} is now the default.`
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

async function remove(row: AiProvider) {
  if (!confirm(`Delete AI provider “${row.name}”?`)) return
  try {
    await deleteAiProvider(row.uuid)
    success.value = 'AI provider deleted.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

async function sendTest() {
  testing.value = true
  error.value = null
  testResult.value = null
  try {
    const payload: { api_endpoint?: string; model?: string; api_key?: string; driver?: string } = {
      api_endpoint: form.api_endpoint.trim() || undefined,
      model: form.model.trim() || undefined,
      driver: form.driver,
    }
    if (form.api_key.trim()) payload.api_key = form.api_key.trim()
    testResult.value = await testAiProvider(payload, editing.value?.uuid)
    if (testResult.value.ok) {
      success.value = testResult.value.message
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'AI configuration test failed')
  } finally {
    testing.value = false
  }
}

function driverLabel(key: string) {
  return drivers.value.find((d) => d.key === key)?.label || key
}

onMounted(load)
</script>

<template>
  <div>
    <PortalPageChrome
      :title="locale.t('settings.ai_title', 'AI providers')"
      :lede="locale.t('settings.ai_lede', 'Configure OpenAI and other chat models. Keys are stored encrypted. Leave the key blank to keep the current value.')"
    >
      <template #actions>
        <v-btn color="primary" @click="openCreate">{{ locale.t('settings.ai_add', 'Add provider') }}</v-btn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">{{ locale.t('actions.loading', 'Loading…') }}</div>

    <v-card v-else variant="outlined">
      <v-table density="compact">
        <thead>
          <tr>
            <th>{{ locale.t('settings.ai_name', 'Name') }}</th>
            <th>{{ locale.t('settings.ai_driver', 'Provider') }}</th>
            <th>{{ locale.t('settings.ai_model', 'Model name') }}</th>
            <th>{{ locale.t('settings.ai_status', 'Status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in providers" :key="row.uuid">
            <td>
              {{ row.name }}
              <v-chip v-if="row.is_default" size="x-small" color="primary" class="ml-2">
                {{ locale.t('settings.ai_default', 'Default') }}
              </v-chip>
            </td>
            <td>{{ driverLabel(row.driver) }}</td>
            <td>
              <code class="text-caption">{{ row.model || '—' }}</code>
            </td>
            <td>{{ row.is_active ? locale.t('actions.active', 'Active') : locale.t('actions.inactive', 'Inactive') }}</td>
            <td class="text-right">
              <v-btn size="small" variant="text" @click="openEdit(row)">{{ locale.t('actions.edit', 'Edit') }}</v-btn>
              <v-btn v-if="!row.is_default" size="small" variant="text" @click="makeDefault(row)">
                {{ locale.t('actions.set_default', 'Set default') }}
              </v-btn>
              <v-btn v-if="!row.is_default" size="small" variant="text" color="error" @click="remove(row)">
                {{ locale.t('actions.delete', 'Delete') }}
              </v-btn>
            </td>
          </tr>
          <tr v-if="!providers.length">
            <td colspan="5" class="text-center text-medium-emphasis py-6">
              {{ locale.t('settings.ai_empty', 'No AI providers yet. OpenAI is seeded as the default.') }}
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="720">
      <v-card>
        <v-card-title>
          {{
            editing
              ? locale.t('settings.ai_edit', 'Edit AI provider')
              : locale.t('settings.ai_create', 'Add AI provider')
          }}
        </v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" :label="locale.t('settings.ai_name', 'Name')" density="compact" class="mb-2" />
          <v-select
            :model-value="form.driver"
            :items="drivers"
            item-title="label"
            item-value="key"
            :label="locale.t('settings.ai_driver', 'Provider')"
            density="compact"
            class="mb-2"
            :disabled="!!editing"
            @update:model-value="onDriverChange"
          />
          <p v-if="activeDriver" class="text-body-2 text-medium-emphasis mb-3">
            {{ activeDriver.description }}
            {{ locale.t('settings.ai_preset_hint', 'Choosing a provider fills the default endpoint and model. You can still change them.') }}
          </p>
          <v-text-field
            v-model="form.api_endpoint"
            :label="locale.t('settings.ai_endpoint', 'API base')"
            placeholder="https://api.openai.com/v1"
            density="compact"
            class="mb-2"
          />
          <v-text-field
            v-model="form.model"
            :label="locale.t('settings.ai_model', 'Model name')"
            :placeholder="activeDriver?.model || 'gpt-4o-mini'"
            density="compact"
            class="mb-2"
          />
          <p v-if="editing?.has_api_key" class="text-body-2 text-medium-emphasis mb-2">
            {{ locale.t('settings.ai_key_on_file', 'API key is on file. Enter a new key only to replace it.') }}
          </p>
          <v-text-field
            v-model="form.api_key"
            :label="locale.t('settings.ai_api_key', 'API key (optional)')"
            type="password"
            autocomplete="new-password"
            density="compact"
            class="mb-2"
          />
          <v-textarea v-model="form.description" label="Description" density="compact" rows="2" class="mb-2" />
          <div class="d-flex ga-4 mb-3">
            <v-switch
              v-model="form.is_active"
              :label="locale.t('actions.active', 'Active')"
              density="compact"
              color="primary"
              hide-details
            />
            <v-switch
              v-model="form.is_default"
              :label="locale.t('settings.ai_default', 'Default')"
              density="compact"
              color="primary"
              hide-details
            />
          </div>
          <v-btn :loading="testing" variant="outlined" @click="sendTest">
            {{ locale.t('settings.ai_test', 'Test AI configuration') }}
          </v-btn>
          <div v-if="testResult" class="mt-3 text-body-2" :class="testResult.ok ? 'text-success' : 'text-error'">
            <strong>
              {{
                testResult.ok
                  ? locale.t('settings.ai_connection_ok', 'Connection OK')
                  : locale.t('settings.ai_connection_failed', 'Connection failed')
              }}
            </strong>
            <p class="mb-1">{{ testResult.message }}</p>
            <ul class="text-medium-emphasis mb-0">
              <li v-if="testResult.endpoint">{{ testResult.endpoint }}</li>
              <li v-if="testResult.model">{{ testResult.model }}</li>
              <li v-if="testResult.latency_ms != null">{{ testResult.latency_ms }} ms</li>
              <li v-if="testResult.http_status != null">HTTP {{ testResult.http_status }}</li>
              <li v-if="testResult.reply_preview">{{ testResult.reply_preview }}</li>
            </ul>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">{{ locale.t('actions.cancel', 'Cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">{{ locale.t('actions.save', 'Save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
