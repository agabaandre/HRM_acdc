<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  createLookupRow,
  deleteLookupRow,
  fetchLookupRows,
  updateLookupRow,
  type LookupColumnMeta,
} from '@/lib/settingsApi'

const route = useRoute()
const table = computed(() => String(route.params.table || ''))

const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const search = ref('')
const page = ref(1)

const rows = ref<Record<string, unknown>[]>([])
const meta = ref<{
  read_only: boolean
  label: string
  pk: string
  columns?: Record<string, LookupColumnMeta>
  last_page?: number
  total?: number
} | null>(null)

const editId = ref<string | number | null>(null)
const form = reactive<Record<string, unknown>>({})

const columns = computed(() => meta.value?.columns ?? {})
const columnKeys = computed(() => Object.keys(columns.value))
const pk = computed(() => meta.value?.pk ?? 'id')

function selectItems(cfg?: LookupColumnMeta) {
  return Object.entries(cfg?.options ?? {}).map(([value, title]) => ({ title, value }))
}

function defaultFormValue(cfg?: LookupColumnMeta) {
  if (cfg?.type === 'checkbox') return false
  if (cfg?.type === 'select') return Object.keys(cfg.options ?? {})[0] ?? ''
  return ''
}

function columnDisplay(col: string, value: unknown): string {
  const cfg = columns.value[col]
  if (cfg?.type === 'checkbox') return value ? 'Yes' : 'No'
  if (cfg?.type === 'select' && cfg.options) {
    const key = String(value ?? '')
    return cfg.options[key] ?? key
  }
  return value == null ? '' : String(value)
}

function resetForm() {
  editId.value = null
  Object.keys(form).forEach((k) => delete form[k])
  for (const [col, cfg] of Object.entries(columns.value)) {
    form[col] = defaultFormValue(cfg)
  }
}

function editRow(row: Record<string, unknown>) {
  editId.value = row[pk.value] as string | number
  for (const col of columnKeys.value) {
    const cfg = columns.value[col]
    if (cfg?.type === 'checkbox') {
      form[col] = !!row[col]
    } else {
      form[col] = row[col] ?? ''
    }
  }
}

async function load() {
  if (!table.value) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchLookupRows(table.value, {
      q: search.value || undefined,
      page: page.value,
      per_page: 20,
    })
    rows.value = res.data
    meta.value = res.meta
    if (!editId.value) resetForm()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load lookup table')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  if (meta.value?.read_only) return
  saving.value = true
  success.value = null
  error.value = null
  try {
    if (editId.value != null) {
      await updateLookupRow(table.value, editId.value, { ...form })
      success.value = 'Record updated.'
    } else {
      await createLookupRow(table.value, { ...form })
      success.value = 'Record created.'
    }
    resetForm()
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save record')
  } finally {
    saving.value = false
  }
}

async function onDelete(id: string | number) {
  if (!confirm('Delete this record?')) return
  try {
    await deleteLookupRow(table.value, id)
    success.value = 'Record deleted.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not delete record')
  }
}

watch(table, () => {
  page.value = 1
  search.value = ''
  void load()
})

watch([search, page], () => void load())

onMounted(() => void load())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-3">
      <CbpPageHeading
        :title="meta?.label || table"
        :subtitle="meta?.read_only ? 'Read-only list (advanced edits remain in CI3).' : 'Manage lookup values.'"
      />
      <RouterLink to="/settings" style="text-decoration:none">
        <v-btn variant="outlined" size="small">← Settings</v-btn>
      </RouterLink>
    </div>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-text-field
      v-model="search"
      label="Search"
      density="compact"
      clearable
      hide-details
      class="mb-4"
      style="max-width: 320px"
    />

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-row v-else>
      <v-col v-if="!meta?.read_only" cols="12" md="4">
        <v-card variant="outlined">
          <v-card-title>{{ editId != null ? 'Edit' : 'Add' }}</v-card-title>
          <v-card-text>
            <template v-for="col in columnKeys" :key="col">
              <v-checkbox
                v-if="columns[col]?.type === 'checkbox'"
                v-model="form[col]"
                :label="columns[col].label"
                hide-details
                class="mb-2"
              />
              <v-select
                v-else-if="columns[col]?.type === 'select'"
                v-model="form[col]"
                :items="selectItems(columns[col])"
                :label="columns[col].label"
                :required="!!columns[col].required"
                class="mb-2"
              />
              <v-text-field
                v-else
                v-model="form[col]"
                :type="columns[col]?.type === 'number' ? 'number' : 'text'"
                :label="columns[col].label"
                :required="!!columns[col].required"
                class="mb-2"
              />
            </template>
          </v-card-text>
          <v-card-actions>
            <v-btn color="primary" size="small" :loading="saving" @click="onSave">Save</v-btn>
            <v-btn v-if="editId != null" variant="text" size="small" @click="resetForm">Cancel</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>

      <v-col cols="12" :md="meta?.read_only ? 12 : 8">
        <v-card variant="outlined">
          <v-table>
            <thead>
              <tr>
                <th v-if="meta?.read_only">Order</th>
                <th v-if="meta?.read_only">System</th>
                <th v-if="meta?.read_only">Key</th>
                <th v-if="meta?.read_only">Enabled</th>
                <template v-else>
                  <th v-for="col in columnKeys" :key="col">{{ columns[col].label }}</th>
                  <th></th>
                </template>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in rows" :key="String(row[pk] ?? idx)">
                <template v-if="meta?.read_only">
                  <td>{{ row.sort_order }}</td>
                  <td>{{ row.system_name }}</td>
                  <td><code>{{ row.module_key }}</code></td>
                  <td>{{ row.is_enabled ? 'Yes' : 'No' }}</td>
                </template>
                <template v-else>
                  <td v-for="col in columnKeys" :key="col">
                    {{ columnDisplay(col, row[col]) }}
                  </td>
                  <td class="text-no-wrap">
                    <v-btn size="x-small" variant="outlined" class="me-1" @click="editRow(row)">Edit</v-btn>
                    <v-btn size="x-small" variant="text" color="error" @click="onDelete(row[pk] as string | number)">Delete</v-btn>
                  </td>
                </template>
              </tr>
              <tr v-if="!rows.length">
                <td :colspan="meta?.read_only ? 4 : columnKeys.length + 1" class="text-medium-emphasis text-center">
                  No records.
                </td>
              </tr>
            </tbody>
          </v-table>
          <v-card-actions v-if="(meta?.last_page || 1) > 1">
            <v-btn size="small" :disabled="page <= 1" @click="page--">Prev</v-btn>
            <span class="text-caption px-2">Page {{ page }} / {{ meta?.last_page }}</span>
            <v-btn size="small" :disabled="page >= (meta?.last_page || 1)" @click="page++">Next</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
