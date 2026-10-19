<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import type { StatusReportRow } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

const items = ref<StatusReportRow[]>([])
const loading = ref(true)
const error = ref('')
const period = ref('')
const router = useRouter()

const portfolioReports = [
  {
    key: 'data_quality',
    name: 'Data Quality',
    description: 'Projects missing roles, modules, or dates, and tasks missing end dates.',
    to: 'report-data-quality',
    icon: 'mdi-database-check-outline',
    color: 'primary',
  },
  {
    key: 'projects_list',
    name: 'All Projects',
    description: 'Full list of every project in the register with status, stage, and dates.',
    to: 'report-projects',
    icon: 'mdi-folder-multiple-outline',
    color: 'info',
  },
  {
    key: 'tasks_list',
    name: 'All Tasks',
    description: 'Full list of tasks with the project or module each one is attached to.',
    to: 'report-tasks',
    icon: 'mdi-checkbox-marked-outline',
    color: 'secondary',
  },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/status-reports', { params: { period: period.value || undefined } })
    items.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load reports.')
  } finally {
    loading.value = false
  }
}

function goToReport(item: StatusReportRow) {
  router.push({ name: 'report-detail', params: { id: item.id } })
}

function onReportRow(_event: unknown, row: { item: StatusReportRow }) {
  goToReport(row.item)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Reports" subtitle="Portfolio checks and delivery status reporting">
      <template #actions>
        <v-btn color="primary" :to="{ name: 'report-new' }">New status report</v-btn>
      </template>
    </PageHeader>

    <ParentCard title="Portfolio reports" class="mb-4">
      <v-row>
        <v-col v-for="report in portfolioReports" :key="report.key" cols="12" md="6" lg="4">
          <v-card
            elevation="10"
            class="withbg h-100 cursor-pointer"
            @click="router.push({ name: report.to })"
          >
            <v-card-text>
              <div class="d-flex align-center ga-3 mb-3">
                <v-avatar :color="report.color" variant="tonal" size="40" rounded>
                  <v-icon :icon="report.icon" />
                </v-avatar>
                <div class="text-subtitle-1 font-weight-medium">{{ report.name }}</div>
              </div>
              <div class="text-body-2 text-medium-emphasis">{{ report.description }}</div>
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn color="primary" variant="text" :to="{ name: report.to }">Open report</v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </ParentCard>

    <ParentCard title="Submitted status reports">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-select
        v-model="period"
        class="mb-4"
        style="max-width: 280px"
        :items="[
          { title: 'All cadences', value: '' },
          { title: 'Weekly', value: 'weekly' },
          { title: 'Monthly', value: 'monthly' },
          { title: 'Quarterly', value: 'quarterly' },
        ]"
        hide-details
        @update:model-value="load"
      />
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Title', key: 'title' },
          { title: 'Cadence', key: 'period_label' },
          { title: 'Coverage', key: 'project' },
          { title: 'RAG', key: 'rag_status' },
          { title: 'Period', key: 'period_end' },
          { title: 'By', key: 'submitted_by' },
        ]"
        hover
        @click:row="onReportRow"
      >
        <template #item.project="{ item }">{{ item.project?.code || 'All projects' }}</template>
        <template #item.rag_status="{ item }"><RagChip :status="item.rag_status" :label="item.rag_status" small /></template>
        <template #item.period_end="{ item }">{{ formatDate(item.period_start) }} – {{ formatDate(item.period_end) }}</template>
        <template #item.submitted_by="{ item }">{{ item.submitted_by?.name || '—' }}</template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
