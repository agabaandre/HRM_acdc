<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { statusColor } from '@/lib/devtrack'

type RequestDetail = {
  id: number
  reference: string
  title: string
  requesting_unit: string
  directorate: string | null
  requester_name: string
  requester_email: string | null
  phone: string | null
  problem_statement: string
  proposed_solution: string | null
  rationale: string | null
  stakeholders: string | null
  mandate_alignment: string | null
  urgency: string
  urgency_label: string
  desired_timeline: string | null
  budget_estimate: string | null
  existing_alternatives: string | null
  additional_comments: string | null
  data_classification: string
  data_classification_label: string
  work_class: string | null
  work_class_label: string | null
  priority_category: string | null
  priority_label: string | null
  weighted_score: number
  status: string
  status_label: string
  indicator: { id: number; code: string; title: string } | null
  workplan_indicator_id: number | null
  project: { id: number; code: string; name: string } | null
  acknowledged_by: string | null
  acknowledged_at: string | null
  created_at: string
  updated_at: string
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const message = ref('')
const item = ref<RequestDetail | null>(null)

function display(value: string | null | undefined): string {
  const text = (value ?? '').trim()
  return text === '' ? '—' : text
}

function isHtmlEmpty(value: string | null | undefined): boolean {
  if (!value) return true
  const plain = value
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .trim()
  return plain === ''
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get(`/admin/work-requests/${route.params.id}`)
    item.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load request.')
  } finally {
    loading.value = false
  }
}

async function acknowledge() {
  if (!item.value) return
  try {
    await api.post(`/admin/work-requests/${item.value.id}/acknowledge`)
    message.value = `${item.value.reference} acknowledged.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not acknowledge request.')
  }
}

async function convert() {
  if (!item.value) return
  try {
    const res = await api.post(`/admin/work-requests/${item.value.id}/convert`)
    message.value = `${item.value.reference} converted to ${res.data.project.code}.`
    await router.push({ name: 'project-detail', params: { id: res.data.project.id } })
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not convert request.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      :title="item?.title || 'Request'"
      :subtitle="item ? `${item.reference} · ${item.status_label}` : 'Software request details'"
    >
      <template #actions>
        <v-btn variant="text" :to="{ name: 'requests' }">Back</v-btn>
        <v-btn
          v-if="item"
          color="primary"
          :to="{ name: 'request-edit', params: { id: item.id } }"
        >
          Edit
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else-if="item">
      <ParentCard title="Summary" class="mb-4">
        <div class="d-flex flex-wrap ga-2 mb-4">
          <v-chip size="small" :color="statusColor(item.status)" variant="tonal">{{ item.status_label }}</v-chip>
          <v-chip v-if="item.work_class_label" size="small" variant="outlined">{{ item.work_class_label }}</v-chip>
          <v-chip v-if="item.urgency_label" size="small" variant="outlined">Priority: {{ item.urgency_label }}</v-chip>
          <v-chip v-if="item.priority_label" size="small" variant="outlined">{{ item.priority_label }}</v-chip>
        </div>
        <v-row dense>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Ref</div>
            <div class="text-body-1">{{ item.reference }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Work class</div>
            <div class="text-body-1">{{ display(item.work_class_label) }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Data classification</div>
            <div class="text-body-1">{{ display(item.data_classification_label) }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Workplan indicator</div>
            <div class="text-body-1">
              <template v-if="item.indicator">{{ item.indicator.code }} — {{ item.indicator.title }}</template>
              <template v-else>—</template>
            </div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Linked project</div>
            <div class="text-body-1">
              <router-link
                v-if="item.project"
                :to="{ name: 'project-detail', params: { id: item.project.id } }"
              >
                {{ item.project.code }} — {{ item.project.name }}
              </router-link>
              <template v-else>—</template>
            </div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Weighted score</div>
            <div class="text-body-1">{{ item.weighted_score }} / 100</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Created</div>
            <div class="text-body-1">{{ formatDate(item.created_at) }}</div>
          </v-col>
          <v-col cols="12" md="3">
            <div class="text-caption text-medium-emphasis">Acknowledged</div>
            <div class="text-body-1">
              <template v-if="item.acknowledged_at">
                {{ formatDate(item.acknowledged_at) }}
                <span v-if="item.acknowledged_by"> by {{ item.acknowledged_by }}</span>
              </template>
              <template v-else>—</template>
            </div>
          </v-col>
        </v-row>
        <div class="d-flex ga-2 mt-4">
          <v-btn
            v-if="item.status === 'submitted'"
            variant="tonal"
            @click="acknowledge"
          >
            Acknowledge
          </v-btn>
          <v-btn
            v-if="!item.project"
            color="primary"
            variant="tonal"
            @click="convert"
          >
            Convert to project
          </v-btn>
        </div>
      </ParentCard>

      <ParentCard title="Requester" class="mb-4">
        <v-row dense>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Requester name</div>
            <div class="text-body-1">{{ display(item.requester_name) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Division</div>
            <div class="text-body-1">{{ display(item.requesting_unit) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Directorate</div>
            <div class="text-body-1">{{ display(item.directorate) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Email</div>
            <div class="text-body-1">{{ display(item.requester_email) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Phone</div>
            <div class="text-body-1">{{ display(item.phone) }}</div>
          </v-col>
        </v-row>
      </ParentCard>

      <ParentCard title="Request details" class="mb-4">
        <v-row dense>
          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Request title</div>
            <div class="text-body-1">{{ display(item.title) }}</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Problem / need</div>
            <div
              v-if="!isHtmlEmpty(item.problem_statement)"
              class="rich-content text-body-1"
              v-html="item.problem_statement"
            />
            <div v-else class="text-body-1">—</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Proposed solution</div>
            <div
              v-if="!isHtmlEmpty(item.proposed_solution)"
              class="rich-content text-body-1"
              v-html="item.proposed_solution"
            />
            <div v-else class="text-body-1">—</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Why is this needed?</div>
            <div
              v-if="!isHtmlEmpty(item.rationale)"
              class="rich-content text-body-1"
              v-html="item.rationale"
            />
            <div v-else class="text-body-1">—</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Affected stakeholders</div>
            <div class="text-body-1 text-pre-wrap">{{ display(item.stakeholders) }}</div>
          </v-col>

          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Mandate alignment</div>
            <div class="text-body-1">{{ display(item.mandate_alignment) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Priority</div>
            <div class="text-body-1">{{ display(item.urgency_label) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Desired timeline</div>
            <div class="text-body-1">{{ display(item.desired_timeline) }}</div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-caption text-medium-emphasis">Budget estimate</div>
            <div class="text-body-1">{{ display(item.budget_estimate) }}</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Existing alternatives</div>
            <div class="text-body-1 text-pre-wrap">{{ display(item.existing_alternatives) }}</div>
          </v-col>

          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-1">Additional comments</div>
            <div class="text-body-1 text-pre-wrap">{{ display(item.additional_comments) }}</div>
          </v-col>
        </v-row>
      </ParentCard>
    </template>
  </div>
</template>

<style scoped>
.rich-content :deep(p) {
  margin: 0 0 0.5rem;
}
.rich-content :deep(p:last-child) {
  margin-bottom: 0;
}
.rich-content :deep(ul),
.rich-content :deep(ol) {
  padding-left: 1.25rem;
  margin: 0 0 0.5rem;
}
.text-pre-wrap {
  white-space: pre-wrap;
}
</style>
