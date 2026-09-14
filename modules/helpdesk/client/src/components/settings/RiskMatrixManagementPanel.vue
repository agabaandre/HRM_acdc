<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
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
  { title: 'Priority', key: 'priority', sortable: false, width: '120px' },
  { title: 'Scope', key: 'scope', sortable: false, minWidth: '160px' },
  { title: 'Notes', key: 'notes', sortable: false, minWidth: '180px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const rows = ref<MatrixRow[]>([])
const summary = ref<Summary | null>(null)
const categories = ref<CategoryOption[]>([])
const busyId = ref<number | null>(null)
const listFilter = ref('')
const priorityFilter = ref<TicketPriority | 'all'>('all')
const scopeFilter = ref<'all' | 'global' | 'category'>('all')

const createModalOpen = ref(false)
const editModalOpen = ref(false)
const editingId = ref<number | null>(null)

const staffRows = ref<StaffRow[]>([])
const selectedStaffIds = ref<number[]>([])
const selectedScopeIds = ref<number[]>([0])

const createDraft = reactive({
  priority: 'high' as TicketPriority,
  notes: '',
  is_active: true,
})

const editDraft = reactive({
  priority: 'high' as TicketPriority,
  category_scope: 0,
  notes: '',
  is_active: true,
  staff_label: '',
})

const scopeItems = computed((): SelectNumberItem[] => [
  { label: 'All categories (global)', value: 0 },
  ...categories.value.map((c) => ({ label: c.name, value: c.id })),
])

const staffSelectItems = computed((): SelectNumberItem[] =>
  staffRows.value.map((s) => ({
    value: s.id,
    label: s.duty_station_name
      ? `${s.name} · ${s.duty_station_name}`
      : s.name,
  })),
)

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

const mappingPreviewCount = computed(() => {
  const staffCount = selectedStaffIds.value.length
  const scopeCount = selectedScopeIds.value.length
  return staffCount > 0 && scopeCount > 0 ? staffCount * scopeCount : 0
})

function validateCreateDraft(): FormError[] {
  const errors: FormError[] = []
  if (selectedStaffIds.value.length === 0) {
    errors.push({ name: 'staff_ids', message: 'Select at least one staff member' })
  }
  if (selectedScopeIds.value.length === 0) {
    errors.push({ name: 'category_ids', message: 'Select at least one scope (global and/or categories)' })
  }
  return errors
}

function scopeLabel(row: MatrixRow): string {
  if (row.category_id == null) return 'Global'
  return row.category?.name ?? categories.value.find((c) => c.id === row.category_id)?.name ?? `Category #${row.category_id}`
}

function notesPreview(notes: string | null): string {
  const t = (notes ?? '').trim()
  if (!t) return '—'
  return t.length > 80 ? `${t.slice(0, 80)}…` : t
}

function resetCreateDraft() {
  selectedStaffIds.value = []
  selectedScopeIds.value = [0]
  createDraft.priority = 'high'
  createDraft.notes = ''
  createDraft.is_active = true
}

function openCreateModal() {
  resetCreateDraft()
  createModalOpen.value = true
}

function closeCreateModal() {
  createModalOpen.value = false
  resetCreateDraft()
}

function openEditModal(row: MatrixRow) {
  editingId.value = row.id
  editDraft.priority = row.priority
  editDraft.category_scope = row.category_id ?? 0
  editDraft.notes = row.notes ?? ''
  editDraft.is_active = row.is_active
  editDraft.staff_label = row.staff_name ?? `Staff #${row.staff_id}`
  editModalOpen.value = true
}

function closeEditModal() {
  editModalOpen.value = false
  editingId.value = null
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
    const { data } = await api.get<{ data: { staff: StaffRow[] } }>('/api/v1/reference-data/staff')
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
    notifyError(apiErrorMessage(e, 'Failed to load priority matrix.'))
  }
}

async function saveCreateModal(_event?: FormSubmitEvent<typeof createDraft>) {
  const errors = validateCreateDraft()
  if (errors.length) {
    notifyError(errors[0]!.message)
    return
  }
  busyId.value = -1
  try {
    const { data } = await api.post<{ message?: string; data?: { created: number; skipped: number } }>(
      '/api/v1/admin/risk-matrix/bulk',
      {
        staff_ids: selectedStaffIds.value,
        category_ids: selectedScopeIds.value,
        priority: createDraft.priority,
        notes: createDraft.notes.trim() || null,
        is_active: createDraft.is_active,
      },
    )
    notifySuccess(data.message ?? 'Priority matrix updated.')
    closeCreateModal()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not add entries.'))
  } finally {
    busyId.value = null
  }
}

async function saveEditModal(_event?: FormSubmitEvent<typeof editDraft>) {
  if (editingId.value == null) return
  busyId.value = editingId.value
  try {
    await api.put(`/api/v1/admin/risk-matrix/${editingId.value}`, {
      priority: editDraft.priority,
      category_id: editDraft.category_scope > 0 ? editDraft.category_scope : null,
      notes: editDraft.notes.trim() || null,
      is_active: editDraft.is_active,
    })
    notifySuccess(`Updated ${editDraft.staff_label}.`)
    closeEditModal()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function remove(row: MatrixRow) {
  const label = row.staff_name ?? `Staff #${row.staff_id}`
  if (!window.confirm(`Remove priority matrix entry for “${label}”?`)) {
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

onMounted(async () => {
  await Promise.all([loadCategories(), loadStaff(), load()])
})
</script>

<template>
  <section class="priority-matrix" aria-labelledby="priority-matrix-heading">
    <header class="priority-matrix__intro">
      <h2 id="priority-matrix-heading">Priority matrix</h2>
      <p class="hint">
        Map prioritised staff to ticket priority levels. When someone on the matrix opens a request, their ticket
        priority is set automatically — requesters never choose priority on
        <RouterLink to="/tickets/new">Create ticket</RouterLink>.
        Category-specific rules override global ones; otherwise the
        <RouterLink to="/settings/categories">category default</RouterLink> applies.
      </p>
    </header>

    <v-alert type="info" variant="tonal" density="comfortable" class="priority-matrix__alert">
      <strong>Priority order on create:</strong>
      1) Active priority matrix rule for the requester (category-specific, else global) →
      2) Issue category default →
      3) Medium if neither applies.
      Agents with <strong>Reassign tickets</strong> may change priority after submission.
    </v-alert>

    <div v-if="summary" class="priority-matrix__summary">
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

    <div class="toolbar">
      <p class="toolbar-hint muted">
        Edit each mapping in a modal. Keep the table lean for scanning staff, priority, and scope.
      </p>
      <UButton color="primary" @click="openCreateModal">Add mappings</UButton>
    </div>

    <v-card elevation="10" class="matrix-table-card">
      <v-card-text class="matrix-table-toolbar">
        <UInput
          v-model="listFilter"
          type="search"
          icon="mdi-magnify"
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
          <span
            class="priority-pill"
            :style="{ background: `${priorityMeta(item.priority).color}18`, color: priorityMeta(item.priority).color }"
          >
            {{ priorityMeta(item.priority).label }}
          </span>
        </template>

        <template #item.scope="{ item }">
          {{ scopeLabel(item) }}
        </template>

        <template #item.notes="{ item }">
          <span class="notes-cell" :title="item.notes ?? undefined">{{ notesPreview(item.notes) }}</span>
        </template>

        <template #item.is_active="{ item }">
          <UCheckbox :model-value="item.is_active" disabled hide-details />
        </template>

        <template #item.actions="{ item }">
          <div class="actions">
            <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditModal(item)">
              Edit
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
          <p class="muted matrix-empty">No priority matrix entries yet. Use Add mappings to create rules.</p>
        </template>
      </v-data-table>
    </v-card>

    <UModal
      v-model:open="createModalOpen"
      title="Add matrix mappings"
      :ui="{ content: 'max-w-xl' }"
    >
      <template #body>
        <UForm
          :state="createDraft"
          :validate="validateCreateDraft"
          class="hd-form hd-form--grid"
          @submit="saveCreateModal"
        >
          <UFormField label="Staff members" name="staff_ids" required class="span-2">
            <USelectMenu
              v-model="selectedStaffIds"
              :items="staffSelectItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-account-multiple"
              placeholder="Search and select staff…"
            />
          </UFormField>

          <UFormField label="Ticket priority" name="priority" required>
            <USelect v-model="createDraft.priority" :items="PRIORITY_ITEMS" icon="mdi-flag-outline" />
          </UFormField>

          <UFormField
            label="Scopes"
            name="category_ids"
            required
            class="span-2"
           
            description="Pick global, one category, or several at once"
          >
            <USelectMenu
              v-model="selectedScopeIds"
              :items="scopeItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-tag-multiple-outline"
              placeholder="Global and/or categories…"
            />
          </UFormField>

          <UFormField label="Notes" name="notes" class="span-2" description="Shared note for all new mappings (optional)">
            <UTextarea v-model="createDraft.notes" :rows="2" maxlength="2000" placeholder="e.g. Director — escalate all ICT requests" />
          </UFormField>

          <UFormField name="is_active">
            <UCheckbox v-model="createDraft.is_active" label="Active" />
          </UFormField>

          <p v-if="mappingPreviewCount > 0" class="mapping-preview span-2" role="status">
            Will create up to <strong>{{ mappingPreviewCount }}</strong>
            {{ mappingPreviewCount === 1 ? 'mapping' : 'mappings' }}
            (existing combinations are skipped).
          </p>
        </UForm>
      </template>
      <template #footer>
        <UButton color="neutral" variant="outline" :disabled="busyId !== null" @click="closeCreateModal">Cancel</UButton>
        <UButton
          color="primary"
          :loading="busyId === -1"
          label="Add to matrix"
          @click="saveCreateModal()"
        />
      </template>
    </UModal>

    <UModal
      v-model:open="editModalOpen"
      title="Edit matrix entry"
      :ui="{ content: 'max-w-xl' }"
    >
      <template #body>
        <UForm :state="editDraft" class="hd-form hd-form--grid" @submit="saveEditModal">
          <UFormField label="Staff member" name="staff" class="span-2">
            <UInput :model-value="editDraft.staff_label" disabled />
          </UFormField>

          <UFormField label="Ticket priority" name="priority" required>
            <USelect v-model="editDraft.priority" :items="PRIORITY_ITEMS" icon="mdi-flag-outline" />
          </UFormField>

          <UFormField label="Scope" name="category_scope" required>
            <USelectMenu
              v-model="editDraft.category_scope"
              :items="scopeItems"
              value-key="value"
              searchable
              icon="mdi-tag-outline"
              placeholder="Select scope…"
            />
          </UFormField>

          <UFormField label="Notes" name="notes" class="span-2">
            <UTextarea v-model="editDraft.notes" :rows="2" maxlength="2000" />
          </UFormField>

          <UFormField name="is_active">
            <UCheckbox v-model="editDraft.is_active" label="Active" />
          </UFormField>
        </UForm>
      </template>
      <template #footer>
        <UButton color="neutral" variant="outline" :disabled="busyId !== null" @click="closeEditModal">Cancel</UButton>
        <UButton
          color="primary"
          :loading="busyId !== null && busyId !== -1"
          label="Save changes"
          @click="saveEditModal()"
        />
      </template>
    </UModal>
  </section>
</template>

<style scoped>
.priority-matrix__intro h2 {
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
.priority-matrix__alert {
  margin-bottom: 1rem;
}
.priority-matrix__summary {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 1rem;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.toolbar-hint {
  margin: 0;
  flex: 1;
  min-width: 14rem;
  font-size: 0.88rem;
  line-height: 1.45;
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
.mapping-preview {
  margin: 0;
  font-size: 0.85rem;
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
.notes-cell {
  font-size: 0.85rem;
  color: #475569;
}
.priority-pill {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
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
