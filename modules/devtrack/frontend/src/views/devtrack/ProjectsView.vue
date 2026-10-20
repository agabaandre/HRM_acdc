<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { ragColor, type ProjectSummary } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

const items = ref<ProjectSummary[]>([])
const loading = ref(true)
const error = ref('')
const q = ref('')
const rag = ref('')
const router = useRouter()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/projects', { params: { q: q.value || undefined, rag_status: rag.value || undefined } })
    items.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load projects.')
  } finally {
    loading.value = false
  }
}

function goToProject(item: ProjectSummary) {
  router.push({ name: 'project-detail', params: { id: item.id } })
}

function onProjectRow(_event: unknown, row: { item: ProjectSummary }) {
  goToProject(row.item)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Projects" subtitle="Every software project and its tasks, linked to a workplan indicator">
      <template #actions>
        <v-btn color="primary" :to="{ name: 'project-new' }">New project</v-btn>
      </template>
    </PageHeader>

    <ParentCard title="Register">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-row class="mb-2">
        <v-col cols="12" md="6">
          <v-text-field v-model="q" label="Search" prepend-inner-icon="mdi-magnify" clearable hide-details @keyup.enter="load" @click:clear="load" />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="rag"
            :items="[
              { title: 'All RAG', value: '' },
              { title: 'Red', value: 'red' },
              { title: 'Amber', value: 'amber' },
              { title: 'Green', value: 'green' },
            ]"
            label="RAG"
            hide-details
            @update:model-value="load"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center">
          <v-btn variant="tonal" @click="load">Filter</v-btn>
        </v-col>
      </v-row>
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code', width: 140 },
          { title: 'Req ref', key: 'work_request', width: 140 },
          { title: 'Project', key: 'name' },
          { title: 'Indicator', key: 'indicator' },
          { title: 'Class', key: 'work_class_code' },
          { title: 'Stage', key: 'stage_label' },
          { title: 'Status', key: 'status_label' },
          { title: 'RAG', key: 'rag_status' },
          { title: 'Tasks', key: 'progress_percent' },
        ]"
        item-value="id"
        hover
        @click:row="onProjectRow"
      >
        <template #item.work_request="{ item }">{{ item.work_request?.reference || '—' }}</template>
        <template #item.indicator="{ item }">
          <div>{{ item.indicator?.code ?? '—' }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.indicator?.title }}</div>
        </template>
        <template #item.status_label="{ item }">
          <v-chip size="small" variant="tonal" color="primary">{{ item.status_label || '—' }}</v-chip>
        </template>
        <template #item.rag_status="{ item }">
          <RagChip :status="item.rag_status" :label="item.rag_status" />
        </template>
        <template #item.progress_percent="{ item }">
          <div class="d-flex align-center ga-2">
            <v-progress-linear :model-value="item.progress_percent" :color="ragColor(item.rag_status)" rounded height="8" style="min-width: 80px" />
            <span class="text-caption">{{ item.done_tasks }}/{{ item.open_tasks + item.done_tasks }}</span>
          </div>
        </template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
