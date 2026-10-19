<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { statusColor, type WorkRequestRow } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

const items = ref<WorkRequestRow[]>([])
const loading = ref(true)
const error = ref('')
const message = ref('')
const q = ref('')
const router = useRouter()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/work-requests', {
      params: { q: q.value || undefined },
    })
    items.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load requests.')
  } finally {
    loading.value = false
  }
}

async function acknowledge(item: WorkRequestRow) {
  try {
    await api.post(`/admin/work-requests/${item.id}/acknowledge`)
    message.value = `${item.reference} acknowledged.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not acknowledge request.')
  }
}

async function convert(item: WorkRequestRow) {
  try {
    const res = await api.post(`/admin/work-requests/${item.id}/convert`)
    message.value = `${item.reference} converted to ${res.data.project.code}.`
    await router.push({ name: 'project-detail', params: { id: res.data.project.id } })
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not convert request.')
  }
}

function goToRequest(item: WorkRequestRow) {
  router.push({ name: 'request-detail', params: { id: item.id } })
}

function onRequestRow(_event: unknown, row: { item: WorkRequestRow }) {
  goToRequest(row.item)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Requests" subtitle="One controlled channel — no work starts until it is registered">
      <template #actions>
        <v-btn color="primary" :to="{ name: 'request-new' }">Register request</v-btn>
      </template>
    </PageHeader>
    <ParentCard title="Request register">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
      <v-row class="mb-2">
        <v-col cols="12" md="8">
          <v-text-field
            v-model="q"
            label="Search"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            @keyup.enter="load"
            @click:clear="load"
          />
        </v-col>
        <v-col cols="12" md="4" class="d-flex align-center">
          <v-btn variant="tonal" @click="load">Search</v-btn>
        </v-col>
      </v-row>
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Ref', key: 'reference', width: 140 },
          { title: 'Request', key: 'title' },
          { title: 'Unit', key: 'requesting_unit' },
          { title: 'Class', key: 'work_class_label' },
          { title: 'Score', key: 'weighted_score' },
          { title: 'Priority', key: 'priority_label' },
          { title: 'Status', key: 'status' },
          { title: '', key: 'actions', sortable: false },
        ]"
        item-value="id"
        hover
        @click:row="onRequestRow"
      >
        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(item.status)" variant="tonal">{{ item.status_label }}</v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex ga-1" @click.stop>
            <v-btn v-if="item.status === 'submitted'" size="small" variant="tonal" @click="acknowledge(item)">Ack</v-btn>
            <v-btn v-if="!item.project" size="small" color="primary" variant="tonal" @click="convert(item)">Convert</v-btn>
          </div>
        </template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
