<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { type CatalogOption, type DevTrackOptions, type StaffMember } from '@/lib/devtrack'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const options = ref<DevTrackOptions | null>(null)
const staff = ref<StaffMember[]>([])
const parentProject = ref<{ id: number; code: string; name: string } | null>(null)

const isEdit = computed(() => route.name === 'module-edit')
const moduleId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const form = ref({
  project_id: 0,
  name: '',
  description: '',
  work_class: 'medium_project',
  stage: 'intake',
  rag_status: 'green',
  rag_reason: '',
  priority_category: 'planned',
  status: 'not_started',
  requesting_division: '',
  business_owner_staff_id: null as number | null,
  responsible_developer_staff_id: null as number | null,
  start_date: '',
  target_end_date: '',
  sprint_goal: '',
  budget_status: '',
  resource_status: '',
  data_classification: 'internal',
  scope: '',
  success_measures: '',
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

    if (isEdit.value && moduleId.value) {
      const res = await api.get(`/admin/project-modules/${moduleId.value}`)
      const m = res.data.data
      parentProject.value = m.project
      form.value = {
        project_id: m.project_id,
        name: m.name,
        description: m.description ?? '',
        work_class: m.work_class || 'medium_project',
        stage: m.stage || 'intake',
        rag_status: m.rag_status || 'green',
        rag_reason: m.rag_reason ?? '',
        priority_category: m.priority_category || 'planned',
        status: m.status || 'not_started',
        requesting_division: m.requesting_division ?? '',
        business_owner_staff_id: m.business_owner_staff_id,
        responsible_developer_staff_id: m.responsible_developer_staff_id,
        start_date: m.start_date ?? '',
        target_end_date: m.target_end_date ?? '',
        sprint_goal: m.sprint_goal ?? '',
        budget_status: m.budget_status ?? '',
        resource_status: m.resource_status ?? '',
        data_classification: m.data_classification || 'internal',
        scope: m.scope ?? '',
        success_measures: m.success_measures ?? '',
      }
    } else {
      const pid = Number(route.params.id)
      const projRes = await api.get(`/admin/projects/${pid}`)
      parentProject.value = { id: projRes.data.data.id, code: projRes.data.data.code, name: projRes.data.data.name }
      form.value.project_id = pid
      form.value.work_class = projRes.data.data.work_class || 'medium_project'
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load the module form.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.name.trim()) {
    error.value = 'Module name is required.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      start_date: form.value.start_date || null,
      target_end_date: form.value.target_end_date || null,
      business_owner_staff_id: form.value.business_owner_staff_id || null,
      responsible_developer_staff_id: form.value.responsible_developer_staff_id || null,
    }
    if (isEdit.value && moduleId.value) {
      await api.put(`/admin/project-modules/${moduleId.value}`, payload)
      await router.push({ name: 'module-detail', params: { id: String(moduleId.value) } })
    } else {
      const res = await api.post('/admin/project-modules', payload)
      await router.push({ name: 'module-detail', params: { id: String(res.data.data.id) } })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save the module.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="isEdit ? 'Edit module' : 'Add module'"
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

    <ParentCard v-else title="Module register entry">
      <v-row>
        <v-col cols="12" md="6">
          <FormField label="Module name" required>
            <v-text-field v-model="form.name" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Work class">
            <v-select v-model="form.work_class" :items="opts(options?.work_classes)" />
          </FormField>
        </v-col>
        <v-col cols="12">
          <FormField label="Description">
            <v-textarea v-model="form.description" rows="2" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Stage">
            <v-select v-model="form.stage" :items="opts(options?.project_stages)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Status">
            <v-select v-model="form.status" :items="opts(options?.module_statuses)" />
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
              hide-details="auto"
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
              hide-details="auto"
              placeholder="Select from staff directory"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="RAG status">
            <v-select v-model="form.rag_status" :items="opts(options?.rag_statuses)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Priority">
            <v-select v-model="form.priority_category" :items="opts(options?.priority_categories)" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Data classification">
            <v-select v-model="form.data_classification" :items="opts(options?.data_classifications)" />
          </FormField>
        </v-col>
        <v-col cols="12">
          <FormField label="RAG reason">
            <v-textarea v-model="form.rag_reason" rows="2" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Start date">
            <v-text-field v-model="form.start_date" type="date" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Target end date">
            <v-text-field v-model="form.target_end_date" type="date" />
          </FormField>
        </v-col>
        <v-col cols="12" md="6">
          <FormField label="Sprint goal">
            <v-text-field v-model="form.sprint_goal" />
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
      </v-row>
      <div class="d-flex ga-2 mt-4">
        <v-btn color="primary" :loading="saving" @click="save">Save module</v-btn>
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
