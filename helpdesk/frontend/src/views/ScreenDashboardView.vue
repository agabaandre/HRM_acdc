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
}
interface TrendDay {
  day: string
  created: number
  resolved: number
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
  trend: TrendDay[]
  csat: { avg_score: number | null; responses: number; note?: string }
}

const data = ref<ScreenData | null>(null)
const lastFetchedAt = ref<number | null>(null)
const consecutiveErrors = ref(0)
const isStale = ref(false)
const clock = ref(new Date())
const theme = ref<'dark' | 'light'>('dark')
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

onMounted(() => {
  initTheme()
  applyTheme()
  void fetchScreen()
  pollTimer = window.setInterval(fetchScreen, REFRESH_INTERVAL_MS)
  clockTimer = window.setInterval(() => {
    clock.value = new Date()
  }, 1000)
  staleTimer = window.setInterval(checkStaleness, 5000)
  // Hide page scrollbars while on this view
  document.documentElement.classList.add('screen-mode')
  document.body.classList.add('screen-mode')
})

onUnmounted(() => {
  if (pollTimer) window.clearInterval(pollTimer)
  if (clockTimer) window.clearInterval(clockTimer)
  if (staleTimer) window.clearInterval(staleTimer)
  document.documentElement.classList.remove('screen-mode')
  document.body.classList.remove('screen-mode')
})
</script>

<template>
  <div class="screen" :class="`theme-${theme}`">
    <!-- Top bar -->
    <header class="screen-bar">
      <div class="screen-brand">
        <span class="brand-dot" />
        <div>
          <p class="brand-title">Africa CDC · IT Service Desk</p>
          <p class="brand-sub">Live operations dashboard</p>
        </div>
      </div>
      <div class="screen-clock">
        <p class="clock-time">{{ clockTime }}</p>
        <p class="clock-date">{{ clockDate }}</p>
      </div>
      <div class="screen-status">
        <div class="theme-switch" role="group" aria-label="Dashboard theme">
          <button type="button" class="theme-btn" :class="{ active: theme === 'dark' }" @click="setTheme('dark')">Dark</button>
          <button type="button" class="theme-btn" :class="{ active: theme === 'light' }" @click="setTheme('light')">Light</button>
        </div>
        <span class="status-dot" :class="{ live: !isStale && consecutiveErrors === 0, stale: isStale || consecutiveErrors > 1 }" />
        <span class="status-label">{{ isStale ? 'Reconnecting' : 'Live' }} · {{ lastUpdatedLabel }}</span>
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
            <span>{{ data.volumes.in_progress }} in progress</span>
          </p>
        </article>

        <article class="kpi kpi-unassigned" :class="{ alert: data.volumes.unassigned > 0 }">
          <p class="kpi-label">Unassigned</p>
          <p class="kpi-value">{{ data.volumes.unassigned }}</p>
          <p class="kpi-sub">Waiting to pick up</p>
        </article>

        <article class="kpi kpi-awaiting">
          <p class="kpi-label">Awaiting confirm</p>
          <p class="kpi-value">{{ data.volumes.awaiting_confirm }}</p>
          <p class="kpi-sub">Resolution sent</p>
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

      <!-- Agent closures + open tickets tickers -->
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

      <section class="card open-tickets-card">
        <header class="card-head">
          <h2>Staff with open tickets</h2>
          <span class="card-sub">Assigned now · {{ agentOpenTickets.length }} agents</span>
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
                <p class="ticker-count">{{ agent.open }} open</p>
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
/* Global page mode — hide scrollbars, lock height for kiosk display. */
html.screen-mode,
body.screen-mode {
  background: #0b1220;
  margin: 0;
  height: 100%;
  overflow: hidden;
}
html.screen-mode #app,
body.screen-mode #app {
  height: 100%;
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

  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(22, 163, 74, 0.12), transparent 60%),
    radial-gradient(1000px 500px at -10% 110%, rgba(59, 130, 246, 0.10), transparent 60%),
    #0b1220;
  color: var(--ink);
  display: flex;
  flex-direction: column;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  padding: 1.25rem 1.5rem 0.65rem;
  gap: 1.1rem;
  overflow: hidden;
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

/* Top bar */
.screen-bar {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1.25rem;
}
.screen-brand {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}
.brand-dot {
  width: 14px;
  height: 14px;
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
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}
.brand-sub {
  margin: 0;
  font-size: 0.82rem;
  color: var(--ink-muted);
}
.screen-clock {
  text-align: center;
}
.clock-time {
  margin: 0;
  font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #fff;
  line-height: 1;
}
.clock-date {
  margin: 0.25rem 0 0;
  font-size: 0.82rem;
  color: var(--ink-muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
.screen-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: flex-end;
  font-size: 0.82rem;
  color: var(--ink-muted);
  font-variant-numeric: tabular-nums;
}
.theme-switch {
  display: inline-flex;
  border: 1px solid var(--tile-border);
  border-radius: 999px;
  padding: 2px;
  margin-right: 0.4rem;
  background: rgba(15, 23, 42, 0.26);
}
.theme-btn {
  border: 0;
  background: transparent;
  color: var(--ink-muted);
  border-radius: 999px;
  padding: 0.15rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
}
.theme-btn.active {
  background: #16a34a;
  color: #fff;
}
.theme-btn:not(.active):hover {
  color: var(--ink);
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #475569;
}
.status-dot.live {
  background: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.22);
}
.status-dot.stale {
  background: #f59e0b;
}

/* Grid layout */
.screen-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  grid-auto-rows: minmax(120px, auto);
  grid-template-areas:
    'kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis'
    'wait wait duty duty duty duty category category priority priority priority priority'
    'closures closures closures closures closures closures closures open open open open open'
    'trend trend trend trend trend trend trend trend trend trend trend trend';
  gap: 0.9rem;
  min-height: 0;
}
.kpis { grid-area: kpis; }
.wait-card { grid-area: wait; }
.duty-card { grid-area: duty; }
.priority-card { grid-area: priority; }
.category-card { grid-area: category; }
.closures-card { grid-area: closures; }
.open-tickets-card { grid-area: open; }
.trend-card { grid-area: trend; }

/* KPI tiles */
.kpis {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.9rem;
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
.kpi-unassigned { --kpi-accent: #f59e0b; }
.kpi-awaiting { --kpi-accent: #a855f7; }
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
  font-size: 2.65rem;
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
.open-tickets-card {
  min-height: 5.5rem;
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

/* Stack a bit on smaller monitors / portrait setups */
@media (max-width: 1200px) {
  .screen-grid {
    grid-template-areas:
      'kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis kpis'
      'wait wait wait wait wait wait duty duty duty duty duty duty'
      'category category category category category category priority priority priority priority priority priority'
      'closures closures closures closures closures closures closures closures closures closures closures closures'
      'open open open open open open open open open open open open'
      'trend trend trend trend trend trend trend trend trend trend trend trend';
  }
  .kpis { grid-template-columns: repeat(3, 1fr); }
}
</style>
