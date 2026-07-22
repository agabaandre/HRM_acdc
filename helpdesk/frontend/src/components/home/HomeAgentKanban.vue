<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../common/CbpAvatar.vue'
import TicketReassignModal, {
  type ReassignTicketRef,
} from '../tickets/TicketReassignModal.vue'
import TicketResolveModal, {
  type ResolveTicketRef,
} from '../tickets/TicketResolveModal.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { canReassignTickets, ticketStatusAllowsReassign } from '../../lib/canReassignTickets'
import { notifyError, notifySuccess } from '../../lib/notify'
import { priorityMeta } from '../../lib/ticketTableMeta'
import { scheduleIdle } from '../../lib/scheduleIdle'
import { useAuthStore } from '../../stores/auth'

withDefaults(
  defineProps<{
    /** Hide “Open full agent desk” when already on the agent dashboard. */
    embedded?: boolean
    /** Hide time-of-day greeting (shown once on the agent desk hero). */
    hideGreeting?: boolean
  }>(),
  { embedded: false, hideGreeting: false },
)

export interface KanbanTicket {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  requester_name?: string | null
  assigned_user_id?: number | null
  assignee?: { id: number; name: string; avatar_url?: string | null } | null
  assignees?: { id: number; name: string; avatar_url?: string | null }[]
  category?: { id: number; name: string } | null
  updated_at?: string | null
}

interface KanbanColumn {
  id: string
  label: string
  hint: string
  accent: string
}

const ACTIVE_STATUSES = ['open', 'pending', 'in_progress'] as const
const RESOLVED_STATUSES = ['resolved', 'awaiting_requester_confirmation'] as const
type ActiveStatus = (typeof ACTIVE_STATUSES)[number]

const columns: KanbanColumn[] = [
  { id: 'open', label: 'Open', hint: 'Logged · not yet started', accent: '#2563eb' },
  { id: 'pending', label: 'Pending', hint: 'Waiting on requester or third party', accent: '#4f46e5' },
  { id: 'in_progress', label: 'In progress', hint: 'Actively being worked', accent: '#7c3aed' },
  { id: 'resolved', label: 'Resolved', hint: 'Solution delivered · pending closure', accent: '#16a34a' },
]

function isResolvedStatus(status: string): boolean {
  return (RESOLVED_STATUSES as readonly string[]).includes(status)
}

function boardColumnId(status: string): string | null {
  if ((ACTIVE_STATUSES as readonly string[]).includes(status)) {
    return status
  }
  if (isResolvedStatus(status)) {
    return 'resolved'
  }
  return null
}

const auth = useAuthStore()
const loading = ref(false)
const savingId = ref<number | null>(null)
const tickets = ref<KanbanTicket[]>([])
const dragTicketId = ref<number | null>(null)
const dragOverColumn = ref<string | null>(null)
const configureTicket = ref<ReassignTicketRef | null>(null)
const resolveTicket = ref<ResolveTicketRef | null>(null)
let loadAbort: AbortController | null = null
const KANBAN_PAGE_SIZE = 50
const KANBAN_STATUSES = [...ACTIVE_STATUSES, ...RESOLVED_STATUSES].join(',')

const boardTickets = computed(() =>
  tickets.value.filter((t) => boardColumnId(t.status) !== null),
)

const canConfigure = computed(() => canReassignTickets(auth.me?.profile))

const greeting = computed(() => {
  const name = auth.me?.name?.trim() || 'there'
  const hour = new Date().getHours()
  if (hour < 12) return `Good morning, ${name}`
  if (hour < 17) return `Good afternoon, ${name}`
  return `Good evening, ${name}`
})

function ticketAssigneeNames(t: KanbanTicket): string {
  const names = t.assignees?.map((a) => a.name) ?? (t.assignee?.name ? [t.assignee.name] : [])
  return names.length > 0 ? names.join(', ') : ''
}

function canConfigureTicket(t: KanbanTicket): boolean {
  return canConfigure.value && ticketStatusAllowsReassign(t.status)
}

function openConfigure(t: KanbanTicket): void {
  configureTicket.value = {
    id: t.id,
    ticket_number: t.ticket_number,
    subject: t.subject,
  }
}

function openResolve(ticket: KanbanTicket): void {
  resolveTicket.value = {
    id: ticket.id,
    ticket_number: ticket.ticket_number,
    subject: ticket.subject,
    category: ticket.category ?? null,
  }
}

function closeResolve(): void {
  resolveTicket.value = null
}

async function onResolved(): Promise<void> {
  await loadTickets()
}

function closeConfigure(): void {
  configureTicket.value = null
}

async function onConfigured(): Promise<void> {
  await loadTickets()
}

const columnTickets = computed(() => {
  const map = new Map<string, KanbanTicket[]>()
  for (const col of columns) {
    map.set(col.id, [])
  }
  for (const t of boardTickets.value) {
    const colId = boardColumnId(t.status)
    if (!colId) {
      continue
    }
    const list = map.get(colId)
    if (list) {
      list.push(t)
    }
  }
  for (const col of columns) {
    map.get(col.id)?.sort((a, b) => b.id - a.id)
  }
  return map
})

const totalActive = computed(() => boardTickets.value.length)

async function loadTickets(): Promise<void> {
  loadAbort?.abort()
  loadAbort = new AbortController()
  const signal = loadAbort.signal
  loading.value = true
  try {
    const { data } = await api.get<{ data: KanbanTicket[] }>('/api/v1/tickets', {
      params: {
        per_page: KANBAN_PAGE_SIZE,
        assigned_to_me: 1,
        status_in: KANBAN_STATUSES,
      },
      signal,
    })
    if (signal.aborted) {
      return
    }
    tickets.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    if (signal.aborted) {
      return
    }
    notifyError(apiErrorMessage(e, 'Could not load your ticket board.'))
    tickets.value = []
  } finally {
    if (!signal.aborted) {
      loading.value = false
    }
  }
}

function onDragStart(ticket: KanbanTicket, ev: DragEvent): void {
  if (isResolvedStatus(ticket.status)) {
    ev.preventDefault()
    notifyError('Resolved tickets stay in Resolved until the requester closes them or they auto-close.')
    return
  }
  dragTicketId.value = ticket.id
  if (ev.dataTransfer) {
    ev.dataTransfer.effectAllowed = 'move'
    ev.dataTransfer.setData('text/plain', String(ticket.id))
  }
}

function onDragEnd(): void {
  dragTicketId.value = null
  dragOverColumn.value = null
}

function onDragOverColumn(columnId: string, ev: DragEvent): void {
  ev.preventDefault()
  if (ev.dataTransfer) {
    ev.dataTransfer.dropEffect = 'move'
  }
  dragOverColumn.value = columnId
}

function onDragLeaveColumn(columnId: string): void {
  if (dragOverColumn.value === columnId) {
    dragOverColumn.value = null
  }
}

async function onDropColumn(columnId: string, ev: DragEvent): Promise<void> {
  ev.preventDefault()
  dragOverColumn.value = null
  const raw = ev.dataTransfer?.getData('text/plain') ?? ''
  const ticketId = Number(raw)
  if (!Number.isFinite(ticketId) || ticketId <= 0) {
    return
  }
  const ticket = tickets.value.find((t) => t.id === ticketId)
  if (!ticket || boardColumnId(ticket.status) === columnId) {
    return
  }
  if (columnId === 'resolved') {
    dragTicketId.value = null
    openResolve(ticket)
    return
  }
  if (isResolvedStatus(ticket.status)) {
    return
  }
  await moveTicket(ticket, columnId as ActiveStatus)
}

async function moveTicket(ticket: KanbanTicket, newStatus: ActiveStatus): Promise<void> {
  const prev = ticket.status
  ticket.status = newStatus
  savingId.value = ticket.id
  try {
    await api.patch(`/api/v1/tickets/${ticket.id}`, { status: newStatus })
    const col = columns.find((c) => c.id === newStatus)
    notifySuccess(`Moved to ${col?.label ?? newStatus.replace(/_/g, ' ')}`)
  } catch (e: unknown) {
    ticket.status = prev
    notifyError(apiErrorMessage(e, 'Could not update ticket status.'))
  } finally {
    savingId.value = null
    dragTicketId.value = null
  }
}

function priorityChip(priority: string): { label: string; className: string } {
  const p = priorityMeta(priority)
  if (priority === 'high' || priority === 'critical') {
    return { label: p.label, className: 'kb-priority kb-priority--high' }
  }
  if (priority === 'low') {
    return { label: p.label, className: 'kb-priority kb-priority--low' }
  }
  return { label: p.label, className: 'kb-priority' }
}

function relativeTime(iso?: string | null): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    const diff = Date.now() - d.getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 48) return `${hrs}h ago`
    return d.toLocaleDateString()
  } catch {
    return ''
  }
}

onMounted(() => {
  scheduleIdle(() => {
    void loadTickets()
  })
})

onUnmounted(() => {
  loadAbort?.abort()
})
</script>

<template>
  <section class="hd-kanban" :class="{ 'hd-kanban--embedded': embedded }" aria-label="Your ticket board">
    <header class="hd-kanban-head">
      <div>
        <p v-if="!hideGreeting" class="hd-kanban-eyebrow">{{ greeting }}</p>
        <h2 class="hd-kanban-title">Your ticket board</h2>
        <p class="hd-kanban-sub">
          ITIL workflow: drag between Open, Pending, and In progress. Drop on Resolved to enter resolution notes and notify the requester.
        </p>
      </div>
      <div class="hd-kanban-head-actions">
        <span class="hd-kanban-count" role="status">{{ totalActive }} on board</span>
        <UButton
          type="button"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          icon="i-lucide-refresh-cw"
          @click="loadTickets"
        >
          Refresh
        </UButton>
        <UButton to="/tickets/new" color="primary" size="sm" icon="i-lucide-plus">
          Create ticket
        </UButton>
      </div>
    </header>

    <div v-if="loading && tickets.length === 0" class="hd-kanban-loading" role="status">
      Loading your tickets…
    </div>

    <div v-else class="hd-kanban-board">
      <div
        v-for="col in columns"
        :key="col.id"
        class="hd-kanban-col"
        :class="{
          'is-drag-over': dragOverColumn === col.id,
          'hd-kanban-col--resolved': col.id === 'resolved',
        }"
        @dragover="onDragOverColumn(col.id, $event)"
        @dragleave="onDragLeaveColumn(col.id)"
        @drop="onDropColumn(col.id, $event)"
      >
        <div class="hd-kanban-col-head" :style="{ '--col-accent': col.accent }">
          <span class="hd-kanban-col-label">{{ col.label }}</span>
          <span class="hd-kanban-col-count">{{ columnTickets.get(col.id)?.length ?? 0 }}</span>
        </div>
        <p class="hd-kanban-col-hint">{{ col.hint }}</p>

        <ul class="hd-kanban-cards">
          <li
            v-for="t in columnTickets.get(col.id)"
            :key="t.id"
            class="hd-kanban-card-wrap"
          >
            <article
              class="hd-kanban-card"
              :class="{
                'is-dragging': dragTicketId === t.id,
                'is-saving': savingId === t.id,
              }"
              :draggable="!isResolvedStatus(t.status)"
              @dragstart="onDragStart(t, $event)"
              @dragend="onDragEnd"
            >
              <div class="hd-kanban-card-top">
                <RouterLink :to="`/tickets/${t.id}`" class="hd-kanban-ticket-no" @click.stop>
                  {{ t.ticket_number }}
                </RouterLink>
                <div class="hd-kanban-card-top-actions">
                  <UButton
                    v-if="canConfigureTicket(t)"
                    type="button"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    icon="mdi-cog-outline"
                    title="Configure agents, priority, and category"
                    @click.stop="openConfigure(t)"
                  />
                  <span :class="priorityChip(t.priority).className">{{ priorityChip(t.priority).label }}</span>
                </div>
              </div>
              <RouterLink :to="`/tickets/${t.id}`" class="hd-kanban-subject">
                {{ t.subject }}
              </RouterLink>
              <div class="hd-kanban-card-meta">
                <CbpAvatar
                  size="xs"
                  :name="t.requester_name || 'Requester'"
                  :image-url="null"
                />
                <span class="hd-kanban-requester">{{ t.requester_name || 'Requester' }}</span>
                <span v-if="relativeTime(t.updated_at)" class="hd-kanban-time">{{ relativeTime(t.updated_at) }}</span>
              </div>
              <p v-if="t.category?.name" class="hd-kanban-cat">{{ t.category.name }}</p>
              <p v-if="ticketAssigneeNames(t)" class="hd-kanban-agents">{{ ticketAssigneeNames(t) }}</p>
            </article>
          </li>
        </ul>

        <p v-if="(columnTickets.get(col.id)?.length ?? 0) === 0" class="hd-kanban-empty-col">
          {{ col.id === 'resolved' ? 'Drop here to resolve' : 'Drop tickets here' }}
        </p>
      </div>
    </div>

    <footer class="hd-kanban-foot">
      <RouterLink v-if="!embedded" to="/desk/agent" class="hd-kanban-link">Open full agent desk →</RouterLink>
      <RouterLink to="/tickets" class="hd-kanban-link">All tickets →</RouterLink>
    </footer>

    <TicketReassignModal
      :ticket="configureTicket"
      @close="closeConfigure"
      @reassigned="onConfigured"
    />

    <TicketResolveModal
      :ticket="resolveTicket"
      @close="closeResolve"
      @resolved="onResolved"
    />
  </section>
</template>
