<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import HomeAgentKanban from '../components/home/HomeAgentKanban.vue'
import TicketReassignModal, {
  type ReassignTicketRef,
} from '../components/tickets/TicketReassignModal.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { canReassignTickets, ticketStatusAllowsReassign } from '../lib/canReassignTickets'
import { notifyError, notifySuccess } from '../lib/notify'
import {
  formatTableCountLabel,
  priorityMeta,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'

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

type FilterKey = 'all' | 'pending' | 'awaiting' | 'overdue' | 'due_today' | 'high' | 'resolved'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const counts = ref<Counts | null>(null)
const breakdown = ref<Breakdown | null>(null)
const recent = ref<RecentRow[]>([])
const generatedAt = ref<string | null>(null)
const activeFilter = ref<FilterKey>('all')
const recentPage = ref(1)
const recentItemsPerPage = ref(10)
const recentItemsPerPageOptions = [10, 20, 50] as const
const recentSectionRef = ref<HTMLElement | null>(null)
const workModeSaving = ref<'remote' | 'onsite' | 'clear' | null>(null)

const canReassign = computed(() => canReassignTickets(auth.me?.profile))

const currentWorkMode = computed(() => auth.me?.profile?.work_mode ?? null)

async function setWorkMode(mode: 'remote' | 'onsite' | null): Promise<void> {
  workModeSaving.value = mode ?? 'clear'
  try {
    await auth.updateWorkMode(mode)
    notifySuccess(
      mode ? `You're now marked as working ${mode}.` : 'Work-mode cleared.',
    )
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not update work mode.'))
  } finally {
    workModeSaving.value = null
  }
}

// Reassign modal state
const reassignTicket = ref<ReassignTicketRef | null>(null)
async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/reports/agent-dashboard')
    counts.value = data.data.counts as Counts
    breakdown.value = data.data.breakdown as Breakdown
    recent.value = data.data.recent as RecentRow[]
    generatedAt.value = data.data.generated_at ?? null
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Unable to load dashboard.'))
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
    { key: 'due_today' as FilterKey, label: 'Due today', count: counts.value.due_today },
    { key: 'high' as FilterKey, label: 'High priority', count: counts.value.high_priority_pending },
    { key: 'resolved' as FilterKey, label: 'Resolved (7 days)', count: counts.value.resolved_this_week },
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

function isDueToday(row: RecentRow): boolean {
  if (!row.sla_resolution_due_at) return false
  if (['resolved', 'closed', 'awaiting_requester_confirmation'].includes(row.status)) return false
  const due = new Date(row.sla_resolution_due_at)
  if (Number.isNaN(due.getTime())) return false
  const today = new Date(now.value)
  return (
    due.getFullYear() === today.getFullYear()
    && due.getMonth() === today.getMonth()
    && due.getDate() === today.getDate()
  )
}

function focusFilter(key: FilterKey): void {
  activeFilter.value = key
  requestAnimationFrame(() => {
    recentSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}

function onKpiKeydown(event: KeyboardEvent, key: FilterKey): void {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    focusFilter(key)
  }
}

function canReassignRow(row: RecentRow): boolean {
  return canReassign.value && ticketStatusAllowsReassign(row.status)
}

function openReassign(row: RecentRow): void {
  reassignTicket.value = {
    id: row.id,
    ticket_number: row.ticket_number,
    subject: row.subject,
  }
}

function closeReassign(): void {
  reassignTicket.value = null
}

async function onReassigned(): Promise<void> {
  await load()
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
    case 'due_today':
      return rows.filter(isDueToday)
    case 'high':
      return rows.filter((r) => ['high', 'urgent'].includes(r.priority) && ['open', 'pending', 'in_progress'].includes(r.status))
    case 'resolved':
      return rows.filter((r) => ['resolved', 'closed'].includes(r.status))
    case 'all':
    default:
      return rows
  }
})

const tableCountLabel = computed(() =>
  formatTableCountLabel(
    filteredRecent.value.length,
    filteredRecent.value.length,
    recentPage.value,
    recentItemsPerPage.value,
  ),
)

watch(activeFilter, () => {
  recentPage.value = 1
})

const activeFilterLabel = computed(() => {
  return filterChips.value.find((c) => c.key === activeFilter.value)?.label ?? ''
})

const recentHeaders = computed((): DataTableHeader[] => {
  const cols: DataTableHeader[] = [
    { title: '#', key: 'row_num', sortable: false, width: '52px', align: 'center' },
    { title: 'Ticket', key: 'ticket_number', sortable: false, minWidth: '120px' },
    { title: 'Subject', key: 'subject', sortable: false, minWidth: '200px' },
    { title: 'Requester', key: 'requester_name', sortable: false, minWidth: '140px' },
    { title: 'Status', key: 'status', sortable: false, width: '130px' },
    { title: 'Priority', key: 'priority', sortable: false, width: '110px' },
    { title: 'Activity', key: 'activity', sortable: false, width: '140px' },
  ]
  if (canReassign.value) {
    cols.push({ title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '110px' })
  }
  return cols
})

function recentRowProps(data: { item: RecentRow }) {
  return {
    class: isOverdue(data.item) ? 'hd-data-table-row--overdue' : '',
  }
}

function onRecentRowClick(_event: Event, row: { item: RecentRow }) {
  openTicket(row.item.id)
}

function filteredRowCounter(idx: number): number {
  return rowIndex(recentPage.value, recentItemsPerPage.value, idx)
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

function openTicket(id: number): void {
  void router.push(`/tickets/${id}`)
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

    <UiParentCard title="Agent ticket board" class="mp-kanban-shell agent-kanban">
      <HomeAgentKanban embedded />
    </UiParentCard>

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

    <template v-if="counts">
      <!-- KPI cards -->
      <section class="kpis" aria-label="Key metrics">
        <article
          class="kpi kpi-pending kpi-action"
          :class="{ 'kpi-active': activeFilter === 'pending' }"
          role="button"
          tabindex="0"
          aria-label="Open queue — view tickets you are working on"
          @click="focusFilter('pending')"
          @keydown="onKpiKeydown($event, 'pending')"
        >
          <header>
            <span class="kpi-icon" aria-hidden="true">🗂</span>
            <span class="kpi-label">Open queue</span>
          </header>
          <p class="kpi-value">{{ counts.pending }}</p>
          <p class="kpi-sub">Tickets you're working on</p>
        </article>

        <article
          class="kpi kpi-awaiting kpi-action"
          :class="{ 'kpi-active': activeFilter === 'awaiting' }"
          role="button"
          tabindex="0"
          aria-label="Awaiting confirm — view tickets waiting on requester"
          @click="focusFilter('awaiting')"
          @keydown="onKpiKeydown($event, 'awaiting')"
        >
          <header>
            <span class="kpi-icon" aria-hidden="true">⏳</span>
            <span class="kpi-label">Awaiting confirm</span>
          </header>
          <p class="kpi-value">{{ counts.awaiting_requester_confirmation }}</p>
          <p class="kpi-sub">Resolution sent — waiting on requester</p>
        </article>

        <article
          class="kpi kpi-overdue kpi-action"
          :class="{ alert: counts.overdue > 0, 'kpi-active': activeFilter === 'overdue' }"
          role="button"
          tabindex="0"
          aria-label="Overdue — view tickets past SLA"
          @click="focusFilter('overdue')"
          @keydown="onKpiKeydown($event, 'overdue')"
        >
          <header>
            <span class="kpi-icon" aria-hidden="true">⚠️</span>
            <span class="kpi-label">Overdue</span>
          </header>
          <p class="kpi-value">{{ counts.overdue }}</p>
          <p class="kpi-sub">{{ counts.overdue > 0 ? 'Past SLA — handle now' : 'No SLA breaches' }}</p>
        </article>

        <article
          class="kpi kpi-due-today kpi-action"
          :class="{ 'kpi-active': activeFilter === 'due_today' }"
          role="button"
          tabindex="0"
          aria-label="Due today — view tickets with SLA expiring today"
          @click="focusFilter('due_today')"
          @keydown="onKpiKeydown($event, 'due_today')"
        >
          <header>
            <span class="kpi-icon" aria-hidden="true">📅</span>
            <span class="kpi-label">Due today</span>
          </header>
          <p class="kpi-value">{{ counts.due_today }}</p>
          <p class="kpi-sub">SLA expires before midnight</p>
        </article>

        <article
          class="kpi kpi-high kpi-action"
          :class="{ 'kpi-active': activeFilter === 'high' }"
          role="button"
          tabindex="0"
          aria-label="High priority — view urgent open tickets"
          @click="focusFilter('high')"
          @keydown="onKpiKeydown($event, 'high')"
        >
          <header>
            <span class="kpi-icon" aria-hidden="true">🔥</span>
            <span class="kpi-label">High priority</span>
          </header>
          <p class="kpi-value">{{ counts.high_priority_pending }}</p>
          <p class="kpi-sub">High or urgent — still open</p>
        </article>

        <article
          class="kpi kpi-resolved kpi-action"
          :class="{ 'kpi-active': activeFilter === 'resolved' }"
          role="button"
          tabindex="0"
          aria-label="Resolved — view recently resolved tickets"
          @click="focusFilter('resolved')"
          @keydown="onKpiKeydown($event, 'resolved')"
        >
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
      <section ref="recentSectionRef" aria-labelledby="recent-heading">
        <v-card class="hd-data-table-card" variant="outlined">
          <v-card-text class="hd-data-table-card__head hd-desk-recent-head">
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
                @click="focusFilter(c.key)"
              >
                {{ c.label }} <span class="chip-count">{{ c.count }}</span>
              </button>
            </div>

            <p v-if="filteredRecent.length" class="table-count" role="status">
              Showing <strong>{{ tableCountLabel }}</strong>
              <span v-if="activeFilter !== 'all'" class="table-count-filter"> · filter: {{ activeFilterLabel }}</span>
            </p>
          </v-card-text>

          <v-data-table
            v-if="filteredRecent.length || loading"
            v-model:page="recentPage"
            v-model:items-per-page="recentItemsPerPage"
            class="hd-data-table hd-data-table--clickable"
            :headers="recentHeaders"
            :items="filteredRecent"
            :items-per-page-options="[...recentItemsPerPageOptions]"
            :loading="loading"
            density="compact"
            hover
            item-value="id"
            :row-props="recentRowProps"
            @click:row="onRecentRowClick"
          >
            <template #item.row_num="{ index }">
              <span class="hd-dt-row-num">{{ filteredRowCounter(index) }}</span>
            </template>

            <template #item.ticket_number="{ item }">
              <span class="hd-dt-ticket-num">
                <span class="hd-dt-status-dot" :style="{ background: statusMeta(item.status).color }" aria-hidden="true" />
                {{ item.ticket_number }}
              </span>
            </template>

            <template #item.subject="{ item }">
              <span class="hd-dt-subject-text">{{ item.subject }}</span>
              <span v-if="item.category" class="hd-dt-category">{{ item.category.name }}</span>
            </template>

            <template #item.requester_name="{ item }">
              <div class="hd-dt-person">
                <CbpAvatar size="sm" :name="item.requester_name || 'Requester'" :image-url="null" />
                <span class="hd-dt-person-name">{{ item.requester_name ?? '—' }}</span>
              </div>
            </template>

            <template #item.status="{ item }">
              <span
                class="hd-dt-pill"
                :style="{ background: statusMeta(item.status).bg, color: statusMeta(item.status).color }"
              >
                {{ statusMeta(item.status).label }}
              </span>
            </template>

            <template #item.priority="{ item }">
              <span
                class="hd-dt-pill"
                :style="{ background: priorityMeta(item.priority).bg, color: priorityMeta(item.priority).color }"
              >
                {{ priorityMeta(item.priority).label }}
              </span>
            </template>

            <template #item.activity="{ item }">
              <span class="hd-dt-activity">
                <span>{{ relativeTime(item.created_at) }}</span>
                <span
                  v-if="item.sla_resolution_due_at"
                  class="hd-dt-activity-sla"
                  :class="{ 'is-overdue': isOverdue(item) }"
                >
                  {{ dueLabel(item.sla_resolution_due_at) }}
                </span>
              </span>
            </template>

            <template v-if="canReassign" #item.actions="{ item }">
              <UButton
                v-if="canReassignRow(item)"
                type="button"
                color="neutral"
                variant="outline"
                size="xs"
                label="Reassign"
                title="Reassign this ticket to another agent"
                @click.stop="openReassign(item)"
              />
              <span v-else class="hd-dt-empty">›</span>
            </template>

            <template #loading>
              <div class="hd-dt-loading">Loading tickets…</div>
            </template>
          </v-data-table>

          <v-card-text v-else>
            <p class="muted">
              {{ recent.length === 0 ? 'No tickets assigned to you yet.' : 'No tickets match this filter.' }}
            </p>
          </v-card-text>
        </v-card>
      </section>
    </template>

    <p v-else class="muted">Loading…</p>

    <!-- Reassign modal -->
    <TicketReassignModal
      :ticket="reassignTicket"
      @close="closeReassign"
      @reassigned="onReassigned"
    />
  </div>
</template>

<style scoped>
.agent-dash {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.agent-kanban {
  margin-bottom: 0.25rem;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
  padding: 1rem 1.1rem;
  border: 1px solid var(--hd-line);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  position: relative;
  overflow: hidden;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.kpi:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.kpi-action {
  cursor: pointer;
  user-select: none;
}
.kpi-action:focus-visible {
  outline: 2px solid #0d7a3a;
  outline-offset: 2px;
}
.kpi-active {
  border-color: var(--kpi-accent, #119a48);
  box-shadow: 0 0 0 2px var(--kpi-accent-soft, rgba(17, 154, 72, 0.2)), 0 6px 18px rgba(15, 23, 42, 0.08);
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
  border-radius: 4px;
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
  border-radius: 4px;
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
.hd-desk-recent-head {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
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
  border: 1px solid var(--hd-line);
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

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
/* Toast */
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #0f172a;
  color: #fff;
  padding: 0.75rem 1rem;
  border-radius: 4px;
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

.muted {
  color: #64748b;
}
.err {
  margin: 0;
  padding: 0.7rem 0.9rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 4px;
}
</style>
