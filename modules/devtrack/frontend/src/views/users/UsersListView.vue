<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type IntegrationSummary = { id: number; name: string; slug: string }

type UserRow = {
  id: number
  name: string
  email: string
  phone?: string | null
  organisation?: string | null
  registration_source?: string
  is_admin: boolean
  is_active: boolean
  approval_status?: string
  totp_required?: boolean
  two_factor_totp_enabled?: boolean
  must_setup_totp?: boolean
  external_integrations: IntegrationSummary[]
}

type UsersMeta = {
  pending_count: number
  approved_count: number
  rejected_count: number
  public_count: number
  system_count: number
  total_count: number
}

type StatusTab = 'all' | 'pending' | 'approved' | 'rejected'
type SourceFilter = '' | 'public' | 'system' | null

const items = ref<UserRow[]>([])
const meta = ref<UsersMeta>({
  pending_count: 0,
  approved_count: 0,
  rejected_count: 0,
  public_count: 0,
  system_count: 0,
  total_count: 0,
})
const loading = ref(true)
const actingId = ref<number | null>(null)
const message = ref('')
const error = ref('')
const route = useRoute()
const router = useRouter()

const initialTab = ((): StatusTab => {
  const raw = typeof route.query.tab === 'string' ? route.query.tab : 'all'
  if (raw === 'pending' || raw === 'approved' || raw === 'rejected') return raw
  return 'all'
})()

const statusTab = ref<StatusTab>(initialTab)
const sourceFilter = ref<SourceFilter>('')

const sourceOptions = [
  { value: '', title: 'All sources' },
  { value: 'public', title: 'Public registration' },
  { value: 'system', title: 'System users' },
]

const cardTitle = computed(() => {
  if (statusTab.value === 'pending') return 'Pending approval'
  if (statusTab.value === 'approved') return 'Approved users'
  if (statusTab.value === 'rejected') return 'Rejected users'
  return 'All users'
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/users', {
      params: {
        approval_status: statusTab.value === 'all' ? undefined : statusTab.value,
        registration_source: sourceFilter.value || undefined,
      },
    })
    items.value = res.data.data
    meta.value = {
      pending_count: res.data.meta?.pending_count ?? 0,
      approved_count: res.data.meta?.approved_count ?? 0,
      rejected_count: res.data.meta?.rejected_count ?? 0,
      public_count: res.data.meta?.public_count ?? 0,
      system_count: res.data.meta?.system_count ?? 0,
      total_count: res.data.meta?.total_count ?? items.value.length,
    }
  } catch (err) {
    items.value = []
    error.value = apiErrorMessage(err, 'Could not load users.')
  } finally {
    loading.value = false
  }
}

async function approve(item: UserRow) {
  actingId.value = item.id
  message.value = ''
  error.value = ''
  try {
    const res = await api.post(`/admin/users/${item.id}/approve`)
    message.value = res.data.message || 'Account approved.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not approve account.')
  } finally {
    actingId.value = null
  }
}

async function reject(item: UserRow) {
  const reason = window.prompt('Optional rejection reason:', '') ?? undefined
  if (reason === undefined) return
  actingId.value = item.id
  message.value = ''
  error.value = ''
  try {
    const res = await api.post(`/admin/users/${item.id}/reject`, {
      reason: reason || null,
    })
    message.value = res.data.message || 'Account rejected.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not reject account.')
  } finally {
    actingId.value = null
  }
}

async function remove(item: UserRow) {
  if (item.approval_status !== 'rejected') return
  if (!confirm(`Permanently delete rejected account "${item.name}"?`)) return
  actingId.value = item.id
  message.value = ''
  error.value = ''
  try {
    await api.delete(`/admin/users/${item.id}`)
    message.value = 'Rejected account deleted.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not delete account.')
  } finally {
    actingId.value = null
  }
}

function syncTabQuery(tab: StatusTab) {
  const nextQuery = { ...route.query }
  if (tab === 'all') {
    delete nextQuery.tab
  } else {
    nextQuery.tab = tab
  }
  router.replace({ query: nextQuery })
}

watch(statusTab, (tab) => {
  syncTabQuery(tab)
  load()
})

watch(sourceFilter, () => {
  load()
})

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="User management" subtitle="Approve registrations and manage panel accounts">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" :to="{ name: 'user-new' }">Add user</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <v-alert
      v-if="meta.pending_count > 0 && statusTab !== 'pending'"
      type="warning"
      variant="tonal"
      class="mb-4"
      border="start"
    >
      {{ meta.pending_count }} account{{ meta.pending_count === 1 ? '' : 's' }} awaiting approval.
      <v-btn class="ms-2" size="small" variant="tonal" color="warning" @click="statusTab = 'pending'">
        Review pending
      </v-btn>
    </v-alert>

    <v-tabs v-model="statusTab" color="primary" class="mb-4">
      <v-tab value="all">All ({{ meta.total_count }})</v-tab>
      <v-tab value="pending">
        Pending
        <v-badge
          v-if="meta.pending_count > 0"
          :content="meta.pending_count"
          color="warning"
          inline
          class="ms-1"
        />
      </v-tab>
      <v-tab value="approved">Approved ({{ meta.approved_count }})</v-tab>
      <v-tab value="rejected">Rejected ({{ meta.rejected_count }})</v-tab>
    </v-tabs>

    <ParentCard :title="cardTitle">
      <div class="d-flex flex-wrap ga-3 align-center mb-4">
        <v-select
          v-model="sourceFilter"
          :items="sourceOptions"
          item-title="title"
          item-value="value"
          label="Registration source"
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          style="max-width: 260px"
        />
        <v-chip size="small" variant="tonal">
          Public: {{ meta.public_count }}
        </v-chip>
        <v-chip size="small" variant="tonal">
          System: {{ meta.system_count }}
        </v-chip>
      </div>

      <AppDataTable
        :loading="loading"
        :items="items"
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Email', key: 'email' },
          { title: 'Organisation', key: 'organisation' },
          { title: 'Phone', key: 'phone' },
          { title: 'Source', key: 'registration_source' },
          { title: 'Approval', key: 'approval_status' },
          { title: 'Role', key: 'is_admin' },
          { title: 'Authenticator', key: 'authenticator' },
          { title: 'App access', key: 'external_integrations' },
          { title: 'Active', key: 'is_active' },
          { title: 'Actions', key: 'actions', sortable: false, width: 220 },
        ]"
      >
        <template #item.organisation="{ item }">
          {{ item.organisation || '—' }}
        </template>
        <template #item.phone="{ item }">
          {{ item.phone || '—' }}
        </template>
        <template #item.registration_source="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            :color="item.registration_source === 'public' ? 'info' : 'default'"
          >
            {{ item.registration_source === 'public' ? 'Public' : 'System' }}
          </v-chip>
        </template>
        <template #item.approval_status="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            class="text-capitalize"
            :color="
              item.approval_status === 'approved'
                ? 'success'
                : item.approval_status === 'pending'
                  ? 'warning'
                  : item.approval_status === 'rejected'
                    ? 'error'
                    : 'default'
            "
          >
            {{ item.approval_status || 'approved' }}
          </v-chip>
        </template>
        <template #item.is_admin="{ item }">
          <v-chip :color="item.is_admin ? 'primary' : 'default'" size="small" variant="tonal">
            {{ item.is_admin ? 'Admin' : 'User' }}
          </v-chip>
        </template>
        <template #item.authenticator="{ item }">
          <v-chip
            v-if="item.must_setup_totp"
            size="small"
            color="warning"
            variant="tonal"
          >
            Setup required
          </v-chip>
          <v-chip
            v-else-if="item.two_factor_totp_enabled"
            size="small"
            color="success"
            variant="tonal"
          >
            {{ item.totp_required ? 'Required' : 'Enabled' }}
          </v-chip>
          <span v-else class="text-medium-emphasis">Optional</span>
        </template>
        <template #item.external_integrations="{ item }">
          <template v-if="item.is_admin">
            <v-chip size="small" color="primary" variant="tonal">All apps</v-chip>
          </template>
          <template v-else-if="!item.external_integrations?.length">
            <span class="text-medium-emphasis">None</span>
          </template>
          <div v-else class="d-flex flex-wrap ga-1">
            <v-chip
              v-for="app in item.external_integrations"
              :key="app.id"
              size="small"
              variant="tonal"
            >
              {{ app.name }}
            </v-chip>
          </div>
        </template>
        <template #item.is_active="{ item }">
          <v-icon :color="item.is_active ? 'success' : 'error'">
            {{ item.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
          </v-icon>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex ga-1 flex-wrap">
            <v-btn
              v-if="item.approval_status === 'pending'"
              size="small"
              color="success"
              variant="tonal"
              :loading="actingId === item.id"
              @click="approve(item)"
            >
              Approve
            </v-btn>
            <v-btn
              v-if="item.approval_status === 'pending'"
              size="small"
              color="error"
              variant="tonal"
              :loading="actingId === item.id"
              @click="reject(item)"
            >
              Reject
            </v-btn>
            <v-btn
              size="small"
              variant="text"
              icon="mdi-pencil"
              @click="router.push({ name: 'user-edit', params: { id: item.id } })"
            />
            <v-btn
              v-if="item.approval_status === 'rejected'"
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
