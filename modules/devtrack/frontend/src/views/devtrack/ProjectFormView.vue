<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import type { CatalogOption, DevTrackOptions, StaffMember, WorkRequestRow } from '@/lib/devtrack'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => route.name === 'project-edit')
const id = computed(() => route.params.id as string | undefined)

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const options = ref<DevTrackOptions | null>(null)
const indicators = ref<Array<{ id: number; code: string; title: string }>>([])
const requests = ref<WorkRequestRow[]>([])
const staff = ref<StaffMember[]>([])
const namedRoles = computed(() => options.value?.named_roles ?? [])

const form = ref({
  name: '',
  description: '',
  work_class: 'medium_project',
  workplan_indicator_id: null as number | null,
  work_request_id: null as number | null,
  stage: 'intake',
  rag_status: 'green',
  rag_reason: '',
  priority_category: 'planned',
  status: 'not_started',
  start_date: '',
  target_end_date: '',
  sprint_goal: '',
  budget_status: '',
  resource_status: '',
  data_classification: 'internal',
  scope: '',
  success_measures: '',
  modules: [] as string[],
  roles: {} as Record<string, number | null>,
})

const indicatorItems = computed(() =>
  indicators.value.map((item) => ({ title: `${item.code} — ${item.title}`, value: item.id })),
)
const requestItems = computed(() =>
  requests.value
    .filter((item) => !item.project?.id || item.project.id === Number(id.value) || item.id === form.value.work_request_id)
    .map((item) => ({ title: `${item.reference} — ${item.title}`, value: item.id })),
)
const staffItems = computed(() =>
  [...staff.value]
    .map((item) => ({
      title: item.email ? `${item.name} (${item.email})` : item.name,
      value: item.id,
      name: item.name,
      email: item.email ?? '',
    }))
    .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })),
)

async function load() {
  loading.value = true
  try {
    const [optRes, indRes, staffRes, reqRes] = await Promise.all([
      api.get('/admin/devtrack/options'),
      api.get('/admin/workplan-indicators'),
      api.get('/admin/devtrack/staff'),
      api.get('/admin/work-requests'),
    ])
    options.value = optRes.data.data
    indicators.value = indRes.data.data
    staff.value = staffRes.data.data
    requests.value = reqRes.data.data
    for (const role of namedRoles.value) {
      form.value.roles[role.value] = form.value.roles[role.value] ?? null
    }
    if (isEdit.value && id.value) {
      const res = await api.get(`/admin/projects/${id.value}`)
      const p = res.data.data
      form.value = {
        name: p.name,
        description: p.description ?? '',
        work_class: p.work_class,
        workplan_indicator_id: p.indicator?.id ?? null,
        work_request_id: p.work_request?.id ?? p.work_request_id ?? null,
        stage: p.stage,
        rag_status: p.rag_status,
        rag_reason: p.rag_reason ?? '',
        priority_category: p.priority_category,
        status: p.status,
        start_date: p.start_date ?? '',
        target_end_date: p.target_end_date ?? '',
        sprint_goal: p.sprint_goal ?? '',
        budget_status: p.budget_status ?? '',
        resource_status: p.resource_status ?? '',
        data_classification: p.data_classification,
        scope: p.scope ?? '',
        success_measures: p.success_measures ?? '',
        modules: Array.isArray(p.modules) ? [...p.modules] : [],
        roles: Object.fromEntries(
          namedRoles.value.map((role) => {
            const slot = p.roles?.[role.value]
            return [role.value, slot?.staff?.id ?? slot?.staff_id ?? null]
          }),
        ),
      }
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the form.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.workplan_indicator_id) {
    error.value = 'Every project must be linked to a workplan indicator.'
    return
  }
  const modules = form.value.modules
    .map((item) => item.trim())
    .filter((item, index, all) => item !== '' && all.findIndex((value) => value.toLowerCase() === item.toLowerCase()) === index)
  if (modules.length === 0) {
    error.value = 'Add at least one module for this project.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      modules,
      work_request_id: form.value.work_request_id || null,
      start_date: form.value.start_date || null,
      target_end_date: form.value.target_end_date || null,
      roles: Object.entries(form.value.roles)
        .filter(([, staffId]) => !!staffId)
        .map(([role, staffId]) => ({ role, staff_id: staffId })),
    }
    if (isEdit.value && id.value) {
      await api.put(`/admin/projects/${id.value}`, payload)
      await router.push({ name: 'project-detail', params: { id: id.value } })
    } else {
      const res = await api.post('/admin/projects', payload)
      await router.push({ name: 'project-detail', params: { id: res.data.data.id } })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save the project.')
  } finally {
    saving.value = false
  }
}

watch(
  () => [route.name, route.params.id] as const,
  () => {
    void load()
  },
  { immediate: true },
)

function applyRequest(requestId: number | null) {
  const selected = requests.value.find((item) => item.id === requestId)
  if (!selected || isEdit.value) return
  if (!form.value.name) form.value.name = selected.title
  if (!form.value.workplan_indicator_id && selected.workplan_indicator_id) {
    form.value.workplan_indicator_id = selected.workplan_indicator_id
  }
  if (selected.work_class) form.value.work_class = selected.work_class
  if (!form.value.description && selected.problem_statement) {
    form.value.description = selected.problem_statement
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/\s+/g, ' ')
      .trim()
  }
}

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

function filterStaff(
  _itemTitle: string,
  queryText: string,
  item: { raw?: { title?: string; name?: string; email?: string } },
) {
  const q = queryText.trim().toLowerCase()
  if (!q) return true
  const raw = item.raw ?? {}
  const haystack = `${raw.title ?? ''} ${raw.name ?? ''} ${raw.email ?? ''}`.toLowerCase()
  return haystack.includes(q)
}
</script>

<template>
  <div>
    <PageHeader :title="isEdit ? 'Edit project' : 'New project'" subtitle="Named ownership, class, and a workplan indicator are required" />
    <ParentCard :title="isEdit ? 'Update register entry' : 'Register a project'">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-skeleton-loader v-if="loading" type="article" />
      <v-form v-else @submit.prevent="save">
        <v-row>
          <v-col cols="12" md="6">
            <FormField label="Project name" required>
              <v-text-field v-model="form.name" />
            </FormField>
          </v-col>
          <v-col cols="12" md="3">
            <FormField label="Req ref">
              <v-select
                v-model="form.work_request_id"
                :items="requestItems"
                clearable
                placeholder="Select request"
                @update:model-value="applyRequest"
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="3">
            <FormField label="Work class" required>
              <v-select v-model="form.work_class" :items="opts(options?.work_classes)" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Workplan indicator" required>
              <v-select v-model="form.workplan_indicator_id" :items="indicatorItems" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Description">
              <v-textarea v-model="form.description" rows="3" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Stage">
              <v-select v-model="form.stage" :items="opts(options?.project_stages)" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="RAG status">
              <v-select v-model="form.rag_status" :items="opts(options?.rag_statuses)" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Priority">
              <v-select v-model="form.priority_category" :items="opts(options?.priority_categories)" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="RAG reason">
              <v-textarea v-model="form.rag_reason" rows="2" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Start date">
              <v-text-field v-model="form.start_date" type="date" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Target end date">
              <v-text-field v-model="form.target_end_date" type="date" />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Data classification">
              <v-select v-model="form.data_classification" :items="opts(options?.data_classifications)" />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Current sprint goal">
              <v-text-field v-model="form.sprint_goal" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Status">
              <div class="d-flex flex-wrap ga-2">
                <v-btn
                  v-for="status in options?.project_statuses ?? []"
                  :key="status.value"
                  type="button"
                  size="small"
                  :variant="status.value === form.status ? 'flat' : 'tonal'"
                  :color="status.value === form.status ? 'primary' : undefined"
                  @click="form.status = status.value"
                >
                  {{ status.short_label || status.label }}
                </v-btn>
              </div>
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Resource status">
              <v-text-field v-model="form.resource_status" />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Budget status">
              <v-text-field v-model="form.budget_status" />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Scope">
              <v-textarea v-model="form.scope" rows="3" />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Success measures">
              <v-textarea v-model="form.success_measures" rows="3" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Modules" required>
              <v-combobox
                v-model="form.modules"
                multiple
                chips
                closable-chips
                clearable
                hide-details="auto"
                placeholder="Type a module name and press Enter"
                hint="Add at least one module. Press Enter after each module name."
                persistent-hint
              />
            </FormField>
          </v-col>
        </v-row>

        <h3 class="text-h6 mt-4 mb-3">Six named roles</h3>
        <p class="text-body-2 text-medium-emphasis mb-3">
          Pick each role from the Staff directory (synced from Staff Share).
        </p>
        <v-row>
          <v-col v-for="role in namedRoles" :key="role.value" cols="12" md="6">
            <FormField :label="role.label">
              <v-autocomplete
                v-model="form.roles[role.value]"
                :items="staffItems"
                item-title="title"
                item-value="value"
                clearable
                auto-select-first
                :placeholder="staffItems.length ? 'Search staff by name or email' : 'No staff synced yet'"
                :disabled="staffItems.length === 0"
                :custom-filter="filterStaff"
                no-data-text="No matching staff"
              />
            </FormField>
          </v-col>
        </v-row>

        <div class="d-flex ga-2">
          <v-btn color="primary" type="submit" :loading="saving">Save</v-btn>
          <v-btn
            variant="text"
            :to="isEdit && id ? { name: 'project-detail', params: { id } } : { name: 'projects' }"
          >
            Cancel
          </v-btn>
        </div>
      </v-form>
    </ParentCard>
  </div>
</template>
