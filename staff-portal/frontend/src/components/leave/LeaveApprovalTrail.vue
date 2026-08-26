<script setup lang="ts">
import type { LeaveWorkflowStep } from '@/lib/leaveApi'

const props = withDefaults(
  defineProps<{
    steps: LeaveWorkflowStep[]
    compact?: boolean
  }>(),
  { compact: false },
)

function statusColor(step: LeaveWorkflowStep): string {
  if (step.status === 'Approved') return 'success'
  if (step.status === 'Rejected') return 'error'
  if (step.is_current) return 'warning'
  return 'default'
}

function statusIcon(step: LeaveWorkflowStep): string {
  if (step.status === 'Approved') return 'fa-solid fa-check'
  if (step.status === 'Rejected') return 'fa-solid fa-xmark'
  if (step.is_current) return 'fa-solid fa-hourglass-half'
  return 'fa-solid fa-circle'
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
</script>

<template>
  <div v-if="!steps.length" class="text-caption text-medium-emphasis">No approval workflow on this request.</div>
  <div v-else class="leave-trail" :class="{ 'leave-trail--compact': compact }">
    <div
      v-for="(step, index) in steps"
      :key="step.id || `${step.role}-${index}`"
      class="leave-trail__item"
      :class="{
        'is-approved': step.status === 'Approved',
        'is-rejected': step.status === 'Rejected',
        'is-current': step.is_current,
      }"
    >
      <div class="leave-trail__marker" aria-hidden="true">
        <i :class="statusIcon(step)" />
      </div>
      <div class="leave-trail__body">
        <div class="leave-trail__top">
          <span class="leave-trail__label">{{ step.label }}</span>
          <v-chip size="x-small" :color="statusColor(step)" :variant="step.is_current ? 'flat' : 'tonal'">
            {{ step.is_current && step.status === 'Pending' ? 'Waiting' : step.status }}
          </v-chip>
        </div>
        <div class="leave-trail__name">{{ step.staff_name || `Staff #${step.staff_id}` }}</div>
        <div v-if="!compact && step.acted_at" class="leave-trail__when">{{ formatWhen(step.acted_at) }}</div>
        <div v-if="!compact && step.comments" class="leave-trail__comments">{{ step.comments }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.leave-trail {
  display: flex;
  flex-direction: column;
  position: relative;
  padding: 0.15rem 0 0.15rem 0.15rem;
}

.leave-trail::before {
  content: '';
  position: absolute;
  left: 11px;
  top: 12px;
  bottom: 12px;
  width: 2px;
  background: #e9ecef;
}

.leave-trail__item {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  position: relative;
  margin: 0 0 0.9rem;
}

.leave-trail__item:last-child {
  margin-bottom: 0;
}

.leave-trail__marker {
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #64748b;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.62rem;
  z-index: 1;
}

.leave-trail__item.is-approved .leave-trail__marker {
  border-color: #119a48;
  color: #119a48;
}

.leave-trail__item.is-rejected .leave-trail__marker {
  border-color: #dc2626;
  color: #dc2626;
}

.leave-trail__item.is-current .leave-trail__marker {
  border-color: #b45309;
  color: #b45309;
}

.leave-trail__body {
  min-width: 0;
  flex: 1 1 auto;
}

.leave-trail__top {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem 0.55rem;
}

.leave-trail__label {
  font-weight: 600;
  color: #1f2933;
  font-size: 0.875rem;
}

.leave-trail__name {
  color: #455a64;
  font-size: 0.8125rem;
}

.leave-trail__when,
.leave-trail__comments {
  margin-top: 0.2rem;
  font-size: 0.75rem;
  color: #64748b;
}

.leave-trail__comments {
  margin-top: 0.35rem;
  padding: 0.4rem 0.55rem;
  background: #f8fafc;
  border-radius: 0.4rem;
  color: #455a64;
}

.leave-trail--compact .leave-trail__item {
  margin-bottom: 0.55rem;
}
</style>
