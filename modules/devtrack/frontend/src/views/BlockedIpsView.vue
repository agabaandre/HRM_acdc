<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateTime12h } from '@/lib/formatDate'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type ActorRef = { id: number; name: string; email: string } | null

type BlockedIpRow = {
  id: number
  ip_address: string
  reason: string
  is_active: boolean
  audit_log_id: number | null
  blocked_by: ActorRef
  unblocked_by: ActorRef
  unblocked_at: string | null
  created_at: string | null
  updated_at: string | null
}

type BlockedEmailRow = {
  id: number
  email: string
  reason: string
  is_active: boolean
  audit_log_id: number | null
  blocked_by: ActorRef
  unblocked_by: ActorRef
  unblocked_at: string | null
  created_at: string | null
  updated_at: string | null
}

const tab = ref<'ips' | 'emails'>('ips')
const ipItems = ref<BlockedIpRow[]>([])
const emailItems = ref<BlockedEmailRow[]>([])
const loading = ref(true)
const error = ref('')
const message = ref('')
const search = ref('')
const showInactive = ref(false)
const unblockingId = ref<number | null>(null)

const addOpen = ref(false)
const addScope = ref<'ip' | 'email' | 'both'>('ip')
const addIp = ref('')
const addEmail = ref('')
const addReason = ref('')
const adding = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = {
      active_only: showInactive.value ? 0 : 1,
      q: search.value.trim() || undefined,
    }
    const [ipsRes, emailsRes] = await Promise.all([
      api.get('/admin/blocked-ips', { params }),
      api.get('/admin/blocked-emails', { params }),
    ])
    ipItems.value = ipsRes.data.data
    emailItems.value = emailsRes.data.data
  } catch (err) {
    ipItems.value = []
    emailItems.value = []
    error.value = apiErrorMessage(err, 'Could not load blocked access.')
  } finally {
    loading.value = false
  }
}

async function unblockIp(row: BlockedIpRow) {
  if (!row.is_active) return
  if (!confirm(`Unblock ${row.ip_address}?`)) return
  unblockingId.value = row.id
  error.value = ''
  message.value = ''
  try {
    const res = await api.delete(`/admin/blocked-ips/${row.id}`)
    message.value = res.data.message || `Unblocked ${row.ip_address}.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not unblock IP.')
  } finally {
    unblockingId.value = null
  }
}

async function unblockEmail(row: BlockedEmailRow) {
  if (!row.is_active) return
  if (!confirm(`Unblock ${row.email}?`)) return
  unblockingId.value = row.id
  error.value = ''
  message.value = ''
  try {
    const res = await api.delete(`/admin/blocked-emails/${row.id}`)
    message.value = res.data.message || `Unblocked ${row.email}.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not unblock email.')
  } finally {
    unblockingId.value = null
  }
}

function openAdd() {
  addScope.value = tab.value === 'emails' ? 'email' : 'ip'
  addIp.value = ''
  addEmail.value = ''
  addReason.value = ''
  addOpen.value = true
}

async function submitAdd() {
  adding.value = true
  error.value = ''
  message.value = ''
  try {
    const payload: Record<string, unknown> = {
      scope: addScope.value,
      reason: addReason.value.trim(),
    }
    if (addScope.value === 'ip' || addScope.value === 'both') {
      payload.ip_address = addIp.value.trim()
    }
    if (addScope.value === 'email' || addScope.value === 'both') {
      payload.email = addEmail.value.trim()
    }
    const res = await api.post('/admin/blocked-ips', payload)
    message.value = res.data.message || 'Access blocked.'
    addOpen.value = false
    if (addScope.value === 'email') tab.value = 'emails'
    if (addScope.value === 'ip') tab.value = 'ips'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not block access.')
  } finally {
    adding.value = false
  }
}

watch(showInactive, () => {
  load()
})

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Blocked access"
      subtitle="IPs and emails denied from the email server admin panel and API"
    >
      <template #actions>
        <v-btn color="error" variant="tonal" prepend-icon="mdi-cancel" @click="openAdd">
          Block…
        </v-btn>
        <v-btn variant="text" prepend-icon="mdi-clipboard-text-clock-outline" :to="{ name: 'audit-logs' }">
          Audit logs
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <ParentCard title="Filters">
      <v-row dense>
        <v-col cols="12" md="6">
          <v-text-field
            v-model="search"
            label="Search IP, email, or reason"
            clearable
            variant="outlined"
            hide-details
            density="comfortable"
            prepend-inner-icon="mdi-magnify"
            @keyup.enter="load"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center">
          <v-switch
            v-model="showInactive"
            label="Include unblocked"
            color="primary"
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center">
          <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Refresh</v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="ips">Blocked IPs ({{ ipItems.length }})</v-tab>
      <v-tab value="emails">Blocked emails ({{ emailItems.length }})</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="ips">
        <AppDataTable
          :loading="loading"
          :items="ipItems"
          :headers="[
            { title: 'IP address', key: 'ip_address' },
            { title: 'Reason', key: 'reason' },
            { title: 'Status', key: 'is_active' },
            { title: 'Blocked by', key: 'blocked_by' },
            { title: 'When', key: 'created_at' },
            { title: 'Actions', key: 'actions', sortable: false },
          ]"
          class="elevation-0"
        >
          <template #item.ip_address="{ item }">
            <span class="font-weight-medium text-no-wrap">{{ item.ip_address }}</span>
          </template>
          <template #item.is_active="{ item }">
            <v-chip size="small" :color="item.is_active ? 'error' : 'success'" variant="tonal">
              {{ item.is_active ? 'Blocked' : 'Unblocked' }}
            </v-chip>
          </template>
          <template #item.blocked_by="{ item }">
            <div v-if="item.blocked_by">
              <div>{{ item.blocked_by.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.blocked_by.email }}</div>
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #item.created_at="{ item }">
            <div class="text-no-wrap">{{ item.created_at ? formatDateTime12h(item.created_at) : '—' }}</div>
            <div v-if="!item.is_active && item.unblocked_at" class="text-caption text-medium-emphasis">
              Unblocked {{ formatDateTime12h(item.unblocked_at) }}
            </div>
          </template>
          <template #item.actions="{ item }">
            <v-btn
              v-if="item.is_active"
              size="small"
              color="primary"
              variant="tonal"
              :loading="unblockingId === item.id"
              @click="unblockIp(item)"
            >
              Unblock
            </v-btn>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #no-data>
            <div class="text-medium-emphasis pa-6 text-center">
              No blocked IPs yet. Block from Audit logs.
            </div>
          </template>
        </AppDataTable>
      </v-window-item>

      <v-window-item value="emails">
        <AppDataTable
          :loading="loading"
          :items="emailItems"
          :headers="[
            { title: 'Email', key: 'email' },
            { title: 'Reason', key: 'reason' },
            { title: 'Status', key: 'is_active' },
            { title: 'Blocked by', key: 'blocked_by' },
            { title: 'When', key: 'created_at' },
            { title: 'Actions', key: 'actions', sortable: false },
          ]"
          class="elevation-0"
        >
          <template #item.email="{ item }">
            <span class="font-weight-medium">{{ item.email }}</span>
          </template>
          <template #item.is_active="{ item }">
            <v-chip size="small" :color="item.is_active ? 'error' : 'success'" variant="tonal">
              {{ item.is_active ? 'Blocked' : 'Unblocked' }}
            </v-chip>
          </template>
          <template #item.blocked_by="{ item }">
            <div v-if="item.blocked_by">
              <div>{{ item.blocked_by.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.blocked_by.email }}</div>
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #item.created_at="{ item }">
            <div class="text-no-wrap">{{ item.created_at ? formatDateTime12h(item.created_at) : '—' }}</div>
            <div v-if="!item.is_active && item.unblocked_at" class="text-caption text-medium-emphasis">
              Unblocked {{ formatDateTime12h(item.unblocked_at) }}
            </div>
          </template>
          <template #item.actions="{ item }">
            <v-btn
              v-if="item.is_active"
              size="small"
              color="primary"
              variant="tonal"
              :loading="unblockingId === item.id"
              @click="unblockEmail(item)"
            >
              Unblock
            </v-btn>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #no-data>
            <div class="text-medium-emphasis pa-6 text-center">
              No blocked emails yet. Block from Audit logs.
            </div>
          </template>
        </AppDataTable>
      </v-window-item>
    </v-window>

    <v-dialog v-model="addOpen" max-width="560">
      <v-card>
        <v-card-title>Block access</v-card-title>
        <v-card-text>
          <v-radio-group v-model="addScope" class="mb-2" hide-details>
            <v-radio label="IP only" value="ip" />
            <v-radio label="Email only" value="email" />
            <v-radio label="IP and email" value="both" />
          </v-radio-group>
          <v-text-field
            v-if="addScope === 'ip' || addScope === 'both'"
            v-model="addIp"
            label="IP address"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            autocomplete="off"
          />
          <v-text-field
            v-if="addScope === 'email' || addScope === 'both'"
            v-model="addEmail"
            label="Email"
            type="email"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            autocomplete="off"
          />
          <v-textarea
            v-model="addReason"
            label="Reason"
            variant="outlined"
            density="comfortable"
            rows="3"
            hint="Required — shown in the blocked access lists"
            persistent-hint
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="addOpen = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="adding"
            :disabled="
              addReason.trim().length < 3 ||
              ((addScope === 'ip' || addScope === 'both') && !addIp.trim()) ||
              ((addScope === 'email' || addScope === 'both') && !addEmail.trim())
            "
            @click="submitAdd"
          >
            Block
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
