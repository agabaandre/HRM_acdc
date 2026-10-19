<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import TaskActivityChart from '@/components/devtrack/TaskActivityChart.vue'
import StatCard from '@/components/dashboard/StatCard.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { formatDate } from '@/lib/formatDate'
import { ragColor, statusColor, type ProjectSummary, type StatusReportRow, type WorkItemRow } from '@/lib/devtrack'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type ActivityDay = { date: string; label: string; created: number; completed: number }

type IndicatorRow = {
  id: number
  code: string
  title: string
  programme: string | null
  projects_count: number
  open_tasks: number
  done_tasks: number
}

type ProjectManagerProject = {
  id: number
  code: string
  name: string
  status: string
  status_label: string
  completed_tasks: number
  pending_tasks: number
}

type ProjectManagerRow = {
  key: string
  name: string
  email: string | null
  projects_count: number
  status_summary: string
  status_counts: {
    not_started: number
    in_progress: number
    under_review: number
    completed: number
  }
  completed_tasks: number
  pending_tasks: number
  projects: ProjectManagerProject[]
}

type DashboardData = {
  stats: Record<string, number>
  rag: { green: number; amber: number; red: number }
  stages: Array<{ stage: string; label: string; count: number }>
  work_classes: Array<{ work_class: string; label: string; count: number }>
  indicators: IndicatorRow[]
  task_activity: ActivityDay[]
  project_managers: ProjectManagerRow[]
  portfolio: ProjectSummary[]
  my_work: WorkItemRow[]
  attention: WorkItemRow[]
  recent_reports: StatusReportRow[]
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()
const selectedManager = ref<ProjectManagerRow | null>(null)
const managerDialog = ref(false)

const statusStatCards = computed(() => {
  const stats = data.value?.stats ?? {}
  return [
    { key: 'total_projects', title: 'Total Projects', value: stats.total_projects ?? 0, icon: 'mdi-folder-multiple-outline', color: 'primary', subtitle: 'All projects registered', to: 'projects' },
    { key: 'completed_projects', title: 'Completed', value: stats.completed_projects ?? 0, icon: 'mdi-check-circle-outline', color: 'success', subtitle: 'Marked completed', to: 'projects' },
    { key: 'in_progress_projects', title: 'In Progress', value: stats.in_progress_projects ?? 0, icon: 'mdi-progress-clock', color: 'info', subtitle: 'Currently underway', to: 'projects' },
    { key: 'not_started_projects', title: 'Not yet Started', value: stats.not_started_projects ?? 0, icon: 'mdi-timer-sand', color: 'secondary', subtitle: 'Awaiting kickoff', to: 'projects' },
  ]
})

const moduleStatCards = computed(() => {
  const stats = data.value?.stats ?? {}
  return [
    { key: 'total_modules', title: 'Total Modules', value: stats.total_modules ?? 0, icon: 'mdi-view-module-outline', color: 'primary', subtitle: 'Across all projects', to: 'projects' },
    { key: 'completed_modules', title: 'Completed', value: stats.completed_modules ?? 0, icon: 'mdi-check-circle-outline', color: 'success', subtitle: 'Modules completed', to: 'projects' },
    { key: 'in_progress_modules', title: 'In Progress', value: stats.in_progress_modules ?? 0, icon: 'mdi-progress-clock', color: 'info', subtitle: 'Modules underway', to: 'projects' },
    { key: 'not_started_modules', title: 'Not Yet Started', value: stats.not_started_modules ?? 0, icon: 'mdi-timer-sand', color: 'secondary', subtitle: 'Modules awaiting kickoff', to: 'projects' },
  ]
})

function goToProject(item: ProjectSummary) {
  router.push({ name: 'project-detail', params: { id: item.id } })
}

function goToReport(item: StatusReportRow) {
  router.push({ name: 'report-detail', params: { id: item.id } })
}

function onProjectRow(_event: unknown, row: { item: ProjectSummary }) {
  goToProject(row.item)
}

function onReportRow(_event: unknown, row: { item: StatusReportRow }) {
  goToReport(row.item)
}

function onManagerRow(_event: unknown, row: { item: ProjectManagerRow }) {
  selectedManager.value = row.item
  managerDialog.value = true
}

function openManagerProject(project: ProjectManagerProject) {
  managerDialog.value = false
  router.push({ name: 'project-detail', params: { id: project.id } })
}

onMounted(async () => {
  try {
    const res = await api.get('/admin/dashboard')
    data.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <PageHeader
      :title="`Welcome back, ${auth.user?.name ?? 'team'}`"
      :subtitle="`${branding.branding.app_name} — project status, assigned work and reporting cadence`"
    >
      <template #actions>
        <v-btn color="primary" variant="tonal" :to="{ name: 'request-new' }">Register request</v-btn>
        <v-btn color="primary" :to="{ name: 'project-new' }">New project</v-btn>
      </template>
    </PageHeader>

    <v-row v-if="loading">
      <v-col v-for="n in 8" :key="n" cols="12" sm="6" lg="3">
        <v-skeleton-loader type="card" />
      </v-col>
    </v-row>

    <template v-else-if="data">
      <v-row>
        <v-col v-for="card in statusStatCards" :key="card.key" cols="12" sm="6" lg="3">
          <StatCard
            :title="card.title"
            :value="card.value"
            :icon="card.icon"
            :color="card.color"
            :subtitle="card.subtitle"
            clickable
            @click="router.push({ name: card.to })"
          />
        </v-col>
      </v-row>

      <v-row class="mt-1">
        <v-col v-for="card in moduleStatCards" :key="card.key" cols="12" sm="6" lg="3">
          <StatCard
            :title="card.title"
            :value="card.value"
            :icon="card.icon"
            :color="card.color"
            :subtitle="card.subtitle"
            clickable
            @click="router.push({ name: card.to })"
          />
        </v-col>
      </v-row>

      <ParentCard title="Project managers" class="mt-4">
        <AppDataTable
          :items="data.project_managers ?? []"
          :headers="[
            { title: 'Project manager', key: 'name' },
            { title: 'Projects managed', key: 'projects_count', width: 140 },
            { title: 'Project status', key: 'status_summary' },
            { title: 'Completed tasks', key: 'completed_tasks', width: 150 },
            { title: 'Pending tasks', key: 'pending_tasks', width: 140 },
          ]"
          item-value="key"
          density="comfortable"
          hover
          class="cursor-pointer"
          @click:row="onManagerRow"
        >
          <template #item.name="{ item }">
            <div class="font-weight-medium">{{ item.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.email || '—' }}</div>
          </template>
          <template #item.projects_count="{ item }">
            <v-chip size="small" variant="tonal" color="primary">{{ item.projects_count }}</v-chip>
          </template>
          <template #item.status_summary="{ item }">
            <div class="d-flex flex-wrap ga-1">
              <v-chip
                v-if="item.status_counts.not_started"
                size="x-small"
                variant="tonal"
                color="secondary"
              >
                {{ item.status_counts.not_started }} Not Started
              </v-chip>
              <v-chip
                v-if="item.status_counts.in_progress"
                size="x-small"
                variant="tonal"
                color="info"
              >
                {{ item.status_counts.in_progress }} In Progress
              </v-chip>
              <v-chip
                v-if="item.status_counts.under_review"
                size="x-small"
                variant="tonal"
                color="warning"
              >
                {{ item.status_counts.under_review }} Under Review
              </v-chip>
              <v-chip
                v-if="item.status_counts.completed"
                size="x-small"
                variant="tonal"
                color="success"
              >
                {{ item.status_counts.completed }} Completed
              </v-chip>
              <span
                v-if="
                  !item.status_counts.not_started &&
                  !item.status_counts.in_progress &&
                  !item.status_counts.under_review &&
                  !item.status_counts.completed
                "
                class="text-medium-emphasis"
              >—</span>
            </div>
          </template>
          <template #item.completed_tasks="{ item }">
            <span class="font-weight-medium text-success">{{ item.completed_tasks }}</span>
          </template>
          <template #item.pending_tasks="{ item }">
            <span class="font-weight-medium">{{ item.pending_tasks }}</span>
          </template>
          <template #no-data>
            <div class="text-body-2 text-medium-emphasis pa-4">
              No project managers assigned yet.
            </div>
          </template>
        </AppDataTable>
      </ParentCard>

      <v-dialog v-model="managerDialog" max-width="860">
        <v-card v-if="selectedManager">
          <v-card-title class="d-flex flex-column align-start ga-1">
            <span>{{ selectedManager.name }}</span>
            <span class="text-caption text-medium-emphasis font-weight-regular">
              {{ selectedManager.email || 'No email on file' }}
            </span>
          </v-card-title>
          <v-card-subtitle class="pb-2">
            {{ selectedManager.projects_count }} project{{ selectedManager.projects_count === 1 ? '' : 's' }}
            · {{ selectedManager.completed_tasks }} completed tasks
            · {{ selectedManager.pending_tasks }} pending
          </v-card-subtitle>
          <v-card-text>
            <div class="d-flex flex-wrap ga-1 mb-4">
              <v-chip
                v-if="selectedManager.status_counts.not_started"
                size="small"
                variant="tonal"
                color="secondary"
              >
                {{ selectedManager.status_counts.not_started }} Not Started
              </v-chip>
              <v-chip
                v-if="selectedManager.status_counts.in_progress"
                size="small"
                variant="tonal"
                color="info"
              >
                {{ selectedManager.status_counts.in_progress }} In Progress
              </v-chip>
              <v-chip
                v-if="selectedManager.status_counts.under_review"
                size="small"
                variant="tonal"
                color="warning"
              >
                {{ selectedManager.status_counts.under_review }} Under Review
              </v-chip>
              <v-chip
                v-if="selectedManager.status_counts.completed"
                size="small"
                variant="tonal"
                color="success"
              >
                {{ selectedManager.status_counts.completed }} Completed
              </v-chip>
            </div>
            <AppDataTable
              :items="selectedManager.projects ?? []"
              :headers="[
                { title: 'Code', key: 'code', width: 130 },
                { title: 'Project', key: 'name' },
                { title: 'Status', key: 'status', width: 140 },
                { title: 'Completed tasks', key: 'completed_tasks', width: 150 },
                { title: 'Pending tasks', key: 'pending_tasks', width: 140 },
              ]"
              item-value="id"
              density="comfortable"
              hover
              class="cursor-pointer"
              @click:row="(_e: unknown, row: { item: ProjectManagerProject }) => openManagerProject(row.item)"
            >
              <template #item.status="{ item }">
                <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
                  {{ item.status_label }}
                </v-chip>
              </template>
              <template #item.completed_tasks="{ item }">
                <span class="font-weight-medium text-success">{{ item.completed_tasks }}</span>
              </template>
              <template #no-data>
                <div class="text-body-2 text-medium-emphasis pa-4">
                  No projects assigned to this manager.
                </div>
              </template>
            </AppDataTable>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="managerDialog = false">Close</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-row class="mt-1">
        <v-col cols="12" lg="8">
          <TaskActivityChart :activity="data.task_activity ?? []" />
        </v-col>
        <v-col cols="12" lg="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <h3 class="card-title mb-1">RAG posture</h3>
              <h5 class="card-subtitle mb-6">Active projects against the operating model</h5>
              <div class="d-flex flex-column ga-4">
                <div v-for="row in [
                  { key: 'green', label: 'Under control', hint: 'No intervention needed', count: data.rag.green },
                  { key: 'amber', label: 'Risk forming', hint: 'Mitigation in progress', count: data.rag.amber },
                  { key: 'red', label: 'Major blocker', hint: 'Escalation required', count: data.rag.red },
                ]" :key="row.key" class="d-flex align-center justify-space-between">
                  <div>
                    <RagChip :status="row.key" :label="row.label" />
                    <div class="text-caption text-medium-emphasis mt-1">{{ row.hint }}</div>
                  </div>
                  <div class="text-h5 font-weight-semibold">{{ row.count }}</div>
                </div>
              </div>
              <v-divider class="my-5" />
              <div class="text-subtitle-2 mb-3">Lifecycle pipeline</div>
              <div v-for="stage in data.stages" :key="stage.stage" class="d-flex align-center justify-space-between mb-2">
                <span class="text-body-2">{{ stage.label }}</span>
                <v-chip size="small" variant="tonal">{{ stage.count }}</v-chip>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="Projects">
        <template #action>
          <v-btn variant="text" color="primary" :to="{ name: 'projects' }">View all projects</v-btn>
        </template>
        <AppDataTable
          :items="data.portfolio"
          :headers="[
            { title: 'Project', key: 'name' },
            { title: 'Indicator', key: 'indicator' },
            { title: 'Class', key: 'work_class_code' },
            { title: 'Stage', key: 'stage_label' },
            { title: 'RAG', key: 'rag_status' },
            { title: 'Progress', key: 'progress_percent' },
            { title: 'Next gate', key: 'next_gate' },
          ]"
          item-value="id"
          hover
          class="dashboard-table"
          @click:row="onProjectRow"
        >
          <template #item.name="{ item }">
            <div class="font-weight-medium">{{ item.code }}</div>
            <div class="text-body-2">{{ item.name }}</div>
          </template>
          <template #item.indicator="{ item }">
            <div class="text-body-2">{{ item.indicator?.code ?? '—' }}</div>
            <div class="text-caption text-medium-emphasis text-truncate" style="max-width: 220px">
              {{ item.indicator?.title }}
            </div>
          </template>
          <template #item.work_class_code="{ item }">
            <v-chip size="small" variant="tonal">{{ item.work_class_code }} · {{ item.work_class_label }}</v-chip>
          </template>
          <template #item.rag_status="{ item }">
            <RagChip :status="item.rag_status" :label="item.rag_status" />
          </template>
          <template #item.progress_percent="{ item }">
            <div class="d-flex align-center ga-2" style="min-width: 120px">
              <v-progress-linear :model-value="item.progress_percent" :color="ragColor(item.rag_status)" rounded height="8" />
              <span class="text-caption">{{ item.progress_percent }}%</span>
            </div>
          </template>
          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">No projects in the register yet.</div>
          </template>
        </AppDataTable>
      </ParentCard>

      <v-row>
        <v-col cols="12" lg="6">
          <ParentCard title="My assigned work">
            <template #action>
              <v-btn variant="text" color="primary" :to="{ name: 'my-work' }">Open board</v-btn>
            </template>
            <v-list v-if="data.my_work.length" lines="two">
              <v-list-item v-for="item in data.my_work" :key="item.id" :to="{ name: 'work', query: { q: item.key } }">
                <template #prepend>
                  <v-chip size="x-small" :color="statusColor(item.status)" variant="tonal">{{ item.status_label }}</v-chip>
                </template>
                <v-list-item-title>{{ item.key }} · {{ item.title }}</v-list-item-title>
                <v-list-item-subtitle>
                  {{ item.project?.code }} · due {{ formatDate(item.due_date) }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
            <div v-else class="text-medium-emphasis py-4">Nothing assigned to you right now.</div>
          </ParentCard>
        </v-col>
        <v-col cols="12" lg="6">
          <ParentCard title="Attention">
            <v-list v-if="data.attention.length" lines="two">
              <v-list-item v-for="item in data.attention" :key="item.id">
                <template #prepend>
                  <v-icon color="warning">mdi-clock-alert-outline</v-icon>
                </template>
                <v-list-item-title>{{ item.key }} · {{ item.title }}</v-list-item-title>
                <v-list-item-subtitle>
                  {{ item.project?.code }} · {{ item.assignee?.name || 'Unassigned' }}
                  <span v-if="item.is_overdue"> · overdue</span>
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
            <div v-else class="text-medium-emphasis py-4">No overdue work items.</div>
          </ParentCard>
        </v-col>
      </v-row>

      <ParentCard title="Workplan indicators">
        <template #action>
          <v-btn variant="text" color="primary" :to="{ name: 'indicators' }">Manage indicators</v-btn>
        </template>
        <v-row>
          <v-col v-for="indicator in data.indicators" :key="indicator.id" cols="12" md="6" lg="3">
            <v-card variant="outlined" class="h-100">
              <v-card-text>
                <div class="text-caption text-medium-emphasis">{{ indicator.code }}</div>
                <div class="font-weight-medium mb-3">{{ indicator.title }}</div>
                <div class="d-flex justify-space-between text-caption">
                  <span>{{ indicator.projects_count }} projects</span>
                  <span>{{ indicator.done_tasks }}/{{ indicator.open_tasks + indicator.done_tasks }} tasks done</span>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </ParentCard>

      <ParentCard title="Recent reports">
        <template #action>
          <v-btn variant="text" color="primary" :to="{ name: 'reports' }">All reports</v-btn>
        </template>
        <AppDataTable
          :items="data.recent_reports"
          :headers="[
            { title: 'Report', key: 'title' },
            { title: 'Cadence', key: 'period_label' },
            { title: 'Coverage', key: 'project' },
            { title: 'RAG', key: 'rag_status' },
            { title: 'Period', key: 'period_end' },
          ]"
          item-value="id"
          hover
          hide-default-footer
          @click:row="onReportRow"
        >
          <template #item.project="{ item }">
            {{ item.project ? `${item.project.code}` : 'All projects' }}
          </template>
          <template #item.rag_status="{ item }">
            <RagChip :status="item.rag_status" :label="item.rag_status" small />
          </template>
          <template #item.period_end="{ item }">
            {{ formatDate(item.period_start) }} – {{ formatDate(item.period_end) }}
          </template>
        </AppDataTable>
      </ParentCard>
    </template>
  </div>
</template>
