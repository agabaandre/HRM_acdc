<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api } from '../lib/api'
import CbpAvatar from '../components/common/CbpAvatar.vue'

interface Volumes {
  open: number
  pending: number
  in_progress: number
  awaiting_confirm: number
  unassigned: number
  created_today: number
  resolved_today: number
  closed_today: number
  total_active: number
}
interface Wait {
  avg_first_response_minutes: number | null
  longest_open_minutes: number | null
  oldest_open_ticket_number: string | null
  oldest_open_priority: string | null
  window_label?: string
}
interface Sla {
  sample_window_days: number
  response_within_sla_pct: number | null
  resolution_within_sla_pct: number | null
  response_sample_size: number
  resolution_sample_size: number
  breached_pending: number
}
interface CategoryRow {
  id: number
  name: string
  open: number
}
interface DutyStationRow {
  name: string
  open: number
  closed_this_week: number
  overtime: number
}
interface AgentClosureRow {
  id: number
  name: string
  avatar_url?: string | null
  closed: number
}
interface WorkloadRow {
  id: number
  name: string
  avatar_url?: string | null
  open: number
  in_progress?: number
}
interface InProgressRow {
  id: number
  name: string
  avatar_url?: string | null
  in_progress: number
}
interface TrendDay {
  day: string
  created: number
  resolved: number
}
interface AgentLeaderboardAgent {
  id: number
  name: string
  avatar_url?: string | null
  tickets_worked: number
  avg_response_minutes: number | null
  score: number
}
interface AgentLeaderboard {
  period_label: string
  weights: { tickets: number; response: number }
  agent: AgentLeaderboardAgent | null
}
interface ScreenData {
  generated_at: string
  volumes: Volumes
  wait: Wait
  sla: Sla
  by_priority: { urgent: number; high: number; medium: number; low: number }
  by_category: CategoryRow[]
  by_duty_station: DutyStationRow[]
  closures_by_agent_month: AgentClosureRow[]
  workload: WorkloadRow[]
  in_progress_workload: InProgressRow[]
  trend: TrendDay[]
  agent_of_week: AgentLeaderboard
  agent_of_month: AgentLeaderboard
  csat: { avg_score: number | null; responses: number; note?: string }
}

const data = ref<ScreenData | null>(null)
const lastFetchedAt = ref<number | null>(null)
const consecutiveErrors = ref(0)
const isStale = ref(false)
const clock = ref(new Date())
const theme = ref<'dark' | 'light'>('dark')
const isBrowserFullscreen = ref(false)
let pollTimer: number | undefined
let clockTimer: number | undefined
let staleTimer: number | undefined

const REFRESH_INTERVAL_MS = 15000
const STALE_THRESHOLD_MS = 60000
const THEME_STORAGE_KEY = 'helpdesk.screen.theme'

async function fetchScreen(): Promise<void> {
  try {
    const { data: payload } = await api.get<{ data: ScreenData }>('/api/v1/public/screen')
    data.value = payload.data
    lastFetchedAt.value = Date.now()
    consecutiveErrors.value = 0
    isStale.value = false
  } catch (e) {
    consecutiveErrors.value += 1
  }
}

function checkStaleness(): void {
  if (!lastFetchedAt.value) return
  isStale.value = Date.now() - lastFetchedAt.value > STALE_THRESHOLD_MS
}

const fmtMinutes = (m: number | null): string => {
  if (m === null || m === undefined || Number.isNaN(m)) return '—'
  if (m < 60) return `${Math.round(m)}m`
  if (m < 60 * 24) return `${Math.round(m / 60)}h`
  return `${Math.floor(m / (60 * 24))}d ${Math.round((m % (60 * 24)) / 60)}h`
}

const clockTime = computed(() => clock.value.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' }))
const clockDate = computed(() => clock.value.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }))

const totalPriorities = computed(() => {
  const p = data.value?.by_priority
  return p ? p.urgent + p.high + p.medium + p.low : 0
})

const priorityBars = computed(() => {
  const p = data.value?.by_priority
  if (!p) return []
  const total = totalPriorities.value || 1
  return [
    { key: 'urgent', label: 'Urgent', color: '#ef4444', count: p.urgent, pct: (p.urgent / total) * 100 },
    { key: 'high', label: 'High', color: '#f97316', count: p.high, pct: (p.high / total) * 100 },
    { key: 'medium', label: 'Medium', color: '#3b82f6', count: p.medium, pct: (p.medium / total) * 100 },
    { key: 'low', label: 'Low', color: '#64748b', count: p.low, pct: (p.low / total) * 100 },
  ]
})

const trendMaxValue = computed(() => {
  const t = data.value?.trend ?? []
  let max = 1
  for (const row of t) {
    max = Math.max(max, row.created, row.resolved)
  }
  return max
})

const maxCategory = computed(() => {
  const c = data.value?.by_category ?? []
  return c.reduce((acc, r) => Math.max(acc, r.open), 0) || 1
})

const agentClosures = computed(() => data.value?.closures_by_agent_month ?? [])
const agentClosuresTicker = computed(() => {
  const rows = agentClosures.value
  return rows.length > 0 ? [...rows, ...rows] : []
})
const agentClosuresDuration = computed(() => {
  const count = agentClosures.value.length
  if (count < 1) return '0s'
  return `${Math.max(28, count * 4)}s`
})

const agentOpenTickets = computed(() => data.value?.workload ?? [])
const agentOpenTicketsTicker = computed(() => {
  const rows = agentOpenTickets.value
  return rows.length > 0 ? [...rows, ...rows] : []
})
const agentOpenTicketsDuration = computed(() => {
  const count = agentOpenTickets.value.length
  if (count < 1) return '0s'
  return `${Math.max(28, count * 4)}s`
})

const inProgressAgents = computed(() => data.value?.in_progress_workload ?? [])
const inProgressAgentsTicker = computed(() => {
  const rows = inProgressAgents.value
  return rows.length > 0 ? [...rows, ...rows] : []
})
const inProgressAgentsDuration = computed(() => {
  const count = inProgressAgents.value.length
  if (count < 1) return '0s'
  return `${Math.max(28, count * 4)}s`
})

const statusPipeline = computed(() => {
  const v = data.value?.volumes
  if (!v) return []
  const items = [
    { key: 'open', label: 'Open', count: v.open, color: '#2563eb' },
    { key: 'pending', label: 'Pending', count: v.pending, color: '#4f46e5' },
    { key: 'in_progress', label: 'In progress', count: v.in_progress, color: '#7c3aed' },
    { key: 'awaiting', label: 'Awaiting confirm', count: v.awaiting_confirm, color: '#b45309' },
  ]
  const total = items.reduce((sum, item) => sum + item.count, 0) || 1
  return items.map((item) => ({ ...item, pct: (item.count / total) * 100 }))
})

const lastUpdatedLabel = computed(() => {
  if (!lastFetchedAt.value) return 'syncing…'
  const ageSec = Math.round((Date.now() - lastFetchedAt.value) / 1000)
  if (ageSec < 5) return 'live'
  return `${ageSec}s ago`
})

function initTheme(): void {
  const stored = window.localStorage.getItem(THEME_STORAGE_KEY)
  if (stored === 'light' || stored === 'dark') {
    theme.value = stored
    return
  }
  // Dark is the default mode for TV/lobby display.
  theme.value = 'dark'
}

function applyTheme(): void {
  // Theme is applied via root element class binding in this component.
}

function setTheme(next: 'dark' | 'light'): void {
  theme.value = next
  window.localStorage.setItem(THEME_STORAGE_KEY, next)
  applyTheme()
}

function syncFullscreenState(): void {
  const doc = document as Document & { webkitFullscreenElement?: Element | null }
  isBrowserFullscreen.value = !!(document.fullscreenElement ?? doc.webkitFullscreenElement)
}

async function toggleFullscreen(): Promise<void> {
  try {
    const docEl = document.documentElement as HTMLElement & {
      webkitRequestFullscreen?: () => Promise<void> | void
    }
    const doc = document as Document & {
      webkitExitFullscreen?: () => Promise<void> | void
      webkitFullscreenElement?: Element | null
    }
    const isFs = !!(document.fullscreenElement ?? doc.webkitFullscreenElement)
    if (!isFs) {
      if (docEl.requestFullscreen) await docEl.requestFullscreen()
      else if (docEl.webkitRequestFullscreen) await docEl.webkitRequestFullscreen()
    } else if (document.exitFullscreen) await document.exitFullscreen()
    else if (doc.webkitExitFullscreen) await doc.webkitExitFullscreen()
  } catch {
    // User gesture denied or unsupported — ignore.
  }
}

function onFullscreenChange(): void {
  syncFullscreenState()
}

onMounted(() => {
  initTheme()
  applyTheme()
  syncFullscreenState()
  document.addEventListener('fullscreenchange', onFullscreenChange)
  document.addEventListener('webkitfullscreenchange', onFullscreenChange)
  void fetchScreen()
  pollTimer = window.setInterval(fetchScreen, REFRESH_INTERVAL_MS)
  clockTimer = window.setInterval(() => {
    clock.value = new Date()
  }, 1000)
  staleTimer = window.setInterval(checkStaleness, 5000)
  document.documentElement.classList.add('screen-mode')
  document.body.classList.add('screen-mode')
})

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  document.removeEventListener('webkitfullscreenchange', onFullscreenChange)
  if (pollTimer) window.clearInterval(pollTimer)
  if (clockTimer) window.clearInterval(clockTimer)
  if (staleTimer) window.clearInterval(staleTimer)
  document.documentElement.classList.remove('screen-mode')
  document.body.classList.remove('screen-mode')
})
</script>

<template>
  <div
    class="screen"
    :class="[`theme-${theme}`, { 'is-browser-fullscreen': isBrowserFullscreen }]"
  >
    <!-- Top bar (facility TV layout: brand · live · clock · toolbar) -->
    <header class="screen-bar">
      <div class="screen-brand">
        <span class="brand-dot" />
        <div class="screen-brand-text">
          <p class="brand-title">Africa CDC · IT Service Desk</p>
          <p class="brand-sub">Live operations dashboard</p>
        </div>
      </div>
      <div class="screen-live-pill" aria-hidden="true">
        <span class="live-dot" :class="{ stale: isStale || consecutiveErrors > 1 }" />
        {{ isStale ? 'Reconnecting' : 'Live' }}
      </div>
      <div class="screen-clock">
        <p class="clock-time">{{ clockTime }}</p>
        <p class="clock-date">{{ clockDate }}</p>
      </div>
      <div class="screen-toolbar">
        <div class="theme-switch" role="group" aria-label="Dashboard theme">
          <button type="button" class="screen-toolbar-btn" :class="{ active: theme === 'dark' }" @click="setTheme('dark')">Dark</button>
          <button type="button" class="screen-toolbar-btn" :class="{ active: theme === 'light' }" @click="setTheme('light')">Light</button>
        </div>
        <button
          type="button"
          class="screen-toolbar-btn screen-toolbar-btn--icon"
          :title="isBrowserFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
          :aria-label="isBrowserFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
          @click="toggleFullscreen"
        >
          <i :class="isBrowserFullscreen ? 'bx bx-exit-fullscreen' : 'bx bx-fullscreen'" aria-hidden="true" />
        </button>
        <span class="status-label">{{ lastUpdatedLabel }}</span>
      </div>
    </header>

    <main v-if="data" class="screen-grid">
      <!-- KPI tiles -->
      <section class="kpis">
        <article class="kpi kpi-open">
          <p class="kpi-label">Active tickets</p>
          <p class="kpi-value">{{ data.volumes.total_active }}</p>
          <p class="kpi-sub">
            <span>{{ data.volumes.open }} open</span>
            <span class="dot">·</span>
            <span>{{ data.volumes.pending }} pending</span>
          </p>
        </article>

        <article class="kpi kpi-inprogress" :class="{ pulse: data.volumes.in_progress > 0 }">
          <p class="kpi-label">In progress</p>
          <p class="kpi-value">{{ data.volumes.in_progress }}</p>
          <p class="kpi-sub">Being worked now</p>
        </article>

        <article class="kpi kpi-agent-week">
          <p class="kpi-label">Agent of the week</p>
          <template v-if="data.agent_of_week?.agent">
            <div class="kpi-agent-row">
              <CbpAvatar :name="data.agent_of_week.agent.name" :image-url="data.agent_of_week.agent.avatar_url ?? null" size="lg" />
              <p class="kpi-value kpi-value--name">{{ data.agent_of_week.agent.name }}</p>
            </div>
            <p class="kpi-sub">
              {{ data.agent_of_week.agent.tickets_worked }} tickets worked
              <span class="dot">·</span>
              {{ fmtMinutes(data.agent_of_week.agent.avg_response_minutes) }} avg response
            </p>
          </template>
          <template v-else>
            <p class="kpi-value">—</p>
            <p class="kpi-sub">No qualifying activity this week</p>
          </template>
        </article>

        <article class="kpi kpi-agent-month">
          <p class="kpi-label">Agent of the month</p>
          <template v-if="data.agent_of_month?.agent">
            <div class="kpi-agent-row">
              <CbpAvatar :name="data.agent_of_month.agent.name" :image-url="data.agent_of_month.agent.avatar_url ?? null" size="lg" />
              <p class="kpi-value kpi-value--name">{{ data.agent_of_month.agent.name }}</p>
            </div>
            <p class="kpi-sub">
              {{ data.agent_of_month.agent.tickets_worked }} tickets worked
              <span class="dot">·</span>
              {{ fmtMinutes(data.agent_of_month.agent.avg_response_minutes) }} avg response
            </p>
          </template>
          <template v-else>
            <p class="kpi-value">—</p>
            <p class="kpi-sub">No qualifying activity this month</p>
          </template>
        </article>

        <article class="kpi kpi-response">
          <p class="kpi-label">Avg response time</p>
          <p class="kpi-value">{{ fmtMinutes(data.wait.avg_first_response_minutes) }}</p>
          <p class="kpi-sub">{{ data.wait.window_label }}</p>
        </article>

        <article class="kpi kpi-new">
          <p class="kpi-label">New today</p>
          <p class="kpi-value">{{ data.volumes.created_today }}</p>
          <p class="kpi-sub">Logged since midnight</p>
        </article>

        <article class="kpi kpi-resolved">
          <p class="kpi-label">Resolved today</p>
          <p class="kpi-value">{{ data.volumes.resolved_today }}</p>
          <p class="kpi-sub">{{ data.volumes.closed_today }} closed</p>
        </article>
      </section>

      <!-- Queue status pipeline -->
      <section class="card status-pipeline-card">
        <header class="card-head">
          <h2>Queue breakdown</h2>
          <span class="card-sub">{{ data.volumes.total_active }} active</span>
        </header>
        <div class="status-pipeline">
          <div class="status-pipeline-bar" role="img" :aria-label="`Queue: ${statusPipeline.map((s) => `${s.count} ${s.label}`).join(', ')}`">
            <span
              v-for="seg in statusPipeline"
              :key="seg.key"
              class="status-seg"
              :style="{ width: seg.pct + '%', background: seg.color }"
              :title="`${seg.label}: ${seg.count}`"
            />
          </div>
          <ul class="status-legend">
            <li v-for="seg in statusPipeline" :key="seg.key" class="status-legend-item">
              <span class="status-swatch" :style="{ background: seg.color }" />
              <span class="status-legend-label">{{ seg.label }}</span>
              <strong class="status-legend-count">{{ seg.count }}</strong>
            </li>
          </ul>
        </div>
      </section>

      <!-- Wait times -->
      <section class="card wait-card">
        <header class="card-head">
          <h2>Traffic &amp; wait times</h2>
        </header>
        <div class="wait-row">
          <div class="wait-block">
            <p class="wait-label">Avg first response</p>
            <p class="wait-value">{{ fmtMinutes(data.wait.avg_first_response_minutes) }}</p>
            <p class="wait-meta">{{ data.wait.window_label }}</p>
          </div>
          <div class="wait-block">
            <p class="wait-label">Longest open</p>
            <p class="wait-value wait-warn">{{ fmtMinutes(data.wait.longest_open_minutes) }}</p>
            <p v-if="data.wait.oldest_open_ticket_number" class="wait-meta">
              {{ data.wait.oldest_open_ticket_number }} · {{ data.wait.oldest_open_priority }}
            </p>
            <p v-else class="wait-meta">—</p>
          </div>
        </div>
      </section>

      <!-- Duty station breakdown -->
      <section class="card duty-card">
        <header class="card-head">
          <h2>Tickets by duty station</h2>
          <span class="card-sub">Open · closed this week · overtime</span>
        </header>
        <div v-if="data.by_duty_station.length" class="duty-table-wrap">
          <table class="duty-table">
            <thead>
              <tr>
                <th>Duty station</th>
                <th class="num">Open</th>
                <th class="num">Closed (wk)</th>
                <th class="num">Overtime</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in data.by_duty_station" :key="`${row.name}-${index}`">
                <td class="duty-name">{{ row.name }}</td>
                <td class="num">
                  <span class="duty-pill open">{{ row.open }}</span>
                </td>
                <td class="num">
                  <span class="duty-pill closed">{{ row.closed_this_week }}</span>
                </td>
                <td class="num">
                  <span class="duty-pill overtime" :class="{ hot: row.overtime > 0 }">{{ row.overtime }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="muted">No ticket activity by duty station.</p>
      </section>

      <!-- Category breakdown -->
      <section class="card category-card">
        <header class="card-head">
          <h2>Open by category</h2>
          <span class="card-sub">Top {{ data.by_category.length }}</span>
        </header>
        <ul v-if="data.by_category.length" class="category-list">
          <li v-for="c in data.by_category" :key="c.id" class="cat-row">
            <span class="cat-name">{{ c.name }}</span>
            <span class="cat-bar">
              <span class="cat-fill" :style="{ width: ((c.open / maxCategory) * 100) + '%' }" />
            </span>
            <span class="cat-count">{{ c.open }}</span>
          </li>
        </ul>
        <p v-else class="muted">No open tickets across categories.</p>
      </section>

      <!-- Priority matrix -->
      <section class="card priority-card">
        <header class="card-head">
          <h2>Priority matrix</h2>
          <span class="card-sub">{{ totalPriorities }} active</span>
        </header>
        <div class="priority-grid">
          <article v-for="p in priorityBars" :key="p.key" class="priority-cell" :style="{ '--p-color': p.color }">
            <div class="priority-cell-head">
              <span class="priority-name">{{ p.label }}</span>
              <span class="priority-count">{{ p.count }}</span>
            </div>
            <div class="priority-track">
              <span class="priority-fill" :style="{ width: p.pct + '%' }" />
            </div>
          </article>
        </div>
      </section>

      <!-- Agent closures + in-progress + open tickets tickers -->
      <section class="card closures-card">
        <header class="card-head">
          <h2>Tickets closed by agent</h2>
          <span class="card-sub">This month · {{ agentClosures.length }} agents</span>
        </header>
        <div v-if="agentClosures.length" class="ticker-viewport" aria-hidden="true">
          <div
            class="ticker-track"
            :style="{ animationDuration: agentClosuresDuration }"
          >
            <article
              v-for="(agent, index) in agentClosuresTicker"
              :key="`closed-${agent.id}-${index}`"
              class="ticker-card"
            >
              <CbpAvatar :name="agent.name" :image-url="agent.avatar_url ?? null" size="sm" />
              <div class="ticker-meta">
                <p class="ticker-name">{{ agent.name }}</p>
                <p class="ticker-count">{{ agent.closed }} closed</p>
              </div>
            </article>
          </div>
        </div>
        <p v-else class="muted">No agent closures recorded this month yet.</p>
      </section>

      <section class="card in-progress-card">
        <header class="card-head">
          <h2>Tickets in progress</h2>
          <span class="card-sub">{{ data.volumes.in_progress }} active · {{ inProgressAgents.length }} agents</span>
        </header>
        <div v-if="inProgressAgents.length" class="ticker-viewport" aria-hidden="true">
          <div
            class="ticker-track"
            :style="{ animationDuration: inProgressAgentsDuration }"
          >
            <article
              v-for="(agent, index) in inProgressAgentsTicker"
              :key="`progress-${agent.id}-${index}`"
              class="ticker-card ticker-card--progress"
            >
              <CbpAvatar :name="agent.name" :image-url="agent.avatar_url ?? null" size="sm" />
              <div class="ticker-meta">
                <p class="ticker-name">{{ agent.name }}</p>
                <p class="ticker-count">{{ agent.in_progress }} in progress</p>
              </div>
            </article>
          </div>
        </div>
        <p v-else class="muted">No tickets in progress right now.</p>
      </section>

      <section class="card open-tickets-card">
        <header class="card-head">
          <h2>Agent workload</h2>
          <span class="card-sub">All active assigned · {{ agentOpenTickets.length }} agents</span>
        </header>
        <div v-if="agentOpenTickets.length" class="ticker-viewport" aria-hidden="true">
          <div
            class="ticker-track"
            :style="{ animationDuration: agentOpenTicketsDuration }"
          >
            <article
              v-for="(agent, index) in agentOpenTicketsTicker"
              :key="`open-${agent.id}-${index}`"
              class="ticker-card"
            >
              <CbpAvatar :name="agent.name" :image-url="agent.avatar_url ?? null" size="sm" />
              <div class="ticker-meta">
                <p class="ticker-name">{{ agent.name }}</p>
                <p class="ticker-count">
                  {{ agent.open }} active
                  <span v-if="(agent.in_progress ?? 0) > 0" class="ticker-sub"> · {{ agent.in_progress }} in progress</span>
                </p>
              </div>
            </article>
          </div>
        </div>
        <p v-else class="muted">No assigned open tickets right now.</p>
      </section>

      <!-- 30-day trend -->
      <section class="card trend-card">
        <header class="card-head">
          <h2>30-day trend</h2>
          <span class="card-sub">
            <span class="legend-pip" style="background:#3b82f6" /> Created
            <span class="legend-pip" style="background:#16a34a" /> Resolved
          </span>
        </header>
        <div class="trend-bars">
          <div v-for="(d, i) in data.trend" :key="d.day" class="trend-col" :title="`${d.day}: ${d.created} new, ${d.resolved} resolved`">
            <span class="trend-bar trend-bar-created" :style="{ height: ((d.created / trendMaxValue) * 100) + '%' }" />
            <span class="trend-bar trend-bar-resolved" :style="{ height: ((d.resolved / trendMaxValue) * 100) + '%' }" />
            <span v-if="i % 5 === 0" class="trend-tick">{{ d.day.slice(5) }}</span>
          </div>
        </div>
      </section>
    </main>

    <div v-else class="screen-loading">
      <p>Loading dashboard…</p>
    </div>

    <footer class="screen-foot">
      <span>Updates every {{ REFRESH_INTERVAL_MS / 1000 }}s · Aggregate metrics only · No personal data displayed</span>
    </footer>
  </div>
</template>

<style>
/* Screen route — scrollable like facility TV; fullscreen uses browser API. */
html.screen-mode,
body.screen-mode {
  margin: 0;
  padding: 0;
  min-height: 100%;
  min-height: 100dvh;
  background: #0b1220;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

html.screen-mode #app,
html.screen-mode #app > div {
  min-height: 100%;
  height: auto !important;
  overflow: visible !important;
}

html.screen-mode .hd-content-frame--full,
html.screen-mode .hd-content-frame--full .hd-content-frame__body {
  min-height: 100vh;
  min-height: 100dvh;
  height: auto !important;
  overflow: visible !important;
  padding: 0 !important;
  margin: 0 !important;
  max-width: none !important;
}
</style>

<style scoped>
.screen {
  --tile-bg: #111a2c;
  --tile-border: rgba(148, 163, 184, 0.16);
  --ink: #e2e8f0;
  --ink-muted: #94a3b8;
  --ink-faint: #64748b;
  --accent: #16a34a;
  --warn: #f59e0b;
  --bad: #ef4444;

  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  min-height: 100vh;
  min-height: 100dvh;
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(22, 163, 74, 0.12), transparent 60%),
    radial-gradient(1000px 500px at -10% 110%, rgba(59, 130, 246, 0.10), transparent 60%),
    #0b1220;
  color: var(--ink);
  display: flex;
  flex-direction: column;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  padding: clamp(0.75rem, 2vw, 1.5rem) clamp(0.85rem, 2.5vw, 1.5rem) clamp(0.65rem, 1.5vw, 1rem);
  padding-left: max(clamp(0.85rem, 2.5vw, 1.5rem), env(safe-area-inset-left, 0px));
  padding-right: max(clamp(0.85rem, 2.5vw, 1.5rem), env(safe-area-inset-right, 0px));
  padding-bottom: max(clamp(0.65rem, 1.5vw, 1rem), env(safe-area-inset-bottom, 0px));
  gap: clamp(0.65rem, 1.5vw, 1.1rem);
  overflow-x: hidden;
}

/* Browser fullscreen (expand icon) — fill display like facility TV */
.screen.is-browser-fullscreen {
  min-height: 100%;
  height: 100%;
  overflow: hidden;
}
.screen.is-browser-fullscreen .screen-grid {
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
.screen.theme-light {
  --tile-bg: #ffffff;
  --tile-border: rgba(15, 23, 42, 0.12);
  --ink: #0f172a;
  --ink-muted: #475569;
  --ink-faint: #64748b;
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(22, 163, 74, 0.08), transparent 60%),
    radial-gradient(1000px 500px at -10% 110%, rgba(59, 130, 246, 0.07), transparent 60%),
    #f1f5f9;
}
.screen.theme-light .kpi-value,
.screen.theme-light .priority-count,
.screen.theme-light .workload-count,
.screen.theme-light .cat-count,
.screen.theme-light .wait-value {
  color: #0f172a;
}
.screen.theme-light .clock-time {
  color: #0f172a;
}
.screen.theme-light .kpi,
.screen.theme-light .card,
.screen.theme-light .wait-block,
.screen.theme-light .priority-cell {
  box-shadow: 0 1px 2px rgba(2, 6, 23, 0.06);
}
.screen.theme-light .wait-block,
.screen.theme-light .priority-cell {
  background: #f8fafc;
}
.screen.theme-light .workload-bar,
.screen.theme-light .cat-bar,
.screen.theme-light .priority-track {
  background: rgba(15, 23, 42, 0.12);
}

/* Top bar — facility TV style flex + wrap */
.screen-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem 1rem;
  flex-wrap: wrap;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid var(--tile-border);
}
.screen-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1 1 200px;
  min-width: 0;
}
.screen-brand-text {
  min-width: 0;
}
.brand-dot {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  border-radius: 999px;
  background: #16a34a;
  box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.22), 0 0 22px rgba(22, 163, 74, 0.55);
  animation: brand-pulse 2.4s ease-in-out infinite;
}
@keyframes brand-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.18); }
}
.brand-title {
  margin: 0;
  font-size: clamp(1rem, 2.2vw, 1.35rem);
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1.2;
}
.brand-sub {
  margin: 0.15rem 0 0;
  font-size: clamp(0.72rem, 1.6vw, 0.88rem);
  color: var(--ink-muted);
}
.screen-live-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  background: rgba(22, 163, 74, 0.15);
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  flex-shrink: 0;
}
.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--accent);
  animation: live-pulse 1.8s ease-in-out infinite;
}
.live-dot.stale {
  background: var(--warn);
  animation: none;
}
@keyframes live-pulse {
  0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
  70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); }
  100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.screen-clock {
  text-align: center;
  flex-shrink: 0;
}
.clock-time {
  margin: 0;
  font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: clamp(1.25rem, 2.5vw, 2rem);
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #fff;
  line-height: 1;
}
.clock-date {
  margin: 0.25rem 0 0;
  font-size: clamp(0.68rem, 1.4vw, 0.82rem);
  color: var(--ink-muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
.screen-toolbar {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-left: auto;
  flex-wrap: wrap;
  font-size: 0.78rem;
  color: var(--ink-muted);
  font-variant-numeric: tabular-nums;
}
.theme-switch {
  display: inline-flex;
  border: 1px solid var(--tile-border);
  border-radius: 8px;
  padding: 2px;
  background: rgba(15, 23, 42, 0.26);
}
.theme-switch .screen-toolbar-btn {
  border: 0;
  background: transparent;
  padding: 0.25rem 0.55rem;
}
.theme-switch .screen-toolbar-btn.active {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}
.screen-toolbar-btn {
  border: 1px solid var(--tile-border);
  background: var(--tile-bg);
  color: var(--ink-muted);
  border-radius: 8px;
  padding: 0.35rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  line-height: 1.2;
}
.screen-toolbar-btn:hover {
  border-color: var(--accent);
  color: var(--ink);
}
.screen-toolbar-btn.active {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}
.screen-toolbar-btn--icon {
  padding: 0.35rem 0.55rem;
  font-size: 1.1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.status-label {
  white-space: nowrap;
}

/* Grid layout — every template-areas row MUST have exactly 12 tokens (one per column). */
.screen-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  grid-auto-rows: auto;
  grid-template-areas:
    'kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis'
    'pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline'
    'wait wait duty duty duty duty category category priority priority priority priority'
    'closures closures closures closures closures closures inprogress inprogress inprogress inprogress inprogress inprogress'
    'open open open open open open open open open open open open'
    'trend trend trend trend trend trend trend trend trend trend trend trend';
  gap: clamp(0.55rem, 1.2vw, 0.9rem);
  min-height: 0;
  width: 100%;
}
.kpis { grid-area: kpis; min-width: 0; }
.status-pipeline-card { grid-area: pipeline; min-width: 0; }
.wait-card { grid-area: wait; min-width: 0; }
.duty-card { grid-area: duty; min-width: 0; }
.priority-card { grid-area: priority; min-width: 0; }
.category-card { grid-area: category; min-width: 0; }
.closures-card { grid-area: closures; min-width: 0; }
.in-progress-card { grid-area: inprogress; min-width: 0; }
.open-tickets-card { grid-area: open; min-width: 0; }
.trend-card { grid-area: trend; min-width: 0; }

/* KPI tiles */
.kpis {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: clamp(0.55rem, 1.2vw, 0.9rem);
}
@media (max-width: 1200px) {
  .kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.kpi {
  background: var(--tile-bg);
  border: 1px solid var(--tile-border);
  border-radius: 4px;
  padding: 0.95rem 1.05rem;
  position: relative;
  overflow: hidden;
  transition: background 0.3s ease;
}
.kpi::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--kpi-accent, #475569);
}
.kpi-open { --kpi-accent: #3b82f6; }
.kpi-inprogress { --kpi-accent: #7c3aed; }
.kpi-inprogress.pulse .kpi-value {
  animation: inprogress-pulse 2.2s ease-in-out infinite;
}
@keyframes inprogress-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.72; }
}
.kpi-unassigned { --kpi-accent: #f59e0b; }
.kpi-awaiting { --kpi-accent: #a855f7; }
.kpi-agent-week { --kpi-accent: #eab308; }
.kpi-agent-month { --kpi-accent: #f97316; }
.kpi-agent-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.35rem;
}
.kpi-value--name {
  font-size: clamp(1.1rem, 2.5vw, 1.55rem) !important;
  line-height: 1.15;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kpi-response { --kpi-accent: #0ea5e9; }
.kpi-unassigned.alert { background: linear-gradient(135deg, #111a2c 0%, #2a1a04 100%); }
.kpi-new { --kpi-accent: #06b6d4; }
.kpi-resolved { --kpi-accent: #16a34a; }
.kpi-label {
  margin: 0;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--ink-muted);
}
.kpi-value {
  margin: 0.35rem 0 0.25rem;
  font-size: clamp(1.75rem, 4vw, 2.65rem);
  font-weight: 800;
  color: #fff;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.kpi-sub {
  margin: 0;
  font-size: 0.78rem;
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}
.kpi-sub .dot { color: #475569; }

/* Queue status pipeline */
.status-pipeline {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.status-pipeline-bar {
  display: flex;
  height: 12px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(148, 163, 184, 0.12);
}
.status-seg {
  display: block;
  height: 100%;
  min-width: 2px;
  transition: width 0.6s ease;
}
.status-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.5rem;
}
@media (max-width: 640px) {
  .status-legend {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.status-legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: var(--ink-muted);
  min-width: 0;
}
.status-swatch {
  width: 9px;
  height: 9px;
  border-radius: 2px;
  flex-shrink: 0;
}
.status-legend-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.status-legend-count {
  margin-left: auto;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}

/* Cards */
.card {
  background: var(--tile-bg);
  border: 1px solid var(--tile-border);
  border-radius: 4px;
  padding: 0.95rem 1.05rem;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.6rem;
  flex-shrink: 0;
}
.card-head h2 {
  margin: 0;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--ink);
}
.card-sub {
  font-size: 0.78rem;
  color: var(--ink-muted);
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}
.legend-pip {
  display: inline-block;
  width: 9px;
  height: 9px;
  border-radius: 2px;
  margin-right: 2px;
  margin-left: 6px;
}

/* Wait times */
.wait-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
  flex: 1;
  align-items: center;
}
.wait-block {
  background: rgba(15, 23, 42, 0.55);
  border: 1px solid var(--tile-border);
  border-radius: 4px;
  padding: 0.7rem 0.85rem;
  text-align: center;
}
.wait-label {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--ink-muted);
}
.wait-value {
  margin: 0.35rem 0 0.2rem;
  font-size: 1.85rem;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
}
.wait-warn {
  color: #fb923c;
}
.wait-meta {
  margin: 0;
  font-size: 0.72rem;
  color: var(--ink-faint);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

/* Priority matrix */
.priority-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.6rem;
  flex: 1;
  align-content: center;
}
.priority-cell {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--tile-border);
  border-radius: 4px;
  padding: 0.55rem 0.75rem;
}
.priority-cell-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 0.4rem;
}
.priority-name {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.priority-count {
  font-size: 1.45rem;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
}
.priority-track {
  height: 8px;
  background: rgba(148, 163, 184, 0.12);
  border-radius: 999px;
  overflow: hidden;
}
.priority-fill {
  display: block;
  height: 100%;
  background: var(--p-color, #3b82f6);
  border-radius: 999px;
  transition: width 0.6s ease;
}

/* Workload */
.workload-list,
.category-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  overflow-y: auto;
  flex: 1;
}
.workload-row {
  display: grid;
  grid-template-columns: 36px 1fr 1fr auto;
  align-items: center;
  gap: 0.6rem;
}
.workload-name {
  font-size: 0.88rem;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.workload-bar {
  position: relative;
  height: 8px;
  background: rgba(148, 163, 184, 0.12);
  border-radius: 999px;
  overflow: hidden;
}
.workload-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #16a34a, #22c55e);
  border-radius: 999px;
  transition: width 0.6s ease;
}
.workload-count {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: #fff;
  min-width: 28px;
  text-align: right;
}

/* Categories */
.cat-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  align-items: center;
  gap: 0.6rem;
}
.cat-name {
  font-size: 0.88rem;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cat-bar {
  position: relative;
  height: 8px;
  background: rgba(148, 163, 184, 0.12);
  border-radius: 999px;
  overflow: hidden;
}
.cat-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #06b6d4);
  border-radius: 999px;
  transition: width 0.6s ease;
}
.duty-fill {
  background: linear-gradient(90deg, #16a34a, #22c55e);
}

/* Duty station table */
.duty-table-wrap {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.duty-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.duty-table th,
.duty-table td {
  padding: 0.42rem 0.35rem;
  text-align: left;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
}
.duty-table th {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ink-muted);
  font-weight: 700;
}
.duty-table th.num,
.duty-table td.num {
  text-align: right;
  width: 4.5rem;
}
.duty-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 10rem;
}
.duty-pill {
  display: inline-block;
  min-width: 1.75rem;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  text-align: center;
}
.duty-pill.open {
  background: rgba(59, 130, 246, 0.18);
  color: #93c5fd;
}
.duty-pill.closed {
  background: rgba(22, 163, 74, 0.18);
  color: #86efac;
}
.duty-pill.overtime {
  background: rgba(100, 116, 139, 0.2);
  color: var(--ink-muted);
}
.duty-pill.overtime.hot {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}
.screen.theme-light .duty-pill.open { color: #1d4ed8; }
.screen.theme-light .duty-pill.closed { color: #15803d; }
.screen.theme-light .duty-pill.overtime.hot { color: #b91c1c; }

/* Agent closures + open tickets tickers */
.closures-card,
.in-progress-card,
.open-tickets-card {
  min-height: 5.5rem;
}
.ticker-card--progress {
  border-color: rgba(124, 58, 237, 0.35);
  background: rgba(124, 58, 237, 0.12);
}
.screen.theme-light .ticker-card--progress {
  background: #f5f3ff;
  border-color: rgba(124, 58, 237, 0.25);
}
.ticker-sub {
  color: #a78bfa;
  font-weight: 600;
}
.screen.theme-light .ticker-sub {
  color: #6d28d9;
}
.ticker-viewport {
  position: relative;
  overflow: hidden;
  flex: 1;
  mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}
.ticker-track {
  display: flex;
  align-items: stretch;
  gap: 0.75rem;
  width: max-content;
  animation-name: ticker-ltr;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}
@keyframes ticker-ltr {
  0% { transform: translateX(-50%); }
  100% { transform: translateX(0); }
}
.ticker-card {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex: 0 0 auto;
  min-width: 220px;
  padding: 0.55rem 0.85rem;
  border-radius: 4px;
  border: 1px solid var(--tile-border);
  background: rgba(15, 23, 42, 0.45);
}
.screen.theme-light .ticker-card {
  background: #f8fafc;
}
.ticker-meta {
  min-width: 0;
}
.ticker-name {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ticker-count {
  margin: 0.15rem 0 0;
  font-size: 0.76rem;
  color: var(--ink-muted);
  font-variant-numeric: tabular-nums;
}

.cat-count {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: #fff;
  min-width: 24px;
  text-align: right;
}

/* Trend chart */
.trend-bars {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  flex: 1;
  padding: 0.3rem 0 1.4rem;
  position: relative;
}
.trend-col {
  flex: 1;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 1px;
  position: relative;
  height: 100%;
}
.trend-bar {
  flex: 1;
  border-radius: 2px 2px 0 0;
  min-height: 2px;
  transition: height 0.6s ease;
}
.trend-bar-created {
  background: #3b82f6;
}
.trend-bar-resolved {
  background: #16a34a;
}
.trend-tick {
  position: absolute;
  bottom: -1.1rem;
  font-size: 0.62rem;
  color: var(--ink-faint);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

/* Footer */
.screen-foot {
  font-size: 0.7rem;
  color: var(--ink-faint);
  text-align: center;
  padding: 0.25rem 0;
}

.muted {
  color: var(--ink-muted);
  text-align: center;
  margin: auto;
  font-size: 0.88rem;
}
.screen-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-muted);
  font-size: 1.05rem;
}

/* Big screens — boost typography for legibility from across the room. */
@media (min-width: 1600px) {
  .screen { padding: 1.5rem 2rem 0.75rem; gap: 1.25rem; }
  .brand-title { font-size: 1.35rem; }
  .clock-time { font-size: 2.5rem; }
  .kpi-value { font-size: 3.4rem; }
  .priority-count { font-size: 1.7rem; }
}
@media (min-width: 1920px) {
  .kpi-value { font-size: 4rem; }
}

/* Stack on smaller screens (facility TV breakpoints) */
@media (max-width: 1100px) {
  .screen-grid {
    grid-template-areas:
      'kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis'
      'pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline pipeline'
      'wait wait wait wait wait wait duty duty duty duty duty duty'
      'category category category category category category priority priority priority priority priority priority'
      'closures closures closures closures closures closures closures closures closures closures closures closures'
      'inprogress inprogress inprogress inprogress inprogress inprogress inprogress inprogress inprogress inprogress inprogress inprogress'
      'open open open open open open open open open open open open'
      'trend trend trend trend trend trend trend trend trend trend trend trend';
  }
  .kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (max-width: 768px) {
  .screen-bar {
    justify-content: center;
    text-align: center;
  }
  .screen-brand {
    flex: 1 1 100%;
    justify-content: center;
  }
  .screen-toolbar {
    margin-left: 0;
    width: 100%;
    justify-content: center;
  }
  .screen-clock {
    width: 100%;
    order: 3;
  }
  .screen-live-pill {
    order: 2;
  }
  .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .kpi { padding: 0.75rem 0.85rem; }
  .card { padding: 0.8rem 0.9rem; }
  .status-label { font-size: 0.7rem; }
}

@media (max-width: 640px) {
  .screen-grid {
    grid-template-columns: 1fr;
    grid-template-areas:
      'kpis'
      'pipeline'
      'wait'
      'duty'
      'category'
      'priority'
      'inprogress'
      'open'
      'closures'
      'trend';
  }
  .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .wait-row { grid-template-columns: 1fr; }
  .priority-grid { grid-template-columns: 1fr; }
  .status-legend { grid-template-columns: 1fr 1fr; }
  .duty-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .duty-table { min-width: 300px; }
  .trend-bars {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    min-width: 0;
  }
  .ticker-card { min-width: 160px; }
}

@media (max-width: 380px) {
  .kpis { grid-template-columns: 1fr; }
  .status-legend { grid-template-columns: 1fr; }
}
</style>
