<script setup lang="ts">
import AppDataTable from '@/components/shared/AppDataTable.vue'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import DatePickerField from '@/components/forms/DatePickerField.vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import {
  statusColor,
  type CatalogOption,
  type DevTrackOptions,
  type EnhancementRow,
  type TeamMember,
} from '@/lib/devtrack'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const message = ref('')
const enhancement = ref<EnhancementRow | null>(null)
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
  acceptance_criteria: '',
})

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [enhRes, optRes, teamRes] = await Promise.all([
      api.get(`/admin/enhancements/${route.params.id}`),
      api.get('/admin/devtrack/options'),
      api.get('/admin/devtrack/team'),
    ])
    enhancement.value = enhRes.data.data
    options.value = optRes.data.data
    team.value = teamRes.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the enhancement.')
  } finally {
    loading.value = false
  }
}

async function addTask() {
  if (!enhancement.value || !taskForm.value.title.trim()) {
    error.value = 'Task title is required.'
    return
  }
  savingTask.value = true
  error.value = ''
  try {
    await api.post('/admin/work-items', {
      ...taskForm.value,
      project_id: enhancement.value.project_id,
      enhancement_id: enhancement.value.id,
      project_module_id: enhancement.value.project_module_id,
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
      acceptance_criteria: '',
    }
    message.value = 'Task added to enhancement.'
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

function commitDueDate(item: { id: number; due_date: string | null }, value: string | null) {
  const next = value || null
  const current = item.due_date || null
  if (next === current) return
  void patchTask(item, 'due_date', next)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="enhancement ? `${enhancement.reference} · ${enhancement.name}` : 'Enhancement'"
      :subtitle="enhancement?.project ? `${enhancement.project.code} · ${enhancement.project.name}` : 'Enhancement detail'"
    >
      <template #actions>
        <v-btn
          v-if="enhancement"
          variant="tonal"
          :to="{ name: 'project-detail', params: { id: String(enhancement.project_id) } }"
        >
          Back to project
        </v-btn>
        <v-btn
          v-if="enhancement"
          variant="tonal"
          :to="{ name: 'enhancement-edit', params: { id: String(enhancement.id) } }"
        >
          Edit
        </v-btn>
        <v-btn color="primary" @click="taskDialog = true">Add Task</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else-if="enhancement">
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip size="small" class="mt-1" :color="statusColor(enhancement.status)" variant="flat">
                {{ enhancement.status_label }}
              </v-chip>
              <div class="text-caption text-medium-emphasis mt-3">Work type</div>
              <div>{{ enhancement.work_type_label }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Priority</div>
              <div>{{ enhancement.priority_label }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Timeline</div>
              <div>{{ formatDate(enhancement.planned_start) }} – {{ formatDate(enhancement.due_date) }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Module</div>
              <div>{{ enhancement.module ? `${enhancement.module.code} · ${enhancement.module.name}` : '—' }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Progress</div>
              <div>{{ enhancement.done_tasks }} of {{ enhancement.total_tasks }} tasks completed</div>
              <v-progress-linear :model-value="enhancement.progress_percent" color="primary" height="8" rounded class="mt-2" />
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card elevation="10" class="withbg h-100">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Business owner</div>
              <div>{{ enhancement.business_owner?.name || '—' }}</div>
              <div class="text-caption">{{ enhancement.requesting_division || '' }}</div>
              <div class="text-caption text-medium-emphasis mt-3">Responsible developer / lead</div>
              <div>{{ enhancement.responsible_developer?.name || '—' }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <ParentCard title="Description and scope" class="mb-4">
        <div class="text-body-2 mb-3">{{ enhancement.description || '—' }}</div>
        <div class="text-caption text-medium-emphasis">Scope</div>
        <div class="text-body-2 mb-3">{{ enhancement.scope || '—' }}</div>
        <div class="text-caption text-medium-emphasis">Acceptance criteria</div>
        <div class="text-body-2">{{ enhancement.acceptance_criteria || '—' }}</div>
      </ParentCard>

      <ParentCard title="Enhancement tasks">
        <AppDataTable
          :items="enhancement.work_items || []"
          :headers="[
            { title: 'Key', key: 'key' },
            { title: 'Title', key: 'title' },
            { title: 'Status', key: 'status' },
            { title: 'Priority', key: 'priority_label' },
            { title: 'Assignee', key: 'assignee' },
            { title: 'Due', key: 'due_date' },
          ]"
          density="comfortable"
        >
          <template #item.status="{ item }">
            <v-select
              :model-value="item.status"
              :items="opts(options?.work_item_statuses)"
              density="compact"
              hide-details
              style="max-width: 160px"
              @update:model-value="(v: string) => patchTask(item, 'status', v)"
            />
          </template>
          <template #item.assignee="{ item }">{{ item.assignee?.name || '—' }}</template>
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
    </template>

    <v-dialog v-model="taskDialog" max-width="640">
      <v-card>
        <v-card-title>Add enhancement task</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12"><FormField label="Title" required><v-text-field v-model="taskForm.title" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Status"><v-select v-model="taskForm.status" :items="opts(options?.work_item_statuses)" /></FormField></v-col>
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
            <v-col cols="12"><FormField label="Acceptance criteria"><v-textarea v-model="taskForm.acceptance_criteria" rows="2" /></FormField></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="taskDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingTask" @click="addTask">Add Task</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
