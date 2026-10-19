<script setup lang="ts">
import AppDataTable from '@/components/shared/AppDataTable.vue'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import DatePickerField from '@/components/forms/DatePickerField.vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import {
  ragColor,
  statusColor,
  type CatalogOption,
  type DevTrackOptions,
  type ProjectModuleRow,
  type TeamMember,
} from '@/lib/devtrack'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const message = ref('')
const moduleRow = ref<ProjectModuleRow | null>(null)
const options = ref<DevTrackOptions | null>(null)
const team = ref<TeamMember[]>([])
const taskDialog = ref(false)
const savingTask = ref(false)
const taskForm = ref({
  title: '',
  type: 'task',
  status: 'not_started',
  priority: 'medium',
  assignee_id: null as number | null,
  due_date: null as string | null,
  description: '',
})

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [modRes, optRes, teamRes] = await Promise.all([
      api.get(`/admin/project-modules/${route.params.id}`),
      api.get('/admin/devtrack/options'),
      api.get('/admin/devtrack/team'),
    ])
    moduleRow.value = modRes.data.data
    options.value = optRes.data.data
    team.value = teamRes.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the module.')
  } finally {
    loading.value = false
  }
}

async function addTask() {
  if (!moduleRow.value || !taskForm.value.title.trim()) {
    error.value = 'Task title is required.'
    return
  }
  savingTask.value = true
  error.value = ''
  try {
    await api.post('/admin/work-items', {
      ...taskForm.value,
      project_id: moduleRow.value.project_id,
      project_module_id: moduleRow.value.id,
      assignee_id: taskForm.value.assignee_id || null,
      due_date: taskForm.value.due_date || null,
    })
    taskDialog.value = false
    taskForm.value = {
      title: '',
      type: 'task',
      status: 'not_started',
      priority: 'medium',
      assignee_id: null,
      due_date: null,
      description: '',
    }
    message.value = 'Task added to module.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not add the task.')
  } finally {
    savingTask.value = false
  }
}

async function patchTask(item: { id: number }, field: string, value: unknown) {
  try {
    await api.put(`/admin/work-items/${item.id}`, { [field]: value })
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update the task.')
  }
}

function commitTitle(item: { id: number; title: string }, event: Event) {
  const value = (event.target as HTMLInputElement).value.trim()
  if (!value || value === item.title) return
  void patchTask(item, 'title', value)
}

function commitDueDate(item: { id: number; due_date: string | null }, value: string | null) {
  const next = value || null
  const current = item.due_date || null
  if (next === current) return
  void patchTask(item, 'due_date', next)
}

async function advance(stage: string) {
  if (!moduleRow.value || stage === moduleRow.value.stage) return
  try {
    await api.put(`/admin/project-modules/${moduleRow.value.id}`, { stage })
    message.value = 'Stage updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not advance stage.')
  }
}

async function setStatus(status: string) {
  if (!moduleRow.value || status === moduleRow.value.status) return
  try {
    await api.put(`/admin/project-modules/${moduleRow.value.id}`, { status })
    message.value = 'Status updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update status.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="moduleRow ? `${moduleRow.code} · ${moduleRow.name}` : 'Module'"
      :subtitle="moduleRow?.project ? `${moduleRow.project.code} · ${moduleRow.project.name}` : 'Project module'"
    >
      <template #actions>
        <v-btn
          v-if="moduleRow"
          color="primary"
          variant="tonal"
          :to="{ name: 'project-detail', params: { id: String(moduleRow.project_id) } }"
        >
          Back to project
        </v-btn>
        <v-btn
          v-if="moduleRow"
          variant="tonal"
          :to="{ name: 'module-edit', params: { id: String(moduleRow.id) } }"
        >
          Edit
        </v-btn>
        <v-btn color="primary" @click="taskDialog = true">Assign work</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else-if="moduleRow">
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip size="small" class="mt-1" :color="statusColor(moduleRow.status)" variant="flat">
                {{ moduleRow.status_label }}
              </v-chip>
              <div class="text-caption text-medium-emphasis mt-3">RAG</div>
              <RagChip :status="moduleRow.rag_status" :label="moduleRow.rag_status" />
              <p class="text-body-2 mt-2 mb-0">{{ moduleRow.rag_reason || 'No RAG note recorded.' }}</p>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Current stage</div>
              <div class="text-h6">{{ moduleRow.stage_full_label || moduleRow.stage_label }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Window</div>
              <div>{{ formatDate(moduleRow.start_date) }} – {{ formatDate(moduleRow.target_end_date) }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Delivery</div>
              <v-progress-linear
                :model-value="moduleRow.progress_percent"
                :color="ragColor(moduleRow.rag_status)"
                height="10"
                rounded
                class="mt-2"
              />
              <div class="text-caption mt-2">
                {{ moduleRow.done_tasks }} of {{ moduleRow.total_tasks }} tasks completed
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="Ownership" class="mb-4">
        <v-row>
          <v-col cols="12" md="6">
            <div class="text-caption text-medium-emphasis">Requesting division / business owner unit</div>
            <div class="text-body-2">{{ moduleRow.requesting_division || '—' }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Business owner</div>
            <div class="text-body-2">{{ moduleRow.business_owner?.name || '—' }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Responsible developer / lead</div>
            <div class="text-body-2">{{ moduleRow.responsible_developer?.name || '—' }}</div>
          </v-col>
        </v-row>
      </ParentCard>

      <ParentCard title="Plan" class="mb-4">
        <v-row>
          <v-col cols="12" md="6">
            <div class="text-caption text-medium-emphasis">Scope</div>
            <div class="text-body-2">{{ moduleRow.scope || '—' }}</div>
          </v-col>
          <v-col cols="12" md="6">
            <div class="text-caption text-medium-emphasis">Success measures</div>
            <div class="text-body-2">{{ moduleRow.success_measures || '—' }}</div>
          </v-col>
          <v-col cols="12">
            <div class="text-caption text-medium-emphasis">Description</div>
            <div class="text-body-2">{{ moduleRow.description || '—' }}</div>
          </v-col>
        </v-row>
      </ParentCard>

      <ParentCard title="Module work items" class="mb-4">
        <AppDataTable
          :items="moduleRow.work_items || []"
          :headers="[
            { title: 'Key', key: 'key', width: 130 },
            { title: 'Title', key: 'title' },
            { title: 'Type', key: 'type', width: 140 },
            { title: 'Status', key: 'status', width: 160 },
            { title: 'Priority', key: 'priority', width: 140 },
            { title: 'Assignee', key: 'assignee_id', width: 180 },
            { title: 'Due', key: 'due_date', width: 150 },
          ]"
          item-value="id"
          density="comfortable"
        >
          <template #item.title="{ item }">
            <v-text-field
              :model-value="item.title"
              density="compact"
              hide-details
              variant="outlined"
              @keydown.enter="(e: KeyboardEvent) => (e.target as HTMLInputElement).blur()"
              @blur="(e: FocusEvent) => commitTitle(item, e)"
            />
          </template>
          <template #item.type="{ item }">
            <v-select
              :model-value="item.type"
              :items="opts(options?.work_item_types)"
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="(v: string) => patchTask(item, 'type', v)"
            />
          </template>
          <template #item.status="{ item }">
            <v-select
              :model-value="item.status"
              :items="opts(options?.work_item_statuses)"
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="(v: string) => patchTask(item, 'status', v)"
            />
          </template>
          <template #item.priority="{ item }">
            <v-select
              :model-value="item.priority"
              :items="opts(options?.work_item_priorities)"
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="(v: string) => patchTask(item, 'priority', v)"
            />
          </template>
          <template #item.assignee_id="{ item }">
            <v-select
              :model-value="item.assignee_id"
              :items="team.map((m) => ({ title: m.name, value: m.id }))"
              density="compact"
              hide-details
              variant="outlined"
              clearable
              @update:model-value="(v: number | null) => patchTask(item, 'assignee_id', v)"
            />
          </template>
          <template #item.due_date="{ item }">
            <DatePickerField
              :model-value="item.due_date"
              density="compact"
              hide-details
              @update:model-value="(v) => commitDueDate(item, v)"
            />
          </template>
        </AppDataTable>
      </ParentCard>

      <ParentCard title="Linked enhancements" class="mb-4">
        <AppDataTable
          :items="moduleRow.enhancements || []"
          :headers="[
            { title: 'Reference', key: 'reference' },
            { title: 'Name', key: 'name' },
            { title: 'Status', key: 'status_label' },
            { title: 'Progress', key: 'progress' },
          ]"
          density="comfortable"
          @click:row="(_e: unknown, row: { item: { id: number } }) => router.push({ name: 'enhancement-detail', params: { id: String(row.item.id) } })"
        >
          <template #item.progress="{ item }">
            {{ item.done_tasks }} of {{ item.total_tasks }} tasks
          </template>
        </AppDataTable>
      </ParentCard>

      <ParentCard title="Advance stage">
        <div class="d-flex flex-wrap ga-2">
          <v-btn
            v-for="stage in options?.project_stages"
            :key="stage.value"
            size="small"
            :variant="stage.value === moduleRow.stage ? 'flat' : 'tonal'"
            :color="stage.value === moduleRow.stage ? 'primary' : undefined"
            @click="advance(stage.value)"
          >
            {{ stage.short_label || stage.label }}
          </v-btn>
        </div>
      </ParentCard>

      <ParentCard title="Status">
        <div class="d-flex flex-wrap ga-2">
          <v-btn
            v-for="status in options?.module_statuses"
            :key="status.value"
            size="small"
            :variant="status.value === moduleRow.status ? 'flat' : 'tonal'"
            :color="status.value === moduleRow.status ? 'primary' : undefined"
            @click="setStatus(status.value)"
          >
            {{ status.short_label || status.label }}
          </v-btn>
        </div>
      </ParentCard>
    </template>

    <v-dialog v-model="taskDialog" max-width="640">
      <v-card>
        <v-card-title>Assign module work</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12"><FormField label="Title" required><v-text-field v-model="taskForm.title" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Type"><v-select v-model="taskForm.type" :items="opts(options?.work_item_types)" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Status"><v-select v-model="taskForm.status" :items="opts(options?.work_item_statuses)" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Priority"><v-select v-model="taskForm.priority" :items="opts(options?.work_item_priorities)" /></FormField></v-col>
            <v-col cols="12" md="6">
              <FormField label="Assignee">
                <v-select
                  v-model="taskForm.assignee_id"
                  :items="team.map((m) => ({ title: m.name, value: m.id }))"
                  clearable
                />
              </FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Due date">
                <DatePickerField v-model="taskForm.due_date" />
              </FormField>
            </v-col>
            <v-col cols="12"><FormField label="Description"><v-textarea v-model="taskForm.description" rows="2" /></FormField></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="taskDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingTask" @click="addTask">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
