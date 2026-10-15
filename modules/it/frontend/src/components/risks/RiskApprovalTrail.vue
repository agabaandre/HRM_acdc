<script setup lang="ts">
import { computed } from 'vue'
import { useLocaleStore } from '@/stores/locale'

export type RiskApprovalStep = {
  id: number
  risk_id: number
  step_order: number
  role: string
  assignee_staff_id: number | null
  assignee_name?: string | null
  status: string
  is_current?: boolean
  updated_at?: string | null
  created_at?: string | null
}

const props = withDefaults(
  defineProps<{
    steps: RiskApprovalStep[]
    workflowState?: string | null
    canApproveId?: number | null
    approving?: boolean
  }>(),
  {
    steps: () => [],
    workflowState: null,
    canApproveId: null,
    approving: false,
  },
)

const emit = defineEmits<{
  approve: [approvalId: number]
}>()

const locale = useLocaleStore()

const roleLabel = (role: string) => {
  const map: Record<string, string> = {
    risk_focal: locale.t('rr.role_risk_focal', 'Risk focal'),
    hod: locale.t('rr.role_hod', 'Head of Division'),
    director: locale.t('rr.role_director', 'Director'),
    sm_focal: locale.t('rr.role_sm_focal', 'SM focal'),
    extra: locale.t('rr.role_extra', 'Additional reviewer'),
    oio: locale.t('rr.role_oio', 'OIO review'),
  }
  return map[role] || role.replace(/_/g, ' ')
}

const statusLabel = (status: string, isCurrent?: boolean) => {
  if (isCurrent && status === 'pending') return locale.t('rr.approval_waiting', 'Waiting')
  if (status === 'approved') return locale.t('rr.approval_approved', 'Approved')
  if (status === 'skipped') return locale.t('rr.approval_skipped', 'Skipped')
  if (status === 'feedback_requested') return locale.t('rr.approval_feedback', 'Feedback requested')
  if (status === 'pending') return locale.t('rr.approval_pending', 'Pending')
  return status
}

function formatWhen(value?: string | null): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const ordered = computed(() =>
  [...props.steps].sort((a, b) => Number(a.step_order) - Number(b.step_order)),
)
</script>

<template>
  <div class="rr-approval-trail">
    <div class="rr-approval-trail__head">
      <strong>{{ locale.t('rr.io_review_trail', 'IO / approval trail') }}</strong>
      <span v-if="workflowState" class="rr-approval-trail__state">{{ workflowState }}</span>
    </div>
    <p v-if="!ordered.length" class="rr-muted">
      {{ locale.t('rr.no_approval_trail', 'Not submitted for review yet.') }}
    </p>
    <ol v-else class="rr-approval-trail__list">
      <li
        v-for="step in ordered"
        :key="step.id"
        class="rr-approval-trail__item"
        :class="{
          'is-current': step.is_current,
          'is-approved': step.status === 'approved',
          'is-skipped': step.status === 'skipped',
          'is-feedback': step.status === 'feedback_requested',
        }"
      >
        <div class="rr-approval-trail__marker" aria-hidden="true" />
        <div class="rr-approval-trail__body">
          <div class="rr-approval-trail__top">
            <span class="rr-approval-trail__role">{{ roleLabel(step.role) }}</span>
            <span class="rr-approval-trail__status">{{ statusLabel(step.status, step.is_current) }}</span>
          </div>
          <div class="rr-approval-trail__name">
            {{ step.assignee_name || (step.assignee_staff_id ? `Staff #${step.assignee_staff_id}` : locale.t('rr.unassigned', 'Unassigned')) }}
          </div>
          <div v-if="step.updated_at || step.created_at" class="rr-approval-trail__when">
            {{ formatWhen(step.updated_at || step.created_at) }}
          </div>
          <button
            v-if="canApproveId === step.id"
            type="button"
            class="rr-btn rr-btn--primary rr-approval-trail__approve"
            :disabled="approving"
            @click.stop="emit('approve', step.id)"
          >
            {{ approving ? locale.t('rr.approving', 'Approving…') : locale.t('rr.approve', 'Approve') }}
          </button>
        </div>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.rr-approval-trail {
  padding: 0.65rem 0.85rem 0.85rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}
.rr-approval-trail__head {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 0.55rem;
  font-size: 0.82rem;
}
.rr-approval-trail__state {
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.rr-approval-trail__list {
  list-style: none;
  margin: 0;
  padding: 0 0 0 0.35rem;
  border-left: 2px solid #cbd5e1;
}
.rr-approval-trail__item {
  position: relative;
  display: grid;
  grid-template-columns: 0.85rem 1fr;
  gap: 0.55rem;
  padding: 0 0 0.75rem 0.65rem;
}
.rr-approval-trail__item:last-child {
  padding-bottom: 0;
}
.rr-approval-trail__marker {
  width: 0.65rem;
  height: 0.65rem;
  margin-top: 0.2rem;
  margin-left: -1.05rem;
  border-radius: 50%;
  background: #94a3b8;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #cbd5e1;
}
.rr-approval-trail__item.is-current .rr-approval-trail__marker {
  background: #f59e0b;
  box-shadow: 0 0 0 1px #f59e0b;
}
.rr-approval-trail__item.is-approved .rr-approval-trail__marker {
  background: #16a34a;
  box-shadow: 0 0 0 1px #16a34a;
}
.rr-approval-trail__item.is-skipped .rr-approval-trail__marker {
  background: #cbd5e1;
}
.rr-approval-trail__item.is-feedback .rr-approval-trail__marker {
  background: #2563eb;
  box-shadow: 0 0 0 1px #2563eb;
}
.rr-approval-trail__top {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: baseline;
}
.rr-approval-trail__role {
  font-size: 0.84rem;
  font-weight: 650;
  color: #0f172a;
  text-transform: capitalize;
}
.rr-approval-trail__status {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
}
.rr-approval-trail__item.is-current .rr-approval-trail__status {
  color: #b45309;
}
.rr-approval-trail__item.is-approved .rr-approval-trail__status {
  color: #15803d;
}
.rr-approval-trail__name {
  font-size: 0.82rem;
  color: #334155;
}
.rr-approval-trail__when {
  margin-top: 0.1rem;
  font-size: 0.72rem;
  color: #94a3b8;
}
.rr-approval-trail__approve {
  margin-top: 0.4rem;
}
.rr-muted {
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
}
</style>
