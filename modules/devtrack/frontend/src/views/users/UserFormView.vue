<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'

type IntegrationOption = { id: number; name: string; slug: string }

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => route.name === 'user-edit')
const id = computed(() => route.params.id as string | undefined)

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const integrations = ref<IntegrationOption[]>([])

const form = ref({
  name: '',
  email: '',
  password: '',
  is_admin: false,
  is_active: true,
  external_integration_ids: [] as number[],
})

const integrationItems = computed(() =>
  integrations.value.map((i) => ({
    title: `${i.name} (${i.slug})`,
    value: i.id,
  })),
)

watch(
  () => form.value.is_admin,
  (isAdmin) => {
    if (isAdmin) {
      form.value.external_integration_ids = []
    }
  },
)

async function loadIntegrations() {
  const res = await api.get('/admin/external-integrations')
  integrations.value = (res.data.data ?? []).map((i: IntegrationOption) => ({
    id: i.id,
    name: i.name,
    slug: i.slug,
  }))
}

async function loadUser() {
  if (!isEdit.value || !id.value) return
  const res = await api.get(`/admin/users/${id.value}`)
  const u = res.data.data
  form.value = {
    name: u.name,
    email: u.email,
    password: '',
    is_admin: u.is_admin,
    is_active: u.is_active,
    external_integration_ids: u.external_integration_ids ?? [],
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name,
      email: form.value.email,
      is_admin: form.value.is_admin,
      is_active: form.value.is_active,
      external_integration_ids: form.value.is_admin ? [] : form.value.external_integration_ids,
    }
    if (form.value.password) {
      payload.password = form.value.password
    } else if (!isEdit.value) {
      error.value = 'Password is required.'
      return
    }

    if (isEdit.value && id.value) {
      await api.put(`/admin/users/${id.value}`, payload)
    } else {
      await api.post('/admin/users', payload)
    }
    await router.push({ name: 'users' })
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save user.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([loadIntegrations(), loadUser()])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <PageHeader :title="isEdit ? 'Edit user' : 'New user'" />

    <ParentCard :title="isEdit ? 'Update account' : 'Create account'">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-skeleton-loader v-if="loading" type="article" />
      <v-form v-else @submit.prevent="save">
        <v-row>
          <v-col cols="12" md="6" class="form-stack">
            <FormField label="Full name" required>
              <v-text-field v-model="form.name" variant="outlined" hide-details />
            </FormField>
            <FormField label="Email" required>
              <v-text-field v-model="form.email" type="email" variant="outlined" hide-details />
            </FormField>
            <FormField :label="isEdit ? 'New password (optional)' : 'Password'" :required="!isEdit">
              <v-text-field
                v-model="form.password"
                type="password"
                variant="outlined"
                hide-details
                hint="Minimum 12 characters with mixed case and numbers"
              />
            </FormField>
            <FormField label="App credentials">
              <v-select
                v-model="form.external_integration_ids"
                :items="integrationItems"
                :disabled="form.is_admin"
                multiple
                chips
                closable-chips
                variant="outlined"
                hide-details="auto"
                placeholder="Select one or more apps"
                :hint="
                  form.is_admin
                    ? 'Administrators automatically see email logs from every app.'
                    : 'User can only view email logs sent by the selected app credentials.'
                "
                persistent-hint
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="6" class="d-flex flex-column justify-center ga-4">
            <v-switch v-model="form.is_admin" label="Administrator" color="primary" hide-details />
            <v-switch v-model="form.is_active" label="Active" color="success" hide-details />
            <p class="text-body-2 text-medium-emphasis">
              Non-admin users must be bound to at least one app credential to see related email logs.
              Admins have unrestricted access.
            </p>
            <v-alert v-if="!isEdit" type="info" variant="tonal" density="compact">
              New users must set up an authenticator app on their first sign-in. It cannot be disabled later.
            </v-alert>
          </v-col>
        </v-row>
        <div class="d-flex ga-2 mt-6">
          <v-btn color="primary" type="submit" :loading="saving">Save</v-btn>
          <v-btn variant="text" :to="{ name: 'users' }">Cancel</v-btn>
        </div>
      </v-form>
    </ParentCard>
  </div>
</template>
