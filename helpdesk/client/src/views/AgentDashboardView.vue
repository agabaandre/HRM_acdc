<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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

interface KpiCardDef {
  key: FilterKey
  label: string
  icon: string
  value: number
  sub: string
  accent: string
  accentSoft: string
  alert?: boolean
}

const kpiCards = computed((): KpiCardDef[] => {
  const c = counts.value
  if (!c) return []
  return [
    {
      key: 'pending',
      label: 'Open queue',
      icon: '🗂',
      value: c.pending,
      sub: "Tickets you're working on",
      accent: '#2563eb',
      accentSoft: '#dbeafe',
    },
    {
      key: 'awaiting',
      label: 'Awaiting confirm',
      icon: '⏳',
      value: c.awaiting_requester_confirmation,
      sub: 'Resolution sent — waiting on requester',
      accent: '#d97706',
      accentSoft: '#fef3c7',
    },
    {
      key: 'overdue',
      label: 'Overdue',
      icon: '⚠️',
      value: c.overdue,
      sub: c.overdue > 0 ? 'Past SLA — handle now' : 'No SLA breaches',
      accent: c.overdue > 0 ? '#dc2626' : '#94a3b8',
      accentSoft: c.overdue > 0 ? '#fee2e2' : '#e2e8f0',
      alert: c.overdue > 0,
    },
    {
      key: 'due_today',
      label: 'Due today',
      icon: '📅',
      value: c.due_today,
      sub: 'SLA expires before midnight',
      accent: '#7c3aed',
      accentSoft: '#ede9fe',
    },
    {
      key: 'high',
      label: 'High priority',
      icon: '🔥',
      value: c.high_priority_pending,
      sub: 'High or urgent — still open',
      accent: '#ea580c',
      accentSoft: '#ffedd5',
    },
    {
      key: 'resolved',
      label: 'Resolved (7 days)',
      icon: '✅',
      value: c.resolved_this_week,
      sub: `${c.new_today} new today · ${c.total_received} all-time`,
      accent: '#16a34a',
      accentSoft: '#dcfce7',
    },
  ]
})

function toggleWorkMode(mode: 'remote' | 'onsite'): void {
  void setWorkMode(currentWorkMode.value === mode ? null : mode)
}

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

    <template v-if="counts">
      <v-sheet class="dash-hero" rounded="lg" elevation="3">
        <v-row align="center" justify="space-between" class="dash-hero__row">
          <v-col cols="12" md="auto">
            <p class="dash-greet">
              {{ greeting }}
              <span class="dash-wave" aria-hidden="true">👋</span>
            </p>
            <p class="dash-date">{{ todayLabel }}</p>
          </v-col>
          <v-col cols="12" md="auto">
            <div class="dash-tools">
              <div class="work-mode" role="group" aria-label="Set your current location">
                <span class="work-mode-label">Working from</span>
                <v-btn-toggle
                  class="work-mode-toggle"
                  divided
                  rounded="pill"
                  density="compact"
                  variant="flat"
                  :model-value="currentWorkMode"
                  :disabled="workModeSaving !== null"
                >
                  <v-btn
                    value="remote"
                    size="small"
                    :active="currentWorkMode === 'remote'"
                    @click="toggleWorkMode('remote')"
                  >
                    <span class="seg-dot remote" aria-hidden="true" />
                    {{ workModeSaving === 'remote' ? 'Saving…' : 'Remote' }}
                  </v-btn>
                  <v-btn
                    value="onsite"
                    size="small"
                    :active="currentWorkMode === 'onsite'"
                    @click="toggleWorkMode('onsite')"
                  >
                    <span class="seg-dot onsite" aria-hidden="true" />
                    {{ workModeSaving === 'onsite' ? 'Saving…' : 'Onsite' }}
                  </v-btn>
                </v-btn-toggle>
              </div>
              <span v-if="generatedLabel" class="dash-updated">{{ generatedLabel }}</span>
              <v-btn
                variant="outlined"
                size="small"
                class="dash-refresh-btn"
                :loading="loading"
                prepend-icon="mdi-refresh"
                @click="load"
              >
                Refresh
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-sheet>

      <v-row class="kpis kpis--strip" dense aria-label="Key metrics">
        <v-col
          v-for="kpi in kpiCards"
          :key="kpi.key"
          cols="6"
          sm="4"
          md="2"
        >
          <v-card
            class="kpi-card"
            :class="{
              'kpi-card--active': activeFilter === kpi.key,
              'kpi-card--alert': kpi.alert,
            }"
            variant="outlined"
            :style="{
              '--kpi-accent': kpi.accent,
              '--kpi-accent-soft': kpi.accentSoft,
            }"
            role="button"
            tabindex="0"
            :aria-label="`${kpi.label} — ${kpi.sub}`"
            @click="focusFilter(kpi.key)"
            @keydown="onKpiKeydown($event, kpi.key)"
          >
            <v-card-text class="kpi-card__body">
              <div class="kpi-card__head">
                <v-avatar size="28" rounded="sm" class="kpi-card__icon" :color="kpi.accentSoft">
                  <span aria-hidden="true">{{ kpi.icon }}</span>
                </v-avatar>
                <span class="kpi-card__label">{{ kpi.label }}</span>
              </div>
              <p class="kpi-card__value">{{ kpi.value }}</p>
              <p class="kpi-card__sub">{{ kpi.sub }}</p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <HomeAgentKanban embedded hide-greeting class="agent-kanban" />

      <v-row v-if="breakdown" class="charts" aria-label="Workload breakdown">
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="chart-card">
            <v-card-item>
              <v-card-title class="chart-card__title">By status</v-card-title>
              <template #append>
                <v-chip size="small" variant="tonal">{{ totalForStatusBar }} tickets</v-chip>
              </template>
            </v-card-item>
            <v-card-text>
              <div v-if="statusSegments.length" class="bar" role="img" aria-label="Tickets grouped by status">
                <span
                  v-for="s in statusSegments"
                  :key="s.key"
                  class="bar-seg"
                  :style="{ width: s.pct + '%', background: s.color }"
                  :title="`${s.label}: ${s.count}`"
                />
              </div>
              <v-list v-if="statusSegments.length" density="compact" class="legend-list">
                <v-list-item v-for="s in statusSegments" :key="s.key" class="legend-item">
                  <template #prepend>
                    <span class="dot" :style="{ background: s.color }" />
                  </template>
                  <v-list-item-title class="legend-label">{{ s.label }}</v-list-item-title>
                  <template #append>
                    <span class="legend-count">{{ s.count }}</span>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else class="muted">No tickets yet.</p>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card variant="outlined" class="chart-card">
            <v-card-item>
              <v-card-title class="chart-card__title">By priority</v-card-title>
              <template #append>
                <v-chip size="small" variant="tonal">{{ totalForPriorityBar }} tickets</v-chip>
              </template>
            </v-card-item>
            <v-card-text>
              <div v-if="prioritySegments.length" class="bar" role="img" aria-label="Tickets grouped by priority">
                <span
                  v-for="s in prioritySegments"
                  :key="s.key"
                  class="bar-seg"
                  :style="{ width: s.pct + '%', background: s.color }"
                  :title="`${s.label}: ${s.count}`"
                />
              </div>
              <v-list v-if="prioritySegments.length" density="compact" class="legend-list">
                <v-list-item v-for="s in prioritySegments" :key="s.key" class="legend-item">
                  <template #prepend>
                    <span class="dot" :style="{ background: s.color }" />
                  </template>
                  <v-list-item-title class="legend-label">{{ s.label }}</v-list-item-title>
                  <template #append>
                    <span class="legend-count">{{ s.count }}</span>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else class="muted">No tickets yet.</p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <section ref="recentSectionRef" aria-labelledby="recent-heading">
        <v-card class="hd-data-table-card" variant="outlined">
          <v-card-text class="hd-data-table-card__head hd-desk-recent-head">
            <header class="recent-head">
              <div>
                <h2 id="recent-heading">Recent tickets</h2>
                <p class="recent-sub">Newest 25 tickets assigned to you</p>
              </div>
              <v-btn
                to="/tickets"
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-arrow-right"
              >
                See all tickets
              </v-btn>
            </header>

            <v-chip-group
              v-model="activeFilter"
              mandatory
              selected-class="hd-filter-chip--active"
              class="hd-filter-chips"
            >
              <v-chip
                v-for="c in filterChips"
                :key="c.key"
                :value="c.key"
                filter
                variant="outlined"
                size="small"
                :class="{
                  'hd-filter-chip--warn': c.key === 'overdue' && c.count > 0,
                  'hd-filter-chip--hot': c.key === 'high' && c.count > 0,
                }"
                @click="focusFilter(c.key)"
              >
                {{ c.label }}
                <span class="hd-filter-chip-count">{{ c.count }}</span>
              </v-chip>
            </v-chip-group>

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

    <v-skeleton-loader v-else type="article, table" class="agent-dash-loading" />

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
  margin-top: 0.25rem;
}

.agent-dash-loading {
  border-radius: 4px;
}

.dash-hero {
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #0d7a3a 0%, #119a48 100%) !important;
  color: #fff;
}

.dash-hero__row {
  margin: 0;
}

.dash-greet {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.3;
}

.dash-wave {
  display: inline-block;
  margin-left: 0.25rem;
}

.dash-date {
  margin: 0.2rem 0 0;
  font-size: 0.88rem;
  opacity: 0.88;
}

.dash-tools {
  display: flex;
  gap: 0.65rem;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.dash-updated {
  font-size: 0.78rem;
  opacity: 0.9;
}

.dash-refresh-btn {
  border-color: rgba(255, 255, 255, 0.45) !important;
  color: #fff !important;
}

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

.work-mode-toggle {
  background: rgba(15, 23, 42, 0.32) !important;
}

.work-mode-toggle :deep(.v-btn) {
  color: rgba(255, 255, 255, 0.9) !important;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 600;
  font-size: 0.82rem;
}

.work-mode-toggle :deep(.v-btn--active) {
  background: #fff !important;
  color: #0f172a !important;
}

.seg-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  display: inline-block;
  margin-right: 0.15rem;
}

.seg-dot.remote {
  background: #38bdf8;
}

.seg-dot.onsite {
  background: #22c55e;
}

.kpis {
  margin: 0;
}

@media (min-width: 960px) {
  .kpis--strip {
    flex-wrap: nowrap;
  }

  .kpis--strip > .v-col {
    min-width: 0;
  }

  .kpis--strip .kpi-card__body {
    padding: 0.75rem 0.65rem !important;
  }

  .kpis--strip .kpi-card__head {
    margin-bottom: 0.35rem;
  }

  .kpis--strip .kpi-card__label {
    font-size: 0.68rem;
    line-height: 1.2;
  }

  .kpis--strip .kpi-card__value {
    font-size: 1.55rem;
  }

  .kpis--strip .kpi-card__sub {
    font-size: 0.72rem;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .kpis--strip .kpi-card__icon.v-avatar {
    width: 24px !important;
    height: 24px !important;
    font-size: 0.85rem;
  }
}

.kpi-card {
  cursor: pointer;
  user-select: none;
  position: relative;
  overflow: hidden;
  transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
  border-color: var(--hd-line) !important;
}

.kpi-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--kpi-accent, #119a48);
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
}

.kpi-card:focus-visible {
  outline: 2px solid #0d7a3a;
  outline-offset: 2px;
}

.kpi-card--active {
  border-color: var(--kpi-accent, #119a48) !important;
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--kpi-accent, #119a48) 22%, transparent);
}

.kpi-card--alert {
  background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
}

.kpi-card--alert .kpi-card__value {
  color: #b91c1c;
}

.kpi-card__body {
  padding: 1rem 1.1rem !important;
}

.kpi-card__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.45rem;
}

.kpi-card__icon {
  font-size: 0.95rem;
}

.kpi-card__label {
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
}

.kpi-card__value {
  font-size: 2rem;
  font-weight: 800;
  margin: 0;
  color: #0f172a;
  line-height: 1;
}

.kpi-card__sub {
  margin: 0.45rem 0 0;
  font-size: 0.82rem;
  color: #64748b;
}

.charts {
  margin: 0;
}

.chart-card__title {
  font-size: 0.95rem !important;
  font-weight: 700 !important;
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

.legend-list {
  padding: 0.5rem 0 0 !important;
  background: transparent !important;
}

.legend-item {
  min-height: 32px !important;
  padding-inline: 0 !important;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  flex-shrink: 0;
  margin-right: 0.5rem;
}

.legend-label {
  font-size: 0.82rem !important;
  color: #334155;
}

.legend-count {
  font-weight: 800;
  color: #0f172a;
}

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

.hd-filter-chips {
  gap: 0.35rem;
}

.hd-filter-chips :deep(.v-chip) {
  font-weight: 600;
}

.hd-filter-chip-count {
  margin-left: 0.35rem;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  font-weight: 800;
  font-size: 0.74rem;
}

.hd-filter-chips :deep(.hd-filter-chip--active) {
  background: #0d7a3a !important;
  color: #fff !important;
  border-color: #0d7a3a !important;
}

.hd-filter-chips :deep(.hd-filter-chip--active .hd-filter-chip-count) {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.hd-filter-chips :deep(.hd-filter-chip--warn) {
  border-color: #fecaca !important;
  color: #b91c1c !important;
}

.hd-filter-chips :deep(.hd-filter-chip--hot) {
  border-color: #fed7aa !important;
  color: #9a3412 !important;
}

.muted {
  color: #64748b;
}
</style>
