<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type Provider = {
  id: number
  name: string
  driver: string
  driver_label: string
  is_default: boolean
  is_active: boolean
  from_address: string | null
  config_corrupt?: boolean
}

const items = ref<Provider[]>([])
const loading = ref(true)
const actingId = ref<number | null>(null)
const error = ref('')
const message = ref('')
const router = useRouter()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/email-providers')
    items.value = res.data.data ?? []
  } catch (err) {
    items.value = []
    error.value = apiErrorMessage(err, 'Could not load providers.')
  } finally {
    loading.value = false
  }
}

async function setDefault(item: Provider) {
  error.value = ''
  message.value = ''
  try {
    await api.post(`/admin/email-providers/${item.id}/set-default`)
    message.value = `“${item.name}” is now the default provider.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not set default provider.')
  }
}

async function setActive(item: Provider, active: boolean) {
  if (item.is_default && !active) {
    error.value = 'Cannot disable the default provider. Set another provider as default first.'
    return
  }
  actingId.value = item.id
  error.value = ''
  message.value = ''
  try {
    await api.put(`/admin/email-providers/${item.id}`, { is_active: active })
    message.value = active
      ? `“${item.name}” enabled.`
      : `“${item.name}” disabled.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, active ? 'Could not enable provider.' : 'Could not disable provider.')
  } finally {
    actingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <h1 class="page-title mb-0">Email providers</h1>
      <v-btn color="primary" prepend-icon="mdi-plus" :to="{ name: 'provider-new' }">Add provider</v-btn>
    </div>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>
    <v-alert
      v-if="items.some((i) => i.config_corrupt)"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      Some providers have credentials encrypted with an old APP_KEY. Edit them and re-save the connection settings
      (client secret / SMTP password).
    </v-alert>

    <AppDataTable
      :loading="loading"
      :items="items"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Driver', key: 'driver_label' },
        { title: 'From', key: 'from_address' },
        { title: 'Default', key: 'is_default' },
        { title: 'Active', key: 'is_active' },
        { title: 'Actions', key: 'actions', sortable: false, width: 220 },
      ]"
    >
      <template #item.name="{ item }">
        {{ item.name }}
        <v-chip v-if="item.config_corrupt" class="ml-2" color="warning" size="x-small">Re-save secrets</v-chip>
      </template>
      <template #item.is_default="{ item }">
        <v-chip v-if="item.is_default" color="primary" size="small">Default</v-chip>
      </template>
      <template #item.is_active="{ item }">
        <v-icon :color="item.is_active ? 'success' : 'error'">
          {{ item.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
        </v-icon>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex ga-1 flex-wrap align-center">
          <v-btn
            size="small"
            variant="text"
            icon="mdi-pencil"
            @click="router.push({ name: 'provider-edit', params: { id: item.id } })"
          />
          <v-btn
            v-if="!item.is_default"
            size="small"
            variant="text"
            icon="mdi-star"
            @click="setDefault(item)"
          />
          <v-btn
            v-if="item.is_active && !item.is_default"
            size="small"
            color="warning"
            variant="tonal"
            :loading="actingId === item.id"
            @click="setActive(item, false)"
          >
            Disable
          </v-btn>
          <v-btn
            v-else-if="!item.is_active"
            size="small"
            color="success"
            variant="tonal"
            :loading="actingId === item.id"
            @click="setActive(item, true)"
          >
            Enable
          </v-btn>
        </div>
      </template>
      <template #no-data>
        <div class="text-medium-emphasis pa-6">
          No providers yet. Click <strong>Add provider</strong> to create one.
        </div>
      </template>
    </AppDataTable>
  </div>
</template>
