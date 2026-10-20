<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { statusColor, type CatalogOption, type DevTrackOptions, type ProjectSummary, type SprintRow } from '@/lib/devtrack'
import AppDataTable from '@/components/shared/AppDataTable.vue'

const items = ref<SprintRow[]>([])
const projects = ref<ProjectSummary[]>([])
const options = ref<DevTrackOptions | null>(null)
const loading = ref(true)
const error = ref('')
const dialog = ref(false)
const saving = ref(false)
const form = ref({
  project_id: null as number | null,
  name: '',
  goal: '',
  start_date: '',
  end_date: '',
  status: 'planned',
  committed_points: 0,
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [s, p, o] = await Promise.all([
      api.get('/admin/sprints'),
      api.get('/admin/projects'),
      api.get('/admin/devtrack/options'),
    ])
    items.value = s.data.data
    projects.value = p.data.data
    options.value = o.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load sprints.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    await api.post('/admin/sprints', { ...form.value, project_id: form.value.project_id || null })
    dialog.value = false
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not create sprint.')
  } finally {
    saving.value = false
  }
}

function opts(list?: CatalogOption[]) {
  return (list ?? []).map((item) => ({ title: item.label, value: item.value }))
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Sprints" subtitle="Two-week delivery cycles with a goal, capacity and committed tasks">
      <template #actions>
        <v-btn color="primary" @click="dialog = true">New sprint</v-btn>
      </template>
    </PageHeader>
    <ParentCard title="Sprint register">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Sprint', key: 'name' },
          { title: 'Project', key: 'project' },
          { title: 'Window', key: 'start_date' },
          { title: 'Status', key: 'status' },
          { title: 'Progress', key: 'progress_percent' },
        ]"
      >
        <template #item.project="{ item }">{{ item.project?.code || 'Team-wide' }}</template>
        <template #item.start_date="{ item }">{{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}</template>
        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(item.status)" variant="tonal">{{ item.status_label }}</v-chip>
        </template>
        <template #item.progress_percent="{ item }">
          {{ item.work_items_done }}/{{ item.work_items_count }} · {{ item.progress_percent }}%
        </template>
      </AppDataTable>
    </ParentCard>

    <v-dialog v-model="dialog" max-width="640">
      <v-card>
        <v-card-title>Plan a sprint</v-card-title>
        <v-card-text>
          <FormField label="Name" required><v-text-field v-model="form.name" /></FormField>
          <FormField label="Project">
            <v-select v-model="form.project_id" :items="projects.map((p) => ({ title: `${p.code} ${p.name}`, value: p.id }))" clearable />
          </FormField>
          <FormField label="Goal"><v-textarea v-model="form.goal" rows="2" /></FormField>
          <v-row>
            <v-col cols="12" md="6"><FormField label="Start"><v-text-field v-model="form.start_date" type="date" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="End"><v-text-field v-model="form.end_date" type="date" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Status"><v-select v-model="form.status" :items="opts(options?.sprint_statuses)" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Committed points"><v-text-field v-model.number="form.committed_points" type="number" /></FormField></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Create</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
