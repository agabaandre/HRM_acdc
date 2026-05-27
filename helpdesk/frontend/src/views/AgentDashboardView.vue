<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../lib/apiErrorMessage'

interface Counts {
  total_received: number
  pending: number
  awaiting_requester_confirmation: number
  resolved: number
  closed: number
  overdue: number
  due_today: number
  high_priority_pending: number
  new_today: number
  resolved_this_week: number
}

interface Breakdown {
  by_status: Record<string, number>
  by_priority: Record<string, number>
}

interface RecentRow {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  requester_name: string | null
  category?: { id: number; name: string } | null
  sla_resolution_due_at?: string | null
  created_at?: string | null
}

type FilterKey = 'all' | 'pending' | 'awaiting' | 'overdue' | 'high'

interface EligibleAgent {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  duty_station?: string | null
  open_workload: number
}

const auth = useAuthStore()
const err = ref<string | null>(null)
const loading = ref(false)
const counts = ref<Counts | null>(null)
const breakdown = ref<Breakdown | null>(null)
const recent = ref<RecentRow[]>([])
const generatedAt = ref<string | null>(null)
const activeFilter = ref<FilterKey>('all')
const toast = ref<string | null>(null)
const workModeSaving = ref<'remote' | 'onsite' | 'clear' | null>(null)

const canReassign = computed(() => {
  const p = auth.me?.profile
  return p?.role === 'admin' || !!p?.can_reassign_tickets
})

const currentWorkMode = computed(() => auth.me?.profile?.work_mode ?? null)

async function setWorkMode(mode: 'remote' | 'onsite' | null): Promise<void> {
  workModeSaving.value = mode ?? 'clear'
  try {
    await auth.updateWorkMode(mode)
    toast.value = mode
      ? `You're now marked as working ${mode}.`
      : 'Work-mode cleared.'
    setTimeout(() => {
      if (toast.value && toast.value.startsWith('You') ) toast.value = null
      if (toast.value === 'Work-mode cleared.') toast.value = null
    }, 2400)
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Could not update work mode.')
  } finally {
    workModeSaving.value = null
  }
}

// Reassign modal state
const reassignTicket = ref<RecentRow | null>(null)
const reassignCandidates = ref<EligibleAgent[]>([])
const reassignCandidatesLoading = ref(false)
const reassignSelectedId = ref<number | null>(null)
const reassignReason = ref('')
const reassignSubmitting = ref(false)
const reassignErr = ref<string | null>(null)

async function load(): Promise<void> {
  err.value = null
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/agent-dashboard')
    counts.value = data.data.counts as Counts
    breakdown.value = data.data.breakdown as Breakdown
    recent.value = data.data.recent as RecentRow[]
    generatedAt.value = data.data.generated_at ?? null
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Unable to load dashboard.')
  } finally {
    loading.value = false
  }
}

const greeting = computed(() => {
  const name = auth.me?.name?.split(' ')[0] ?? 'there'
  const hour = new Date().getHours()
  if (hour < 12) return `Good morning, ${name}`
  if (hour < 17) return `Good afternoon, ${name}`
  return `Good evening, ${name}`
})

const todayLabel = computed(() => {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
})

const generatedLabel = computed(() => {
  if (!generatedAt.value) return ''
  try {
    return `Updated ${new Date(generatedAt.value).toLocaleTimeString()}`
  } catch {
    return ''
  }
})

const totalForStatusBar = computed(() => {
  const b = breakdown.value?.by_status
  if (!b) return 0
  return Object.values(b).reduce((a, n) => a + n, 0)
})

const totalForPriorityBar = computed(() => {
  const b = breakdown.value?.by_priority
  if (!b) return 0
  return Object.values(b).reduce((a, n) => a + n, 0)
})

const statusSegments = computed(() => {
  if (!breakdown.value) return []
  const order: Array<{ key: string; label: string; color: string }> = [
    { key: 'open', label: 'Open', color: '#2563eb' },
    { key: 'pending', label: 'Pending', color: '#6366f1' },
    { key: 'in_progress', label: 'In progress', color: '#7c3aed' },
    { key: 'awaiting_requester_confirmation', label: 'Awaiting confirm', color: '#d97706' },
    { key: 'resolved', label: 'Resolved', color: '#16a34a' },
    { key: 'closed', label: 'Closed', color: '#64748b' },
  ]
  const total = totalForStatusBar.value || 1
  return order
    .map((o) => ({ ...o, count: breakdown.value!.by_status[o.key] ?? 0 }))
    .filter((s) => s.count > 0)
    .map((s) => ({ ...s, pct: (s.count / total) * 100 }))
})

const prioritySegments = computed(() => {
  if (!breakdown.value) return []
  const order: Array<{ key: string; label: string; color: string }> = [
    { key: 'low', label: 'Low', color: '#94a3b8' },
    { key: 'medium', label: 'Medium', color: '#2563eb' },
    { key: 'high', label: 'High', color: '#ea580c' },
    { key: 'urgent', label: 'Urgent', color: '#dc2626' },
  ]
  const total = totalForPriorityBar.value || 1
  return order
    .map((o) => ({ ...o, count: breakdown.value!.by_priority[o.key] ?? 0 }))
    .filter((s) => s.count > 0)
    .map((s) => ({ ...s, pct: (s.count / total) * 100 }))
})

const filterChips = computed(() => {
  if (!counts.value) return []
  return [
    { key: 'all' as FilterKey, label: 'All', count: recent.value.length },
    { key: 'pending' as FilterKey, label: 'Open queue', count: counts.value.pending },
    { key: 'awaiting' as FilterKey, label: 'Awaiting confirm', count: counts.value.awaiting_requester_confirmation },
    { key: 'overdue' as FilterKey, label: 'Overdue', count: counts.value.overdue },
    { key: 'high' as FilterKey, label: 'High priority', count: counts.value.high_priority_pending },
  ]
})

const now = ref(Date.now())
function refreshNow(): void {
  now.value = Date.now()
}
let nowTimer: number | undefined

function isOverdue(row: RecentRow): boolean {
  if (!row.sla_resolution_due_at) return false
  if (['resolved', 'closed', 'awaiting_requester_confirmation'].includes(row.status)) return false
  return new Date(row.sla_resolution_due_at).getTime() < now.value
}

function canReassignRow(row: RecentRow): boolean {
  return canReassign.value && ['open', 'pending', 'in_progress'].includes(row.status)
}

async function openReassign(row: RecentRow): Promise<void> {
  reassignTicket.value = row
  reassignSelectedId.value = null
  reassignReason.value = ''
  reassignErr.value = null
  reassignCandidates.value = []
  reassignCandidatesLoading.value = true
  try {
    const { data } = await api.get<{ data: EligibleAgent[] }>(
      `/api/v1/tickets/${row.id}/eligible-agents`,
    )
    reassignCandidates.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    reassignErr.value = apiErrorMessage(e, 'Could not load agents for this category.')
  } finally {
    reassignCandidatesLoading.value = false
  }
}

function closeReassign(): void {
  reassignTicket.value = null
  reassignCandidates.value = []
  reassignSelectedId.value = null
  reassignReason.value = ''
  reassignErr.value = null
  reassignSubmitting.value = false
}

async function submitReassign(): Promise<void> {
  if (!reassignTicket.value || !reassignSelectedId.value) {
    reassignErr.value = 'Pick an agent first.'
    return
  }
  if (reassignReason.value.trim().length < 5) {
    reassignErr.value = 'Reason must be at least 5 characters.'
    return
  }
  reassignSubmitting.value = true
  reassignErr.value = null
  try {
    await api.post(`/api/v1/tickets/${reassignTicket.value.id}/reassign`, {
      assignee_user_id: reassignSelectedId.value,
      reason: reassignReason.value.trim(),
    })
    const newAgent = reassignCandidates.value.find((a) => a.id === reassignSelectedId.value)
    toast.value = `Reassigned ${reassignTicket.value.ticket_number}${newAgent ? ` to ${newAgent.name}` : ''}.`
    closeReassign()
    await load()
    window.setTimeout(() => {
      toast.value = null
    }, 4000)
  } catch (e: unknown) {
    reassignErr.value = apiErrorMessage(e, 'Reassignment failed.')
  } finally {
    reassignSubmitting.value = false
  }
}

const filteredRecent = computed<RecentRow[]>(() => {
  const rows = recent.value
  switch (activeFilter.value) {
    case 'pending':
      return rows.filter((r) => ['open', 'pending', 'in_progress'].includes(r.status))
    case 'awaiting':
      return rows.filter((r) => r.status === 'awaiting_requester_confirmation')
    case 'overdue':
      return rows.filter(isOverdue)
    case 'high':
      return rows.filter((r) => ['high', 'urgent'].includes(r.priority))
    case 'all':
    default:
      return rows
  }
})

function statusMeta(status: string): { label: string; color: string; bg: string } {
  switch (status) {
    case 'open':
      return { label: 'Open', color: '#1d4ed8', bg: '#dbeafe' }
    case 'pending':
      return { label: 'Pending', color: '#4338ca', bg: '#e0e7ff' }
    case 'in_progress':
      return { label: 'In progress', color: '#6d28d9', bg: '#ede9fe' }
    case 'awaiting_requester_confirmation':
      return { label: 'Awaiting confirm', color: '#b45309', bg: '#fef3c7' }
    case 'resolved':
      return { label: 'Resolved', color: '#15803d', bg: '#dcfce7' }
    case 'closed':
      return { label: 'Closed', color: '#334155', bg: '#e2e8f0' }
    default:
      return { label: status, color: '#334155', bg: '#e2e8f0' }
  }
}

function priorityMeta(priority: string): { label: string; color: string; bg: string } {
  switch (priority) {
    case 'urgent':
      return { label: 'Urgent', color: '#991b1b', bg: '#fee2e2' }
    case 'high':
      return { label: 'High', color: '#9a3412', bg: '#ffedd5' }
    case 'medium':
      return { label: 'Medium', color: '#1e3a8a', bg: '#dbeafe' }
    case 'low':
      return { label: 'Low', color: '#334155', bg: '#e2e8f0' }
    default:
      return { label: priority, color: '#334155', bg: '#e2e8f0' }
  }
}

function relativeTime(iso?: string | null): string {
  if (!iso) return '—'
  const t = new Date(iso).getTime()
  if (Number.isNaN(t)) return '—'
  const diffSec = Math.round((now.value - t) / 1000)
  const abs = Math.abs(diffSec)
  if (abs < 60) return diffSec >= 0 ? 'just now' : 'in a moment'
  const min = Math.round(abs / 60)
  if (min < 60) return diffSec >= 0 ? `${min}m ago` : `in ${min}m`
  const hr = Math.round(min / 60)
  if (hr < 24) return diffSec >= 0 ? `${hr}h ago` : `in ${hr}h`
  const day = Math.round(hr / 24)
  if (day < 30) return diffSec >= 0 ? `${day}d ago` : `in ${day}d`
  return new Date(iso).toLocaleDateString()
}

function dueLabel(iso?: string | null): string {
  if (!iso) return 'No SLA'
  const t = new Date(iso).getTime()
  if (Number.isNaN(t)) return 'No SLA'
  const diffSec = Math.round((t - now.value) / 1000)
  const abs = Math.abs(diffSec)
  if (abs < 60) return diffSec >= 0 ? 'due now' : 'overdue moments ago'
  const min = Math.round(abs / 60)
  if (min < 60) return diffSec >= 0 ? `due in ${min}m` : `overdue ${min}m`
  const hr = Math.round(min / 60)
  if (hr < 24) return diffSec >= 0 ? `due in ${hr}h` : `overdue ${hr}h`
  const day = Math.round(hr / 24)
  return diffSec >= 0 ? `due in ${day}d` : `overdue ${day}d`
}

onMounted(() => {
  void load()
  nowTimer = window.setInterval(refreshNow, 30000)
})

onUnmounted(() => {
  if (nowTimer) window.clearInterval(nowTimer)
})
</script>

<template>
  <div class="agent-dash">
    <CbpPageHeading title="Agent dashboard" back-to="/" back-label="← Overview">
      <template #lede>
        Your workload at a glance — what's open, what's overdue, and what needs your attention next.
      </template>
    </CbpPageHeading>

    <header class="dash-hello">
      <div>
        <p class="dash-greet">{{ greeting }} <span class="dash-wave" aria-hidden="true">👋</span></p>
        <p class="dash-date">{{ todayLabel }}</p>
      </div>
      <div class="dash-tools">
        <div class="work-mode" role="group" aria-label="Set your current location">
          <span class="work-mode-label">Working from</span>
          <div class="work-mode-seg">
            <button
              type="button"
              class="seg-btn"
              :class="{ active: currentWorkMode === 'remote' }"
              :disabled="workModeSaving !== null"
              :aria-pressed="currentWorkMode === 'remote'"
              :title="currentWorkMode === 'remote' ? 'You are marked remote' : 'Mark yourself as working remotely'"
              @click="setWorkMode(currentWorkMode === 'remote' ? null : 'remote')"
            >
              <span class="seg-dot remote" aria-hidden="true" />
              {{ workModeSaving === 'remote' ? 'Saving…' : 'Remote' }}
            </button>
            <button
              type="button"
              class="seg-btn"
              :class="{ active: currentWorkMode === 'onsite' }"
              :disabled="workModeSaving !== null"
              :aria-pressed="currentWorkMode === 'onsite'"
              :title="currentWorkMode === 'onsite' ? 'You are marked onsite' : 'Mark yourself as working from the office'"
              @click="setWorkMode(currentWorkMode === 'onsite' ? null : 'onsite')"
            >
              <span class="seg-dot onsite" aria-hidden="true" />
              {{ workModeSaving === 'onsite' ? 'Saving…' : 'Onsite' }}
            </button>
          </div>
        </div>
        <span v-if="generatedLabel" class="dash-updated">{{ generatedLabel }}</span>
        <button type="button" class="btn-ghost" :disabled="loading" @click="load">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>
    </header>

    <p v-if="err" class="err" role="alert">{{ err }}</p>

    <template v-if="counts">
      <!-- KPI cards -->
      <section class="kpis" aria-label="Key metrics">
        <article class="kpi kpi-pending">
          <header>
            <span class="kpi-icon" aria-hidden="true">🗂</span>
            <span class="kpi-label">Open queue</span>
          </header>
          <p class="kpi-value">{{ counts.pending }}</p>
          <p class="kpi-sub">Tickets you're working on</p>
        </article>

        <article class="kpi kpi-awaiting">
          <header>
            <span class="kpi-icon" aria-hidden="true">⏳</span>
            <span class="kpi-label">Awaiting confirm</span>
          </header>
          <p class="kpi-value">{{ counts.awaiting_requester_confirmation }}</p>
          <p class="kpi-sub">Resolution sent — waiting on requester</p>
        </article>

        <article class="kpi kpi-overdue" :class="{ alert: counts.overdue > 0 }">
          <header>
            <span class="kpi-icon" aria-hidden="true">⚠️</span>
            <span class="kpi-label">Overdue</span>
          </header>
          <p class="kpi-value">{{ counts.overdue }}</p>
          <p class="kpi-sub">{{ counts.overdue > 0 ? 'Past SLA — handle now' : 'No SLA breaches' }}</p>
        </article>

        <article class="kpi kpi-due-today">
          <header>
            <span class="kpi-icon" aria-hidden="true">📅</span>
            <span class="kpi-label">Due today</span>
          </header>
          <p class="kpi-value">{{ counts.due_today }}</p>
          <p class="kpi-sub">SLA expires before midnight</p>
        </article>

        <article class="kpi kpi-high">
          <header>
            <span class="kpi-icon" aria-hidden="true">🔥</span>
            <span class="kpi-label">High priority</span>
          </header>
          <p class="kpi-value">{{ counts.high_priority_pending }}</p>
          <p class="kpi-sub">High or urgent — still open</p>
        </article>

        <article class="kpi kpi-resolved">
          <header>
            <span class="kpi-icon" aria-hidden="true">✅</span>
            <span class="kpi-label">Resolved (7 days)</span>
          </header>
          <p class="kpi-value">{{ counts.resolved_this_week }}</p>
          <p class="kpi-sub">{{ counts.new_today }} new today · {{ counts.total_received }} all-time</p>
        </article>
      </section>

      <!-- Breakdowns -->
      <section v-if="breakdown" class="charts" aria-label="Workload breakdown">
        <article class="chart cbp-card">
          <header class="chart-head">
            <h2>By status</h2>
            <span class="chart-total">{{ totalForStatusBar }} tickets</span>
          </header>
          <div v-if="statusSegments.length" class="bar" role="img" aria-label="Tickets grouped by status">
            <span
              v-for="s in statusSegments"
              :key="s.key"
              class="bar-seg"
              :style="{ width: s.pct + '%', background: s.color }"
              :title="`${s.label}: ${s.count}`"
            />
          </div>
          <ul v-if="statusSegments.length" class="legend">
            <li v-for="s in statusSegments" :key="s.key">
              <span class="dot" :style="{ background: s.color }" />
              <span class="legend-label">{{ s.label }}</span>
              <span class="legend-count">{{ s.count }}</span>
            </li>
          </ul>
          <p v-else class="muted">No tickets yet.</p>
        </article>

        <article class="chart cbp-card">
          <header class="chart-head">
            <h2>By priority</h2>
            <span class="chart-total">{{ totalForPriorityBar }} tickets</span>
          </header>
          <div v-if="prioritySegments.length" class="bar" role="img" aria-label="Tickets grouped by priority">
            <span
              v-for="s in prioritySegments"
              :key="s.key"
              class="bar-seg"
              :style="{ width: s.pct + '%', background: s.color }"
              :title="`${s.label}: ${s.count}`"
            />
          </div>
          <ul v-if="prioritySegments.length" class="legend">
            <li v-for="s in prioritySegments" :key="s.key">
              <span class="dot" :style="{ background: s.color }" />
              <span class="legend-label">{{ s.label }}</span>
              <span class="legend-count">{{ s.count }}</span>
            </li>
          </ul>
          <p v-else class="muted">No tickets yet.</p>
        </article>
      </section>

      <!-- Recent activity -->
      <section class="cbp-card recent" aria-labelledby="recent-heading">
        <header class="recent-head">
          <div>
            <h2 id="recent-heading">Recent tickets</h2>
            <p class="recent-sub">Newest 25 tickets assigned to you</p>
          </div>
          <RouterLink to="/tickets" class="see-all">See all tickets →</RouterLink>
        </header>

        <div class="chips" role="tablist" aria-label="Filter recent tickets">
          <button
            v-for="c in filterChips"
            :key="c.key"
            role="tab"
            type="button"
            class="chip"
            :class="{ 'is-active': activeFilter === c.key, 'chip-warn': c.key === 'overdue' && c.count > 0, 'chip-hot': c.key === 'high' && c.count > 0 }"
            :aria-selected="activeFilter === c.key"
            @click="activeFilter = c.key"
          >
            {{ c.label }} <span class="chip-count">{{ c.count }}</span>
          </button>
        </div>

        <div v-if="filteredRecent.length" class="rows">
          <RouterLink
            v-for="r in filteredRecent"
            :key="r.id"
            :to="`/tickets/${r.id}`"
            class="row"
            :class="{ 'is-overdue': isOverdue(r) }"
          >
            <span class="row-num">
              <span class="status-dot" :style="{ background: statusMeta(r.status).color }" aria-hidden="true" />
              {{ r.ticket_number }}
            </span>

            <span class="row-subj">
              <span class="row-subj-line">{{ r.subject }}</span>
              <span v-if="r.category" class="row-cat">{{ r.category.name }}</span>
            </span>

            <span class="row-person">
              <CbpAvatar size="sm" :name="r.requester_name || 'Requester'" :image-url="null" />
              <span class="row-person-name">{{ r.requester_name ?? '—' }}</span>
            </span>

            <span class="pill" :style="{ background: statusMeta(r.status).bg, color: statusMeta(r.status).color }">
              {{ statusMeta(r.status).label }}
            </span>

            <span class="pill" :style="{ background: priorityMeta(r.priority).bg, color: priorityMeta(r.priority).color }">
              {{ priorityMeta(r.priority).label }}
            </span>

            <span class="row-time">
              <span class="row-time-rel">{{ relativeTime(r.created_at) }}</span>
              <span
                v-if="r.sla_resolution_due_at"
                class="row-time-sla"
                :class="{ 'is-overdue': isOverdue(r) }"
              >
                {{ dueLabel(r.sla_resolution_due_at) }}
              </span>
            </span>

            <span class="row-action">
              <button
                v-if="canReassignRow(r)"
                type="button"
                class="reassign-btn"
                title="Reassign this ticket to another agent"
                @click.stop.prevent="openReassign(r)"
              >
                Reassign
              </button>
              <span v-else class="row-arrow" aria-hidden="true">›</span>
            </span>
          </RouterLink>
        </div>
        <p v-else class="muted">
          {{ recent.length === 0 ? 'No tickets assigned to you yet.' : 'No tickets match this filter.' }}
        </p>
      </section>
    </template>

    <p v-else-if="!err" class="muted">Loading…</p>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" class="toast" role="status">{{ toast }}</div>
    </Transition>

    <!-- Reassign modal -->
    <Teleport to="body">
      <div v-if="reassignTicket" class="modal-backdrop" @click.self="closeReassign">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="reassign-title">
          <header class="modal-head">
            <div>
              <h2 id="reassign-title" class="modal-title">Reassign ticket</h2>
              <p class="modal-sub">
                <strong>{{ reassignTicket.ticket_number }}</strong>
                <span v-if="reassignTicket.subject"> — {{ reassignTicket.subject }}</span>
              </p>
            </div>
            <button type="button" class="modal-close" aria-label="Close" @click="closeReassign">×</button>
          </header>

          <div class="modal-body">
            <p v-if="reassignErr" class="reassign-err" role="alert">{{ reassignErr }}</p>

            <section class="modal-section">
              <h3 class="modal-section-title">New assignee</h3>
              <p v-if="reassignCandidatesLoading" class="muted">Loading agents…</p>
              <p
                v-else-if="reassignCandidates.length === 0 && !reassignErr"
                class="muted"
              >
                No other agents handle this category. Configure category routing on
                <RouterLink to="/settings/agents">Settings → Agents</RouterLink>.
              </p>
              <ul v-else class="agent-list">
                <li v-for="a in reassignCandidates" :key="a.id">
                  <label class="agent-choice" :class="{ 'is-checked': reassignSelectedId === a.id }">
                    <input
                      v-model="reassignSelectedId"
                      type="radio"
                      :value="a.id"
                      name="reassign-agent"
                    />
                    <CbpAvatar size="sm" :name="a.name" :image-url="a.avatar_url ?? null" />
                    <span class="agent-meta">
                      <span class="agent-name-row">{{ a.name }}</span>
                      <span class="agent-sub">
                        <span v-if="a.duty_station">{{ a.duty_station }}</span>
                        <span v-if="a.duty_station" class="dot-sep">·</span>
                        <span>{{ a.open_workload }} open</span>
                      </span>
                    </span>
                  </label>
                </li>
              </ul>
            </section>

            <section class="modal-section">
              <label class="reason-label">
                <span>Reason for reassigning <span class="req">*</span></span>
                <textarea
                  v-model="reassignReason"
                  rows="4"
                  placeholder="e.g. Out of office for 3 days — please pick this up."
                  required
                  minlength="5"
                  maxlength="2000"
                ></textarea>
                <span class="reason-help">
                  Recorded on the ticket history and as an internal comment for the new assignee.
                </span>
              </label>
            </section>
          </div>

          <footer class="modal-foot">
            <button type="button" class="btn-secondary" :disabled="reassignSubmitting" @click="closeReassign">
              Cancel
            </button>
            <button
              type="button"
              class="btn-primary"
              :disabled="reassignSubmitting || !reassignSelectedId || reassignReason.trim().length < 5"
              @click="submitReassign"
            >
              {{ reassignSubmitting ? 'Reassigning…' : 'Reassign ticket' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.agent-dash {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.dash-hello {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.1rem;
  background: linear-gradient(135deg, #0d7a3a 0%, #119a48 100%);
  color: #fff;
  border-radius: 14px;
  box-shadow: 0 6px 18px rgba(13, 122, 58, 0.18);
}
.dash-greet {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
}
.dash-wave {
  display: inline-block;
  margin-left: 0.25rem;
}
.dash-date {
  margin: 0.15rem 0 0;
  font-size: 0.85rem;
  opacity: 0.85;
}
.dash-tools {
  display: flex;
  gap: 0.6rem;
  align-items: center;
  flex-wrap: wrap;
}
.dash-updated {
  font-size: 0.78rem;
  opacity: 0.9;
}

/* Working-from segmented control */
.work-mode {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 999px;
  padding: 0.25rem 0.4rem 0.25rem 0.85rem;
}
.work-mode-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.92;
}
.work-mode-seg {
  display: inline-flex;
  background: rgba(15, 23, 42, 0.32);
  border-radius: 999px;
  padding: 2px;
}
.seg-btn {
  appearance: none;
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.32rem 0.8rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
  font-family: inherit;
}
.seg-btn:hover:not(:disabled):not(.active) {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}
.seg-btn.active {
  background: #fff;
  color: #0f172a;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
}
.seg-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}
.seg-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  display: inline-block;
}
.seg-dot.remote {
  background: #38bdf8;
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.32);
}
.seg-dot.onsite {
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.32);
}
.seg-btn.active .seg-dot.remote {
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.55);
}
.seg-btn.active .seg-dot.onsite {
  box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.55);
}
.btn-ghost {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.35);
  padding: 0.45rem 0.9rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s ease;
}
.btn-ghost:hover {
  background: rgba(255, 255, 255, 0.28);
}
.btn-ghost:disabled {
  opacity: 0.7;
  cursor: wait;
}

/* KPI grid */
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 0.85rem;
}
.kpi {
  background: #fff;
  border-radius: 14px;
  padding: 1rem 1.1rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  position: relative;
  overflow: hidden;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.kpi:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.kpi::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--kpi-accent, #119a48);
}
.kpi header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.4rem;
}
.kpi-icon {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--kpi-accent-soft, rgba(17, 154, 72, 0.12));
  border-radius: 8px;
  font-size: 0.95rem;
}
.kpi-label {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
}
.kpi-value {
  font-size: 2rem;
  font-weight: 800;
  margin: 0;
  color: #0f172a;
  line-height: 1;
}
.kpi-sub {
  margin: 0.45rem 0 0;
  font-size: 0.82rem;
  color: #64748b;
}

/* KPI accents */
.kpi-pending {
  --kpi-accent: #2563eb;
  --kpi-accent-soft: #dbeafe;
}
.kpi-awaiting {
  --kpi-accent: #d97706;
  --kpi-accent-soft: #fef3c7;
}
.kpi-overdue {
  --kpi-accent: #94a3b8;
  --kpi-accent-soft: #e2e8f0;
}
.kpi-overdue.alert {
  --kpi-accent: #dc2626;
  --kpi-accent-soft: #fee2e2;
  background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
}
.kpi-overdue.alert .kpi-value {
  color: #b91c1c;
}
.kpi-due-today {
  --kpi-accent: #7c3aed;
  --kpi-accent-soft: #ede9fe;
}
.kpi-high {
  --kpi-accent: #ea580c;
  --kpi-accent-soft: #ffedd5;
}
.kpi-resolved {
  --kpi-accent: #16a34a;
  --kpi-accent-soft: #dcfce7;
}

/* Charts */
.charts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 0.85rem;
}
.chart {
  padding: 1rem 1.1rem;
}
.chart-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.7rem;
}
.chart-head h2 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1f2937;
}
.chart-total {
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 600;
}
.bar {
  display: flex;
  width: 100%;
  height: 12px;
  border-radius: 6px;
  overflow: hidden;
  background: #f1f5f9;
}
.bar-seg {
  display: block;
  height: 100%;
}
.legend {
  list-style: none;
  margin: 0.7rem 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.4rem 0.8rem;
}
.legend li {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.82rem;
  color: #334155;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  flex-shrink: 0;
}
.legend-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.legend-count {
  font-weight: 700;
  color: #0f172a;
}

/* Recent */
.recent {
  padding: 1rem 1.1rem;
}
.recent-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
  flex-wrap: wrap;
}
.recent-head h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
}
.recent-sub {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  color: #64748b;
}
.see-all {
  color: #0d7a3a;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
}
.see-all:hover {
  text-decoration: underline;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.85rem;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
}
.chip:hover {
  background: #f8fafc;
}
.chip.is-active {
  background: #0d7a3a;
  border-color: #0d7a3a;
  color: #fff;
}
.chip-count {
  background: rgba(15, 23, 42, 0.08);
  color: #1f2937;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.74rem;
}
.chip.is-active .chip-count {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}
.chip-warn {
  border-color: #fecaca;
  color: #b91c1c;
  background: #fef2f2;
}
.chip-warn .chip-count {
  background: #fee2e2;
  color: #991b1b;
}
.chip-warn.is-active {
  background: #b91c1c;
  border-color: #b91c1c;
}
.chip-hot {
  border-color: #fed7aa;
  color: #9a3412;
  background: #fff7ed;
}
.chip-hot .chip-count {
  background: #ffedd5;
  color: #9a3412;
}
.chip-hot.is-active {
  background: #ea580c;
  border-color: #ea580c;
}

.rows {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1.4fr) minmax(0, 1fr) auto auto minmax(120px, auto) minmax(40px, auto);
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: 10px;
  background: #fff;
  border: 1px solid #e5e7eb;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.12s ease, transform 0.12s ease, box-shadow 0.12s ease;
}
.row:hover {
  border-color: #cbd5e1;
  transform: translateX(2px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}
.row.is-overdue {
  border-left: 3px solid #dc2626;
  background: #fffbfb;
}
.row-num {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.82rem;
  font-weight: 700;
  color: #1f2937;
  white-space: nowrap;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  flex-shrink: 0;
}
.row-subj {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.row-subj-line {
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row-cat {
  font-size: 0.75rem;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 0.05rem 0.5rem;
  width: max-content;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.row-person {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}
.row-person-name {
  font-size: 0.85rem;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pill {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}
.row-time {
  display: flex;
  flex-direction: column;
  font-size: 0.78rem;
  color: #64748b;
  white-space: nowrap;
}
.row-time-sla.is-overdue {
  color: #b91c1c;
  font-weight: 700;
}
.row-arrow {
  font-size: 1.1rem;
  color: #94a3b8;
  text-align: center;
}
.row-action {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
.reassign-btn {
  padding: 0.32rem 0.7rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #1e293b;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
}
.reassign-btn:hover {
  background: #0d7a3a;
  border-color: #0d7a3a;
  color: #fff;
}

/* Toast */
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #0f172a;
  color: #fff;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
  z-index: 60;
  max-width: 360px;
}
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

/* Reassign modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 70;
}
.modal {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.35);
  display: flex;
  flex-direction: column;
  max-height: 92vh;
}
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.15rem 0.65rem;
  border-bottom: 1px solid #f1f5f9;
}
.modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.modal-sub {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: #475569;
  word-break: break-word;
}
.modal-close {
  background: transparent;
  border: 0;
  color: #64748b;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  padding: 0.15rem 0.4rem;
  border-radius: 6px;
}
.modal-close:hover {
  background: #f1f5f9;
}
.modal-body {
  padding: 0.9rem 1.15rem;
  overflow-y: auto;
}
.modal-section {
  margin-bottom: 1rem;
}
.modal-section:last-child {
  margin-bottom: 0;
}
.modal-section-title {
  margin: 0 0 0.4rem;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
}
.agent-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  max-height: 260px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.4rem;
}
.agent-choice {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.55rem;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  background: #fff;
  transition: background 0.12s ease, border-color 0.12s ease;
}
.agent-choice:hover {
  background: #f8fafc;
}
.agent-choice.is-checked {
  background: rgba(13, 122, 58, 0.07);
  border-color: rgba(13, 122, 58, 0.35);
}
.agent-choice input[type='radio'] {
  margin: 0;
  accent-color: #0d7a3a;
}
.agent-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.agent-name-row {
  font-weight: 600;
  color: #0f172a;
  font-size: 0.92rem;
}
.agent-sub {
  font-size: 0.78rem;
  color: #64748b;
}
.dot-sep {
  margin: 0 0.4rem;
  color: #cbd5e1;
}
.reason-label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.84rem;
  font-weight: 600;
  color: #1f2937;
}
.req {
  color: #b91c1c;
}
.reason-label textarea {
  font-family: inherit;
  font-size: 0.9rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0.55rem 0.7rem;
  resize: vertical;
  min-height: 100px;
}
.reason-label textarea:focus {
  outline: none;
  border-color: #0d7a3a;
  box-shadow: 0 0 0 3px rgba(13, 122, 58, 0.15);
}
.reason-help {
  font-size: 0.76rem;
  font-weight: 500;
  color: #64748b;
}
.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
  padding: 0.85rem 1.15rem 1rem;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
  border-radius: 0 0 14px 14px;
}
.btn-secondary {
  padding: 0.5rem 0.95rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #1f2937;
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
}
.btn-secondary:hover {
  background: #f1f5f9;
}
.btn-primary {
  padding: 0.5rem 1.1rem;
  border-radius: 8px;
  border: 0;
  background: linear-gradient(135deg, #0d7a3a, #065f2c);
  color: #fff;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-primary:hover:not(:disabled) {
  filter: brightness(1.05);
}
.reassign-err {
  margin: 0 0 0.85rem;
  padding: 0.55rem 0.75rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 8px;
  font-size: 0.86rem;
}

@media (max-width: 880px) {
  .row {
    grid-template-columns: auto 1fr;
    grid-auto-flow: row;
    row-gap: 0.45rem;
  }
  .row-subj {
    grid-column: 1 / -1;
  }
  .row-arrow {
    display: none;
  }
}

.muted {
  color: #64748b;
}
.err {
  margin: 0;
  padding: 0.7rem 0.9rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 10px;
}
</style>
