<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
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
  type ProjectSummary,
  type TeamMember,
  type WorkItemRow,
} from '@/lib/devtrack'
import { useAuthStore } from '@/stores/auth'

type EditDraft = {
  title: string
  type: string
  priority: string
  status: string
  assignee_id: number | null
  due_date: string | null
  estimate_hours: number | null
  description: string
  acceptance_criteria: string
}

const props = defineProps<{ mine?: boolean }>()
const route = useRoute()
const auth = useAuthStore()

const items = ref<WorkItemRow[]>([])
const projects = ref<ProjectSummary[]>([])
const team = ref<TeamMember[]>([])
const options = ref<DevTrackOptions | null>(null)
const loading = ref(true)
const movingId = ref<number | null>(null)
const savingId = ref<number | null>(null)
const error = ref('')
const message = ref('')
const dialog = ref(false)
const saving = ref(false)
const dragItemId = ref<number | null>(null)
const dropTarget = ref<string | null>(null)
const expanded = reactive<Record<number, boolean>>({})
const drafts = reactive<Record<number, EditDraft>>({})

const filters = ref({
  q: typeof route.query.q === 'string' ? route.query.q : '',
  project_id: null as number | null,
  assignee_id: null as number | null,
  priority: '',
  type: '',
})

const form = ref({
  project_id: null as number | null,
  title: '',
  type: 'task',
  status: 'not_started',
  priority: 'medium',
  assignee_id: null as number | null,
  due_date: null as string | null,
  estimate_hours: null as number | null,
  acceptance_criteria: '',
  description: '',
})

const columns = [
  { status: 'not_started', label: 'Not yet Started', color: 'secondary' },
  { status: 'in_progress', label: 'In Progress', color: 'info' },
  { status: 'completed', label: 'Completed', color: 'success' },
] as const

const columnCounts = computed(() =>
  Object.fromEntries(columns.map((col) => [col.status, inColumn(col.status).length])),
)

const anyExpanded = computed(() => items.value.some((item) => expanded[item.id]))

function draftFromItem(item: WorkItemRow): EditDraft {
  return {
    title: item.title,
    type: item.type,
    priority: item.priority,
    status: item.status,
    assignee_id: item.assignee_id,
    due_date: item.due_date,
    estimate_hours: item.estimate_hours,
    description: item.description ?? '',
    acceptance_criteria: item.acceptance_criteria ?? '',
  }
}

function ensureDraft(item: WorkItemRow) {
  if (!drafts[item.id]) drafts[item.id] = draftFromItem(item)
}

function isExpanded(id: number) {
  return !!expanded[id]
}

function toggleExpand(item: WorkItemRow) {
  if (expanded[item.id]) {
    expanded[item.id] = false
    return
  }
  ensureDraft(item)
  expanded[item.id] = true
}

function collapseAll() {
  for (const item of items.value) expanded[item.id] = false
}

function expandAll() {
  for (const item of items.value) {
    ensureDraft(item)
    expanded[item.id] = true
  }
}

async function loadMeta() {
  const [p, t, o] = await Promise.all([
    api.get('/admin/projects'),
    api.get('/admin/devtrack/team'),
    api.get('/admin/devtrack/options'),
  ])
  projects.value = p.data.data
  team.value = t.data.data
  options.value = o.data.data
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/work-items', {
      params: {
        q: filters.value.q.trim() || undefined,
        project_id: filters.value.project_id || undefined,
        assignee_id: props.mine ? auth.user?.id : filters.value.assignee_id || undefined,
        mine: props.mine ? 1 : undefined,
        priority: filters.value.priority || undefined,
        type: filters.value.type || undefined,
      },
    })
    items.value = res.data.data
    for (const item of items.value) {
      if (expanded[item.id]) drafts[item.id] = draftFromItem(item)
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load work items.')
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.value = {
    q: '',
    project_id: null,
    assignee_id: null,
    priority: '',
    type: '',
  }
  void load()
}

async function save() {
  if (!form.value.project_id || !form.value.title) {
    error.value = 'Project and title are required.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await api.post('/admin/work-items', {
      ...form.value,
      due_date: form.value.due_date || null,
      assignee_id: props.mine && !form.value.assignee_id ? auth.user?.id : form.value.assignee_id,
    })
    dialog.value = false
    message.value = 'Work assigned.'
    form.value = {
      ...form.value,
      title: '',
      description: '',
      acceptance_criteria: '',
      due_date: null,
      estimate_hours: null,
      status: 'not_started',
    }
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not create work item.')
  } finally {
    saving.value = false
  }
}

async function patch(item: WorkItemRow, field: string, value: unknown) {
  const previous = (item as Record<string, unknown>)[field]
  if (previous === value) return

  if (field === 'status') {
    item.status = String(value)
    const label = options.value?.work_item_statuses?.find((s) => s.value === value)?.label
    if (label) item.status_label = label
  }

  movingId.value = item.id
  try {
    await api.put(`/admin/work-items/${item.id}`, { [field]: value })
    if (field !== 'status') await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update work item.')
    await load()
  } finally {
    movingId.value = null
  }
}

async function saveEdit(item: WorkItemRow) {
  const draft = drafts[item.id]
  if (!draft?.title.trim()) {
    error.value = 'Title is required.'
    return
  }

  savingId.value = item.id
  error.value = ''
  try {
    await api.put(`/admin/work-items/${item.id}`, {
      title: draft.title.trim(),
      type: draft.type,
      priority: draft.priority,
      status: draft.status,
      assignee_id: draft.assignee_id,
      due_date: draft.due_date || null,
      estimate_hours: draft.estimate_hours,
      description: draft.description || null,
      acceptance_criteria: draft.acceptance_criteria || null,
    })
    message.value = 'Task updated.'
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not update work item.')
  } finally {
    savingId.value = null
  }
}

function cancelEdit(item: WorkItemRow) {
  drafts[item.id] = draftFromItem(item)
  expanded[item.id] = false
}

async function moveToStatus(item: WorkItemRow, status: string) {
  if (item.status === status) return
  await patch(item, 'status', status)
  if (drafts[item.id]) drafts[item.id].status = status
  message.value = `Moved to ${columns.find((c) => c.status === status)?.label ?? status}.`
}

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

function inColumn(status: string) {
  return items.value.filter((item) => item.status === status)
}

function onDragStart(event: DragEvent, item: WorkItemRow) {
  if (isExpanded(item.id)) {
    event.preventDefault()
    return
  }
  dragItemId.value = item.id
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(item.id))
  }
}

function onDragEnd() {
  dragItemId.value = null
  dropTarget.value = null
}

function onDragOver(event: DragEvent, status: string) {
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropTarget.value = status
}

function onDragLeave(status: string) {
  if (dropTarget.value === status) dropTarget.value = null
}

async function onDrop(event: DragEvent, status: string) {
  event.preventDefault()
  dropTarget.value = null
  const raw = event.dataTransfer?.getData('text/plain') || String(dragItemId.value ?? '')
  const id = Number(raw)
  dragItemId.value = null
  if (!id) return
  const item = items.value.find((row) => row.id === id)
  if (!item || item.status === status) return
  await moveToStatus(item, status)
}

watch(
  () => [filters.value.project_id, filters.value.assignee_id, filters.value.priority, filters.value.type],
  () => {
    void load()
  },
)

watch(
  () => route.query.q,
  (value) => {
    if (typeof value === 'string' && value !== filters.value.q) {
      filters.value.q = value
      void load()
    }
  },
)

onMounted(async () => {
  await loadMeta()
  if (props.mine && form.value.assignee_id == null) {
    form.value.assignee_id = auth.user?.id ?? null
  }
  await load()
})
</script>

<template>
  <div>
    <PageHeader
      :title="mine ? 'My work' : 'Assign work'"
      :subtitle="mine
        ? 'Your tasks across three status columns — expand a card to edit, drag collapsed cards to move'
        : 'Assign and track work — expand a card to edit, drag collapsed cards between columns'"
    >
      <template #actions>
        <v-btn
          v-if="items.length"
          variant="tonal"
          class="me-2"
          @click="anyExpanded ? collapseAll() : expandAll()"
        >
          {{ anyExpanded ? 'Collapse all' : 'Expand all' }}
        </v-btn>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="dialog = true">New work item</v-btn>
      </template>
    </PageHeader>

    <ParentCard title="Filters">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact" closable @click:close="message = ''">
        {{ message }}
      </v-alert>
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.q"
            label="Search key or title"
            clearable
            hide-details
            prepend-inner-icon="mdi-magnify"
            @keyup.enter="load"
            @click:clear="load"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="filters.project_id"
            :items="projects.map((p) => ({ title: `${p.code} — ${p.name}`, value: p.id }))"
            label="Project"
            clearable
            hide-details
          />
        </v-col>
        <v-col v-if="!mine" cols="12" md="2">
          <v-select
            v-model="filters.assignee_id"
            :items="team.map((m) => ({ title: m.name, value: m.id }))"
            label="Assignee"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="filters.priority"
            :items="opts(options?.work_item_priorities)"
            label="Priority"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="filters.type"
            :items="opts(options?.work_item_types)"
            label="Type"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" md="auto" class="d-flex align-center ga-2">
          <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Apply</v-btn>
          <v-btn variant="text" @click="clearFilters">Clear</v-btn>
        </v-col>
      </v-row>
    </ParentCard>

    <div v-if="loading && !items.length" class="text-center py-10">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else class="work-board" :class="{ 'work-board--loading': loading }">
      <v-card
        v-for="col in columns"
        :key="col.status"
        elevation="10"
        class="withbg work-board__col"
        :class="{ 'work-board__col--drop': dropTarget === col.status }"
        @dragover="onDragOver($event, col.status)"
        @dragleave="onDragLeave(col.status)"
        @drop="onDrop($event, col.status)"
      >
        <v-card-title class="work-board__col-title text-subtitle-1 d-flex justify-space-between align-center">
          <span class="d-flex align-center ga-2">
            <v-icon :color="col.color" size="small">mdi-circle</v-icon>
            {{ col.label }}
          </span>
          <v-chip size="x-small" :color="col.color" variant="tonal">{{ columnCounts[col.status] }}</v-chip>
        </v-card-title>
        <v-card-text class="work-board__col-body">
          <v-card
            v-for="item in inColumn(col.status)"
            :key="item.id"
            variant="outlined"
            class="mb-3 work-board__card"
            :class="{
              'work-board__card--dragging': dragItemId === item.id,
              'work-board__card--busy': movingId === item.id || savingId === item.id,
              'work-board__card--open': isExpanded(item.id),
            }"
            :draggable="!isExpanded(item.id)"
            @dragstart="onDragStart($event, item)"
            @dragend="onDragEnd"
          >
            <v-card-text class="pa-3">
              <!-- Collapsed summary -->
              <div
                class="work-board__summary"
                role="button"
                tabindex="0"
                @click="toggleExpand(item)"
                @keydown.enter.prevent="toggleExpand(item)"
                @keydown.space.prevent="toggleExpand(item)"
              >
                <div class="d-flex align-start justify-space-between ga-2">
                  <div class="work-board__summary-main min-w-0">
                    <div class="text-caption text-medium-emphasis text-truncate">
                      {{ item.key }} · {{ item.project?.code }}
                    </div>
                    <div class="font-weight-medium work-board__title">{{ item.title }}</div>
                    <div class="d-flex flex-wrap align-center ga-1 mt-1">
                      <v-chip size="x-small" :color="statusColor(item.priority)" variant="tonal">
                        {{ item.priority_label }}
                      </v-chip>
                      <v-chip v-if="item.is_overdue" size="x-small" color="error" variant="tonal">Overdue</v-chip>
                      <span class="text-caption text-medium-emphasis">
                        {{ item.assignee?.name || 'Unassigned' }}
                        <template v-if="item.due_date"> · {{ formatDate(item.due_date) }}</template>
                      </span>
                    </div>
                  </div>
                  <div class="d-flex align-center ga-1 flex-shrink-0" @click.stop>
                    <v-icon
                      v-if="!isExpanded(item.id)"
                      size="small"
                      class="work-board__grip text-medium-emphasis"
                      title="Drag to another column"
                    >
                      mdi-drag
                    </v-icon>
                    <v-btn
                      icon
                      variant="text"
                      size="x-small"
                      :aria-label="isExpanded(item.id) ? 'Collapse' : 'Expand'"
                      @click="toggleExpand(item)"
                    >
                      <v-icon size="small">
                        {{ isExpanded(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down' }}
                      </v-icon>
                    </v-btn>
                  </div>
                </div>
              </div>

              <!-- Expanded edit panel -->
              <div
                v-if="isExpanded(item.id) && drafts[item.id]"
                class="work-board__edit mt-3"
                @click.stop
                @mousedown.stop
                @dragstart.stop.prevent
              >
                <v-divider class="mb-3" />
                <FormField label="Title" required>
                  <v-text-field v-model="drafts[item.id].title" density="compact" hide-details variant="outlined" />
                </FormField>
                <v-row dense class="mt-1">
                  <v-col cols="12" sm="6">
                    <FormField label="Type">
                      <v-select
                        v-model="drafts[item.id].type"
                        :items="opts(options?.work_item_types)"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <FormField label="Priority">
                      <v-select
                        v-model="drafts[item.id].priority"
                        :items="opts(options?.work_item_priorities)"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <FormField label="Status">
                      <v-select
                        v-model="drafts[item.id].status"
                        :items="opts(options?.work_item_statuses)"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <FormField label="Assignee">
                      <v-select
                        v-model="drafts[item.id].assignee_id"
                        :items="team.map((m) => ({ title: m.name, value: m.id }))"
                        clearable
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <FormField label="Due">
                      <DatePickerField v-model="drafts[item.id].due_date" density="compact" hide-details />
                    </FormField>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <FormField label="Hours">
                      <v-text-field
                        v-model.number="drafts[item.id].estimate_hours"
                        type="number"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12">
                    <FormField label="Description">
                      <v-textarea
                        v-model="drafts[item.id].description"
                        rows="2"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12">
                    <FormField label="Acceptance criteria">
                      <v-textarea
                        v-model="drafts[item.id].acceptance_criteria"
                        rows="2"
                        density="compact"
                        hide-details
                        variant="outlined"
                      />
                    </FormField>
                  </v-col>
                </v-row>

                <div class="d-flex flex-wrap ga-1 mt-3 mb-2">
                  <v-btn
                    v-for="target in columns.filter((c) => c.status !== item.status)"
                    :key="target.status"
                    size="x-small"
                    variant="tonal"
                    :color="target.color"
                    :loading="movingId === item.id"
                    @click="moveToStatus(item, target.status)"
                  >
                    Move to {{ target.label }}
                  </v-btn>
                </div>

                <div class="d-flex justify-end ga-2 mt-2">
                  <v-btn size="small" variant="text" @click="cancelEdit(item)">Cancel</v-btn>
                  <v-btn
                    size="small"
                    color="primary"
                    :loading="savingId === item.id"
                    @click="saveEdit(item)"
                  >
                    Save
                  </v-btn>
                </div>
              </div>
            </v-card-text>
          </v-card>
          <div v-if="!inColumn(col.status).length" class="work-board__empty text-caption text-medium-emphasis">
            Drop tasks here
          </div>
        </v-card-text>
      </v-card>
    </div>

    <v-dialog v-model="dialog" max-width="720">
      <v-card>
        <v-card-title>Assign a work item</v-card-title>
        <v-card-text>
          <FormField label="Project" required>
            <v-select v-model="form.project_id" :items="projects.map((p) => ({ title: `${p.code} — ${p.name}`, value: p.id }))" />
          </FormField>
          <FormField label="Title" required>
            <v-text-field v-model="form.title" />
          </FormField>
          <v-row>
            <v-col cols="12" md="4">
              <FormField label="Type"><v-select v-model="form.type" :items="opts(options?.work_item_types)" /></FormField>
            </v-col>
            <v-col cols="12" md="4">
              <FormField label="Priority"><v-select v-model="form.priority" :items="opts(options?.work_item_priorities)" /></FormField>
            </v-col>
            <v-col cols="12" md="4">
              <FormField label="Status"><v-select v-model="form.status" :items="opts(options?.work_item_statuses)" /></FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField label="Assignee">
                <v-select v-model="form.assignee_id" :items="team.map((m) => ({ title: m.name, value: m.id }))" clearable />
              </FormField>
            </v-col>
            <v-col cols="12" md="3">
              <FormField label="Due">
                <DatePickerField v-model="form.due_date" />
              </FormField>
            </v-col>
            <v-col cols="12" md="3">
              <FormField label="Hours"><v-text-field v-model.number="form.estimate_hours" type="number" /></FormField>
            </v-col>
            <v-col cols="12">
              <FormField label="Acceptance criteria"><v-textarea v-model="form.acceptance_criteria" rows="3" /></FormField>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Assign</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.work-board {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  align-items: stretch;
  margin-top: 4px;
}

.work-board--loading {
  opacity: 0.72;
  pointer-events: none;
}

.work-board__col {
  min-height: 360px;
  display: flex;
  flex-direction: column;
  border: 2px solid transparent;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.work-board__col--drop {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.04);
}

.work-board__col-title {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.work-board__col-body {
  flex: 1;
  min-height: 280px;
}

.work-board__card {
  cursor: grab;
  background: rgb(var(--v-theme-surface));
}

.work-board__card--open {
  cursor: default;
}

.work-board__card:not(.work-board__card--open):active {
  cursor: grabbing;
}

.work-board__card--dragging {
  opacity: 0.45;
}

.work-board__card--busy {
  opacity: 0.7;
}

.work-board__summary {
  cursor: pointer;
}

.work-board__title {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.work-board__grip {
  flex-shrink: 0;
}

.work-board__edit {
  cursor: default;
}

.work-board__empty {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 24px 12px;
  text-align: center;
}

.min-w-0 {
  min-width: 0;
}

@media (max-width: 960px) {
  .work-board {
    grid-template-columns: 1fr;
  }
}
</style>
