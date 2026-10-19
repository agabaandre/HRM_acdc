<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DatePickerField from '@/components/forms/DatePickerField.vue'
import FormField from '@/components/forms/FormField.vue'
import RagChip from '@/components/devtrack/RagChip.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import AppDataTable from '@/components/shared/AppDataTable.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { statusColor } from '@/lib/devtrack'

type ProjectOption = { id: number; code: string; name: string; label: string }
type PersonOption = { id: number; name: string; email: string | null; label: string }
type PersonRef = { staff_id: number | null; user_id: number | null; name: string; email: string | null }

type ProjectRow = {
  id: number
  code: string
  name: string
  status: string | null
  status_label: string | null
  stage_label: string | null
  rag_status: string | null
  work_class_label: string | null
  start_date: string | null
  target_end_date: string | null
  modules_count: number
  project_manager: PersonRef | null
  technical_lead: PersonRef | null
  indicator: { id: number; code: string; title: string } | null
}

type ProjectsReport = {
  name: string
  summary: { total_projects: number }
  projects: ProjectRow[]
  filter_options: {
    projects: ProjectOption[]
    project_managers: PersonOption[]
    technical_leads: PersonOption[]
  }
}

const loading = ref(true)
const exporting = ref(false)
const error = ref('')
const message = ref('')
const report = ref<ProjectsReport | null>(null)
const router = useRouter()

const filters = ref({
  project_id: null as number | null,
  project_manager_staff_id: null as number | null,
  technical_lead_staff_id: null as number | null,
  start_date: null as string | null,
  end_date: null as string | null,
})

function filterParams() {
  return {
    project_id: filters.value.project_id || undefined,
    project_manager_staff_id: filters.value.project_manager_staff_id || undefined,
    technical_lead_staff_id: filters.value.technical_lead_staff_id || undefined,
    start_date: filters.value.start_date || undefined,
    end_date: filters.value.end_date || undefined,
  }
}

async function load() {
  loading.value = true
  error.value = ''
  message.value = ''
  try {
    const res = await api.get('/admin/reports/projects', { params: filterParams() })
    report.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the projects report.')
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.value = {
    project_id: null,
    project_manager_staff_id: null,
    technical_lead_staff_id: null,
    start_date: null,
    end_date: null,
  }
  void load()
}

async function exportReport(format: 'csv' | 'xls' | 'pdf') {
  exporting.value = true
  error.value = ''
  message.value = ''
  try {
    const res = await api.get('/admin/reports/projects/export', {
      params: { ...filterParams(), format },
      responseType: 'blob',
    })
    const extension = format === 'xls' ? 'xls' : format
    const mime =
      format === 'csv'
        ? 'text/csv;charset=utf-8'
        : format === 'pdf'
          ? 'application/pdf'
          : 'application/vnd.ms-excel'
    const blob = new Blob([res.data], { type: mime })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `projects-report-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${extension}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    message.value = `Exported projects report as ${extension.toUpperCase()}.`
  } catch (err: unknown) {
    const axiosErr = err as { response?: { data?: Blob | string } }
    if (axiosErr.response?.data instanceof Blob) {
      try {
        const parsed = JSON.parse(await axiosErr.response.data.text()) as { message?: string }
        error.value = parsed.message || 'Could not export the projects report.'
      } catch {
        error.value = apiErrorMessage(err, 'Could not export the projects report.')
      }
    } else {
      error.value = apiErrorMessage(err, 'Could not export the projects report.')
    }
  } finally {
    exporting.value = false
  }
}

function openProject(id: number) {
  router.push({ name: 'project-detail', params: { id: String(id) } })
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="All Projects" subtitle="Complete list of registered projects">
      <template #actions>
        <v-btn variant="tonal" :to="{ name: 'reports' }">Back to reports</v-btn>
        <v-menu>
          <template #activator="{ props }">
            <v-btn color="primary" variant="tonal" v-bind="props" :loading="exporting">
              Export
            </v-btn>
          </template>
          <v-list density="compact">
            <v-list-item title="Export CSV" prepend-icon="mdi-file-delimited-outline" @click="exportReport('csv')" />
            <v-list-item title="Export XLS" prepend-icon="mdi-file-excel-outline" @click="exportReport('xls')" />
            <v-list-item title="Export PDF" prepend-icon="mdi-file-pdf-box" @click="exportReport('pdf')" />
          </v-list>
        </v-menu>
        <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Refresh</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>

    <ParentCard title="Filters" class="mb-4">
      <v-row>
        <v-col cols="12" md="4">
          <FormField label="Project">
            <v-select
              v-model="filters.project_id"
              :items="report?.filter_options?.projects ?? []"
              item-title="label"
              item-value="id"
              clearable
              hide-details
              placeholder="All projects"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="4">
          <FormField label="Project manager">
            <v-select
              v-model="filters.project_manager_staff_id"
              :items="report?.filter_options?.project_managers ?? []"
              item-title="label"
              item-value="id"
              clearable
              hide-details
              placeholder="All project managers"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="4">
          <FormField label="Technical lead">
            <v-select
              v-model="filters.technical_lead_staff_id"
              :items="report?.filter_options?.technical_leads ?? []"
              item-title="label"
              item-value="id"
              clearable
              hide-details
              placeholder="All technical leads"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="3">
          <FormField label="Start date from">
            <DatePickerField v-model="filters.start_date" />
          </FormField>
        </v-col>
        <v-col cols="12" md="3">
          <FormField label="End date by">
            <DatePickerField v-model="filters.end_date" />
          </FormField>
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-end ga-2">
          <v-btn color="primary" :loading="loading" @click="load">Apply</v-btn>
          <v-btn variant="tonal" @click="clearFilters">Clear</v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <v-skeleton-loader v-if="loading && !report" type="article" />

    <ParentCard v-else-if="report" :title="`${report.summary.total_projects} projects`">
      <AppDataTable
        :items="report.projects"
        :headers="[
          { title: 'Code', key: 'code', width: 120 },
          { title: 'Project', key: 'name' },
          { title: 'Project manager', key: 'project_manager', width: 180 },
          { title: 'Technical lead', key: 'technical_lead', width: 180 },
          { title: 'Status', key: 'status', width: 130 },
          { title: 'Stage', key: 'stage_label', width: 130 },
          { title: 'RAG', key: 'rag_status', width: 100 },
          { title: 'Class', key: 'work_class_label', width: 130 },
          { title: 'Modules', key: 'modules_count', width: 90 },
          { title: 'Start', key: 'start_date', width: 110 },
          { title: 'End', key: 'target_end_date', width: 110 },
        ]"
        item-value="id"
        hover
        class="cursor-pointer"
        @click:row="(_e: unknown, row: { item: ProjectRow }) => openProject(row.item.id)"
      >
        <template #item.project_manager="{ item }">
          <div v-if="item.project_manager">
            <div>{{ item.project_manager.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.project_manager.email || '' }}</div>
          </div>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.technical_lead="{ item }">
          <div v-if="item.technical_lead">
            <div>{{ item.technical_lead.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.technical_lead.email || '' }}</div>
          </div>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.status="{ item }">
          <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
            {{ item.status_label || '—' }}
          </v-chip>
        </template>
        <template #item.rag_status="{ item }">
          <RagChip v-if="item.rag_status" :status="item.rag_status" :label="item.rag_status" small />
          <span v-else>—</span>
        </template>
        <template #item.start_date="{ item }">{{ formatDate(item.start_date) }}</template>
        <template #item.target_end_date="{ item }">{{ formatDate(item.target_end_date) }}</template>
        <template #item.work_class_label="{ item }">{{ item.work_class_label || '—' }}</template>
        <template #item.stage_label="{ item }">{{ item.stage_label || '—' }}</template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
