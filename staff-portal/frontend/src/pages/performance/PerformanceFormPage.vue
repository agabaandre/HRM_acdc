<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import PerformanceApprovalTrail from '@/components/performance/PerformanceApprovalTrail.vue'
import PerformanceStaffDetailsCard from '@/components/performance/PerformanceStaffDetailsCard.vue'
import PpaSections from '@/components/performance/PpaSections.vue'
import ReviewSections from '@/components/performance/ReviewSections.vue'
import { openApiPdf } from '@/lib/exportDownload'
import { hasRichTextContent } from '@/lib/richText'
import { useLocaleStore } from '@/stores/locale'
import {
  createPerformanceEntry,
  fetchPerformanceEntry,
  fetchPerformanceHub,
  type PerformanceFormPayload,
  type PerformanceFormState,
  type PerformanceObjective,
  type PerformancePhase,
  approvePerformanceEntry,
  consentPerformanceEntry,
  returnPerformanceEntry,
  savePerformanceDraft,
  submitPerformanceEntry,
  updatePerformanceSupervisors,
} from '@/lib/performanceApi'

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()

const loading = ref(false)
const busy = ref(false)
const pdfLoading = ref(false)
const includeTrail = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const payload = ref<PerformanceFormPayload | null>(null)
const form = ref<PerformanceFormState | null>(null)
const workflowComments = ref('')
const supervisor2Agreement = ref(true)
const acceptRating = ref(true)

let latestLoadId = 0

const isCreate = computed(() => route.name === 'performance-create')
const activePhase = computed<PerformancePhase>(() => {
  const raw = String(route.params.phase || 'ppa')
  if (raw === 'midterm' || raw === 'endterm') {
    return raw
  }
  return 'ppa'
})

const title = computed(() => {
  if (isCreate.value) {
    return 'Create PPA'
  }
  if (activePhase.value === 'midterm') {
    return 'Midterm review'
  }
  if (activePhase.value === 'endterm') {
    return 'End-of-year review'
  }
  return 'PPA form'
})

const activeReadonly = computed(() => {
  if (!payload.value) {
    return ''
  }
  if (activePhase.value === 'midterm') {
    return payload.value.midreadonly
  }
  if (activePhase.value === 'endterm') {
    return payload.value.endreadonly
  }
  return payload.value.readonly
})

const readonly = computed(() => activeReadonly.value.includes('readonly'))
const canSave = computed(() => !!payload.value?.can_save && !readonly.value)
const canApprove = computed(() => !!payload.value?.can_approve)
const canReturn = computed(() => !!payload.value?.can_return)
const returnLabel = computed(() =>
  payload.value?.return_target === 'draft' ? 'Return to draft' : 'Return to employee',
)
const canConsent = computed(() => !!payload.value?.can_consent)
const canChangeSupervisors = computed(() => !!payload.value?.can_change_supervisors)
const showReviewGate = computed(
  () => activePhase.value !== 'ppa' && !!payload.value && !payload.value.ppa_approved,
)
const isPersisted = computed(() => !!payload.value?.workflow.state)
const currentPeriod = computed(() =>
  String(route.query.period || payload.value?.entry.performance_period || ''),
)
const submissionCommentLabel = computed(() =>
  activePhase.value === 'ppa' ? 'Comments for Approval' : 'Comments for Submission',
)

const phaseTabs = computed<PortalPillNavItem[]>(() => {
  if (!payload.value || !isPersisted.value) {
    return []
  }

  const baseParams = {
    entryId: payload.value.entry.entry_id,
    staffId: String(payload.value.entry.staff_id),
  }

  const tabs: PortalPillNavItem[] = [
    {
      key: 'ppa',
      label: locale.t('subnav.ppa', 'PPA'),
      icon: 'fa-solid fa-flag',
      to: { name: 'performance-form', params: { ...baseParams, phase: 'ppa' as const } },
      active: activePhase.value === 'ppa',
    },
  ]

  if (payload.value.midterm_exists || activePhase.value === 'midterm') {
    tabs.push({
      key: 'midterm',
      label: locale.t('subnav.midterm', 'Midterm'),
      icon: 'fa-solid fa-chart-simple',
      to: { name: 'performance-form', params: { ...baseParams, phase: 'midterm' as const } },
      active: activePhase.value === 'midterm',
    })
  }

  if (payload.value.endterm_exists || activePhase.value === 'endterm') {
    tabs.push({
      key: 'endterm',
      label: locale.t('subnav.endterm', 'Endterm'),
      icon: 'fa-solid fa-flag-checkered',
      to: { name: 'performance-form', params: { ...baseParams, phase: 'endterm' as const } },
      active: activePhase.value === 'endterm',
    })
  }

  return tabs
})

const submissionComments = computed({
  get: () => {
    if (!form.value) {
      return ''
    }
    if (activePhase.value === 'midterm') {
      return form.value.midterm_comments
    }
    if (activePhase.value === 'endterm') {
      return form.value.endterm_comments
    }
    return form.value.comments
  },
  set: (value: string) => {
    if (!form.value) {
      return
    }
    if (activePhase.value === 'midterm') {
      form.value.midterm_comments = value
      return
    }
    if (activePhase.value === 'endterm') {
      form.value.endterm_comments = value
      return
    }
    form.value.comments = value
  },
})

function cloneFormState(source: PerformanceFormState): PerformanceFormState {
  return JSON.parse(JSON.stringify(source)) as PerformanceFormState
}

function blankObjective(): PerformanceObjective {
  return {
    objective: '',
    timeline: '',
    indicator: '',
    weight: '',
    self_appraisal: '',
    appraiser_rating: '',
  }
}

function normalizeObjectives(
  objectives: Record<number, PerformanceObjective>,
  count: number,
  periodEndYear: number,
  phase: PerformancePhase,
): Record<number, PerformanceObjective> {
  const next: Record<number, PerformanceObjective> = {}
  const seedDate = `${periodEndYear}-12-31`

  for (let index = 1; index <= count; index += 1) {
    const current = objectives[index] ?? objectives[String(index) as unknown as number] ?? blankObjective()
    next[index] = {
      ...blankObjective(),
      ...current,
    }
    if (phase === 'ppa' && index <= 3) {
      if (!next[index].timeline) {
        next[index].timeline = seedDate
      }
      if (next[index].weight === '') {
        next[index].weight = 0
      }
    }
  }

  return next
}

function hydrateFormState(next: PerformanceFormPayload): PerformanceFormState {
  const draft = cloneFormState(next.form)
  draft.objectives = normalizeObjectives(
    draft.objectives,
    next.phase === 'ppa' ? 5 : 10,
    next.period_end_year,
    next.phase,
  )
  return draft
}

function formRouteLocation(next: PerformanceFormPayload) {
  return {
    name: 'performance-form',
    params: {
      phase: next.phase,
      entryId: next.entry.entry_id,
      staffId: String(next.entry.staff_id),
    },
  }
}

function createRouteLocation(period: string) {
  return {
    name: 'performance-create',
    query: period ? { period } : {},
  }
}

function applyPayload(next: PerformanceFormPayload): void {
  payload.value = next
  form.value = hydrateFormState(next)
  workflowComments.value = ''
  supervisor2Agreement.value = true
  acceptRating.value = true
}

function ppaSubmitErrors(): string[] {
  if (!form.value) {
    return []
  }

  const errors: string[] = []
  let total = 0

  for (let index = 1; index <= 5; index += 1) {
    const row = form.value.objectives[index] ?? blankObjective()
    const weight = Number.parseFloat(String(row.weight || 0))
    total += Number.isFinite(weight) ? weight : 0

    if (index <= 3) {
      if (!hasRichTextContent(row.objective)) {
        errors.push(`Objective ${index} is required.`)
      }
      if (!row.timeline.trim()) {
        errors.push(`Timeline ${index} is required.`)
      }
      if (!hasRichTextContent(row.indicator)) {
        errors.push(`Deliverables and KPI's ${index} are required.`)
      }
    }
  }

  if (Math.abs(total - 100) >= 0.01) {
    errors.push(`Objective weights must add up to 100%. Current total: ${total}%.`)
  }

  return errors
}

async function load(): Promise<void> {
  const loadId = ++latestLoadId
  loading.value = true
  error.value = null

  try {
    if (isCreate.value) {
      let period = String(route.query.period || '')
      if (!period) {
        const hub = await fetchPerformanceHub()
        period = hub.period
      }

      const next = await createPerformanceEntry({ period })
      if (loadId !== latestLoadId) {
        return
      }

      if (next.workflow.state) {
        await router.replace(formRouteLocation(next))
        return
      }

      if (String(route.query.period || '') !== next.entry.performance_period) {
        await router.replace(createRouteLocation(next.entry.performance_period))
        return
      }

      applyPayload(next)
      return
    }

    const entryId = String(route.params.entryId || '')
    if (!entryId) {
      error.value = 'Missing performance entry id.'
      return
    }

    const next = await fetchPerformanceEntry(entryId, activePhase.value)
    if (loadId !== latestLoadId) {
      return
    }
    applyPayload(next)
  } catch (e) {
    if (loadId === latestLoadId) {
      error.value = apiErrorMessage(e, 'Could not load performance form')
    }
  } finally {
    if (loadId === latestLoadId) {
      loading.value = false
    }
  }
}

async function saveDraftAction(): Promise<void> {
  if (!payload.value || !form.value) {
    return
  }

  busy.value = true
  error.value = null
  success.value = null

  try {
    const next = await savePerformanceDraft(payload.value.entry.entry_id, activePhase.value, form.value)
    success.value = activePhase.value === 'ppa' ? 'Draft saved.' : `${payload.value.phase_label} draft saved.`

    if (isCreate.value) {
      await router.replace(formRouteLocation(next))
      return
    }

    applyPayload(next)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save draft')
  } finally {
    busy.value = false
  }
}

async function submitAction(): Promise<void> {
  if (!payload.value || !form.value) {
    return
  }

  error.value = null
  success.value = null

  if (activePhase.value === 'ppa') {
    const validationErrors = ppaSubmitErrors()
    if (validationErrors.length) {
      error.value = validationErrors.join(' ')
      return
    }
  }

  busy.value = true
  try {
    const next = await submitPerformanceEntry(payload.value.entry.entry_id, activePhase.value, form.value)
    success.value = `${payload.value.phase_label} submitted.`
    if (isCreate.value) {
      await router.replace(formRouteLocation(next))
      return
    }
    applyPayload(next)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not submit form')
  } finally {
    busy.value = false
  }
}

async function approveAction(): Promise<void> {
  if (!payload.value) {
    return
  }

  busy.value = true
  error.value = null
  success.value = null

  try {
    const next = await approvePerformanceEntry(payload.value.entry.entry_id, activePhase.value, {
      comments: workflowComments.value || undefined,
      supervisor2_agreement:
        activePhase.value === 'endterm' && payload.value.workflow.state?.step === 'supervisor_2'
          ? supervisor2Agreement.value
          : undefined,
    })
    applyPayload(next)
    success.value = 'Approval recorded.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not approve form')
  } finally {
    busy.value = false
  }
}

async function returnAction(): Promise<void> {
  if (!payload.value) {
    return
  }
  if (!hasRichTextContent(workflowComments.value)) {
    error.value = 'Comments are required when returning a form for revision.'
    return
  }

  busy.value = true
  error.value = null
  success.value = null

  try {
    const target = payload.value.return_target
    const next = await returnPerformanceEntry(payload.value.entry.entry_id, activePhase.value, {
      comments: workflowComments.value,
    })
    applyPayload(next)
    success.value =
      target === 'draft' ? 'Returned to draft.' : 'Returned to the employee for revision.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not return form')
  } finally {
    busy.value = false
  }
}

async function saveSupervisorsAction(): Promise<void> {
  if (!payload.value || !form.value) {
    return
  }
  if (!form.value.supervisor_id) {
    error.value = 'Select a first supervisor before updating.'
    return
  }

  if (isCreate.value) {
    await saveDraftAction()
    return
  }

  busy.value = true
  error.value = null
  success.value = null

  try {
    const next = await updatePerformanceSupervisors(
      payload.value.entry.entry_id,
      activePhase.value,
      Number(form.value.supervisor_id),
      form.value.supervisor2_id ? Number(form.value.supervisor2_id) : null,
    )
    applyPayload(next)
    success.value = 'Supervisors updated.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update supervisors')
  } finally {
    busy.value = false
  }
}

async function consentAction(): Promise<void> {
  if (!payload.value) {
    return
  }

  busy.value = true
  error.value = null
  success.value = null

  try {
    const next = await consentPerformanceEntry(payload.value.entry.entry_id, {
      comments: workflowComments.value || undefined,
      accept_rating: acceptRating.value,
    })
    applyPayload(next)
    success.value = 'Consent recorded.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not record consent')
  } finally {
    busy.value = false
  }
}

async function openPdf(): Promise<void> {
  if (!payload.value) {
    return
  }

  pdfLoading.value = true
  error.value = null
  try {
    await openApiPdf(`/api/v1/performance/entries/${payload.value.entry.entry_id}/print`, {
      phase: activePhase.value,
      with_trail: includeTrail.value ? 1 : 0,
    })
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not open PDF')
  } finally {
    pdfLoading.value = false
  }
}

watch(
  () => [route.name, route.params.phase, route.params.entryId, route.query.period],
  () => {
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <div>
    <PortalPageChrome
      :title="title"
      lede="Performance planning and reviews in the SPA."
    >
      <template #tabs>
        <PortalPillSubnav
          v-if="phaseTabs.length"
          :items="phaseTabs"
          :aria-label="locale.t('subnav.perf_phases', 'Performance phases')"
        />
      </template>

      <template #actions>
        <RouterLink
          :to="{ path: '/performance', query: { tab: 'my', period: currentPeriod || undefined } }"
          style="text-decoration:none"
        >
          <v-btn size="small" variant="outlined" class="perf-export-btn">Back to hub</v-btn>
        </RouterLink>
        <template v-if="payload && !isCreate">
          <v-checkbox
            v-model="includeTrail"
            density="compact"
            hide-details
            class="ms-2 perf-trail-check"
            label="Include approval trail"
          />
          <v-btn
            size="small"
            variant="outlined"
            class="ms-2 perf-export-btn"
            prepend-icon="mdi-file-pdf-box"
            :loading="pdfLoading"
            @click="openPdf"
          >
            PDF
          </v-btn>
        </template>
      </template>
    </PortalPageChrome>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="payload && form">
      <v-alert
        v-if="payload.contract_missing"
        type="warning"
        variant="tonal"
        class="mb-3"
      >
        No staff contract is linked to this staff member. Submission should stay in draft until HR fixes the contract.
      </v-alert>

      <v-alert
        v-if="showReviewGate"
        type="info"
        variant="tonal"
        class="mb-3"
      >
        PPA must be approved before this review can be completed.
      </v-alert>

      <div class="perf-form-top mb-4">
        <PerformanceStaffDetailsCard
          :form="form"
          :contract="payload.contract"
          :period-label="payload.period_label"
          :title="activePhase === 'ppa' ? 'A. Staff Details' : 'A. Personal Details'"
          :initiation-label="activePhase === 'ppa' ? 'Initiation Date' : 'In this Position Since'"
          :division-label="activePhase === 'ppa' ? 'Division/Directorate' : 'Directorate/Department'"
          :supervisor-label="activePhase === 'ppa' ? 'First Supervisor' : 'Direct Supervisor'"
          :can-change-supervisors="canChangeSupervisors"
          :supervisor-options="payload.catalogs.supervisor_options || []"
          :supervisor-busy="busy"
          @update:supervisor-id="form.supervisor_id = $event"
          @update:supervisor2-id="form.supervisor2_id = $event || 0"
          @save-supervisors="saveSupervisorsAction"
        />
        <PerformanceApprovalTrail
          variant="actions"
          class="mt-4"
          :phase="activePhase"
          :submission-window="payload.submission_window"
          :state="payload.workflow.state"
          :timeline="payload.workflow.timeline"
          :items="payload.workflow.trail"
          :can-approve="canApprove"
          :can-return="canReturn"
          :return-label="returnLabel"
          :can-consent="canConsent"
          :can-save="canSave"
          :submission-comments="submissionComments"
          :submission-comment-label="submissionCommentLabel"
          :comments="workflowComments"
          :supervisor2-agreement="supervisor2Agreement"
          :accept-rating="acceptRating"
          :busy="busy"
          @update:comments="workflowComments = $event"
          @update:submission-comments="submissionComments = $event"
          @update:supervisor2Agreement="supervisor2Agreement = $event"
          @update:acceptRating="acceptRating = $event"
          @approve="approveAction"
          @return="returnAction"
          @consent="consentAction"
          @save-draft="saveDraftAction"
          @submit="submitAction"
        />
      </div>

      <PpaSections
        v-if="activePhase === 'ppa'"
        :form="form"
        :skills="payload.catalogs.skills"
        :period-end-year="payload.period_end_year"
        :readonly="readonly"
      />

      <ReviewSections
        v-else-if="!showReviewGate"
        :phase="activePhase"
        :form="form"
        :skills="payload.catalogs.skills"
        :competency-groups="payload.catalogs.competency_groups"
        :competency-labels="payload.catalogs.competency_labels"
        :readonly="readonly"
      />

      <div v-if="!isCreate" class="mt-4">
        <PerformanceApprovalTrail
          variant="history"
          :phase="activePhase"
          :submission-window="null"
          :state="payload.workflow.state"
          :timeline="payload.workflow.timeline"
          :items="payload.workflow.trail"
          :can-approve="false"
          :can-return="false"
          :can-consent="false"
          comments=""
          :supervisor2-agreement="false"
          :accept-rating="false"
        />
      </div>
    </template>
  </div>
</template>

<style scoped>
.perf-export-btn {
  background: #ffffff !important;
}

.perf-trail-check {
  margin: 0;
  align-self: center;
}

.perf-trail-check :deep(.v-label) {
  font-size: 0.8rem;
  color: #3a4752;
}

.perf-form-top {
  display: block;
}
</style>
