<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { statusColor } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type RoleOption = { value: string; label: string }

type ProjectIssueRow = {
  id: number
  code: string
  name: string
  status: string | null
  status_label: string | null
}

type MissingRolesRow = ProjectIssueRow & {
  filled_roles_count: number
  required_roles_count: number
  missing_roles: RoleOption[]
  missing_roles_label: string
  modules_count: number
}

type MissingModulesRow = ProjectIssueRow & {
  modules_count: number
}

type MissingProjectDatesRow = ProjectIssueRow & {
  start_date: string | null
  target_end_date: string | null
  missing_start_date: boolean
  missing_end_date: boolean
  missing_dates_label: string
}

type MissingTaskDatesRow = {
  id: number
  key: string
  title: string
  status: string | null
  status_label: string | null
  priority_label: string | null
  assignee: { id: number; name: string } | null
  project: { id: number; code: string; name: string } | null
  module: { id: number; code: string; name: string } | null
  enhancement: { id: number; reference: string; name: string } | null
}

type DataQualityReport = {
  key: string
  name: string
  description: string
  generated_at: string
  summary: {
    total_projects: number
    missing_roles_count: number
    missing_modules_count: number
    missing_project_dates_count: number
    missing_task_dates_count: number
  }
  required_roles: RoleOption[]
  missing_roles: MissingRolesRow[]
  missing_modules: MissingModulesRow[]
  missing_project_dates: MissingProjectDatesRow[]
  missing_task_dates: MissingTaskDatesRow[]
}

const loading = ref(true)
const error = ref('')
const report = ref<DataQualityReport | null>(null)
const router = useRouter()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/reports/data-quality')
    report.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the data quality report.')
  } finally {
    loading.value = false
  }
}

function openProject(id: number) {
  router.push({ name: 'project-detail', params: { id: String(id) } })
}

function openTaskProject(item: MissingTaskDatesRow) {
  if (item.module?.id) {
    router.push({ name: 'module-detail', params: { id: String(item.module.id) } })
    return
  }
  if (item.enhancement?.id) {
    router.push({ name: 'enhancement-detail', params: { id: String(item.enhancement.id) } })
    return
  }
  if (item.project?.id) {
    openProject(item.project.id)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Data Quality"
      subtitle="Projects and tasks with incomplete roles, modules, or dates"
    >
      <template #actions>
        <v-btn variant="tonal" :to="{ name: 'reports' }">Back to reports</v-btn>
        <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Refresh</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-skeleton-loader v-if="loading && !report" type="article" />

    <template v-else-if="report">
      <v-row class="mb-4">
        <v-col cols="12" sm="6" md="4" lg>
          <v-card elevation="10" class="withbg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Projects reviewed</div>
              <div class="text-h5 font-weight-semibold">{{ report.summary.total_projects }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg>
          <v-card elevation="10" class="withbg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Missing roles</div>
              <div class="text-h5 font-weight-semibold">{{ report.summary.missing_roles_count }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg>
          <v-card elevation="10" class="withbg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Missing modules</div>
              <div class="text-h5 font-weight-semibold">{{ report.summary.missing_modules_count }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg>
          <v-card elevation="10" class="withbg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Projects missing dates</div>
              <div class="text-h5 font-weight-semibold">{{ report.summary.missing_project_dates_count }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg>
          <v-card elevation="10" class="withbg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Tasks missing end date</div>
              <div class="text-h5 font-weight-semibold">{{ report.summary.missing_task_dates_count }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="Required roles" class="mb-4">
        <div class="d-flex flex-wrap ga-2">
          <v-chip
            v-for="role in report.required_roles"
            :key="role.value"
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ role.label }}
          </v-chip>
        </div>
      </ParentCard>

      <ParentCard title="Projects missing the 6 roles" class="mb-4">
        <AppDataTable
          :items="report.missing_roles"
          :headers="[
            { title: 'Code', key: 'code', width: 130 },
            { title: 'Project', key: 'name' },
            { title: 'Status', key: 'status', width: 140 },
            { title: 'Roles filled', key: 'filled_roles_count', width: 120 },
            { title: 'Missing roles', key: 'missing_roles_label' },
          ]"
          item-value="id"
          hover
          class="cursor-pointer"
          @click:row="(_e: unknown, row: { item: MissingRolesRow }) => openProject(row.item.id)"
        >
          <template #item.status="{ item }">
            <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
              {{ item.status_label || '—' }}
            </v-chip>
          </template>
          <template #item.filled_roles_count="{ item }">
            {{ item.filled_roles_count }} / {{ item.required_roles_count }}
          </template>
          <template #item.missing_roles_label="{ item }">
            <div class="d-flex flex-wrap ga-1">
              <v-chip
                v-for="role in item.missing_roles"
                :key="role.value"
                size="x-small"
                variant="tonal"
                color="warning"
              >
                {{ role.label }}
              </v-chip>
            </div>
          </template>
          <template #no-data>
            <div class="text-body-2 text-medium-emphasis pa-4">
              All projects have the six named roles assigned.
            </div>
          </template>
        </AppDataTable>
      </ParentCard>

      <ParentCard title="Projects missing at least one module" class="mb-4">
        <AppDataTable
          :items="report.missing_modules"
          :headers="[
            { title: 'Code', key: 'code', width: 130 },
            { title: 'Project', key: 'name' },
            { title: 'Status', key: 'status', width: 140 },
            { title: 'Modules', key: 'modules_count', width: 120 },
          ]"
          item-value="id"
          hover
          class="cursor-pointer"
          @click:row="(_e: unknown, row: { item: MissingModulesRow }) => openProject(row.item.id)"
        >
          <template #item.status="{ item }">
            <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
              {{ item.status_label || '—' }}
            </v-chip>
          </template>
          <template #item.modules_count="{ item }">
            <v-chip size="small" variant="tonal" color="error">{{ item.modules_count }}</v-chip>
          </template>
          <template #no-data>
            <div class="text-body-2 text-medium-emphasis pa-4">
              Every project has at least one module.
            </div>
          </template>
        </AppDataTable>
      </ParentCard>

      <ParentCard title="Projects missing start or end date" class="mb-4">
        <AppDataTable
          :items="report.missing_project_dates ?? []"
          :headers="[
            { title: 'Code', key: 'code', width: 130 },
            { title: 'Project', key: 'name' },
            { title: 'Status', key: 'status', width: 140 },
            { title: 'Start date', key: 'start_date', width: 130 },
            { title: 'End date', key: 'target_end_date', width: 130 },
            { title: 'Missing', key: 'missing_dates_label' },
          ]"
          item-value="id"
          hover
          class="cursor-pointer"
          @click:row="(_e: unknown, row: { item: MissingProjectDatesRow }) => openProject(row.item.id)"
        >
          <template #item.status="{ item }">
            <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
              {{ item.status_label || '—' }}
            </v-chip>
          </template>
          <template #item.start_date="{ item }">
            <span :class="{ 'text-error': item.missing_start_date }">
              {{ item.start_date ? formatDate(item.start_date) : 'Missing' }}
            </span>
          </template>
          <template #item.target_end_date="{ item }">
            <span :class="{ 'text-error': item.missing_end_date }">
              {{ item.target_end_date ? formatDate(item.target_end_date) : 'Missing' }}
            </span>
          </template>
          <template #item.missing_dates_label="{ item }">
            <div class="d-flex flex-wrap ga-1">
              <v-chip v-if="item.missing_start_date" size="x-small" variant="tonal" color="warning">Start date</v-chip>
              <v-chip v-if="item.missing_end_date" size="x-small" variant="tonal" color="warning">End date</v-chip>
            </div>
          </template>
          <template #no-data>
            <div class="text-body-2 text-medium-emphasis pa-4">
              All projects have start and end dates.
            </div>
          </template>
        </AppDataTable>
      </ParentCard>

      <ParentCard title="Tasks missing end date">
        <AppDataTable
          :items="report.missing_task_dates ?? []"
          :headers="[
            { title: 'Key', key: 'key', width: 130 },
            { title: 'Task', key: 'title' },
            { title: 'Status', key: 'status', width: 130 },
            { title: 'Project', key: 'project' },
            { title: 'Module / Enhancement', key: 'module' },
            { title: 'Assignee', key: 'assignee', width: 160 },
          ]"
          item-value="id"
          hover
          class="cursor-pointer"
          @click:row="(_e: unknown, row: { item: MissingTaskDatesRow }) => openTaskProject(row.item)"
        >
          <template #item.status="{ item }">
            <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
              {{ item.status_label || '—' }}
            </v-chip>
          </template>
          <template #item.project="{ item }">
            {{ item.project ? `${item.project.code} · ${item.project.name}` : '—' }}
          </template>
          <template #item.module="{ item }">
            <span v-if="item.module">{{ item.module.code }} · {{ item.module.name }}</span>
            <span v-else-if="item.enhancement">{{ item.enhancement.reference }} · {{ item.enhancement.name }}</span>
            <span v-else class="text-medium-emphasis">Project task</span>
          </template>
          <template #item.assignee="{ item }">{{ item.assignee?.name || '—' }}</template>
          <template #no-data>
            <div class="text-body-2 text-medium-emphasis pa-4">
              All tasks have an end date.
            </div>
          </template>
        </AppDataTable>
      </ParentCard>
    </template>
  </div>
</template>
