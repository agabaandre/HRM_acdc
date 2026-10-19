<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import RichTextField from '@/components/forms/RichTextField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import type { CatalogOption, DevTrackOptions } from '@/lib/devtrack'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEdit = computed(() => route.name === 'request-edit')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const options = ref<DevTrackOptions | null>(null)
const indicators = ref<Array<{ id: number; code: string; title: string }>>([])
const reference = ref('')
const divisionLocked = ref(false)
const directorateLocked = ref(false)

const form = ref({
  title: '',
  requesting_unit: '',
  directorate: '',
  requester_name: '',
  requester_email: '',
  phone: '',
  problem_statement: '',
  proposed_solution: '',
  rationale: '',
  stakeholders: '',
  mandate_alignment: '',
  urgency: 'medium',
  desired_timeline: '',
  budget_estimate: '0',
  existing_alternatives: '',
  additional_comments: '',
  data_classification: 'internal',
  work_class: null as string | null,
  workplan_indicator_id: null as number | null,
})

function applyProfileDefaults() {
  const user = auth.user
  if (!user) return
  if (!form.value.requester_name) form.value.requester_name = user.name ?? ''
  if (!form.value.requester_email) form.value.requester_email = user.email ?? ''
  if (!form.value.phone) form.value.phone = user.phone ?? ''
  const division = user.division || user.organisation
  if (!form.value.requesting_unit && division) {
    form.value.requesting_unit = division
    divisionLocked.value = true
  }
  if (!form.value.directorate && user.directorate) {
    form.value.directorate = user.directorate
    directorateLocked.value = true
  } else {
    directorateLocked.value = Boolean(form.value.directorate)
  }
}

async function load() {
  loading.value = true
  try {
    if (!auth.user) {
      await auth.fetchMe()
    }
    const [opt, ind] = await Promise.all([
      api.get('/admin/devtrack/options'),
      api.get('/admin/workplan-indicators'),
    ])
    options.value = opt.data.data
    indicators.value = ind.data.data
    if (isEdit.value) {
      const res = await api.get(`/admin/work-requests/${route.params.id}`)
      const r = res.data.data
      reference.value = r.reference ?? ''
      Object.assign(form.value, {
        title: r.title ?? '',
        requesting_unit: r.requesting_unit ?? '',
        directorate: r.directorate ?? '',
        requester_name: r.requester_name ?? '',
        requester_email: r.requester_email ?? '',
        phone: r.phone ?? '',
        problem_statement: r.problem_statement ?? '',
        proposed_solution: r.proposed_solution ?? '',
        rationale: r.rationale ?? '',
        stakeholders: r.stakeholders ?? '',
        mandate_alignment: r.mandate_alignment ?? '',
        urgency: r.urgency ?? 'medium',
        desired_timeline: r.desired_timeline ?? '',
        budget_estimate: r.budget_estimate ?? '0',
        existing_alternatives: r.existing_alternatives ?? '',
        additional_comments: r.additional_comments ?? '',
        data_classification: r.data_classification ?? 'internal',
        work_class: r.work_class,
        workplan_indicator_id: r.workplan_indicator_id,
      })
      divisionLocked.value = true
      directorateLocked.value = true
    } else {
      applyProfileDefaults()
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load request.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      directorate: form.value.directorate || null,
      phone: form.value.phone || null,
      proposed_solution: form.value.proposed_solution || null,
      rationale: form.value.rationale || null,
      stakeholders: form.value.stakeholders || null,
      mandate_alignment: form.value.mandate_alignment || null,
      desired_timeline: form.value.desired_timeline || null,
      budget_estimate: form.value.budget_estimate || null,
      existing_alternatives: form.value.existing_alternatives || null,
      additional_comments: form.value.additional_comments || null,
    }
    if (isEdit.value) {
      await api.put(`/admin/work-requests/${route.params.id}`, payload)
      await router.push({ name: 'request-detail', params: { id: route.params.id } })
    } else {
      const res = await api.post('/admin/work-requests', payload)
      const id = res.data?.data?.id
      if (id) {
        await router.push({ name: 'request-detail', params: { id } })
      } else {
        await router.push({ name: 'requests' })
      }
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save request.')
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
    <PageHeader
      :title="isEdit ? 'Update software request' : 'Submit a software request'"
      subtitle="Capture demand before any development starts"
    />
    <ParentCard title="Request form">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-skeleton-loader v-if="loading" type="article" />
      <v-form v-else @submit.prevent="save">
        <p class="text-body-2 text-medium-emphasis mb-4">
          Your division and directorate are filled from the Staff directory / SSO profile and cannot be edited here.
        </p>

        <v-row>
          <v-col v-if="isEdit" cols="12" md="4">
            <FormField label="Ref">
              <v-text-field :model-value="reference" readonly variant="outlined" hide-details />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Requester name" required>
              <v-text-field v-model="form.requester_name" variant="outlined" hide-details />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Division" required>
              <v-text-field
                v-model="form.requesting_unit"
                :readonly="divisionLocked"
                variant="outlined"
                hide-details
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Directorate">
              <v-text-field
                :model-value="directorateLocked ? form.directorate || '—' : form.directorate"
                :readonly="directorateLocked"
                variant="outlined"
                hide-details
                @update:model-value="(v: string) => { if (!directorateLocked) form.directorate = v }"
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Email">
              <v-text-field v-model="form.requester_email" type="email" variant="outlined" hide-details />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Phone">
              <v-text-field v-model="form.phone" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Request title" required>
              <v-text-field v-model="form.title" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Problem / need" required>
              <RichTextField
                v-model="form.problem_statement"
                placeholder="Describe the problem or need..."
              />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Proposed solution">
              <RichTextField
                v-model="form.proposed_solution"
                placeholder="What solution or approach are you proposing?"
              />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Why is this needed?">
              <RichTextField
                v-model="form.rationale"
                placeholder="Why is this needed? What outcome or mandate does it support?"
              />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Affected stakeholders">
              <v-textarea v-model="form.stakeholders" rows="3" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12" md="4">
            <FormField label="Mandate alignment">
              <v-text-field v-model="form.mandate_alignment" variant="outlined" hide-details />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Priority">
              <v-select
                v-model="form.urgency"
                :items="opts(options?.urgency_levels)"
                clearable
                variant="outlined"
                hide-details
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Desired timeline">
              <v-text-field v-model="form.desired_timeline" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12" md="4">
            <FormField label="Budget estimate">
              <v-text-field v-model="form.budget_estimate" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Existing alternatives">
              <v-textarea v-model="form.existing_alternatives" rows="3" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12">
            <FormField label="Additional comments">
              <v-textarea v-model="form.additional_comments" rows="3" variant="outlined" hide-details />
            </FormField>
          </v-col>

          <v-col cols="12" md="4">
            <FormField label="Work class">
              <v-select
                v-model="form.work_class"
                :items="opts(options?.work_classes)"
                clearable
                variant="outlined"
                hide-details
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Data classification">
              <v-select
                v-model="form.data_classification"
                :items="opts(options?.data_classifications)"
                variant="outlined"
                hide-details
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="4">
            <FormField label="Workplan indicator">
              <v-select
                v-model="form.workplan_indicator_id"
                :items="indicators.map((i) => ({ title: `${i.code} — ${i.title}`, value: i.id }))"
                clearable
                variant="outlined"
                hide-details
              />
            </FormField>
          </v-col>
        </v-row>

        <div class="d-flex ga-2 mt-4">
          <v-btn color="primary" type="submit" :loading="saving">
            {{ isEdit ? 'Update request' : 'Submit request' }}
          </v-btn>
          <v-btn
            variant="text"
            :to="isEdit ? { name: 'request-detail', params: { id: route.params.id } } : { name: 'requests' }"
          >
            Cancel
          </v-btn>
        </div>
      </v-form>
    </ParentCard>
  </div>
</template>
