<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import {
  type CatalogOption,
  type DevTrackOptions,
  type ProjectModuleRow,
  type StaffMember,
} from '@/lib/devtrack'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const options = ref<DevTrackOptions | null>(null)
const staff = ref<StaffMember[]>([])
const modules = ref<ProjectModuleRow[]>([])
const parentProject = ref<{ id: number; code: string; name: string } | null>(null)

const isEdit = computed(() => route.name === 'enhancement-edit')
const projectId = computed(() => Number(route.params.id || parentProject.value?.id || 0))
const enhancementId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const form = ref({
  project_id: 0,
  project_module_id: null as number | null,
  name: '',
  work_type: 'feature_improvement',
  description: '',
  scope: '',
  requesting_division: '',
  business_owner_staff_id: null as number | null,
  responsible_developer_staff_id: null as number | null,
  priority: 'medium',
  planned_start: '',
  due_date: '',
  status: 'not_started',
  acceptance_criteria: '',
})

const staffItems = computed(() =>
  [...staff.value]
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((item) => ({ title: `${item.name}${item.email ? ` · ${item.email}` : ''}`, value: item.id })),
)

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [optRes, staffRes] = await Promise.all([
      api.get('/admin/devtrack/options'),
      api.get('/admin/devtrack/staff'),
    ])
    options.value = optRes.data.data
    staff.value = staffRes.data.data

    if (isEdit.value && enhancementId.value) {
      const res = await api.get(`/admin/enhancements/${enhancementId.value}`)
      const e = res.data.data
      parentProject.value = e.project
      form.value = {
        project_id: e.project_id,
        project_module_id: e.project_module_id,
        name: e.name,
        work_type: e.work_type,
        description: e.description ?? '',
        scope: e.scope ?? '',
        requesting_division: e.requesting_division ?? '',
        business_owner_staff_id: e.business_owner_staff_id,
        responsible_developer_staff_id: e.responsible_developer_staff_id,
        priority: e.priority,
        planned_start: e.planned_start ?? '',
        due_date: e.due_date ?? '',
        status: e.status,
        acceptance_criteria: e.acceptance_criteria ?? '',
      }
      const modRes = await api.get('/admin/project-modules', { params: { project_id: e.project_id } })
      modules.value = modRes.data.data
    } else {
      const pid = Number(route.params.id)
      const projRes = await api.get(`/admin/projects/${pid}`)
      parentProject.value = { id: projRes.data.data.id, code: projRes.data.data.code, name: projRes.data.data.name }
      form.value.project_id = pid
      const modRes = await api.get('/admin/project-modules', { params: { project_id: pid } })
      modules.value = modRes.data.data
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the enhancement form.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.name.trim()) {
    error.value = 'Module/enhancement name is required.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      project_module_id: form.value.project_module_id || null,
      planned_start: form.value.planned_start || null,
      due_date: form.value.due_date || null,
      business_owner_staff_id: form.value.business_owner_staff_id || null,
      responsible_developer_staff_id: form.value.responsible_developer_staff_id || null,
    }
    if (isEdit.value && enhancementId.value) {
      await api.put(`/admin/enhancements/${enhancementId.value}`, payload)
      await router.push({ name: 'enhancement-detail', params: { id: String(enhancementId.value) } })
    } else {
      const res = await api.post('/admin/enhancements', payload)
      await router.push({ name: 'enhancement-detail', params: { id: String(res.data.data.id) } })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save the enhancement.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="isEdit ? 'Edit enhancement' : 'Add enhancement'"
      :subtitle="parentProject ? `${parentProject.code} · ${parentProject.name}` : 'Linked to parent project'"
    >
      <template #actions>
        <v-btn
          variant="tonal"
          :to="parentProject ? { name: 'project-detail', params: { id: String(parentProject.id) } } : { name: 'projects' }"
        >
          Back to project
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-skeleton-loader v-if="loading" type="article" />

    <ParentCard v-else title="Enhancement details">
      <v-row>
        <v-col cols="12" md="6">
          <FormField label="Module / enhancement name" required>
            <v-text-field v-model="form.name" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Work type" required>
            <v-select v-model="form.work_type" :items="opts(options?.enhancement_work_types)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Linked module">
            <v-select
              v-model="form.project_module_id"
              :items="modules.map((m) => ({ title: `${m.code} · ${m.name}`, value: m.id }))"
              clearable
              placeholder="Optional"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Priority">
            <v-select v-model="form.priority" :items="opts(options?.work_item_priorities)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Status">
            <v-select v-model="form.status" :items="opts(options?.enhancement_statuses)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Requesting division / business owner unit">
            <v-text-field v-model="form.requesting_division" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Business owner">
            <v-autocomplete
              v-model="form.business_owner_staff_id"
              :items="staffItems"
              clearable
              placeholder="Select from staff directory"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Responsible developer / lead">
            <v-autocomplete
              v-model="form.responsible_developer_staff_id"
              :items="staffItems"
              clearable
              placeholder="Select from staff directory"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Planned start">
            <v-text-field v-model="form.planned_start" type="date" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Due date">
            <v-text-field v-model="form.due_date" type="date" />
          </FormField>
        </v-col>
        <v-col cols="12">
          <FormField label="Description and scope">
            <v-textarea v-model="form.description" rows="3" placeholder="What is included" />
          </FormField>
        </v-col>
        <v-col cols="12">
          <FormField label="Scope notes (included / excluded)">
            <v-textarea v-model="form.scope" rows="3" />
          </FormField>
        </v-col>
        <v-col cols="12">
          <FormField label="Acceptance criteria">
            <v-textarea v-model="form.acceptance_criteria" rows="3" placeholder="What must work before acceptance" />
          </FormField>
        </v-col>
      </v-row>
      <div class="d-flex ga-2 mt-4">
        <v-btn color="primary" :loading="saving" @click="save">Save enhancement</v-btn>
        <v-btn
          variant="tonal"
          :to="parentProject ? { name: 'project-detail', params: { id: String(parentProject.id) } } : { name: 'projects' }"
        >
          Cancel
        </v-btn>
      </div>
    </ParentCard>
  </div>
</template>
