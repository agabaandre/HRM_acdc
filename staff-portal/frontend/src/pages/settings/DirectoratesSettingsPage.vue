<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { notifyApiError, toast } from '@/features/toast'
import {
  createDirectorate,
  deleteDirectorate,
  fetchDirectoratesSettings,
  fetchOrgStaffOptions,
  updateDirectorate,
  type DirectorateRow,
} from '@/lib/settingsApi'
import { downloadClientCsv, downloadClientExcel, fetchAllPages } from '@/lib/clientTableExport'

type StaffOpt = { title: string; value: number; email?: string | null }

const loading = ref(false)
const saving = ref(false)
const exporting = ref(false)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const rows = ref<DirectorateRow[]>([])
const staffOptions = ref<StaffOpt[]>([])
const editId = ref<number | null>(null)
const createExpanded = ref(false)

const form = reactive({
  name: '',
  is_active: true,
  director_id: null as number | null,
})

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

function resetForm() {
  editId.value = null
  form.name = ''
  form.is_active = true
  form.director_id = null
}

function openCreate() {
  resetForm()
  createExpanded.value = true
}

function editRow(row: DirectorateRow) {
  editId.value = row.id
  form.name = row.name || ''
  form.is_active = !!row.is_active
  form.director_id = row.director_id || null
  ensureStaffOption(row.director_id, row.director_name)
  createExpanded.value = true
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
    const res = await fetchDirectoratesSettings({
      q: search.value || undefined,
      page: page.value,
      per_page: 50,
    })
    rows.value = res.data
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
  } catch (e) {
    notifyApiError(e, 'Could not load directorates')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  if (!form.name.trim()) {
    toast.warning('Directorate name is required.', 'Directorates')
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      is_active: form.is_active,
      director_id: form.director_id,
    }
    const message =
      editId.value != null
        ? await updateDirectorate(editId.value, payload)
        : await createDirectorate(payload)
    toast.success(message || 'Directorate saved.', 'Saved')
    resetForm()
    createExpanded.value = false
    await load()
  } catch (e) {
    notifyApiError(e, 'Could not save directorate')
  } finally {
    saving.value = false
  }
}

async function onDelete(row: DirectorateRow) {
  if (!confirm(`Delete directorate “${row.name}”?`)) return
  try {
    const message = await deleteDirectorate(row.id)
    toast.success(message || 'Directorate deleted.', 'Deleted')
    if (editId.value === row.id) {
      resetForm()
      createExpanded.value = false
    }
    await load()
  } catch (e) {
    notifyApiError(e, 'Could not delete directorate')
  }
}

const exportHeaders = ['ID', 'Name', 'Director', 'Active']

function rowToExport(r: DirectorateRow): (string | number)[] {
  return [r.id, r.name, r.director_name ?? '', r.is_active ? 'Yes' : 'No']
}

async function loadAllForExport(): Promise<DirectorateRow[]> {
  return fetchAllPages(async (p, pp) => {
    const res = await fetchDirectoratesSettings({
      q: search.value || undefined,
      page: p,
      per_page: pp,
    })
    return { data: res.data, meta: { last_page: res.meta.last_page } }
  })
}

async function onExportCsv() {
  exporting.value = true
  try {
    const all = await loadAllForExport()
    downloadClientCsv('directorates.csv', exportHeaders, all.map(rowToExport))
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
    downloadClientExcel('directorates.xls', exportHeaders, all.map(rowToExport), 'Directorates')
    toast.success('Excel downloaded.', 'Export')
  } catch (e) {
    notifyApiError(e, 'Export failed')
  } finally {
    exporting.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    void load()
  }, 300)
})
watch(page, () => void load())

onMounted(async () => {
  await Promise.all([loadStaff(), load()])
})
</script>

<template>
  <div class="directorates-settings">
    <div class="d-flex justify-space-between align-center mb-3 flex-wrap ga-2">
      <CbpPageHeading
        title="Directorates"
        subtitle="Name, active status, and optional director — search staff by name or email."
      />
      <div class="d-flex flex-wrap ga-2">
        <RouterLink to="/settings" style="text-decoration: none">
          <v-btn variant="outlined" size="small">← Settings</v-btn>
        </RouterLink>
        <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openCreate">
          New directorate
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
      <v-card v-show="createExpanded" class="mb-4" variant="outlined">
        <v-card-title class="d-flex align-center text-subtitle-1">
          {{ editId != null ? 'Edit directorate' : 'New directorate' }}
          <v-spacer />
          <v-btn
            icon
            variant="text"
            size="small"
            aria-label="Collapse"
            @click="
              createExpanded = false;
              resetForm()
            "
          >
            <v-icon icon="mdi-chevron-up" />
          </v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="5">
              <v-text-field
                v-model="form.name"
                label="Directorate name *"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="form.is_active"
                :items="[
                  { title: 'Yes', value: true },
                  { title: 'No', value: false },
                ]"
                label="Is active?"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="form.director_id"
                :items="staffOptions"
                item-title="title"
                item-value="value"
                :custom-filter="staffFilter"
                label="Director"
                placeholder="Search staff by name or email…"
                density="compact"
                clearable
                auto-select-first
                hide-details="auto"
                class="bg-white"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item v-bind="itemProps" :subtitle="item.raw.email || undefined" />
                </template>
                <template #selection="{ item }">
                  <span>{{ item.title }}</span>
                </template>
              </v-autocomplete>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-btn
            v-if="editId != null"
            variant="text"
            @click="
              resetForm();
              createExpanded = false
            "
          >
            Cancel edit
          </v-btn>
          <v-spacer />
          <v-btn
            variant="text"
            @click="
              createExpanded = false;
              resetForm()
            "
          >
            Cancel
          </v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="onSave">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <v-card variant="outlined">
      <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
        <span>Directorates list</span>
        <v-text-field
          v-model="search"
          label="Search"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          hide-details
          clearable
          class="search-field-white"
          style="max-width: 280px"
        />
      </v-card-title>
      <div v-if="loading" class="text-medium-emphasis px-4 py-3">Loading…</div>
      <v-table v-else density="compact">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Director</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in rows" :key="row.id">
            <td class="text-medium-emphasis">{{ (page - 1) * 50 + i + 1 }}</td>
            <td>{{ row.name }}</td>
            <td>{{ row.director_name || '—' }}</td>
            <td>
              <v-chip size="x-small" :color="row.is_active ? 'success' : 'error'" variant="tonal">
                {{ row.is_active ? 'Active' : 'Inactive' }}
              </v-chip>
            </td>
            <td class="text-end text-no-wrap">
              <v-btn size="x-small" variant="text" @click="editRow(row)">Edit</v-btn>
              <v-btn size="x-small" variant="text" color="error" @click="onDelete(row)">Delete</v-btn>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="5" class="text-center text-medium-emphasis py-6">No directorates found.</td>
          </tr>
        </tbody>
      </v-table>
      <div v-if="lastPage > 1" class="d-flex align-center justify-space-between px-3 py-2">
        <span class="text-caption text-medium-emphasis">{{ total }} total</span>
        <div class="d-flex ga-2">
          <v-btn size="small" variant="text" :disabled="page <= 1" @click="page--">Prev</v-btn>
          <span class="text-caption align-self-center">Page {{ page }} / {{ lastPage }}</span>
          <v-btn size="small" variant="text" :disabled="page >= lastPage" @click="page++">Next</v-btn>
        </div>
      </div>
    </v-card>
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
