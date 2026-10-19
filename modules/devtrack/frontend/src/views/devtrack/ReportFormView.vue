<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import type { CatalogOption, DevTrackOptions, ProjectSummary, StatusReportRow } from '@/lib/devtrack'

const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.name === 'report-new')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const options = ref<DevTrackOptions | null>(null)
const projects = ref<ProjectSummary[]>([])
const report = ref<StatusReportRow | null>(null)

const form = ref({
  project_id: route.query.project_id ? Number(route.query.project_id) : null as number | null,
  period: 'weekly',
  period_start: '',
  period_end: '',
  title: '',
  rag_status: 'green',
  summary: '',
  accomplishments: '',
  next_period_plan: '',
  blockers: '',
  decisions_required: '',
})

async function load() {
  loading.value = true
  try {
    const [opt, p] = await Promise.all([api.get('/admin/devtrack/options'), api.get('/admin/projects')])
    options.value = opt.data.data
    projects.value = p.data.data
    if (!isNew.value) {
      const res = await api.get(`/admin/status-reports/${route.params.id}`)
      report.value = res.data.data
      Object.assign(form.value, {
        project_id: res.data.data.project_id,
        period: res.data.data.period,
        period_start: res.data.data.period_start,
        period_end: res.data.data.period_end,
        title: res.data.data.title,
        rag_status: res.data.data.rag_status,
        summary: res.data.data.summary,
        accomplishments: res.data.data.accomplishments,
        next_period_plan: res.data.data.next_period_plan,
        blockers: res.data.data.blockers,
        decisions_required: res.data.data.decisions_required,
      })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load report.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form.value, project_id: form.value.project_id || null }
    if (isNew.value) {
      const res = await api.post('/admin/status-reports', payload)
      await router.push({ name: 'report-detail', params: { id: res.data.data.id } })
    } else {
      await api.put(`/admin/status-reports/${route.params.id}`, payload)
      await load()
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save report.')
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
    <PageHeader :title="isNew ? 'New status report' : report?.title || 'Report'" :subtitle="isNew ? 'Weekly, monthly or quarterly' : report?.period_label" />
    <ParentCard :title="isNew ? 'Write report' : 'Report'">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-skeleton-loader v-if="loading" type="article" />
      <template v-else>
        <div v-if="!isNew && report" class="mb-6">
          <RagChip :status="report.rag_status" :label="report.rag_label" />
          <p class="text-body-2 mt-3">{{ formatDate(report.period_start) }} – {{ formatDate(report.period_end) }} · {{ report.project?.name || 'All projects' }}</p>
        </div>
        <v-form @submit.prevent="save">
          <v-row>
            <v-col cols="12"><FormField label="Title" required><v-text-field v-model="form.title" /></FormField></v-col>
            <v-col cols="12" md="4">
              <FormField label="Cadence">
                <v-select v-model="form.period" :items="opts(options?.report_periods)" />
              </FormField>
            </v-col>
            <v-col cols="12" md="4"><FormField label="Period start"><v-text-field v-model="form.period_start" type="date" /></FormField></v-col>
            <v-col cols="12" md="4"><FormField label="Period end"><v-text-field v-model="form.period_end" type="date" /></FormField></v-col>
            <v-col cols="12" md="8">
              <FormField label="Coverage">
                <v-select
                  v-model="form.project_id"
                  :items="[{ title: 'All projects', value: null }, ...projects.map((p) => ({ title: `${p.code} ${p.name}`, value: p.id }))]"
                />
              </FormField>
            </v-col>
            <v-col cols="12" md="4">
              <FormField label="RAG">
                <v-select v-model="form.rag_status" :items="opts(options?.rag_statuses)" />
              </FormField>
            </v-col>
            <v-col cols="12"><FormField label="Summary"><v-textarea v-model="form.summary" rows="3" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Accomplishments"><v-textarea v-model="form.accomplishments" rows="4" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Next period plan"><v-textarea v-model="form.next_period_plan" rows="4" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Blockers"><v-textarea v-model="form.blockers" rows="3" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Decisions required from management"><v-textarea v-model="form.decisions_required" rows="3" /></FormField></v-col>
          </v-row>
          <v-btn color="primary" type="submit" :loading="saving">{{ isNew ? 'Submit report' : 'Update report' }}</v-btn>
        </v-form>
      </template>
    </ParentCard>
  </div>
</template>
