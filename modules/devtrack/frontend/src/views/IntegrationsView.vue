<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { useAuthStore } from '@/stores/auth'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type Integration = {
  id: number
  name: string
  slug: string
  client_id: string
  client_secret_hint: string
  email_provider: { id: number; name: string; driver: string } | null
  is_active: boolean
  last_used_at: string | null
}

const items = ref<Integration[]>([])
const loading = ref(true)
const actingId = ref<number | null>(null)
const error = ref('')
const message = ref('')
const router = useRouter()
const auth = useAuthStore()
const isAdmin = computed(() => auth.isAdmin)
const canCreateClients = computed(
  () => isAdmin.value || auth.user?.two_factor_totp_enabled === true,
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/external-integrations')
    items.value = res.data.data
  } catch (err) {
    items.value = []
    error.value = apiErrorMessage(err, 'Could not load integrations.')
  } finally {
    loading.value = false
  }
}

async function setActive(item: Integration, active: boolean) {
  if (!isAdmin.value) return
  actingId.value = item.id
  error.value = ''
  message.value = ''
  try {
    await api.put(`/admin/external-integrations/${item.id}`, { is_active: active })
    message.value = active
      ? `“${item.name}” enabled.`
      : `“${item.name}” disabled.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, active ? 'Could not enable integration.' : 'Could not disable integration.')
  } finally {
    actingId.value = null
  }
}

async function remove(item: Integration) {
  if (item.is_active) return
  if (!confirm(`Permanently delete inactive client "${item.name}"?`)) return
  actingId.value = item.id
  error.value = ''
  message.value = ''
  try {
    await api.delete(`/admin/external-integrations/${item.id}`)
    message.value = 'Inactive client deleted.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not delete client.')
  } finally {
    actingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="isAdmin ? 'External integrations' : 'My clients'"
      :subtitle="
        isAdmin
          ? 'Connecting systems authenticate with client_id + client_secret to obtain a JWT'
          : 'Register clients for your organisation. New clients stay inactive until an administrator activates them.'
      "
    >
      <template #actions>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          :to="canCreateClients ? { name: 'integration-new' } : { name: 'security' }"
        >
          {{ isAdmin ? 'Add integration' : canCreateClients ? 'Add client' : 'Enable authenticator first' }}
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <v-alert v-if="!isAdmin && !canCreateClients" type="warning" variant="tonal" class="mb-4">
      Enable an authenticator app under
      <router-link :to="{ name: 'security' }">Security</router-link>
      before you can register integration clients.
    </v-alert>

    <v-alert v-else-if="!isAdmin" type="info" variant="tonal" class="mb-4">
      You only see clients linked to your account. Inactive clients cannot obtain a JWT until an admin activates them.
    </v-alert>

    <ParentCard :title="isAdmin ? 'Integrations' : 'Clients'">
      <AppDataTable
        :loading="loading"
        :items="items"
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Client ID', key: 'client_id' },
          { title: 'Secret hint', key: 'client_secret_hint' },
          { title: 'Provider', key: 'email_provider' },
          { title: 'Status', key: 'is_active' },
          { title: 'Last used', key: 'last_used_at' },
          { title: 'Actions', key: 'actions', sortable: false, width: 200 },
        ]"
      >
        <template #item.email_provider="{ item }">
          {{ item.email_provider?.name ?? 'Default' }}
        </template>
        <template #item.is_active="{ item }">
          <v-chip size="small" variant="tonal" :color="item.is_active ? 'success' : 'warning'">
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex ga-1 flex-wrap align-center">
            <v-btn
              size="small"
              variant="text"
              icon="mdi-pencil"
              @click="router.push({ name: 'integration-edit', params: { id: item.id } })"
            />
            <v-btn
              v-if="isAdmin && item.is_active"
              size="small"
              color="warning"
              variant="tonal"
              :loading="actingId === item.id"
              @click="setActive(item, false)"
            >
              Disable
            </v-btn>
            <v-btn
              v-else-if="isAdmin && !item.is_active"
              size="small"
              color="success"
              variant="tonal"
              :loading="actingId === item.id"
              @click="setActive(item, true)"
            >
              Enable
            </v-btn>
            <v-btn
              v-if="!item.is_active"
              size="small"
              variant="text"
              icon="mdi-delete"
              color="error"
              :loading="actingId === item.id"
              @click="remove(item)"
            />
          </div>
        </template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
