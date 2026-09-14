<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  createEmailProvider,
  deleteEmailProvider,
  fetchEmailDrivers,
  fetchEmailProviders,
  setDefaultEmailProvider,
  testEmailProvider,
  updateEmailProvider,
  type EmailDriverDef,
  type EmailProvider,
} from '@/lib/settingsApi'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const drivers = ref<EmailDriverDef[]>([])
const providers = ref<EmailProvider[]>([])
const dialog = ref(false)
const editing = ref<EmailProvider | null>(null)
const testTo = ref('')

const form = reactive({
  name: '',
  driver: 'exchange',
  from_address: '',
  from_name: '',
  description: '',
  is_default: false,
  is_active: true,
  config: {} as Record<string, string>,
})

const activeDriver = computed(() => drivers.value.find((d) => d.key === form.driver) || drivers.value[0])

function buildDefaultConfig(driver?: EmailDriverDef) {
  const config: Record<string, string> = {}
  for (const field of driver?.fields || []) {
    config[field.key] = field.default ?? ''
  }
  return config
}

function openCreate() {
  editing.value = null
  form.name = ''
  form.driver = 'exchange'
  form.from_address = ''
  form.from_name = ''
  form.description = ''
  form.is_default = false
  form.is_active = true
  form.config = buildDefaultConfig(drivers.value.find((d) => d.key === 'exchange'))
  testTo.value = ''
  dialog.value = true
}

function openEdit(row: EmailProvider) {
  editing.value = row
  form.name = row.name
  form.driver = row.driver
  form.from_address = row.from_address || ''
  form.from_name = row.from_name || ''
  form.description = row.description || ''
  form.is_default = row.is_default
  form.is_active = row.is_active
  form.config = { ...buildDefaultConfig(drivers.value.find((d) => d.key === row.driver)), ...(row.config || {}) }
  testTo.value = ''
  dialog.value = true
}

function onDriverChange(driver: string) {
  form.driver = driver
  form.config = buildDefaultConfig(drivers.value.find((d) => d.key === driver))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [d, p] = await Promise.all([fetchEmailDrivers(), fetchEmailProviders()])
    drivers.value = d
    providers.value = p
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load email servers')
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
      from_address: form.from_address.trim(),
      from_name: form.from_name.trim(),
      description: form.description.trim(),
      is_default: form.is_default,
      is_active: form.is_active,
      config: { ...form.config },
    }
    if (editing.value) {
      await updateEmailProvider(editing.value.uuid, payload)
      success.value = 'Email provider updated.'
    } else {
      await createEmailProvider(payload)
      success.value = 'Email provider created.'
    }
    dialog.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save email provider')
  } finally {
    saving.value = false
  }
}

async function makeDefault(row: EmailProvider) {
  try {
    await setDefaultEmailProvider(row.uuid)
    success.value = `${row.name} is now the default.`
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

async function remove(row: EmailProvider) {
  if (!confirm(`Delete email provider “${row.name}”?`)) return
  try {
    await deleteEmailProvider(row.uuid)
    success.value = 'Email provider deleted.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

async function sendTest() {
  if (!editing.value || !testTo.value.trim()) return
  testing.value = true
  error.value = null
  try {
    success.value = await testEmailProvider(editing.value.uuid, testTo.value.trim())
  } catch (e) {
    error.value = apiErrorMessage(e, 'Test email failed')
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
      title="Email servers"
      lede="Configure how Staff Portal sends mail. Exchange is the default; empty fields fall back to EXCHANGE_* / MAIL_* env values."
    >
      <template #actions>
        <v-btn color="primary" @click="openCreate">Add provider</v-btn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-card v-else variant="outlined">
      <v-table density="compact">
        <thead>
          <tr>
            <th>Name</th>
            <th>Driver</th>
            <th>From</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in providers" :key="row.uuid">
            <td>
              {{ row.name }}
              <v-chip v-if="row.is_default" size="x-small" color="primary" class="ml-2">Default</v-chip>
            </td>
            <td>{{ driverLabel(row.driver) }}</td>
            <td>{{ row.from_name ? `${row.from_name} <${row.from_address}>` : row.from_address || '—' }}</td>
            <td>{{ row.is_active ? 'Active' : 'Inactive' }}</td>
            <td class="text-right">
              <v-btn size="small" variant="text" @click="openEdit(row)">Edit</v-btn>
              <v-btn v-if="!row.is_default" size="small" variant="text" @click="makeDefault(row)">Set default</v-btn>
              <v-btn v-if="!row.is_default" size="small" variant="text" color="error" @click="remove(row)">Delete</v-btn>
            </td>
          </tr>
          <tr v-if="!providers.length">
            <td colspan="5" class="text-center text-medium-emphasis py-6">No email providers yet.</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="720">
      <v-card>
        <v-card-title>{{ editing ? 'Edit email provider' : 'Add email provider' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Name" density="compact" class="mb-2" />
          <v-select
            :model-value="form.driver"
            :items="drivers"
            item-title="label"
            item-value="key"
            label="Driver"
            density="compact"
            class="mb-2"
            :disabled="!!editing"
            @update:model-value="onDriverChange"
          />
          <p v-if="activeDriver" class="text-body-2 text-medium-emphasis mb-3">{{ activeDriver.description }}</p>
          <v-text-field v-model="form.from_address" label="From address" density="compact" class="mb-2" />
          <v-text-field v-model="form.from_name" label="From name" density="compact" class="mb-2" />
          <v-textarea v-model="form.description" label="Description" density="compact" rows="2" class="mb-2" />
          <template v-for="field in activeDriver?.fields || []" :key="field.key">
            <v-select
              v-if="field.type === 'select'"
              v-model="form.config[field.key]"
              :items="field.options || []"
              :label="field.label"
              density="compact"
              class="mb-2"
            />
            <v-textarea
              v-else-if="field.type === 'textarea'"
              v-model="form.config[field.key]"
              :label="field.label"
              density="compact"
              rows="3"
              class="mb-2"
            />
            <v-text-field
              v-else
              v-model="form.config[field.key]"
              :label="field.label"
              :type="field.type === 'password' ? 'password' : field.type === 'number' ? 'number' : 'text'"
              :placeholder="field.placeholder"
              density="compact"
              class="mb-2"
            />
          </template>
          <div class="d-flex ga-4 mb-3">
            <v-switch v-model="form.is_active" label="Active" density="compact" color="primary" hide-details />
            <v-switch v-model="form.is_default" label="Default" density="compact" color="primary" hide-details />
          </div>
          <div v-if="editing" class="d-flex ga-2 align-center">
            <v-text-field v-model="testTo" label="Test recipient" density="compact" hide-details class="flex-grow-1" />
            <v-btn :loading="testing" variant="outlined" @click="sendTest">Send test</v-btn>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
