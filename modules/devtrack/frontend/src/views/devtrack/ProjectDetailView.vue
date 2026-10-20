<script setup lang="ts">
import AppDataTable from '@/components/shared/AppDataTable.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import DatePickerField from '@/components/forms/DatePickerField.vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { useAuthStore } from '@/stores/auth'
import {
  ragColor,
  statusColor,
  type CatalogOption,
  type DevTrackOptions,
  type EnhancementRow,
  type ProjectModuleRow,
  type RaidRow,
  type ReleaseRow,
  type SprintRow,
  type StatusReportRow,
  type TeamMember,
} from '@/lib/devtrack'

type RoleSlot = {
  role: string
  role_label: string
  staff_id?: number | null
  staff?: { id: number; name: string; email: string | null; work_email?: string | null } | null
  user: { id: number; name: string; email: string | null } | null
}

type ProjectDetail = {
  id: number
  code: string
  name: string
  description: string | null
  work_class_label: string
  work_class_code: string
  stage: string
  stage_label: string
  stage_full_label: string
  next_gate: string | null
  next_gate_approver: string | null
  rag_status: string
  rag_reason: string | null
  priority_label: string
  status: string
  status_label: string
  operational_status: string
  operational_status_label: string
  start_date: string | null
  target_end_date: string | null
  actual_end_date: string | null
  original_completion_date: string | null
  sprint_goal: string | null
  budget_status: string | null
  resource_status: string | null
  uat_progress_percent: number
  deployment_readiness_label: string
  data_classification: string
  scope: string | null
  success_measures: string | null
  modules: string[]
  project_modules: ProjectModuleRow[]
  enhancements: EnhancementRow[]
  releases: ReleaseRow[]
  active_enhancements: number
  next_planned_release: ReleaseRow | null
  indicator: { id: number; code: string; title: string; programme: string | null } | null
  work_request: { id: number; reference: string; title: string } | null
  open_tasks: number
  done_tasks: number
  progress_percent: number
  open_raid: number
  roles: Record<string, RoleSlot | null>
  stage_history: Array<{ id: number; to_stage_label: string; notes: string | null; created_at: string }>
  sprints: SprintRow[]
  raid_items: RaidRow[]
  reports: StatusReportRow[]
  is_archived?: boolean
  archived_at?: string | null
  archive_reason?: string | null
  archived_by_user?: { id: number; name: string; email: string | null } | null
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const tab = ref('overview')
const loading = ref(true)
const error = ref('')
const message = ref('')
const project = ref<ProjectDetail | null>(null)
const archiveDialog = ref(false)
const archiveReason = ref('')
const archiving = ref(false)
const unarchiving = ref(false)
const options = ref<DevTrackOptions | null>(null)
const team = ref<TeamMember[]>([])
const raidDialog = ref(false)
const savingRaid = ref(false)
const releaseDialog = ref(false)
const savingRelease = ref(false)
const releaseForm = ref({
  version: '',
  name: '',
  description: '',
  status: 'planned',
  target_date: '',
})

const raidForm = ref({
  type: 'risk',
  title: '',
  severity: 'medium',
  status: 'open',
  owner_id: null as number | null,
  due_date: '',
  mitigation: '',
  description: '',
})

const id = computed(() => route.params.id as string)

function formatRoleLabel(role: string): string {
  return role
    .split('_')
    .filter(Boolean)
    .map((part, index) => (index === 0 ? part.charAt(0).toUpperCase() + part.slice(1) : part))
    .join(' ')
}

function plainText(value: string | null | undefined): string {
  if (!value) return ''
  return value
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/\s+/g, ' ')
    .trim()
}

const projectSubtitle = computed(() => {
  const text = plainText(project.value?.description)
  return text || 'Project workspace'
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [p, opt, t] = await Promise.all([
      api.get(`/admin/projects/${id.value}`),
      api.get('/admin/devtrack/options'),
      api.get('/admin/devtrack/team'),
    ])
    project.value = p.data.data
    options.value = opt.data.data
    team.value = t.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load project.')
  } finally {
    loading.value = false
  }
}

async function advance(stage: string) {
  if (!project.value) return
  try {
    await api.post(`/admin/projects/${project.value.id}/advance-stage`, { stage, notes: 'Stage advanced from project workspace' })
    message.value = 'Stage updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not advance stage.')
  }
}

async function setStatus(status: string) {
  if (!project.value || status === project.value.status) return
  try {
    await api.put(`/admin/projects/${project.value.id}`, { status })
    message.value = 'Status updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update status.')
  }
}

async function setOperationalStatus(status: string) {
  if (!project.value || status === project.value.operational_status) return
  try {
    await api.put(`/admin/projects/${project.value.id}`, { operational_status: status })
    message.value = 'Operational status updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update operational status.')
  }
}

async function saveRelease() {
  if (!project.value || !releaseForm.value.version.trim()) {
    error.value = 'Release version is required.'
    return
  }
  savingRelease.value = true
  error.value = ''
  try {
    await api.post('/admin/releases', {
      ...releaseForm.value,
      project_id: project.value.id,
      target_date: releaseForm.value.target_date || null,
    })
    releaseDialog.value = false
    releaseForm.value = { version: '', name: '', description: '', status: 'planned', target_date: '' }
    message.value = 'Release recorded.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save release.')
  } finally {
    savingRelease.value = false
  }
}

async function saveRaid() {
  if (!project.value) return
  savingRaid.value = true
  error.value = ''
  try {
    await api.post('/admin/raid-items', {
      ...raidForm.value,
      project_id: project.value.id,
      owner_id: raidForm.value.owner_id || null,
      due_date: raidForm.value.due_date || null,
    })
    raidDialog.value = false
    raidForm.value.title = ''
    message.value = 'RAID item logged.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save RAID item.')
  } finally {
    savingRaid.value = false
  }
}

async function archiveProject() {
  if (!project.value || archiveReason.value.trim().length < 3) {
    error.value = 'Provide a reason of at least 3 characters.'
    return
  }
  archiving.value = true
  error.value = ''
  try {
    await api.post(`/admin/projects/${project.value.id}/archive`, {
      archive_reason: archiveReason.value.trim(),
    })
    archiveDialog.value = false
    archiveReason.value = ''
    message.value = 'Project archived.'
    await router.push({ name: 'archived-projects' })
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not archive the project.')
  } finally {
    archiving.value = false
  }
}

async function unarchiveProject() {
  if (!project.value) return
  unarchiving.value = true
  error.value = ''
  try {
    await api.post(`/admin/projects/${project.value.id}/unarchive`)
    message.value = 'Project restored to the register.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not restore the project.')
  } finally {
    unarchiving.value = false
  }
}

function goToReport(item: StatusReportRow) {
  router.push({ name: 'report-detail', params: { id: item.id } })
}

function onReportRow(_event: unknown, row: { item: StatusReportRow }) {
  goToReport(row.item)
}

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="project ? `${project.code} · ${project.name}` : 'Project'"
      :subtitle="projectSubtitle"
    >
      <template #actions>
        <template v-if="project && !project.is_archived">
          <v-btn
            variant="tonal"
            :to="{ name: 'project-edit', params: { id: String(route.params.id) } }"
          >
            Edit
          </v-btn>
          <v-btn
            variant="tonal"
            color="primary"
            :to="{ name: 'enhancement-new', params: { id: String(route.params.id) } }"
          >
            Add Enhancement
          </v-btn>
          <v-btn
            variant="tonal"
            color="primary"
            :to="{ name: 'module-new', params: { id: String(route.params.id) } }"
          >
            Add Module
          </v-btn>
          <v-btn
            v-if="auth.isAdmin"
            color="error"
            variant="tonal"
            @click="archiveDialog = true"
          >
            Archive
          </v-btn>
        </template>
        <template v-else-if="project?.is_archived">
          <v-btn variant="tonal" :to="{ name: 'archived-projects' }">Archived list</v-btn>
          <v-btn
            v-if="auth.isAdmin"
            color="primary"
            :loading="unarchiving"
            @click="unarchiveProject"
          >
            Restore
          </v-btn>
        </template>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else-if="project">
      <v-alert
        v-if="project.is_archived"
        type="warning"
        variant="tonal"
        class="mb-4"
        density="comfortable"
      >
        <div class="font-weight-medium">This project is archived</div>
        <div class="text-body-2 mt-1">
          {{ project.archive_reason || 'No reason recorded.' }}
        </div>
        <div class="text-caption mt-1">
          Archived {{ formatDate(project.archived_at) }}
          <span v-if="project.archived_by_user"> by {{ project.archived_by_user.name }}</span>
        </div>
      </v-alert>
      <v-row class="mb-4">
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip
                size="small"
                class="mt-1 text-uppercase"
                :color="statusColor(project.status)"
                variant="flat"
              >
                {{ project.status_label || '—' }}
              </v-chip>
              <div class="text-caption text-medium-emphasis mt-3">RAG</div>
              <RagChip :status="project.rag_status" :label="project.rag_status" />
              <p class="text-body-2 mt-2 mb-0">{{ project.rag_reason || 'No RAG note recorded.' }}</p>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Current stage</div>
              <div class="text-h6">{{ project.stage_full_label }}</div>
              <div class="text-caption mt-2">Next gate: {{ project.next_gate || 'Last stage' }}</div>
              <div class="text-caption">Approver: {{ project.next_gate_approver }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Req ref</div>
              <router-link
                v-if="project.work_request"
                class="text-decoration-none"
                :to="{ name: 'request-detail', params: { id: project.work_request.id } }"
              >
                <div class="font-weight-medium">{{ project.work_request.reference }}</div>
                <div class="text-body-2">{{ project.work_request.title }}</div>
              </router-link>
              <div v-else class="text-body-2">No request linked</div>
              <div class="text-caption text-medium-emphasis mt-3">Workplan indicator</div>
              <div class="font-weight-medium">{{ project.indicator?.code }}</div>
              <div class="text-body-2">{{ project.indicator?.title }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Delivery</div>
              <v-progress-linear :model-value="project.progress_percent" :color="ragColor(project.rag_status)" height="10" rounded class="mt-2" />
              <div class="text-caption mt-2">{{ project.done_tasks }} done · {{ project.open_tasks }} open · {{ project.open_raid }} RAID open</div>
              <div class="text-caption mt-2">Modules: {{ project.project_modules?.length ?? project.modules?.length ?? 0 }}</div>
              <div class="text-caption mt-1 text-medium-emphasis">From tasks on modules</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="Lifecycle indicators" class="mb-4">
        <v-row>
          <v-col cols="12" sm="6" md="4" lg="2">
            <div class="text-caption text-medium-emphasis">Original project status</div>
            <div class="font-weight-medium">{{ project.status_label }}</div>
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <div class="text-caption text-medium-emphasis">Original completion date</div>
            <div class="font-weight-medium">{{ formatDate(project.original_completion_date || project.actual_end_date) }}</div>
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <div class="text-caption text-medium-emphasis">System operational status</div>
            <v-select
              :model-value="project.operational_status"
              :items="opts(options?.operational_statuses)"
              density="compact"
              hide-details
              class="mt-1"
              @update:model-value="setOperationalStatus"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <div class="text-caption text-medium-emphasis">Active enhancements</div>
            <div class="font-weight-medium">{{ project.active_enhancements ?? 0 }}</div>
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <div class="text-caption text-medium-emphasis">Next planned release</div>
            <div class="font-weight-medium">
              {{ project.next_planned_release ? `Version ${project.next_planned_release.version}` : '—' }}
            </div>
          </v-col>
          <v-col cols="12" lg="12" class="mt-2">
            <div class="text-caption text-medium-emphasis mb-2">Module progress</div>
            <div v-if="!project.project_modules?.length" class="text-body-2">No modules yet.</div>
            <div v-else class="d-flex flex-column ga-2">
              <div
                v-for="mod in project.project_modules"
                :key="mod.id"
                class="d-flex flex-wrap align-center justify-space-between ga-2"
              >
                <router-link
                  class="text-decoration-none font-weight-medium"
                  :to="{ name: 'module-detail', params: { id: String(mod.id) } }"
                >
                  {{ mod.name }}
                  <v-chip v-if="mod.is_primary" size="x-small" color="primary" class="ms-2">Primary</v-chip>
                </router-link>
                <div class="text-body-2">{{ mod.done_tasks }} of {{ mod.total_tasks }} tasks completed</div>
                <v-progress-linear
                  :model-value="mod.progress_percent"
                  color="primary"
                  height="8"
                  rounded
                  style="min-width: 120px; max-width: 240px"
                />
              </div>
            </div>
          </v-col>
        </v-row>
      </ParentCard>

      <v-tabs v-model="tab" color="primary" class="mb-4">
        <v-tab value="overview">Overview</v-tab>
        <v-tab value="enhancements">Enhancements</v-tab>
        <v-tab value="modules">Modules</v-tab>
        <v-tab value="releases">Releases</v-tab>
        <v-tab value="raid">RAID</v-tab>
        <v-tab value="reports">Reports</v-tab>
      </v-tabs>

      <div v-if="tab === 'overview'">
        <ParentCard title="Named roles">
          <v-row>
            <v-col v-for="(slot, role) in project.roles" :key="role" cols="12" md="4">
              <div class="text-caption text-medium-emphasis">{{ slot?.role_label || formatRoleLabel(String(role)) }}</div>
              <div class="font-weight-medium">{{ slot?.staff?.name || slot?.user?.name || 'Unassigned' }}</div>
              <div class="text-caption">{{ slot?.staff?.email || slot?.staff?.work_email || slot?.user?.email }}</div>
            </v-col>
          </v-row>
        </ParentCard>
        <ParentCard title="Plan">
          <v-row>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Class</div>
              <div>{{ project.work_class_code }} · {{ project.work_class_label }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Req ref</div>
              <div>
                <router-link
                  v-if="project.work_request"
                  class="text-decoration-none"
                  :to="{ name: 'request-detail', params: { id: project.work_request.id } }"
                >{{ project.work_request.reference }}</router-link>
                <span v-else>—</span>
              </div>
              <div class="text-caption text-medium-emphasis mt-3">Window</div>
              <div>{{ formatDate(project.start_date) }} – {{ formatDate(project.target_end_date) }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Sprint goal</div>
              <div>{{ project.sprint_goal || '—' }}</div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="text-caption text-medium-emphasis">Scope</div>
              <div class="text-body-2">{{ project.scope || '—' }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Success measures</div>
              <div class="text-body-2">{{ project.success_measures || '—' }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Modules</div>
              <div v-if="project.project_modules?.length" class="d-flex flex-wrap ga-2 mt-1">
                <v-chip
                  v-for="mod in project.project_modules"
                  :key="mod.id"
                  size="small"
                  variant="tonal"
                  color="primary"
                  :to="{ name: 'module-detail', params: { id: String(mod.id) } }"
                >
                  {{ mod.name }}<span v-if="mod.is_primary"> · Primary</span>
                </v-chip>
              </div>
              <div v-else class="text-body-2">—</div>
            </v-col>
          </v-row>
        </ParentCard>
        <ParentCard title="Advance stage">
          <div class="d-flex flex-wrap ga-2">
            <v-btn
              v-for="stage in options?.project_stages"
              :key="stage.value"
              size="small"
              :variant="stage.value === project.stage ? 'flat' : 'tonal'"
              :color="stage.value === project.stage ? 'primary' : undefined"
              @click="advance(stage.value)"
            >
              {{ stage.short_label || stage.label }}
            </v-btn>
          </div>
        </ParentCard>
        <ParentCard title="Status">
          <div class="d-flex flex-wrap ga-2">
            <v-btn
              v-for="status in options?.project_statuses"
              :key="status.value"
              size="small"
              :variant="status.value === project.status ? 'flat' : 'tonal'"
              :color="status.value === project.status ? 'primary' : undefined"
              @click="setStatus(status.value)"
            >
              {{ status.short_label || status.label }}
            </v-btn>
          </div>
        </ParentCard>
      </div>

      <div v-if="tab === 'enhancements'">
        <ParentCard title="Enhancements">
          <template #action>
            <v-btn
              color="primary"
              variant="tonal"
              :to="{ name: 'enhancement-new', params: { id: String(project.id) } }"
            >
              Add Enhancement
            </v-btn>
          </template>
          <AppDataTable
            :items="project.enhancements || []"
            :headers="[
              { title: 'ID', key: 'reference', width: 140 },
              { title: 'Name', key: 'name' },
              { title: 'Work type', key: 'work_type_label' },
              { title: 'Status', key: 'status_label' },
              { title: 'Progress', key: 'progress' },
              { title: 'Due', key: 'due_date' },
            ]"
            hover
            @click:row="(_e: unknown, row: { item: EnhancementRow }) => router.push({ name: 'enhancement-detail', params: { id: String(row.item.id) } })"
          >
            <template #item.progress="{ item }">
              {{ item.done_tasks }} of {{ item.total_tasks }} tasks
            </template>
            <template #item.due_date="{ item }">{{ formatDate(item.due_date) }}</template>
          </AppDataTable>
        </ParentCard>
      </div>

      <div v-else-if="tab === 'modules'">
        <ParentCard title="Modules">
          <template #action>
            <v-btn
              color="primary"
              variant="tonal"
              :to="{ name: 'module-new', params: { id: String(project.id) } }"
            >
              Add Module
            </v-btn>
          </template>
          <AppDataTable
            :items="project.project_modules || []"
            :headers="[
              { title: 'Code', key: 'code', width: 140 },
              { title: 'Name', key: 'name' },
              { title: 'Primary', key: 'is_primary', width: 110 },
              { title: 'Status', key: 'status_label' },
              { title: 'Stage', key: 'stage_label' },
              { title: 'Progress', key: 'progress' },
            ]"
            hover
            @click:row="(_e: unknown, row: { item: ProjectModuleRow }) => router.push({ name: 'module-detail', params: { id: String(row.item.id) } })"
          >
            <template #item.is_primary="{ item }">
              <v-chip v-if="item.is_primary" size="x-small" color="primary">Primary</v-chip>
            </template>
            <template #item.progress="{ item }">
              {{ item.done_tasks }} of {{ item.total_tasks }} tasks completed
            </template>
          </AppDataTable>
        </ParentCard>
      </div>

      <div v-else-if="tab === 'releases'">
        <ParentCard title="Releases">
          <template #action>
            <v-btn color="primary" variant="tonal" @click="releaseDialog = true">Add release</v-btn>
          </template>
          <AppDataTable
            :items="project.releases || []"
            :headers="[
              { title: 'Version', key: 'version' },
              { title: 'Name', key: 'name' },
              { title: 'Included work', key: 'enhancements' },
              { title: 'Status', key: 'status_label' },
              { title: 'Target', key: 'target_date' },
            ]"
          >
            <template #item.enhancements="{ item }">
              {{
                item.enhancements?.length
                  ? item.enhancements.map((e) => e.name).join(', ')
                  : (item.description || '—')
              }}
            </template>
            <template #item.target_date="{ item }">{{ formatDate(item.target_date) }}</template>
          </AppDataTable>
        </ParentCard>
      </div>

      <div v-else-if="tab === 'raid'">
        <ParentCard title="Risks, assumptions, issues, dependencies">
          <template #action>
            <v-btn color="primary" variant="tonal" @click="raidDialog = true">Log RAID item</v-btn>
          </template>
          <AppDataTable
            :items="project.raid_items"
            :headers="[
              { title: 'Type', key: 'type_label' },
              { title: 'Title', key: 'title' },
              { title: 'Severity', key: 'severity_label' },
              { title: 'Status', key: 'status_label' },
              { title: 'Owner', key: 'owner' },
            ]"
          >
            <template #item.owner="{ item }">{{ item.owner?.name || '—' }}</template>
          </AppDataTable>
        </ParentCard>
      </div>

      <div v-else-if="tab === 'reports'">
        <ParentCard title="Status reports">
          <template #action>
            <v-btn color="primary" variant="tonal" :to="{ name: 'report-new', query: { project_id: project.id } }">New report</v-btn>
          </template>
          <AppDataTable
            :items="project.reports"
            :headers="[
              { title: 'Title', key: 'title' },
              { title: 'Cadence', key: 'period_label' },
              { title: 'RAG', key: 'rag_status' },
              { title: 'Period', key: 'period_end' },
            ]"
            hover
            @click:row="onReportRow"
          >
            <template #item.rag_status="{ item }">
              <RagChip :status="item.rag_status" :label="item.rag_status" small />
            </template>
            <template #item.period_end="{ item }">{{ formatDate(item.period_start) }} – {{ formatDate(item.period_end) }}</template>
          </AppDataTable>
        </ParentCard>
      </div>
    </template>

    <v-dialog v-model="raidDialog" max-width="640">
      <v-card>
        <v-card-title>Log a RAID item</v-card-title>
        <v-card-text>
          <FormField label="Title" required>
            <v-text-field v-model="raidForm.title" />
          </FormField>
          <v-row>
            <v-col cols="12" md="4">
              <FormField label="Type"><v-select v-model="raidForm.type" :items="opts(options?.raid_types)" /></FormField>
            </v-col>
            <v-col cols="12" md="4">
              <FormField label="Severity"><v-select v-model="raidForm.severity" :items="opts(options?.urgency_levels)" /></FormField>
            </v-col>
            <v-col cols="12" md="4">
              <FormField label="Status"><v-select v-model="raidForm.status" :items="opts(options?.raid_statuses)" /></FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Owner">
                <v-select v-model="raidForm.owner_id" :items="team.map((m) => ({ title: m.name, value: m.id }))" clearable />
              </FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Due"><v-text-field v-model="raidForm.due_date" type="date" /></FormField>
            </v-col>
            <v-col cols="12">
              <FormField label="Mitigation / notes"><v-textarea v-model="raidForm.mitigation" rows="3" /></FormField>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="raidDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingRaid" @click="saveRaid">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="releaseDialog" max-width="560">
      <v-card>
        <v-card-title>Add release</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <FormField label="Version" required>
                <v-text-field v-model="releaseForm.version" placeholder="e.g. 1.1" />
              </FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Status">
                <v-select v-model="releaseForm.status" :items="opts(options?.release_statuses)" />
              </FormField>
            </v-col>
            <v-col cols="12">
              <FormField label="Name">
                <v-text-field v-model="releaseForm.name" />
              </FormField>
            </v-col>
            <v-col cols="12">
              <FormField label="Included work / description">
                <v-textarea v-model="releaseForm.description" rows="2" />
              </FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Target date">
                <v-text-field v-model="releaseForm.target_date" type="date" />
              </FormField>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="releaseDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingRelease" @click="saveRelease">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="archiveDialog" max-width="560">
      <v-card>
        <v-card-title>Archive project</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Archived projects leave the main register and appear under Archived Projects.
            Provide a clear reason for the archive.
          </p>
          <FormField label="Reason for archiving" required>
            <v-textarea
              v-model="archiveReason"
              rows="3"
              placeholder="Why is this project being archived?"
              :rules="[(v: string) => (!!v && v.trim().length >= 3) || 'Enter at least 3 characters']"
            />
          </FormField>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="archiveDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="archiving" @click="archiveProject">Archive</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
