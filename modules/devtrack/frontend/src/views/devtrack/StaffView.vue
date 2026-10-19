<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import AppDataTable from '@/components/shared/AppDataTable.vue'
type StaffRow = {
  id: number
  staff_id: number
  fname: string | null
  lname: string | null
  name: string
  work_email: string | null
  phone: string | null
  title: string | null
  job_name: string | null
  division_name: string | null
  duty_station_name: string | null
  status: string | null
  active: boolean
  is_active?: boolean
}

const items = ref<StaffRow[]>([])
const loading = ref(true)
const error = ref('')
const q = ref('')

const activeCount = computed(() => items.value.filter((s) => s.active ?? s.is_active).length)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<{ data: StaffRow[] }>('/admin/staff', {
      params: { q: q.value || undefined },
    })
    items.value = res.data.data
  } catch (err) {
    items.value = []
    error.value = apiErrorMessage(err, 'Could not load staff.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Staff" subtitle="Directory synced from the Africa CDC Staff Share API">
      <template #actions>
        <v-btn variant="tonal" color="primary" :to="{ name: 'staff-share' }" prepend-icon="mdi-cog-outline">
          Sync settings
        </v-btn>
      </template>
    </PageHeader>

    <ParentCard :title="`Directory (${items.length})`">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>

      <v-row class="mb-2" align="center">
        <v-col cols="12" md="6">
          <v-text-field
            v-model="q"
            label="Search name, email, division, or directorate"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            @keyup.enter="load"
            @click:clear="load"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center">
          <v-btn variant="tonal" @click="load">Search</v-btn>
        </v-col>
        <v-col cols="12" md="3" class="text-md-end text-body-2 text-medium-emphasis">
          {{ activeCount }} active
        </v-col>
      </v-row>

      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Staff ID', key: 'staff_id', width: 100 },
          { title: 'Name', key: 'name' },
          { title: 'Email', key: 'work_email' },
          { title: 'Job', key: 'job_name' },
          { title: 'Division', key: 'division_name' },
          { title: 'Duty station', key: 'duty_station_name' },
          { title: 'Phone', key: 'phone', width: 140 },
          { title: 'Status', key: 'status', width: 140 },
          { title: 'Active', key: 'active', width: 100 },
        ]"
        item-value="id"
        hover
      >
        <template #item.work_email="{ item }">{{ item.work_email || '—' }}</template>
        <template #item.job_name="{ item }">{{ item.job_name || item.title || '—' }}</template>
        <template #item.division_name="{ item }">{{ item.division_name || '—' }}</template>
        <template #item.duty_station_name="{ item }">{{ item.duty_station_name || '—' }}</template>
        <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
        <template #item.status="{ item }">{{ item.status || '—' }}</template>
        <template #item.active="{ item }">
          <v-chip size="small" :color="(item.active ?? item.is_active) ? 'success' : 'default'" variant="tonal">
            {{ (item.active ?? item.is_active) ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            No staff rows yet. Open Sync settings to add a Bearer token and sync from Staff Share.
          </div>
        </template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
