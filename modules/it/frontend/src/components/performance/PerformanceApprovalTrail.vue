<script setup lang="ts">
import { computed } from 'vue'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import PortalRichText from '@/components/atoms/PortalRichText.vue'
import PortalRichTextEditor from '@/components/atoms/PortalRichTextEditor.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { toAbsoluteMediaUrl } from '@/lib/personAvatar'
import { hasRichTextContent } from '@/lib/richText'
import type {
  PerformancePhase,
  PerformanceSubmissionWindow,
  PerformanceTrailEntry,
  PerformanceWorkflowState,
  PerformanceWorkflowTimelineStep,
} from '@/lib/performanceApi'

const props = withDefaults(
  defineProps<{
    phase: PerformancePhase
    submissionWindow: PerformanceSubmissionWindow | null
    state: PerformanceWorkflowState | null
    timeline: PerformanceWorkflowTimelineStep[]
    items: PerformanceTrailEntry[]
    canApprove: boolean
    canReturn: boolean
    returnLabel?: string
    canConsent: boolean
    comments: string
    supervisor2Agreement: boolean
    acceptRating: boolean
    busy?: boolean
    variant?: 'actions' | 'history'
    canSave?: boolean
    submissionComments?: string
    submissionCommentLabel?: string
  }>(),
  {
    busy: false,
    returnLabel: 'Return to employee',
    variant: 'actions',
    canSave: false,
    submissionComments: '',
    submissionCommentLabel: 'Comments for Submission',
  },
)

const emit = defineEmits<{
  'update:comments': [value: string]
  'update:supervisor2Agreement': [value: boolean]
  'update:acceptRating': [value: boolean]
  'update:submissionComments': [value: string]
  approve: []
  return: []
  consent: []
  'save-draft': []
  submit: []
}>()

const stateColor = computed(() => {
  switch (props.state?.status_key) {
    case 'approved':
      return 'success'
    case 'draft':
      return 'warning'
    default:
      return 'info'
  }
})

const showActionArea = computed(
  () => props.canApprove || props.canReturn || props.canConsent,
)

const showEmployeeSubmit = computed(
  () => props.variant === 'actions' && props.canSave,
)

const isApproved = computed(() => props.state?.status_key === 'approved')

const showSubmissionWindow = computed(
  () => props.variant === 'actions' && !!props.submissionWindow && !isApproved.value,
)

const showSecondSupervisorAgreement = computed(
  () => props.phase === 'endterm' && props.state?.step === 'supervisor_2' && props.canApprove,
)

const hasSubmissionComments = computed(() => hasRichTextContent(props.submissionComments))
const hasWorkflowComments = computed(() => hasRichTextContent(props.comments))

type TrailDisplayItem = {
  key: string
  staff_id: number | null
  staff_name: string
  action: string
  comments?: string | null
  created_at?: string | null
  photo_url?: string | null
  pending?: boolean
  step_label?: string
}

const displayItems = computed<TrailDisplayItem[]>(() => {
  // API returns newest-first; keep that order.
  const trail = props.items.map((item, index) => ({
    key: `trail-${item.staff_id}-${item.action}-${item.created_at || index}`,
    staff_id: item.staff_id,
    staff_name: item.staff_name?.trim() || `Staff ${item.staff_id}`,
    action: item.action,
    comments: item.comments,
    created_at: item.created_at,
    photo_url: item.photo_url,
    pending: false,
  }))

  const hasSubmission = trail.some((item) => /submit/i.test(item.action))
  const submitStep = props.timeline.find((step) => step.key === 'submit')

  // Ensure employee submission exists as the process baseline (oldest / bottom when newest-first).
  if (!hasSubmission && submitStep) {
    trail.push({
      key: 'timeline-submit',
      staff_id: null,
      staff_name: submitStep.actor || 'Employee',
      action: submitStep.status === 'done' || submitStep.status === 'current' ? 'Submitted' : 'Pending submission',
      comments: null,
      created_at: null,
      photo_url: null,
      pending: submitStep.status !== 'done',
    })
  }

  const current = props.timeline.find((step) => step.status === 'current')
  if (current && current.key !== 'submit' && current.key !== 'approved') {
    const alreadyShown = trail.some(
      (item) =>
        item.pending &&
        item.staff_name === (current.actor || '—') &&
        item.action.toLowerCase().includes(current.label.toLowerCase().slice(0, 8)),
    )
    if (!alreadyShown) {
      trail.unshift({
        key: `timeline-current-${current.key}`,
        staff_id: null,
        staff_name: current.actor || '—',
        action: current.hint || 'In progress',
        comments: null,
        created_at: null,
        photo_url: null,
        pending: true,
        step_label: current.label,
      })
    }
  }

  return trail
})

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}

function actionColor(action: string, pending?: boolean): string {
  if (pending) return 'primary'
  const a = action.toLowerCase()
  if (a.includes('approv') || a.includes('consent')) return 'success'
  if (a.includes('return') || a.includes('reject')) return 'error'
  if (a.includes('submit')) return 'info'
  return 'secondary'
}

function actionKind(action: string, pending?: boolean): 'pending' | 'approved' | 'returned' | 'submitted' | 'other' {
  if (pending) return 'pending'
  const a = action.toLowerCase()
  if (a.includes('approv') || a.includes('consent')) return 'approved'
  if (a.includes('return') || a.includes('reject')) return 'returned'
  if (a.includes('submit')) return 'submitted'
  return 'other'
}

function actionIcon(action: string, pending?: boolean): string {
  switch (actionKind(action, pending)) {
    case 'approved':
      return 'fa-solid fa-check'
    case 'returned':
      return 'fa-solid fa-rotate-left'
    case 'submitted':
      return 'fa-solid fa-clock'
    case 'pending':
      return 'fa-solid fa-ellipsis'
    default:
      return 'fa-solid fa-circle'
  }
}

function photoUrl(item: TrailDisplayItem): string | null {
  return toAbsoluteMediaUrl(resolveAvatarUrl(String(item.photo_url || '')))
}
</script>

<template>
  <v-card
    variant="outlined"
    class="perf-trail-card d-flex flex-column"
    :class="{
      'perf-trail-card--history': variant === 'history',
    }"
  >
    <div class="perf-trail-card__header flex-shrink-0">
      <div class="d-flex align-center justify-space-between ga-2 flex-wrap perf-trail-card__title">
        <span class="text-subtitle-1 font-weight-medium">
          <i
            class="me-2"
            :class="variant === 'history' ? 'fa-solid fa-clock-rotate-left' : 'fa-solid fa-route'"
            style="color: #119a48"
            aria-hidden="true"
          />
          {{ variant === 'history' ? 'Approval trail' : 'Workflow' }}
        </span>
        <v-chip
          v-if="variant === 'actions'"
          :color="stateColor"
          size="small"
          :variant="stateColor === 'warning' ? 'flat' : 'tonal'"
          class="perf-trail-card__state"
        >
          {{ state?.label || 'Not started' }}
        </v-chip>
      </div>
      <v-alert
        v-if="showSubmissionWindow"
        density="compact"
        :type="submissionWindow?.open ? 'success' : 'warning'"
        variant="tonal"
        class="perf-trail-card__window"
      >
        <strong>{{ submissionWindow?.label }}:</strong> {{ submissionWindow?.message }}
      </v-alert>
    </div>

    <v-card-text
      class="perf-trail-card__body d-flex flex-column ga-3"
      :class="{ 'flex-grow-1': variant === 'actions' }"
    >
      <div v-if="showEmployeeSubmit" class="d-flex flex-column ga-3 flex-shrink-0">
        <v-expansion-panels variant="accordion" class="perf-comment-panel">
          <v-expansion-panel>
            <v-expansion-panel-title>
              <span>{{ submissionCommentLabel }}</span>
              <v-chip
                v-if="hasSubmissionComments"
                size="x-small"
                color="success"
                variant="tonal"
                class="ms-2"
              >
                Added
              </v-chip>
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <PortalRichTextEditor
                :model-value="submissionComments"
                :placeholder="submissionCommentLabel"
                :min-rows="2"
                @update:model-value="emit('update:submissionComments', $event)"
              />
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
        <div class="d-flex flex-wrap ga-2">
          <v-btn color="warning" :loading="busy" @click="emit('save-draft')">Save Draft</v-btn>
          <v-btn color="success" :loading="busy" @click="emit('submit')">Submit</v-btn>
        </div>
      </div>

      <div v-if="variant === 'actions' && showActionArea" class="d-flex flex-column ga-3 flex-shrink-0">
        <v-expansion-panels variant="accordion" class="perf-comment-panel">
          <v-expansion-panel>
            <v-expansion-panel-title>
              <span>Comments</span>
              <v-chip
                v-if="hasWorkflowComments"
                size="x-small"
                color="success"
                variant="tonal"
                class="ms-2"
              >
                Added
              </v-chip>
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <PortalRichTextEditor
                :model-value="comments"
                placeholder="Comments"
                :min-rows="2"
                @update:model-value="emit('update:comments', $event)"
              />
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

        <v-checkbox
          v-if="showSecondSupervisorAgreement"
          :model-value="supervisor2Agreement"
          label="I agree with the first supervisor's assessment."
          hide-details
          density="compact"
          @update:model-value="emit('update:supervisor2Agreement', Boolean($event))"
        />

        <v-checkbox
          v-if="canConsent"
          :model-value="acceptRating"
          label="I accept the overall rating assigned by my first supervisor."
          hide-details
          density="compact"
          @update:model-value="emit('update:acceptRating', Boolean($event))"
        />

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            v-if="canConsent"
            color="success"
            size="small"
            :loading="busy"
            @click="emit('consent')"
          >
            Record consent
          </v-btn>
          <template v-else>
            <v-btn
              v-if="canApprove"
              color="success"
              size="small"
              :loading="busy"
              @click="emit('approve')"
            >
              Approve
            </v-btn>
            <v-btn
              v-if="canReturn"
              color="error"
              variant="tonal"
              size="small"
              :loading="busy"
              @click="emit('return')"
            >
              {{ returnLabel }}
            </v-btn>
          </template>
        </div>
      </div>

      <div
        v-else-if="variant === 'actions' && !showEmployeeSubmit"
        class="text-body-2 text-medium-emphasis"
      >
        No action is required from you at this step.
      </div>

      <div v-if="variant === 'history'" class="perf-trail-card__scroll">
        <div v-if="displayItems.length" class="perf-trail-list">
          <div
            v-for="item in displayItems"
            :key="item.key"
            class="perf-trail-item"
            :class="[`is-${actionKind(item.action, item.pending)}`, { 'is-pending': item.pending }]"
          >
            <div class="perf-trail-item__avatar">
              <CbpAvatar
                size="lg"
                :name="item.staff_name"
                :image-url="photoUrl(item)"
              />
            </div>
            <div
              class="perf-trail-item__badge"
              :class="`is-${actionKind(item.action, item.pending)}`"
            >
              <i :class="actionIcon(item.action, item.pending)" aria-hidden="true" />
            </div>
            <div class="perf-trail-item__body">
              <div class="perf-trail-item__time">{{ item.pending ? 'Pending' : formatDate(item.created_at) }}</div>
              <div class="perf-trail-item__title">
                <span>{{ item.staff_name }}</span>
                <v-chip
                  size="x-small"
                  :color="actionColor(item.action, item.pending)"
                  :variant="item.pending ? 'flat' : 'tonal'"
                >
                  {{ item.action }}
                </v-chip>
              </div>
              <div v-if="item.step_label" class="text-caption text-medium-emphasis">
                {{ item.step_label }}
              </div>
              <div
                v-if="item.comments"
                class="perf-trail-item__remarks"
              >
                <strong>Remarks:</strong>
                <PortalRichText
                  class="mt-1"
                  compact
                  :value="item.comments"
                />
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-body-2 text-medium-emphasis py-2">
          No approval activity yet. Employee submission will appear here once the form is submitted.
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
/* Override portal-fields overflow:visible so matched-height scroll does not clip the alert. */
.perf-trail-card {
  overflow: hidden !important;
  background: #fff;
}

.perf-trail-card__header {
  padding: 1.15rem 1rem 0.9rem;
  background: #fff;
  border-bottom: 1px solid rgba(58, 71, 82, 0.08);
}

.perf-trail-card__title {
  padding: 0;
  line-height: 1.35;
}

.perf-trail-card__state {
  font-weight: 700;
  letter-spacing: 0.02em;
}

.perf-trail-card__window {
  margin-top: 0.75rem;
  width: 100%;
}

.perf-comment-panel {
  border: 1px solid rgba(58, 71, 82, 0.12);
  border-radius: 0.55rem;
  overflow: hidden;
}

.perf-comment-panel :deep(.v-expansion-panel-title) {
  min-height: 2.6rem;
  padding: 0 0.9rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #3a4752;
}

.perf-comment-panel :deep(.v-expansion-panel-text__wrapper) {
  padding: 0 0.9rem 0.9rem;
}

.perf-trail-card__body {
  min-height: 0;
  overflow: hidden !important;
  padding-top: 0.85rem !important;
}

.perf-trail-card__scroll {
  flex: 1 1 auto;
  min-height: 0;
}

.perf-trail-card--history {
  overflow: visible !important;
}

.perf-trail-card--history .perf-trail-card__body {
  overflow: visible !important;
}

.perf-trail-list {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 0.15rem 0;
}

.perf-trail-list::before {
  content: '';
  position: absolute;
  left: 23px;
  top: 10px;
  bottom: 10px;
  width: 2px;
  background: #e9ecef;
}

.perf-trail-item {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  position: relative;
  margin: 0 0 1.85rem;
  padding: 0;
  border: none;
  background: transparent;
}

.perf-trail-item:last-child {
  margin-bottom: 0;
}

.perf-trail-item__avatar {
  flex: 0 0 48px;
  width: 48px;
  height: 48px;
  position: relative;
  z-index: 1;
  background: #fff;
  border-radius: 50%;
}

.perf-trail-item__badge {
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  margin-top: 12px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #119a48;
  color: #119a48;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  z-index: 1;
}

.perf-trail-item__badge.is-approved {
  border-color: #119a48;
  color: #119a48;
}

.perf-trail-item__badge.is-returned {
  border-color: #d97706;
  color: #d97706;
}

.perf-trail-item__badge.is-submitted {
  border-color: #0284c7;
  color: #0284c7;
}

.perf-trail-item__badge.is-pending,
.perf-trail-item__badge.is-other {
  border-color: #64748b;
  color: #64748b;
}

.perf-trail-item__body {
  flex: 1 1 auto;
  min-width: 0;
  padding-top: 0.1rem;
}

.perf-trail-item__time {
  font-size: 0.8rem;
  color: #888;
  margin-bottom: 0.15rem;
}

.perf-trail-item__title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem 0.55rem;
  font-weight: 600;
  color: #1f2933;
}

.perf-trail-item__remarks {
  margin-top: 0.55rem;
  padding: 0.55rem 0.7rem;
  background: #f8fafc;
  border-radius: 0.45rem;
  color: #455a64;
  font-size: 0.875rem;
}
</style>
