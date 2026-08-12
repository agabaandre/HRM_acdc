<script setup lang="ts">
import { computed } from 'vue'
import type {
  PerformancePhase,
  PerformanceSubmissionWindow,
  PerformanceWorkflowState,
  PerformanceWorkflowTimelineStep,
} from '@/lib/performanceApi'

const props = withDefaults(
  defineProps<{
    phase: PerformancePhase
    submissionWindow: PerformanceSubmissionWindow | null
    state: PerformanceWorkflowState | null
    timeline: PerformanceWorkflowTimelineStep[]
    canApprove: boolean
    canReturn: boolean
    canConsent: boolean
    comments: string
    supervisor2Agreement: boolean
    acceptRating: boolean
    busy?: boolean
  }>(),
  { busy: false },
)

const emit = defineEmits<{
  'update:comments': [value: string]
  'update:supervisor2Agreement': [value: boolean]
  'update:acceptRating': [value: boolean]
  approve: []
  return: []
  consent: []
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

const showSecondSupervisorAgreement = computed(
  () => props.phase === 'endterm' && props.state?.step === 'supervisor_2' && props.canApprove,
)
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="d-flex align-center justify-space-between ga-2">
      <span class="text-h6">Workflow</span>
      <v-chip :color="stateColor" size="small" variant="tonal">
        {{ state?.label || 'Not started' }}
      </v-chip>
    </v-card-title>
    <v-card-text class="d-flex flex-column ga-4">
      <v-alert
        v-if="submissionWindow"
        density="compact"
        :type="submissionWindow.open ? 'success' : 'warning'"
        variant="tonal"
      >
        <strong>{{ submissionWindow.label }}:</strong> {{ submissionWindow.message }}
      </v-alert>

      <div v-if="timeline.length" class="d-flex flex-column ga-3">
        <div
          v-for="step in timeline"
          :key="step.key"
          class="perf-step"
        >
          <div class="d-flex align-center justify-space-between ga-2">
            <div>
              <div class="font-weight-medium">{{ step.label }}</div>
              <div class="text-caption text-medium-emphasis">{{ step.actor }}</div>
            </div>
            <v-chip
              :color="step.status === 'done' ? 'success' : step.status === 'current' ? 'primary' : 'default'"
              size="x-small"
              variant="tonal"
            >
              {{ step.hint }}
            </v-chip>
          </div>
        </div>
      </div>
      <div v-else class="text-body-2 text-medium-emphasis">No workflow activity yet.</div>

      <div v-if="showActionArea" class="d-flex flex-column ga-3">
        <v-textarea
          :model-value="comments"
          label="Comments"
          rows="3"
          auto-grow
          variant="outlined"
          @update:model-value="emit('update:comments', String($event ?? ''))"
        />

        <v-checkbox
          v-if="showSecondSupervisorAgreement"
          :model-value="supervisor2Agreement"
          label="I agree with the first supervisor's assessment."
          hide-details
          @update:model-value="emit('update:supervisor2Agreement', Boolean($event))"
        />

        <v-checkbox
          v-if="canConsent"
          :model-value="acceptRating"
          label="I accept the end-of-year rating."
          hide-details
          @update:model-value="emit('update:acceptRating', Boolean($event))"
        />

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            v-if="canConsent"
            color="success"
            :loading="busy"
            @click="emit('consent')"
          >
            Record consent
          </v-btn>
          <template v-else>
            <v-btn
              v-if="canApprove"
              color="success"
              :loading="busy"
              @click="emit('approve')"
            >
              Approve
            </v-btn>
            <v-btn
              v-if="canReturn"
              color="error"
              variant="tonal"
              :loading="busy"
              @click="emit('return')"
            >
              Return
            </v-btn>
          </template>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.perf-step {
  border-left: 3px solid rgba(17, 154, 72, 0.18);
  padding-left: 0.9rem;
}
</style>
