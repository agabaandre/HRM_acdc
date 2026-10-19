<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import CaptchaWidget from '@/components/forms/CaptchaWidget.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'

type DriverField = {
  key: string
  label: string
  type: string
  required?: boolean
  default?: string | number
  options?: Array<{ value: string; label: string }>
}

type Driver = { value: string; label: string; fields: DriverField[] }

const SECRET_KEYS = new Set(['client_secret', 'password', 'secret'])

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => route.name === 'provider-edit')
const id = computed(() => route.params.id as string | undefined)

const drivers = ref<Driver[]>([])
const loading = ref(false)
const saving = ref(false)
const testTo = ref('')
const testing = ref(false)
const message = ref('')
const error = ref('')
const captchaKey = ref<string | null>(null)
const captchaAnswer = ref('')
const captchaResetKey = ref(0)
/** Which secret fields already exist server-side (never returned in cleartext). */
const storedSecrets = ref<Record<string, boolean>>({})
/** True when server ciphertext cannot be decrypted (usually after APP_KEY rotation). */
const configCorrupt = ref(false)
const form = ref({
  name: '',
  driver: 'exchange',
  from_address: '',
  from_name: 'Email Server',
  is_active: true,
  is_default: false,
  priority: 100,
  description: '',
  config: {} as Record<string, string | number>,
})

const activeDriver = computed(() => drivers.value.find((d) => d.value === form.value.driver))

function isSecretField(field: DriverField): boolean {
  return field.type === 'password' || SECRET_KEYS.has(field.key)
}

function secretHint(field: DriverField): string {
  if (isEdit.value && storedSecrets.value[field.key]) {
    return 'Stored securely — leave blank to keep the current value'
  }
  return ''
}

async function loadDrivers() {
  const res = await api.get('/admin/email-providers/drivers')
  drivers.value = res.data.data
  // Apply driver field defaults once on create
  if (!isEdit.value && activeDriver.value) {
    for (const field of activeDriver.value.fields) {
      if (field.default !== undefined && form.value.config[field.key] === undefined) {
        form.value.config[field.key] = field.default
      }
    }
  }
}

async function loadProvider() {
  if (!isEdit.value || !id.value) return
  const res = await api.get(`/admin/email-providers/${id.value}`)
  const p = res.data.data
  storedSecrets.value = { ...(p.config_secrets ?? {}) }
  configCorrupt.value = Boolean(p.config_corrupt)

  const config: Record<string, string | number> = { ...(p.config ?? {}) }
  // Secrets are never returned by the API — keep password fields empty.
  for (const key of SECRET_KEYS) {
    delete config[key]
  }

  form.value = {
    name: p.name,
    driver: p.driver,
    from_address: p.from_address ?? '',
    from_name: p.from_name ?? '',
    is_active: p.is_active,
    is_default: p.is_default,
    priority: p.priority,
    description: p.description ?? '',
    config,
  }
}

function buildConfigPayload(): Record<string, string | number> {
  const payload: Record<string, string | number> = {}
  for (const [key, value] of Object.entries(form.value.config)) {
    if (SECRET_KEYS.has(key)) {
      // Only send a secret when the admin typed a new value.
      if (typeof value === 'string' && value.trim() !== '') {
        payload[key] = value
      }
      continue
    }
    if (value !== null && value !== undefined && value !== '') {
      payload[key] = value
    }
  }
  return payload
}

async function save() {
  saving.value = true
  message.value = ''
  error.value = ''
  try {
    const payload = {
      name: form.value.name,
      driver: form.value.driver,
      from_address: form.value.from_address,
      from_name: form.value.from_name,
      is_active: form.value.is_active,
      is_default: form.value.is_default,
      priority: form.value.priority,
      description: form.value.description,
      config: buildConfigPayload(),
    }
    if (isEdit.value && id.value) {
      const res = await api.put(`/admin/email-providers/${id.value}`, payload)
      storedSecrets.value = { ...(res.data.data?.config_secrets ?? storedSecrets.value) }
      configCorrupt.value = Boolean(res.data.data?.config_corrupt)
      // Clear password inputs after a successful save so secrets aren't left in DOM state.
      for (const key of SECRET_KEYS) {
        if (form.value.config[key] !== undefined) {
          form.value.config[key] = ''
        }
      }
      message.value = 'Provider updated.'
    } else {
      await api.post('/admin/email-providers', payload)
      message.value = 'Provider created.'
      await router.push({ name: 'providers' })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save provider.')
  } finally {
    saving.value = false
  }
}

async function sendTest() {
  if (!isEdit.value || !id.value || !testTo.value) return
  testing.value = true
  message.value = ''
  error.value = ''
  try {
    const payload: Record<string, unknown> = { to: testTo.value }
    if (captchaKey.value) {
      payload.captcha_key = captchaKey.value
      payload.captcha = captchaAnswer.value
    }
    await api.post(`/admin/email-providers/${id.value}/test`, payload)
    message.value = `Test email sent to ${testTo.value}`
    captchaResetKey.value += 1
  } catch (err) {
    error.value = apiErrorMessage(err, 'Test email failed.')
    captchaResetKey.value += 1
  } finally {
    testing.value = false
  }
}

onMounted(async () => {
  loading.value = true
  await loadDrivers()
  await loadProvider()
  loading.value = false
})
</script>

<template>
  <div>
    <PageHeader :title="isEdit ? 'Edit provider' : 'New provider'" />
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4">{{ message }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <ParentCard :title="isEdit ? 'Provider settings' : 'New provider'">
      <v-row>
        <v-col cols="12" md="6" class="form-stack">
          <FormField label="Name" required>
            <v-text-field v-model="form.name" variant="outlined" hide-details />
          </FormField>
          <FormField label="Driver" required>
            <v-select
              v-model="form.driver"
              :items="drivers"
              item-title="label"
              item-value="value"
              :disabled="isEdit"
              variant="outlined"
              hide-details
            />
          </FormField>
          <FormField label="From address">
            <v-text-field v-model="form.from_address" variant="outlined" hide-details />
          </FormField>
          <FormField label="From name">
            <v-text-field v-model="form.from_name" variant="outlined" hide-details />
          </FormField>
          <FormField label="Description">
            <v-textarea v-model="form.description" rows="3" variant="outlined" hide-details />
          </FormField>
          <v-switch v-model="form.is_active" label="Active" color="primary" class="mb-2" hide-details />
          <v-switch v-model="form.is_default" label="Set as default" color="primary" hide-details />
        </v-col>

        <v-col cols="12" md="6" class="form-stack">
          <div class="text-subtitle-1 font-weight-bold mb-4">Connection settings</div>
          <v-alert v-if="isEdit && form.driver === 'smtp'" type="info" variant="tonal" density="compact" class="mb-4">
            For cPanel / mail.africacdc.net use port <strong>465</strong> + <strong>SSL</strong>. Username must be the
            full mailbox email (same as From address), not the hostname.
          </v-alert>
          <v-alert v-if="isEdit && configCorrupt" type="warning" variant="tonal" density="compact" class="mb-4">
            Stored credentials cannot be decrypted (the server encryption key may have changed). Re-enter the password
            or secret fields below, then save.
          </v-alert>
          <v-alert v-else-if="isEdit" type="info" variant="tonal" density="compact" class="mb-4">
            Credentials are encrypted on the server and are never shown in this form. Leave secret fields blank to keep
            existing values.
          </v-alert>
          <template v-if="activeDriver">
            <template v-for="field in activeDriver.fields" :key="field.key">
              <FormField
                :label="field.label"
                :required="field.required && !(isEdit && isSecretField(field) && storedSecrets[field.key])"
              >
                <v-select
                  v-if="field.type === 'select'"
                  v-model="form.config[field.key]"
                  :items="field.options ?? []"
                  item-title="label"
                  item-value="value"
                  variant="outlined"
                  hide-details
                />
                <v-text-field
                  v-else
                  v-model="form.config[field.key]"
                  :type="isSecretField(field) ? 'password' : field.type === 'number' ? 'number' : 'text'"
                  :placeholder="secretHint(field)"
                  :autocomplete="isSecretField(field) ? 'new-password' : 'off'"
                  variant="outlined"
                  :hint="secretHint(field)"
                  :persistent-hint="Boolean(secretHint(field))"
                  :hide-details="!secretHint(field)"
                />
              </FormField>
            </template>
          </template>
        </v-col>
      </v-row>

      <div class="d-flex ga-2 mt-6">
        <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
        <v-btn variant="text" :to="{ name: 'providers' }">Cancel</v-btn>
      </div>

      <template v-if="isEdit">
        <v-divider class="my-6" />
        <v-row align="center">
          <v-col cols="12" md="6">
            <FormField label="Test recipient">
              <v-text-field v-model="testTo" type="email" variant="outlined" hide-details />
            </FormField>
          </v-col>
          <v-col cols="12" md="6" class="d-flex flex-column ga-3 pb-4">
            <CaptchaWidget
              :reset-key="captchaResetKey"
              @update:key="captchaKey = $event"
              @update:answer="captchaAnswer = $event"
            />
            <v-btn color="secondary" :loading="testing" class="align-self-start" @click="sendTest">
              Send test email
            </v-btn>
          </v-col>
        </v-row>
      </template>
    </ParentCard>
  </div>
</template>
