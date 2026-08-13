<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import { downloadClientCsv, openClientPdfTable } from '@/lib/clientTableExport'
import {
  createWeeklyTasks,
  fetchWeeklyTasks,
  updateWeeklyTask,
  type WeeklySpecificActivity,
  type WeeklyStats,
  type WeeklyTaskRow,
} from '@/lib/tasksApi'

const loading = ref(false)
const saving = ref(false)
const exporting = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<WeeklyTaskRow[]>([])
const divisions = ref<
  Array<{ division_id: number; division_name: string; division_short_name?: string | null }>
>([])
const staffOptions = ref<Array<{ staff_id: number; fname?: string | null; lname?: string | null }>>(
  [],
)
const specificActivities = ref<WeeklySpecificActivity[]>([])
const financialYear = ref<string>(String(new Date().getFullYear()))
const statusOptions = ref<Array<{ value: number; title: string }>>([])
const stats = ref<WeeklyStats>({
  total: 0,
  pending: 0,
  completed: 0,
  carried_forward: 0,
  cancelled: 0,
  overdue: 0,
  execution_rate: 0,
})

const divisionId = ref<number | null>(null)
const staffId = ref<number | null>(null)
const status = ref<number | null>(null)
const startDate = ref<string | null>(null)
const endDate = ref<string | null>(null)
const specificId = ref<number | null>(null)
const q = ref('')

const page = ref(1)
const perPage = ref(25)

const addOpen = ref(false)
const editOpen = ref(false)
const editing = ref<WeeklyTaskRow | null>(null)

const addForm = reactive({
  work_planner_tasks_id: null as number | null,
  staff_ids: [] as number[],
  start_date: '',
  end_date: '',
  rows: [{ activity_name: '', comments: '' }] as Array<{ activity_name: string; comments: string }>,
})

const editForm = reactive({
  activity_name: '',
  comments: '',
  status: 1,
  staff_ids: [] as number[],
})

const divisionItems = computed(() => [
  { title: 'All divisions', value: null as number | null },
  ...divisions.value.map((d) => ({
    title: d.division_short_name
      ? `${d.division_short_name} — ${d.division_name}`
      : d.division_name,
    value: d.division_id,
  })),
])

const staffItems = computed(() => [
  { title: 'All staff', value: null as number | null },
  ...staffOptions.value.map((s) => ({
    title: `${s.lname || ''}, ${s.fname || ''}`.replace(/^,\s*|,\s*$/g, '').trim() || `#${s.staff_id}`,
    value: s.staff_id,
  })),
])

const staffMultiItems = computed(() =>
  staffOptions.value.map((s) => ({
    title: `${s.lname || ''}, ${s.fname || ''}`.replace(/^,\s*|,\s*$/g, '').trim() || `#${s.staff_id}`,
    value: s.staff_id,
  })),
)

const specificItems = computed(() => [
  { title: 'All specific activities', value: null as number | null, subtitle: '' },
  ...specificActivities.value.map((a) => ({
    title: a.activity_name,
    value: a.activity_id as number | null,
    subtitle: [
      a.workplan_activity || null,
      a.pra_activity_id ? `PRA #${a.pra_activity_id}` : null,
      a.year ? `FY ${a.year}` : null,
    ]
      .filter(Boolean)
      .join(' · '),
  })),
])

const addSpecificItems = computed(() =>
  specificItems.value.filter((i) => i.value != null),
)

const statusItems = computed(() => [
  { title: 'All statuses', value: null as number | null },
  ...statusOptions.value.map((s) => ({ title: s.title, value: s.value })),
])

const total = computed(() => rows.value.length)
const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))
const pagedRows = computed(() => {
  const start = (page.value - 1) * perPage.value
  return rows.value.slice(start, start + perPage.value)
})

const exportHeaders = [
  'Weekly activity',
  'Specific activity',
  'Workplan indicator',
  'Staff',
  'Week',
  'Start',
  'End',
  'Status',
  'Overdue',
  'Comments',
  'Division',
]

function exportCells(row: WeeklyTaskRow): (string | number)[] {
  return [
    row.activity_name || '',
    row.specific_activity || row.planner_activity || '',
    row.workplan_activity || '',
    row.staff_name || '',
    row.week || '',
    row.start_date || '',
    row.end_date || '',
    row.status_label || String(row.status ?? ''),
    row.overdue ? 'Yes' : 'No',
    row.comments || '',
    row.division_name || '',
  ]
}

function filterSubtitle(): string {
  const bits: string[] = []
  const div = divisions.value.find((d) => d.division_id === divisionId.value)
  if (div) bits.push(`Division: ${div.division_short_name || div.division_name}`)
  if (startDate.value || endDate.value) {
    bits.push(`Dates: ${startDate.value || '…'} → ${endDate.value || '…'}`)
  }
  if (status.value) {
    const st = statusOptions.value.find((s) => s.value === status.value)
    if (st) bits.push(`Status: ${st.title}`)
  }
  if (specificId.value) {
    const sp = specificActivities.value.find((a) => a.activity_id === specificId.value)
    if (sp) bits.push(`Specific activity: ${sp.activity_name}`)
  }
  bits.push(
    `Execution: ${stats.value.execution_rate}% (${stats.value.completed}/${stats.value.total} completed)`,
  )
  return bits.join(' · ')
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchWeeklyTasks({
      division_id: divisionId.value,
      staff_id: staffId.value,
      status: status.value,
      start_date: startDate.value,
      end_date: endDate.value,
      work_planner_tasks_id: specificId.value,
      q: q.value || undefined,
    })
    rows.value = res.data
    divisions.value = res.meta.divisions || []
    staffOptions.value = res.meta.staff || []
    specificActivities.value = res.meta.specific_activities || []
    if (res.meta.financial_year != null && String(res.meta.financial_year) !== '') {
      financialYear.value = String(res.meta.financial_year)
    }
    statusOptions.value = res.meta.status_options || []
    stats.value = res.meta.stats || stats.value
    if (divisionId.value == null && res.meta.division_id) {
      divisionId.value = res.meta.division_id
    }
    page.value = 1
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load weekly tasks')
  } finally {
    loading.value = false
  }
}

function setThisWeek() {
  const now = new Date()
  const day = now.getDay()
  const mondayOffset = day === 0 ? -6 : 1 - day
  const monday = new Date(now)
  monday.setDate(now.getDate() + mondayOffset)
  const friday = new Date(monday)
  friday.setDate(monday.getDate() + 4)
  const iso = (d: Date) => d.toISOString().slice(0, 10)
  startDate.value = iso(monday)
  endDate.value = iso(friday)
}

function clearFilters() {
  staffId.value = null
  status.value = null
  startDate.value = null
  endDate.value = null
  specificId.value = null
  q.value = ''
}

function openAdd() {
  success.value = null
  error.value = null
  addForm.work_planner_tasks_id = null
  addForm.staff_ids = []
  addForm.start_date = startDate.value || new Date().toISOString().slice(0, 10)
  const end = new Date(addForm.start_date)
  end.setDate(end.getDate() + 4)
  addForm.end_date = endDate.value || end.toISOString().slice(0, 10)
  addForm.rows = [{ activity_name: '', comments: '' }]
  addOpen.value = true
}

function addActivityRow() {
  addForm.rows.push({ activity_name: '', comments: '' })
}

function removeActivityRow(i: number) {
  if (addForm.rows.length < 2) return
  addForm.rows.splice(i, 1)
}

async function submitAdd() {
  if (!addForm.work_planner_tasks_id) {
    error.value = 'Select a specific activity from your division.'
    return
  }
  const activities = addForm.rows
    .map((r) => ({
      activity_name: r.activity_name.trim(),
      comments: r.comments.trim(),
    }))
    .filter((r) => r.activity_name)
  if (!activities.length) {
    error.value = 'Add at least one weekly activity name.'
    return
  }
  saving.value = true
  error.value = null
  success.value = null
  try {
    const res = await createWeeklyTasks({
      work_planner_tasks_id: addForm.work_planner_tasks_id,
      start_date: addForm.start_date,
      end_date: addForm.end_date,
      staff_ids: addForm.staff_ids,
      activities,
    })
    success.value = res.message
    addOpen.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save weekly tasks')
  } finally {
    saving.value = false
  }
}

function openEdit(row: WeeklyTaskRow) {
  if (Number(row.status) !== 1) return
  editing.value = row
  editForm.activity_name = row.activity_name || ''
  editForm.comments = row.comments || ''
  editForm.status = Number(row.status) || 1
  const ids = String(row.staff_id || '')
    .split(',')
    .map((x) => Number(x.trim()))
    .filter((n) => n > 0)
  editForm.staff_ids = ids
  editOpen.value = true
}

async function submitEdit() {
  if (!editing.value) return
  saving.value = true
  error.value = null
  success.value = null
  try {
    const res = await updateWeeklyTask(editing.value.activity_id, {
      activity_name: editForm.activity_name.trim(),
      comments: editForm.comments.trim(),
      status: editForm.status,
      staff_ids: editForm.staff_ids,
    })
    success.value = res.message
    editOpen.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update task')
  } finally {
    saving.value = false
  }
}

function onPerPage(v: number) {
  perPage.value = v
  page.value = 1
}

function onExportCsv() {
  exporting.value = true
  try {
    downloadClientCsv('weekly-tasks.csv', exportHeaders, rows.value.map(exportCells))
  } finally {
    exporting.value = false
  }
}

function onExportPdf() {
  exporting.value = true
  try {
    openClientPdfTable(
      'Weekly tasks — execution report',
      exportHeaders,
      rows.value.map(exportCells),
      { subtitle: filterSubtitle() },
    )
  } finally {
    exporting.value = false
  }
}

function statusColor(row: WeeklyTaskRow): string {
  if (row.overdue) return 'error'
  switch (Number(row.status)) {
    case 2:
      return 'success'
    case 3:
      return 'warning'
    case 4:
      return 'secondary'
    default:
      return 'info'
  }
}

watch(divisionId, () => {
  specificId.value = null
  staffId.value = null
})

watch(
  [divisionId, staffId, status, startDate, endDate, specificId, q],
  () => void load(),
)
onMounted(() => void load())
</script>

<template>
  <div class="weekly-tasks-page">
    <PortalPageChrome
      title="Weekly tasks"
      :lede="`Report weekly progress against specific activities in your division (financial year ${financialYear}).`"
    >
      <template #actions>
        <v-btn size="small" variant="outlined" class="me-2" @click="setThisWeek">This week</v-btn>
        <v-btn size="small" color="primary" variant="flat" @click="openAdd">
          <i class="fa-solid fa-plus me-2" aria-hidden="true" />
          Add weekly tasks
        </v-btn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-row dense class="mb-3">
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #0f766e" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Total</div>
          <div class="text-h6 text-white">{{ stats.total }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #15803d" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Completed</div>
          <div class="text-h6 text-white">{{ stats.completed }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #1d4ed8" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Pending</div>
          <div class="text-h6 text-white">{{ stats.pending }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #b45309" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Carried forward</div>
          <div class="text-h6 text-white">{{ stats.carried_forward }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #b91c1c" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Overdue</div>
          <div class="text-h6 text-white">{{ stats.overdue }}</div>
        </v-sheet>
      </v-col>
      <v-col cols="6" sm="4" md="2">
        <v-sheet class="perf-kpi pa-3" style="--perf-kpi-bg: #334155" rounded>
          <div class="text-caption text-white" style="opacity:0.85">Execution</div>
          <div class="text-h6 text-white">{{ stats.execution_rate }}%</div>
        </v-sheet>
      </v-col>
    </v-row>

    <div class="portal-staff-filters mb-3">
      <v-row dense>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="divisionId"
            :items="divisionItems"
            label="Division"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-autocomplete
            v-model="specificId"
            :items="specificItems"
            item-title="title"
            item-value="value"
            label="Specific activity"
            density="compact"
            hide-details
            clearable
            auto-select-first
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps" :subtitle="item.raw.subtitle || undefined" />
            </template>
          </v-autocomplete>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="staffId"
            :items="staffItems"
            label="Staff"
            density="compact"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="status"
            :items="statusItems"
            label="Status"
            density="compact"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <UDateInput
            v-model="startDate"
            label="Start date"
            placeholder="From date"
            density="compact"
            hide-details
            :max="endDate || undefined"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <UDateInput
            v-model="endDate"
            label="End date"
            placeholder="To date"
            density="compact"
            hide-details
            :min="startDate || undefined"
          />
        </v-col>
        <v-col cols="12" sm="6" md="4">
          <v-text-field
            v-model="q"
            label="Search activity / comments"
            density="compact"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="2" class="d-flex align-center">
          <v-btn size="small" variant="text" @click="clearFilters">Clear filters</v-btn>
        </v-col>
      </v-row>
    </div>

    <v-card class="portal-data-table-card mb-3" variant="outlined">
      <div class="px-3 pt-1">
        <PortalTableToolbar
          placement="header"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          total-label="Total weekly tasks"
          :exporting="exporting"
          @update:page="(v: number) => (page = v)"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        />
      </div>
      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>
      <v-table v-else density="compact">
        <thead>
          <tr>
            <th>#</th>
            <th>Weekly activity</th>
            <th>Specific activity</th>
            <th>Staff</th>
            <th>Week / dates</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in pagedRows" :key="row.activity_id">
            <td class="text-medium-emphasis">{{ (page - 1) * perPage + index + 1 }}</td>
            <td>
              <div>{{ row.activity_name || '—' }}</div>
              <div v-if="row.comments" class="text-caption text-medium-emphasis">{{ row.comments }}</div>
              <div v-if="row.workplan_id" class="text-caption">
                <RouterLink :to="`/workplan/${row.workplan_id}`">
                  {{ row.workplan_activity || `Workplan #${row.workplan_id}` }}
                </RouterLink>
              </div>
            </td>
            <td>
              <div>{{ row.specific_activity || row.planner_activity || '—' }}</div>
              <div v-if="row.pra_activity_id" class="text-caption text-medium-emphasis">
                PRA #{{ row.pra_activity_id }}
              </div>
            </td>
            <td>{{ row.staff_name || '—' }}</td>
            <td>
              <div>{{ row.week || '—' }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ row.start_date || '—' }} → {{ row.end_date || '—' }}
              </div>
            </td>
            <td>
              <v-chip size="x-small" variant="tonal" :color="statusColor(row)">
                {{ row.status_label || row.status }}
                <span v-if="row.overdue"> · overdue</span>
              </v-chip>
            </td>
            <td class="text-end">
              <v-btn
                v-if="Number(row.status) === 1"
                size="x-small"
                variant="text"
                @click="openEdit(row)"
              >
                Update
              </v-btn>
            </td>
          </tr>
          <tr v-if="!pagedRows.length">
            <td colspan="7" class="text-medium-emphasis text-center py-6">
              No weekly tasks for these filters. Add tasks against a specific activity from your division.
            </td>
          </tr>
        </tbody>
      </v-table>
      <div class="px-3 pb-1">
        <PortalTableToolbar
          placement="footer"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          :show-csv="false"
          :show-pdf="false"
          :show-per-page="false"
          @update:page="(v: number) => (page = v)"
        />
      </div>
    </v-card>

    <v-dialog v-model="addOpen" max-width="720" scrollable>
      <v-card>
        <v-card-title>Add weekly tasks</v-card-title>
        <v-card-subtitle>
          Search and choose a specific activity from financial year {{ financialYear }}, then add one or more
          weekly activities to report on.
        </v-card-subtitle>
        <v-card-text>
          <v-row dense>
            <v-col cols="12">
              <v-autocomplete
                v-model="addForm.work_planner_tasks_id"
                :items="addSpecificItems"
                item-title="title"
                item-value="value"
                :label="`Specific activity — FY ${financialYear} (required)`"
                placeholder="Type to search activities…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                :no-data-text="
                  specificActivities.length
                    ? 'No match — try another search'
                    : `No specific activities for FY ${financialYear} in this division`
                "
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.subtitle || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" sm="6">
              <UDateInput
                v-model="addForm.start_date"
                label="Start date"
                placeholder="Select start date"
                density="compact"
                hide-details
                :max="addForm.end_date || undefined"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <UDateInput
                v-model="addForm.end_date"
                label="End date"
                placeholder="Select end date"
                density="compact"
                hide-details
                :min="addForm.start_date || undefined"
              />
            </v-col>
            <v-col cols="12">
              <v-select
                v-model="addForm.staff_ids"
                :items="staffMultiItems"
                label="Assign staff (optional — defaults to you)"
                density="compact"
                hide-details
                multiple
                chips
                closable-chips
              />
            </v-col>
          </v-row>
          <div class="text-subtitle-2 mt-4 mb-2">Weekly activities</div>
          <div
            v-for="(row, i) in addForm.rows"
            :key="i"
            class="d-flex ga-2 mb-2 align-start"
          >
            <v-text-field
              v-model="row.activity_name"
              label="Activity name"
              density="compact"
              hide-details
              class="flex-grow-1"
            />
            <v-text-field
              v-model="row.comments"
              label="Comments"
              density="compact"
              hide-details
              class="flex-grow-1"
            />
            <v-btn
              icon
              variant="text"
              size="small"
              :disabled="addForm.rows.length < 2"
              @click="removeActivityRow(i)"
            >
              <i class="fa-solid fa-trash" aria-hidden="true" />
            </v-btn>
          </div>
          <v-btn size="small" variant="text" @click="addActivityRow">+ Another activity</v-btn>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="addOpen = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="submitAdd">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="editOpen" max-width="560">
      <v-card>
        <v-card-title>Update weekly task</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="editForm.activity_name"
            label="Activity name"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-textarea
            v-model="editForm.comments"
            label="Comments"
            density="compact"
            rows="2"
            class="mb-2"
            hide-details
          />
          <v-select
            v-model="editForm.status"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            label="Status"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-select
            v-model="editForm.staff_ids"
            :items="staffMultiItems"
            label="Staff"
            density="compact"
            multiple
            chips
            closable-chips
            hide-details
          />
          <div class="text-caption text-medium-emphasis mt-2">
            Setting status to Carried Forward creates a pending copy for next week.
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editOpen = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="submitEdit">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
