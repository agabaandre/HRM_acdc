<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { notifyApiError, toast } from '@/features/toast'
import {
  createDivision,
  deleteDivision,
  fetchDivisionsSettings,
  fetchOrgStaffOptions,
  updateDivision,
  type DivisionFormPayload,
  type DivisionRow,
} from '@/lib/settingsApi'
import { downloadClientCsv, downloadClientExcel, fetchAllPages } from '@/lib/clientTableExport'
import UDateInput from '@cbp/ui/UDateInput.vue'

type StaffOpt = { title: string; value: number; email?: string | null }

type DivForm = {
  division_name: string
  division_short_name: string
  category: string
  directorate_id: number | null
  is_active: boolean
  division_head: number | null
  focal_person: number | null
  finance_officer: number | null
  admin_assistant: number | null
  director_id: number | null
  head_oic_id: number | null
  head_oic_start_date: string
  head_oic_end_date: string
  director_oic_id: number | null
  director_oic_start_date: string
  director_oic_end_date: string
}

const emptyForm = (): DivForm => ({
  division_name: '',
  division_short_name: '',
  category: 'Technical',
  directorate_id: null,
  is_active: true,
  division_head: null,
  focal_person: null,
  finance_officer: null,
  admin_assistant: null,
  director_id: null,
  head_oic_id: null,
  head_oic_start_date: '',
  head_oic_end_date: '',
  director_oic_id: null,
  director_oic_start_date: '',
  director_oic_end_date: '',
})

const loading = ref(false)
const saving = ref(false)
const exporting = ref(false)
const q = ref('')
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const rows = ref<DivisionRow[]>([])
const staffOptions = ref<StaffOpt[]>([])
const directorateOptions = ref<{ title: string; value: number }[]>([])
const categoryOptions = ref<string[]>(['Technical', 'Support'])
const createExpanded = ref(false)
const dialogOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<DivForm>(emptyForm())

const headers = [
  { title: 'Name', key: 'division_name', sortable: false },
  { title: 'Short', key: 'division_short_name', sortable: false },
  { title: 'Category', key: 'category', sortable: false },
  { title: 'Directorate', key: 'directorate_name', sortable: false },
  { title: 'Head', key: 'division_head_name', sortable: false },
  { title: 'Active', key: 'is_active', sortable: false, width: 90 },
  { title: '', key: 'actions', sortable: false, width: 120, align: 'end' as const },
]

const categorySelectItems = computed(() =>
  categoryOptions.value.map((c) => ({ title: c, value: c })),
)

const dialogTitle = computed(() => (editingId.value ? 'Edit division' : 'New division'))

function staffFilter(itemTitle: string, queryText: string, item: unknown): boolean {
  const raw = (item as { raw?: StaffOpt })?.raw
  const hay = `${raw?.title ?? itemTitle} ${raw?.email ?? ''} ${raw?.value ?? ''}`.toLowerCase()
  return hay.includes((queryText || '').toLowerCase().trim())
}

function ensureStaffOption(id: number | null | undefined, name?: string | null) {
  if (!id || id < 1) return
  if (staffOptions.value.some((s) => s.value === id)) return
  staffOptions.value = [...staffOptions.value, { title: name || `Staff #${id}`, value: id }]
}

function fillForm(row?: DivisionRow | null) {
  Object.assign(form, emptyForm())
  if (!row) return
  form.division_name = row.division_name
  form.division_short_name = row.division_short_name ?? ''
  form.category = row.category || categoryOptions.value[0] || 'Technical'
  form.directorate_id = row.directorate_id ?? null
  form.is_active = !!row.is_active
  form.division_head = row.division_head ?? null
  form.focal_person = row.focal_person ?? null
  form.finance_officer = row.finance_officer ?? null
  form.admin_assistant = row.admin_assistant ?? null
  form.director_id = row.director_id ?? null
  form.head_oic_id = row.head_oic_id ?? null
  form.head_oic_start_date = row.head_oic_start_date ?? ''
  form.head_oic_end_date = row.head_oic_end_date ?? ''
  form.director_oic_id = row.director_oic_id ?? null
  form.director_oic_start_date = row.director_oic_start_date ?? ''
  form.director_oic_end_date = row.director_oic_end_date ?? ''
  ensureStaffOption(row.division_head, row.division_head_name)
  ensureStaffOption(row.focal_person, row.focal_person_name)
  ensureStaffOption(row.finance_officer, row.finance_officer_name)
  ensureStaffOption(row.admin_assistant, row.admin_assistant_name)
  ensureStaffOption(row.director_id, row.director_name)
  ensureStaffOption(row.head_oic_id, row.head_oic_name)
  ensureStaffOption(row.director_oic_id, row.director_oic_name)
}

function toPayload(): DivisionFormPayload {
  return {
    division_name: form.division_name.trim(),
    division_short_name: form.division_short_name.trim() || null,
    category: form.category,
    directorate_id: form.directorate_id,
    is_active: form.is_active,
    division_head: form.division_head as number,
    focal_person: form.focal_person as number,
    finance_officer: form.finance_officer as number,
    admin_assistant: form.admin_assistant as number,
    director_id: form.director_id,
    head_oic_id: form.head_oic_id,
    head_oic_start_date: form.head_oic_start_date || null,
    head_oic_end_date: form.head_oic_end_date || null,
    director_oic_id: form.director_oic_id,
    director_oic_start_date: form.director_oic_start_date || null,
    director_oic_end_date: form.director_oic_end_date || null,
  }
}

async function loadStaff() {
  const list = await fetchOrgStaffOptions()
  staffOptions.value = list.map((s) => ({
    title: s.name,
    value: s.staff_id,
    email: s.email,
  }))
}

async function load() {
  loading.value = true
  try {
    const res = await fetchDivisionsSettings({
      q: q.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = res.data
    total.value = res.meta.total
    directorateOptions.value = (res.meta.directorates || []).map((d) => ({
      title: d.name,
      value: d.id,
    }))
    if (res.meta.categories?.length) {
      categoryOptions.value = res.meta.categories
    }
  } catch (e) {
    notifyApiError(e, 'Failed to load divisions')
    rows.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  fillForm(null)
  createExpanded.value = true
}

function openEdit(row: DivisionRow) {
  editingId.value = row.division_id
  fillForm(row)
  createExpanded.value = false
  dialogOpen.value = true
}

async function onSave(fromCreatePanel = false) {
  if (!form.division_name.trim()) {
    toast.warning('Division name is required.', 'Divisions')
    return
  }
  if (!form.directorate_id) {
    toast.warning('Directorate is required.', 'Divisions')
    return
  }
  if (!form.division_head || !form.focal_person || !form.finance_officer || !form.admin_assistant) {
    toast.warning(
      'Head, focal person, finance officer and admin assistant are required.',
      'Divisions',
    )
    return
  }
  saving.value = true
  try {
    const payload = toPayload()
    const message = editingId.value
      ? await updateDivision(editingId.value, payload)
      : await createDivision(payload)
    toast.success(message || 'Division saved.', 'Saved')
    if (editingId.value) {
      dialogOpen.value = false
    } else if (fromCreatePanel) {
      fillForm(null)
      createExpanded.value = false
    } else {
      dialogOpen.value = false
    }
    await load()
  } catch (e) {
    notifyApiError(e, 'Save failed')
  } finally {
    saving.value = false
  }
}

async function onDelete(row: DivisionRow) {
  if (!window.confirm(`Delete division “${row.division_name}”?`)) return
  try {
    const message = await deleteDivision(row.division_id)
    toast.success(message || 'Division deleted.', 'Deleted')
    await load()
  } catch (e) {
    notifyApiError(e, 'Delete failed')
  }
}

const exportHeaders = ['ID', 'Name', 'Short name', 'Category', 'Directorate', 'Head', 'Active']

function rowToExport(r: DivisionRow): (string | number)[] {
  return [
    r.division_id,
    r.division_name,
    r.division_short_name ?? '',
    r.category ?? '',
    r.directorate_name ?? '',
    r.division_head_name ?? '',
    r.is_active ? 'Yes' : 'No',
  ]
}

async function loadAllForExport(): Promise<DivisionRow[]> {
  return fetchAllPages(async (p, pp) => {
    const res = await fetchDivisionsSettings({ q: q.value || undefined, page: p, per_page: pp })
    return { data: res.data, meta: { last_page: res.meta.last_page } }
  })
}

async function onExportCsv() {
  exporting.value = true
  try {
    const all = await loadAllForExport()
    downloadClientCsv('divisions.csv', exportHeaders, all.map(rowToExport))
    toast.success('CSV downloaded.', 'Export')
  } catch (e) {
    notifyApiError(e, 'Export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportExcel() {
  exporting.value = true
  try {
    const all = await loadAllForExport()
    downloadClientExcel('divisions.xls', exportHeaders, all.map(rowToExport), 'Divisions')
    toast.success('Excel downloaded.', 'Export')
  } catch (e) {
    notifyApiError(e, 'Export failed')
  } finally {
    exporting.value = false
  }
}

watch([page, perPage], () => {
  void load()
})

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(q, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    void load()
  }, 300)
})

onMounted(async () => {
  try {
    await loadStaff()
  } catch {
    /* ignore */
  }
  await load()
})
</script>

<template>
  <div class="divisions-settings">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <CbpPageHeading
        title="Divisions"
        subtitle="Manage divisions, directorate links, and responsible officers (CI3 parity)."
      />
      <div class="d-flex flex-wrap ga-2">
        <RouterLink to="/settings" style="text-decoration: none">
          <v-btn variant="outlined" size="small">← Settings</v-btn>
        </RouterLink>
        <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openCreate">
          New division
        </v-btn>
        <v-btn
          variant="tonal"
          prepend-icon="mdi-file-delimited"
          :loading="exporting"
          :disabled="loading"
          @click="onExportCsv"
        >
          CSV
        </v-btn>
        <v-btn
          variant="tonal"
          prepend-icon="mdi-microsoft-excel"
          :loading="exporting"
          :disabled="loading"
          @click="onExportExcel"
        >
          Excel
        </v-btn>
      </div>
    </div>

    <v-expand-transition>
      <v-card v-show="createExpanded" class="mb-4" border>
        <v-card-title class="d-flex align-center text-subtitle-1">
          New division
          <v-spacer />
          <v-btn icon variant="text" size="small" aria-label="Collapse" @click="createExpanded = false">
            <v-icon icon="mdi-chevron-up" />
          </v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.division_name"
                label="Division name *"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field
                v-model="form.division_short_name"
                label="Short name"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="form.category"
                :items="categorySelectItems"
                label="Category *"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                v-model="form.directorate_id"
                :items="directorateOptions"
                label="Directorate *"
                density="compact"
                hide-details="auto"
                clearable
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="6" class="d-flex align-center">
              <v-switch v-model="form.is_active" label="Active" color="primary" hide-details density="compact" />
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.division_head"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Division head *"
                placeholder="Search staff by name or email…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.focal_person"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Focal person *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.finance_officer"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Finance officer *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.admin_assistant"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Admin assistant *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.director_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Director (optional)"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12"><div class="text-subtitle-2 mt-2">Head OIC</div></v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.head_oic_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Head OIC"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput
                v-model="form.head_oic_start_date"
                label="Head OIC start"
                density="compact"
                hide-details
                :max="form.head_oic_end_date || undefined"
              />
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput
                v-model="form.head_oic_end_date"
                label="Head OIC end"
                density="compact"
                hide-details
                :min="form.head_oic_start_date || undefined"
              />
            </v-col>
            <v-col cols="12"><div class="text-subtitle-2 mt-2">Director OIC</div></v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.director_oic_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Director OIC"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput
                v-model="form.director_oic_start_date"
                label="Director OIC start"
                density="compact"
                hide-details
                :max="form.director_oic_end_date || undefined"
              />
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput
                v-model="form.director_oic_end_date"
                label="Director OIC end"
                density="compact"
                hide-details
                :min="form.director_oic_start_date || undefined"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="createExpanded = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="onSave(true)">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <v-card border>
      <v-card-text class="pb-0">
        <v-text-field
          v-model="q"
          label="Search divisions"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          hide-details
          clearable
          class="mb-3 search-field-white"
          style="max-width: 28rem"
        />
      </v-card-text>
      <v-data-table-server
        :headers="headers"
        :items="rows"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        item-value="division_id"
        class="elevation-0"
        @update:page="page = $event"
        @update:items-per-page="perPage = $event"
      >
        <template #item.is_active="{ item }">
          <v-chip size="x-small" :color="item.is_active ? 'success' : 'default'" variant="tonal">
            {{ item.is_active ? 'Yes' : 'No' }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <v-btn icon variant="text" size="small" aria-label="Edit" @click="openEdit(item)">
            <v-icon icon="mdi-pencil" size="small" />
          </v-btn>
          <v-btn icon variant="text" size="small" color="error" aria-label="Delete" @click="onDelete(item)">
            <v-icon icon="mdi-delete" size="small" />
          </v-btn>
        </template>
      </v-data-table-server>
    </v-card>

    <v-dialog v-model="dialogOpen" max-width="920" scrollable>
      <v-card>
        <v-card-title>{{ dialogTitle }}</v-card-title>
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.division_name" label="Division name *" density="compact" hide-details="auto" class="bg-white" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model="form.division_short_name" label="Short name" density="compact" hide-details="auto" class="bg-white" />
            </v-col>
            <v-col cols="12" md="3">
              <v-select v-model="form.category" :items="categorySelectItems" label="Category *" density="compact" hide-details="auto" class="bg-white" />
            </v-col>
            <v-col cols="12" md="6">
              <v-select v-model="form.directorate_id" :items="directorateOptions" label="Directorate *" density="compact" hide-details="auto" clearable class="bg-white" />
            </v-col>
            <v-col cols="12" md="6" class="d-flex align-center">
              <v-switch v-model="form.is_active" label="Active" color="primary" hide-details density="compact" />
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.division_head"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Division head *"
                placeholder="Search staff by name or email…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.focal_person"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Focal person *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.finance_officer"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Finance officer *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.admin_assistant"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Admin assistant *"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.director_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Director (optional)"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12"><div class="text-subtitle-2 mt-2">Head OIC</div></v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.head_oic_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Head OIC"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput v-model="form.head_oic_start_date" label="Head OIC start" density="compact" hide-details :max="form.head_oic_end_date || undefined" />
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput v-model="form.head_oic_end_date" label="Head OIC end" density="compact" hide-details :min="form.head_oic_start_date || undefined" />
            </v-col>
            <v-col cols="12"><div class="text-subtitle-2 mt-2">Director OIC</div></v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.director_oic_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Director OIC"
                placeholder="Search staff…"
                density="compact"
                hide-details="auto"
                clearable
                auto-select-first
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
              </v-autocomplete>
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput v-model="form.director_oic_start_date" label="Director OIC start" density="compact" hide-details :max="form.director_oic_end_date || undefined" />
            </v-col>
            <v-col cols="12" md="4">
              <UDateInput v-model="form.director_oic_end_date" label="Director OIC end" density="compact" hide-details :min="form.director_oic_start_date || undefined" />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialogOpen = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="onSave(false)">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.search-field-white :deep(.v-field) {
  background-color: #fff;
}
.search-field-white :deep(.v-field__overlay) {
  opacity: 0;
}
</style>
