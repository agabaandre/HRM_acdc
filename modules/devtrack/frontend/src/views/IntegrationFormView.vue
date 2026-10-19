<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { generateClientSecret } from '@/lib/secrets'
import { useAuthStore } from '@/stores/auth'

type ProviderOption = { id: number; name: string; driver: string }

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isAdmin = computed(() => auth.isAdmin)
const isEdit = computed(() => route.name === 'integration-edit')
const id = computed(() => route.params.id as string | undefined)

const providers = ref<ProviderOption[]>([])
const loading = ref(false)
const saving = ref(false)
const created = ref(false)
const rotatedSecret = ref<string | null>(null)
const showSecret = ref(false)
const autoGenerateSecret = ref(true)
const copyMessage = ref('')

const form = ref({
  name: '',
  client_id: '',
  client_secret: '',
  email_provider_id: null as number | null,
  allowed_ips: '' as string,
  description: '',
  is_active: true,
})

function generateSecret() {
  form.value.client_secret = generateClientSecret()
  showSecret.value = true
}

async function copySecret(secret: string) {
  await navigator.clipboard.writeText(secret)
  copyMessage.value = 'Copied to clipboard.'
  window.setTimeout(() => {
    copyMessage.value = ''
  }, 2000)
}

async function loadProviders() {
  const res = await api.get('/admin/email-providers')
  providers.value = res.data.data
}

async function loadIntegration() {
  if (!isEdit.value || !id.value) return
  const res = await api.get(`/admin/external-integrations/${id.value}`)
  const i = res.data.data
  form.value = {
    name: i.name,
    client_id: i.client_id ?? i.slug,
    client_secret: '',
    email_provider_id: i.email_provider_id,
    allowed_ips: (i.allowed_ips ?? []).join('\n'),
    description: i.description ?? '',
    is_active: i.is_active,
  }
}

async function save() {
  saving.value = true
  rotatedSecret.value = null
  const payload: Record<string, unknown> = {
    name: form.value.name,
    slug: form.value.client_id,
    client_id: form.value.client_id,
    email_provider_id: form.value.email_provider_id,
    allowed_ips: form.value.allowed_ips
      .split('\n')
      .map((s) => s.trim())
      .filter(Boolean),
    description: form.value.description,
  }

  if (isAdmin.value) {
    payload.is_active = form.value.is_active
  }

  if (autoGenerateSecret.value) {
    payload.generate_secret = true
  } else if (form.value.client_secret) {
    payload.client_secret = form.value.client_secret
  }

  try {
    if (isEdit.value && id.value) {
      const res = await api.put(`/admin/external-integrations/${id.value}`, payload)
      if (res.data.client_secret) {
        rotatedSecret.value = res.data.client_secret
        form.value.client_secret = res.data.client_secret
        showSecret.value = true
        return
      }

      await router.push({ name: 'integrations' })
    } else {
      if (!autoGenerateSecret.value && !form.value.client_secret) {
        generateSecret()
        payload.client_secret = form.value.client_secret
      }

      const res = await api.post('/admin/external-integrations', payload)
      form.value.client_secret = res.data.client_secret ?? form.value.client_secret
      created.value = true
      showSecret.value = true
    }
  } finally {
    saving.value = false
  }
}

watch(autoGenerateSecret, (auto) => {
  if (auto) {
    form.value.client_secret = ''
    return
  }

  if (!form.value.client_secret) {
    generateSecret()
  }
})

onMounted(async () => {
  if (!isEdit.value && !isAdmin.value && !auth.user?.two_factor_totp_enabled) {
    await router.replace({ name: 'security' })
    return
  }

  loading.value = true
  await loadProviders()
  await loadIntegration()
  autoGenerateSecret.value = !isEdit.value
  loading.value = false
})
</script>

<template>
  <div>
    <PageHeader :title="isEdit ? 'Edit integration' : 'New integration'" />

    <v-alert v-if="created" type="success" variant="tonal" class="mb-4">
      Integration created. Share these credentials with the connecting system:
      <div class="mt-2"><strong>client_id:</strong> <code>{{ form.client_id }}</code></div>
      <div class="d-flex align-center ga-2 flex-wrap">
        <div><strong>client_secret:</strong> <code>{{ form.client_secret }}</code></div>
        <v-btn size="small" variant="tonal" @click="copySecret(form.client_secret)">Copy secret</v-btn>
      </div>
      <div v-if="copyMessage" class="text-caption mt-1">{{ copyMessage }}</div>
      <div class="text-caption mt-2">
        Systems call <code>POST /api/v1/integrations/auth/token</code> with these values to obtain a JWT.
      </div>
      <v-btn size="small" class="mt-3" :to="{ name: 'integrations' }">Back to list</v-btn>
    </v-alert>

    <v-alert v-else-if="rotatedSecret" type="success" variant="tonal" class="mb-4">
      Client secret rotated. Share the new secret with the connecting system:
      <div class="d-flex align-center ga-2 flex-wrap mt-2">
        <code>{{ rotatedSecret }}</code>
        <v-btn size="small" variant="tonal" @click="copySecret(rotatedSecret)">Copy secret</v-btn>
      </div>
      <div v-if="copyMessage" class="text-caption mt-1">{{ copyMessage }}</div>
      <v-btn size="small" class="mt-3" :to="{ name: 'integrations' }">Back to list</v-btn>
    </v-alert>

    <ParentCard v-else :title="isEdit ? 'Integration settings' : 'Integration credentials'">
      <v-form @submit.prevent="save">
        <v-row>
          <v-col cols="12" md="6" class="form-stack">
            <FormField label="Name" required>
              <v-text-field v-model="form.name" variant="outlined" hide-details />
            </FormField>
            <FormField label="Client ID" required>
              <v-text-field
                v-model="form.client_id"
                :disabled="isEdit"
                variant="outlined"
                hide-details
              />
            </FormField>
            <FormField :label="isEdit ? 'Client secret (optional)' : 'Client secret'" :required="!isEdit && !autoGenerateSecret">
              <v-switch
                v-model="autoGenerateSecret"
                label="Automatically generate secret"
                color="primary"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="form.client_secret"
                :type="showSecret ? 'text' : 'password'"
                :disabled="autoGenerateSecret"
                :placeholder="autoGenerateSecret ? 'A secure secret will be generated on save' : 'Enter or generate a secret'"
                variant="outlined"
                hide-details
                :append-inner-icon="showSecret ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showSecret = !showSecret"
              >
                <template #append>
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    title="Generate new secret"
                    :disabled="autoGenerateSecret"
                    @click="generateSecret"
                  >
                    <v-icon>mdi-auto-fix</v-icon>
                  </v-btn>
                </template>
              </v-text-field>
              <div class="text-caption text-medium-emphasis mt-1">
                Minimum 16 characters. Use generate for a random secret, or let the server create one on save.
              </div>
            </FormField>
          </v-col>
          <v-col cols="12" md="6" class="form-stack">
            <FormField label="Email provider">
              <v-select
                v-model="form.email_provider_id"
                :items="providers"
                item-title="name"
                item-value="id"
                variant="outlined"
                hide-details
                clearable
              />
            </FormField>
            <FormField label="Allowed IPs (one per line)">
              <v-textarea v-model="form.allowed_ips" rows="3" variant="outlined" hide-details />
            </FormField>
            <FormField label="Description">
              <v-textarea v-model="form.description" rows="2" variant="outlined" hide-details />
            </FormField>
            <v-switch
              v-if="isAdmin"
              v-model="form.is_active"
              label="Active"
              color="primary"
              hide-details
            />
            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-2">
              New clients are created inactive. An administrator must activate this client before it can authenticate.
            </v-alert>
          </v-col>
        </v-row>
        <div class="d-flex ga-2 mt-6">
          <v-btn color="primary" type="submit" :loading="saving">Save</v-btn>
          <v-btn variant="text" :to="{ name: 'integrations' }">Cancel</v-btn>
        </div>
      </v-form>
    </ParentCard>
  </div>
</template>
