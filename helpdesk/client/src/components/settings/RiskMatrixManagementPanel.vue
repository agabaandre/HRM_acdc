<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { PRIORITY_ITEMS, type SelectNumberItem, type TicketPriority } from '../../lib/helpdeskForm'
import { priorityMeta } from '../../lib/ticketTableMeta'
import { notifyError, notifySuccess } from '../../lib/notify'

interface CategoryOption {
  id: number
  name: string
}

interface StaffRow {
  id: number
  name: string
  work_email: string | null
  duty_station_name?: string | null
}

interface MatrixRow {
  id: number
  staff_id: number
  staff_name: string | null
  staff_email: string | null
  duty_station_name: string | null
  priority: TicketPriority
  category_id: number | null
  category: { id: number; name: string } | null
  notes: string | null
  is_active: boolean
}

interface Summary {
  total: number
  active: number
  by_priority: Record<TicketPriority, number>
}

const headers: DataTableHeader[] = [
  { title: 'Staff member', key: 'staff', sortable: false, minWidth: '220px' },
  { title: 'Priority', key: 'priority', sortable: false, width: '150px' },
  { title: 'Scope', key: 'scope', sortable: false, minWidth: '180px' },
  { title: 'Notes', key: 'notes', sortable: false, minWidth: '200px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '170px', align: 'end' },
]

const rows = ref<MatrixRow[]>([])
const summary = ref<Summary | null>(null)
const categories = ref<CategoryOption[]>([])
const busyId = ref<number | null>(null)
const listFilter = ref('')
const priorityFilter = ref<TicketPriority | 'all'>('all')
const scopeFilter = ref<'all' | 'global' | 'category'>('all')

const staffRows = ref<StaffRow[]>([])
const staffSearch = ref('')
const staffResultsOpen = ref(false)
const selectedStaffId = ref<number | null>(null)
let staffSearchTimer: ReturnType<typeof setTimeout> | null = null

const draft = reactive({
  priority: 'high' as TicketPriority,
  category_id: undefined as number | undefined,
  notes: '',
  is_active: true,
})

const categoryItems = computed((): SelectNumberItem[] => [
  { label: 'All categories (global)', value: 0 },
  ...categories.value.map((c) => ({ label: c.name, value: c.id })),
])

const draftCategoryScope = computed({
  get: () => draft.category_id ?? 0,
  set: (v: number) => {
    draft.category_id = v > 0 ? v : undefined
  },
})

const selectedStaff = computed(() =>
  selectedStaffId.value ? staffRows.value.find((s) => s.id === selectedStaffId.value) ?? null : null,
)

const filteredStaffRows = computed(() => {
  const q = staffSearch.value.trim().toLowerCase()
  if (!q) return staffRows.value.slice(0, 40)
  return staffRows.value
    .filter((s) => {
      const hay = `${s.name} ${s.work_email ?? ''} ${s.duty_station_name ?? ''}`.toLowerCase()
      return hay.includes(q)
    })
    .slice(0, 40)
})

const filteredRows = computed(() => {
  const q = listFilter.value.trim().toLowerCase()
  return rows.value.filter((row) => {
    if (priorityFilter.value !== 'all' && row.priority !== priorityFilter.value) {
      return false
    }
    if (scopeFilter.value === 'global' && row.category_id !== null) {
      return false
    }
    if (scopeFilter.value === 'category' && row.category_id === null) {
      return false
    }
    if (q === '') {
      return true
    }
    const hay = [
      row.staff_name,
      row.staff_email,
      row.duty_station_name,
      row.notes,
      row.category?.name,
      String(row.staff_id),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return hay.includes(q)
  })
})

const prioritySummaryChips = computed(() => {
  const bp = summary.value?.by_priority
  if (!bp) return []
  return (['critical', 'high', 'medium', 'low'] as TicketPriority[]).map((p) => ({
    key: p,
    label: priorityMeta(p).label,
    count: bp[p] ?? 0,
    color: priorityMeta(p).color,
  }))
})

function validateDraft(): FormError[] {
  const errors: FormError[] = []
  if (!selectedStaffId.value) {
    errors.push({ name: 'staff_id', message: 'Choose a staff member from the directory' })
  }
  return errors
}

async function loadCategories() {
  try {
    const { data } = await api.get<{ data: CategoryOption[] }>('/api/v1/admin/categories')
    categories.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load categories.'))
  }
}

async function loadStaff() {
  try {
    const params: Record<string, string> = {}
    if (staffSearch.value.trim()) {
      params.q = staffSearch.value.trim()
    }
    const { data } = await api.get<{ data: { staff: StaffRow[] } }>('/api/v1/reference-data/staff', { params })
    staffRows.value = Array.isArray(data.data?.staff) ? data.data.staff : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not load staff directory.'))
    staffRows.value = []
  }
}

async function load() {
  try {
    const { data } = await api.get<{ data: MatrixRow[]; meta?: { summary?: Summary } }>('/api/v1/admin/risk-matrix')
    rows.value = Array.isArray(data.data) ? data.data : []
    summary.value = data.meta?.summary ?? null
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load risk matrix.'))
  }
}

function pickStaff(s: StaffRow) {
  selectedStaffId.value = s.id
  staffSearch.value = s.name
  staffResultsOpen.value = false
}

async function onCreate(_event: FormSubmitEvent<typeof draft>) {
  const errors = validateDraft()
  if (errors.length) {
    notifyError(errors[0]!.message)
    return
  }
  busyId.value = -1
  try {
    await api.post('/api/v1/admin/risk-matrix', {
      staff_id: selectedStaffId.value,
      priority: draft.priority,
      category_id: draft.category_id ?? null,
      notes: draft.notes.trim() || null,
      is_active: draft.is_active,
    })
    notifySuccess('Risk matrix entry added.')
    selectedStaffId.value = null
    staffSearch.value = ''
    draft.priority = 'high'
    draft.category_id = undefined
    draft.notes = ''
    draft.is_active = true
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not add entry.'))
  } finally {
    busyId.value = null
  }
}

async function save(row: MatrixRow) {
  busyId.value = row.id
  try {
    await api.put(`/api/v1/admin/risk-matrix/${row.id}`, {
      priority: row.priority,
      category_id: row.category_id,
      notes: row.notes,
      is_active: row.is_active,
    })
    notifySuccess(`Updated ${row.staff_name ?? 'entry'}.`)
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function remove(row: MatrixRow) {
  const label = row.staff_name ?? `Staff #${row.staff_id}`
  if (!window.confirm(`Remove risk matrix entry for “${label}”?`)) {
    return
  }
  busyId.value = row.id
  try {
    await api.delete(`/api/v1/admin/risk-matrix/${row.id}`)
    notifySuccess('Entry removed.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
  } finally {
    busyId.value = null
  }
}

function rowCategoryScope(row: MatrixRow): number {
  return row.category_id ?? 0
}

function setRowCategoryScope(row: MatrixRow, value: number): void {
  row.category_id = value > 0 ? value : null
  row.category = value > 0 ? categories.value.find((c) => c.id === value) ?? null : null
}

watch(staffSearch, () => {
  staffResultsOpen.value = true
  if (staffSearchTimer) clearTimeout(staffSearchTimer)
  staffSearchTimer = setTimeout(() => {
    void loadStaff()
  }, 250)
})

onMounted(async () => {
  await Promise.all([loadCategories(), loadStaff(), load()])
})
</script>

<template>
  <section class="risk-matrix" aria-labelledby="risk-matrix-heading">
    <header class="risk-matrix__intro">
      <h2 id="risk-matrix-heading">Risk matrix</h2>
      <p class="hint">
        Map prioritised staff to ticket priority levels. When someone on the matrix opens a request, their ticket
        priority is set automatically — requesters never choose priority on
        <RouterLink to="/tickets/new">New request</RouterLink>.
        Category-specific rules override global ones; otherwise the
        <RouterLink to="/settings/categories">category default</RouterLink> applies.
      </p>
    </header>

    <v-alert type="info" variant="tonal" density="comfortable" class="risk-matrix__alert">
      <strong>Priority order on create:</strong>
      1) Active risk matrix rule for the requester (category-specific, else global) →
      2) Issue category default →
      3) Medium if neither applies.
      Agents with <strong>Reassign tickets</strong> may change priority after submission.
    </v-alert>

    <div v-if="summary" class="risk-matrix__summary">
      <v-chip variant="outlined" size="small">{{ summary.active }} active / {{ summary.total }} total</v-chip>
      <v-chip
        v-for="chip in prioritySummaryChips"
        :key="chip.key"
        size="small"
        variant="flat"
        :style="{ background: `${chip.color}18`, color: chip.color, fontWeight: 700 }"
      >
        {{ chip.label }}: {{ chip.count }}
      </v-chip>
    </div>

    <v-card class="new-card" variant="outlined">
      <v-card-item>
        <v-card-title class="text-subtitle-1 font-weight-bold pa-0">Add matrix entry</v-card-title>
        <v-card-subtitle class="pa-0 mt-1">
          Global entries apply to every category; category-scoped entries take precedence for that issue type.
        </v-card-subtitle>
      </v-card-item>
      <v-divider />
      <v-card-text>
        <UForm
          :state="draft"
          :validate="validateDraft"
          class="hd-form hd-form--grid"
          @submit="onCreate"
        >
          <UFormField label="Staff member" name="staff_id" required class="span-2 staff-combo">
            <UInput
              v-model="staffSearch"
              type="search"
              icon="i-lucide-search"
              placeholder="Search name, email, or duty station…"
              autocomplete="off"
              @focus="staffResultsOpen = true"
            />
            <ul
              v-if="staffResultsOpen && filteredStaffRows.length"
              class="combo-results"
              role="listbox"
            >
              <li
                v-for="s in filteredStaffRows"
                :key="s.id"
                role="option"
                class="combo-result"
                :class="{ selected: selectedStaffId === s.id }"
                @mousedown.prevent
                @click="pickStaff(s)"
              >
                <span class="combo-result-name">{{ s.name }}</span>
                <span class="combo-result-meta">
                  {{ s.work_email || '—' }}
                  <template v-if="s.duty_station_name"> · {{ s.duty_station_name }}</template>
                </span>
              </li>
            </ul>
            <p v-if="selectedStaff" class="selected-staff" role="status">
              Selected: <strong>{{ selectedStaff.name }}</strong>
              <span v-if="selectedStaff.work_email"> · {{ selectedStaff.work_email }}</span>
            </p>
          </UFormField>

          <UFormField label="Ticket priority" name="priority" required>
            <USelect v-model="draft.priority" :items="PRIORITY_ITEMS" icon="mdi-flag-outline" />
          </UFormField>

          <UFormField label="Scope" name="category_id" stacked-label description="Global or one issue category">
            <USelect
              v-model="draftCategoryScope"
              :items="categoryItems"
              value-key="value"
            />
          </UFormField>

          <UFormField label="Notes" name="notes" class="span-2" stacked-label description="Why this person is prioritised (optional)">
            <UTextarea v-model="draft.notes" :rows="2" maxlength="2000" placeholder="e.g. Director — escalate all ICT requests" />
          </UFormField>

          <UFormField name="is_active">
            <UCheckbox v-model="draft.is_active" label="Active" />
          </UFormField>

          <div class="full hd-form-actions">
            <UButton type="submit" color="primary" :loading="busyId === -1">Add to matrix</UButton>
          </div>
        </UForm>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="matrix-table-card">
      <v-card-text class="matrix-table-toolbar">
        <UInput
          v-model="listFilter"
          type="search"
          icon="i-lucide-search"
          placeholder="Filter matrix…"
          class="matrix-filter"
          clearable
        />
        <USelect
          v-model="priorityFilter"
          :items="[
            { label: 'All priorities', value: 'all' },
            ...PRIORITY_ITEMS.map((p) => ({ label: p.label, value: p.value })),
          ]"
          class="matrix-filter-select"
        />
        <USelect
          v-model="scopeFilter"
          :items="[
            { label: 'All scopes', value: 'all' },
            { label: 'Global only', value: 'global' },
            { label: 'Category-specific', value: 'category' },
          ]"
          class="matrix-filter-select"
        />
      </v-card-text>

      <v-data-table
        :headers="headers"
        :items="filteredRows"
        item-value="id"
        density="comfortable"
        class="hd-data-table matrix-table"
        hide-default-footer
      >
        <template #item.staff="{ item }">
          <div class="staff-cell">
            <strong>{{ item.staff_name ?? `Staff #${item.staff_id}` }}</strong>
            <span v-if="item.staff_email" class="staff-meta">{{ item.staff_email }}</span>
            <span v-if="item.duty_station_name" class="staff-meta">{{ item.duty_station_name }}</span>
          </div>
        </template>

        <template #item.priority="{ item }">
          <USelect v-model="item.priority" :items="PRIORITY_ITEMS" />
        </template>

        <template #item.scope="{ item }">
          <USelect
            :model-value="rowCategoryScope(item)"
            :items="categoryItems"
            value-key="value"
            @update:model-value="setRowCategoryScope(item, $event as number)"
          />
        </template>

        <template #item.notes="{ item }">
          <UInput v-model="item.notes" placeholder="Notes" />
        </template>

        <template #item.is_active="{ item }">
          <UCheckbox v-model="item.is_active" hide-details />
        </template>

        <template #item.actions="{ item }">
          <div class="actions">
            <UButton
              type="button"
              color="neutral"
              variant="outlined"
              size="small"
              :loading="busyId === item.id"
              @click="save(item)"
            >
              Save
            </UButton>
            <UButton
              type="button"
              color="error"
              variant="soft"
              size="small"
              :disabled="busyId === item.id"
              @click="remove(item)"
            >
              Delete
            </UButton>
          </div>
        </template>

        <template #no-data>
          <p class="muted matrix-empty">No risk matrix entries yet. Add prioritised staff above.</p>
        </template>
      </v-data-table>
    </v-card>
  </section>
</template>

<style scoped>
.risk-matrix__intro h2 {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
  font-weight: 700;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.55;
  max-width: 52rem;
}
.hint a {
  color: #0d7a3a;
  font-weight: 600;
}
.risk-matrix__alert {
  margin-bottom: 1rem;
}
.risk-matrix__summary {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 1rem;
}
.new-card {
  margin-bottom: 1rem;
}
.matrix-table-card {
  overflow: hidden;
}
.matrix-table-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
  padding-bottom: 0.75rem !important;
}
.matrix-filter {
  flex: 1 1 220px;
  min-width: 200px;
}
.matrix-filter-select {
  flex: 0 1 180px;
  min-width: 160px;
}
.staff-combo {
  position: relative;
}
.combo-results {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  background: #fff;
  max-height: 220px;
  overflow: auto;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.combo-result {
  padding: 0.55rem 0.75rem;
  cursor: pointer;
  border-bottom: 1px solid var(--hd-line-subtle);
}
.combo-result:last-child {
  border-bottom: none;
}
.combo-result:hover,
.combo-result.selected {
  background: #f0fdf4;
}
.combo-result-name {
  display: block;
  font-weight: 600;
  color: #0f172a;
}
.combo-result-meta {
  display: block;
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.1rem;
}
.selected-staff {
  margin: 0.45rem 0 0;
  font-size: 0.82rem;
  color: #475569;
}
.staff-cell strong {
  display: block;
}
.staff-meta {
  display: block;
  font-size: 0.78rem;
  color: #64748b;
}
.actions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.muted {
  color: #64748b;
}
.matrix-empty {
  padding: 1rem;
  margin: 0;
}
</style>
