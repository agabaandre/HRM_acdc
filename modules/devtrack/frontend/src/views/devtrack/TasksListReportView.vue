<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { statusColor } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type TaskRow = {
  id: number
  key: string
  title: string
  type_label: string | null
  status: string | null
  status_label: string | null
  priority_label: string | null
  due_date: string | null
  assignee: { id: number; name: string } | null
  project: { id: number; code: string; name: string } | null
  module: { id: number; code: string; name: string } | null
  enhancement: { id: number; reference: string; name: string } | null
  attached_to: string
  attachment_label: string
}

type FilterOption = { value?: string; id?: number; label: string }

type TasksReport = {
  name: string
  summary: { total_tasks: number }
  tasks: TaskRow[]
  filter_options?: {
    statuses: FilterOption[]
    projects: FilterOption[]
    assignees: FilterOption[]
  }
}

const loading = ref(true)
const error = ref('')
const report = ref<TasksReport | null>(null)
const router = useRouter()

const filters = ref({
  status: null as string | null,
  project_id: null as number | null,
  assignee_id: null as number | null,
})

function filterParams() {
  return {
    status: filters.value.status || undefined,
    project_id: filters.value.project_id || undefined,
    assignee_id: filters.value.assignee_id || undefined,
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/reports/tasks', { params: filterParams() })
    report.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the tasks report.')
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.value = {
    status: null,
    project_id: null,
    assignee_id: null,
  }
  void load()
}

function openTask(item: TaskRow) {
  if (item.module?.id) {
    router.push({ name: 'module-detail', params: { id: String(item.module.id) } })
    return
  }
  if (item.enhancement?.id) {
    router.push({ name: 'enhancement-detail', params: { id: String(item.enhancement.id) } })
    return
  }
  if (item.project?.id) {
    router.push({ name: 'project-detail', params: { id: String(item.project.id) } })
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="All Tasks"
      subtitle="Every task with the project or module it is attached to"
    >
      <template #actions>
        <v-btn variant="tonal" :to="{ name: 'reports' }">Back to reports</v-btn>
        <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Refresh</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>

    <ParentCard title="Filters" class="mb-4">
      <v-row>
        <v-col cols="12" md="3">
          <FormField label="Status">
            <v-select
              v-model="filters.status"
              :items="report?.filter_options?.statuses ?? []"
              item-title="label"
              item-value="value"
              clearable
              hide-details
              placeholder="All statuses"
            />
          </FormField>
        </v-col>
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
        <v-col cols="12" md="3">
          <FormField label="Assignee">
            <v-select
              v-model="filters.assignee_id"
              :items="report?.filter_options?.assignees ?? []"
              item-title="label"
              item-value="id"
              clearable
              hide-details
              placeholder="All assignees"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="2" class="d-flex align-end ga-2">
          <v-btn color="primary" :loading="loading" @click="load">Apply</v-btn>
          <v-btn variant="tonal" @click="clearFilters">Clear</v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <v-skeleton-loader v-if="loading && !report" type="article" />

    <ParentCard v-else-if="report" :title="`${report.summary.total_tasks} tasks`">
      <AppDataTable
        :items="report.tasks"
        :headers="[
          { title: 'Key', key: 'key', width: 130 },
          { title: 'Task', key: 'title' },
          { title: 'Status', key: 'status', width: 130 },
          { title: 'Priority', key: 'priority_label', width: 120 },
          { title: 'Attached to', key: 'attached_to', width: 130 },
          { title: 'Project', key: 'project' },
          { title: 'Module / Enhancement', key: 'attachment_label' },
          { title: 'Due', key: 'due_date', width: 120 },
          { title: 'Assignee', key: 'assignee', width: 150 },
        ]"
        item-value="id"
        hover
        class="cursor-pointer"
        @click:row="(_e: unknown, row: { item: TaskRow }) => openTask(row.item)"
      >
        <template #item.status="{ item }">
          <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
            {{ item.status_label || '—' }}
          </v-chip>
        </template>
        <template #item.attached_to="{ item }">
          <v-chip
            size="x-small"
            variant="tonal"
            :color="item.attached_to === 'Module' ? 'info' : item.attached_to === 'Enhancement' ? 'secondary' : 'primary'"
          >
            {{ item.attached_to }}
          </v-chip>
        </template>
        <template #item.project="{ item }">
          {{ item.project ? `${item.project.code} · ${item.project.name}` : '—' }}
        </template>
        <template #item.attachment_label="{ item }">
          <span v-if="item.module">{{ item.module.code }} · {{ item.module.name }}</span>
          <span v-else-if="item.enhancement">{{ item.enhancement.reference }} · {{ item.enhancement.name }}</span>
          <span v-else class="text-medium-emphasis">Project-level task</span>
        </template>
        <template #item.due_date="{ item }">{{ formatDate(item.due_date) }}</template>
        <template #item.assignee="{ item }">{{ item.assignee?.name || '—' }}</template>
        <template #item.priority_label="{ item }">{{ item.priority_label || '—' }}</template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
