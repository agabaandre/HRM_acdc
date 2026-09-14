<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import TicketReassignModal, {
  type ReassignTicketRef,
} from '../components/tickets/TicketReassignModal.vue'
import TicketDatesCell from '../components/tickets/TicketDatesCell.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { canReassignTickets, ticketStatusAllowsReassign } from '../lib/canReassignTickets'
import { type PageSize, type SelectNumberItem, type SelectStringItem } from '../lib/helpdeskForm'
import { isAgentDeskUser } from '../lib/isAgentDeskUser'
import { notifyError } from '../lib/notify'
import { useAuthStore } from '../stores/auth'
import { formatTableCountLabel, priorityMeta, statusMeta } from '../lib/ticketTableMeta'
import { displayPlainText } from '../lib/richText'

interface AssigneeBrief {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
}

interface TicketRow {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  created_at?: string | null
  resolved_at?: string | null
  closed_at?: string | null
  requester_name?: string | null
  assignee?: AssigneeBrief | null
  assignees?: AssigneeBrief[]
}

function assigneeNames(row: TicketRow): string {
  const names = (row.assignees?.length ? row.assignees : row.assignee ? [row.assignee] : []).map((a) => a.name)
  return names.join(', ')
}

function primaryAssignee(row: TicketRow): AssigneeBrief | null {
  if (row.assignee) {
    return row.assignee
  }
  return row.assignees?.[0] ?? null
}

function assigneeCell(row: TicketRow): { avatarName: string; avatarUrl: string | null; label: string } | null {
  const primary = primaryAssignee(row)
  const label = assigneeNames(row)
  if (!primary && !label) {
    return null
  }
  return {
    avatarName: primary?.name ?? label,
    avatarUrl: primary?.avatar_url ?? null,
    label: label || primary?.name || '—',
  }
}

type SortItem = { key: string; order: 'asc' | 'desc' }

type TicketScope = 'all' | 'me'

interface FilterAgent {
  id: number
  name: string
}

const auth = useAuthStore()
const rows = ref<TicketRow[]>([])
const loading = ref(false)
const searchState = reactive<{ q: string }>({ q: '' })
const q = ref('')
const scope = ref<TicketScope>('all')
const agentId = ref<number | 'all'>('all')
const datePreset = ref('all')
const agents = ref<FilterAgent[]>([])
const total = ref(0)
const page = ref(1)
const itemsPerPage = ref<PageSize>(20)
const sortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const reassignTicket = ref<ReassignTicketRef | null>(null)

const itemsPerPageOptions = [10, 20, 50, 100] as const
const scopeItems: SelectStringItem[] = [
  { label: 'All', value: 'all' },
  { label: 'Me', value: 'me' },
]
const datePresetItems: SelectStringItem[] = [
  { label: 'All', value: 'all' },
  { label: 'Today', value: 'today' },
  { label: 'Last 3 days', value: 'last_3_days' },
  { label: 'Last 5 days', value: 'last_5_days' },
  { label: 'Last week', value: 'last_week' },
  { label: 'Last month', value: 'last_month' },
  { label: 'Last months', value: 'last_months' },
]

const canReassign = computed(() => canReassignTickets(auth.me?.profile))
const showStaffFilters = computed(() => isAgentDeskUser(auth.me?.profile))
const agentItems = computed((): Array<SelectNumberItem | SelectStringItem> => [
  { label: 'All agents', value: 'all' },
  ...agents.value.map((agent) => ({ label: agent.name, value: agent.id })),
])
const filtersActive = computed(
  () =>
    q.value.trim() !== ''
    || scope.value === 'me'
    || agentId.value !== 'all'
    || datePreset.value !== 'all',
)

const tableCountLabel = computed(() =>
  formatTableCountLabel(rows.value.length, total.value, page.value, itemsPerPage.value),
)

const headers = computed((): DataTableHeader[] => {
  const cols: DataTableHeader[] = [
    { title: '#', key: 'row_num', sortable: false, width: '52px', align: 'center' },
    { title: 'Ticket', key: 'ticket_number', sortable: true, minWidth: '120px' },
    { title: 'Subject', key: 'subject', sortable: true, minWidth: '200px' },
    { title: 'Requester', key: 'requester_name', sortable: true, minWidth: '150px' },
    { title: 'Assigned to', key: 'assignee_name', sortable: true, minWidth: '150px' },
    { title: 'Status', key: 'status', sortable: true, width: '130px' },
    { title: 'Priority', key: 'priority', sortable: true, width: '110px' },
    { title: 'Dates', key: 'created_at', sortable: true, minWidth: '168px' },
  ]
  if (canReassign.value) {
    cols.push({ title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '110px' })
  }
  return cols
})

function rowNumber(index: number): number {
  return (page.value - 1) * itemsPerPage.value + index + 1
}

function canReassignRow(row: TicketRow): boolean {
  return canReassign.value && ticketStatusAllowsReassign(row.status)
}

function openReassign(row: TicketRow): void {
  reassignTicket.value = {
    id: row.id,
    ticket_number: row.ticket_number,
    subject: row.subject,
  }
}

function closeReassign(): void {
  reassignTicket.value = null
}

async function loadAgents(): Promise<void> {
  if (!showStaffFilters.value) {
    agents.value = []
    return
  }
  try {
    const { data } = await api.get('/api/v1/tickets/filter-agents')
    agents.value = Array.isArray(data.data) ? (data.data as FilterAgent[]) : []
  } catch (e: unknown) {
    agents.value = []
    notifyError(apiErrorMessage(e, 'Failed to load agents'))
  }
}

function applyFilters(): void {
  page.value = 1
  void load()
}

function onScopeChange(): void {
  if (scope.value === 'me') {
    agentId.value = 'all'
  }
  applyFilters()
}

function onAgentChange(): void {
  if (agentId.value !== 'all') {
    scope.value = 'all'
  }
  applyFilters()
}

async function load() {
  loading.value = true
  const sort = sortBy.value[0]
  try {
    const { data } = await api.get('/api/v1/tickets', {
      params: {
        q: q.value.trim() || undefined,
        page: page.value,
        per_page: itemsPerPage.value,
        sort_by: sort?.key && sort.key !== 'row_num' ? sort.key : 'id',
        sort_dir: sort?.order ?? 'desc',
        assigned_to_me: scope.value === 'me' ? 1 : undefined,
        assigned_user_id: scope.value === 'me' || agentId.value === 'all' ? undefined : agentId.value,
        date_preset: datePreset.value === 'all' ? undefined : datePreset.value,
      },
    })
    rows.value = data.data as TicketRow[]
    total.value = Number(data.meta?.total ?? rows.value.length ?? 0)
    page.value = Math.max(1, Number(data.meta?.current_page ?? page.value))
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load tickets'))
  } finally {
    loading.value = false
  }
}

function onUpdateOptions(options: {
  page: number
  itemsPerPage: number
  sortBy: SortItem[]
}) {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage as PageSize
  sortBy.value = options.sortBy.length > 0 ? options.sortBy : [{ key: 'id', order: 'desc' }]
  void load()
}

function doSearch() {
  q.value = searchState.q
  page.value = 1
  void load()
}

function resetSearch() {
  searchState.q = ''
  q.value = ''
  scope.value = 'all'
  agentId.value = 'all'
  datePreset.value = 'all'
  page.value = 1
  void load()
}

watch(showStaffFilters, (show) => {
  if (show) {
    void loadAgents()
  } else {
    agents.value = []
    scope.value = 'all'
    agentId.value = 'all'
  }
}, { immediate: true })
</script>

<template>
  <div>
    <CbpPageHeading title="Tickets" back-to="/" back-label="← Overview" />

    <v-card class="hd-data-table-card hd-page-toolbar" elevation="10">
      <v-card-text class="hd-page-toolbar__search">
        <UForm :state="searchState" class="hd-search-form" @submit="doSearch">
          <UFormField v-if="showStaffFilters" name="scope" label="Tickets" class="hd-ticket-filter">
            <USelect
              v-model="scope"
              :items="scopeItems"
              :clearable="false"
              @update:model-value="onScopeChange"
            />
          </UFormField>
          <UFormField v-if="showStaffFilters" name="agentId" label="Agent" class="hd-ticket-filter hd-ticket-filter--agent">
            <USelect
              v-model="agentId"
              :items="agentItems"
              :clearable="false"
              @update:model-value="onAgentChange"
            />
          </UFormField>
          <UFormField name="datePreset" label="Created" class="hd-ticket-filter">
            <USelect
              v-model="datePreset"
              :items="datePresetItems"
              :clearable="false"
              @update:model-value="applyFilters"
            />
          </UFormField>
          <UFormField name="q" label="Search" class="hd-form-toolbar-grow">
            <UInput
              v-model="searchState.q"
              type="search"
              icon="i-lucide-search"
              placeholder="Search by ticket #, subject, requester, assignee…"
              clearable
            />
          </UFormField>
          <UButton type="submit" color="primary">Search</UButton>
          <UButton type="button" color="neutral" variant="outline" @click="resetSearch">Clear</UButton>
        </UForm>
      </v-card-text>

      <v-card-text class="hd-data-table-card__head">
        <p class="table-count" role="status">
          Showing <strong>{{ tableCountLabel }}</strong>
        </p>
      </v-card-text>

      <v-data-table-server
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        class="hd-data-table"
        :headers="headers"
        :items="rows"
        :items-length="total"
        :items-per-page-options="[...itemsPerPageOptions]"
        :loading="loading"
        density="compact"
        hover
        item-value="id"
        @update:options="onUpdateOptions"
      >
        <template #item.row_num="{ index }">
          <span class="hd-dt-row-num">{{ rowNumber(index) }}</span>
        </template>

        <template #item.ticket_number="{ item }">
          <RouterLink :to="`/tickets/${item.id}`" class="hd-dt-ticket-link">{{ item.ticket_number }}</RouterLink>
        </template>

        <template #item.subject="{ item }">
          <RouterLink :to="`/tickets/${item.id}`" class="hd-dt-subject-link">{{ displayPlainText(item.subject) }}</RouterLink>
        </template>

        <template #item.requester_name="{ item }">
          <div class="hd-dt-person">
            <CbpAvatar size="sm" :name="item.requester_name || 'Requester'" :image-url="null" />
            <span class="hd-dt-person-name">{{ item.requester_name || '—' }}</span>
          </div>
        </template>

        <template #item.assignee_name="{ item }">
          <div v-if="assigneeCell(item)" class="hd-dt-person">
            <CbpAvatar size="sm" :name="assigneeCell(item)?.avatarName ?? 'Agent'" :image-url="assigneeCell(item)?.avatarUrl ?? null" />
            <span class="hd-dt-person-name">{{ assigneeCell(item)?.label ?? '—' }}</span>
          </div>
          <span v-else class="hd-dt-empty">—</span>
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

        <template #item.created_at="{ item }">
          <TicketDatesCell
            :created-at="item.created_at"
            :resolved-at="item.resolved_at"
            :closed-at="item.closed_at"
          />
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
          <span v-else class="hd-dt-empty">—</span>
        </template>

        <template #no-data>
          <div class="hd-dt-empty-msg">
            <template v-if="filtersActive">No tickets match these filters.</template>
            <template v-else>
              No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
            </template>
          </div>
        </template>

        <template #loading>
          <div class="hd-dt-loading">Loading tickets…</div>
        </template>
      </v-data-table-server>
    </v-card>

    <TicketReassignModal
      :ticket="reassignTicket"
      @close="closeReassign"
      @reassigned="load"
    />
  </div>
</template>

<style scoped>
.hd-page-toolbar__search {
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.hd-ticket-filter {
  flex: 0 1 10.5rem;
  min-width: 9.5rem;
}

.hd-ticket-filter--agent {
  flex-basis: 13rem;
  min-width: 12rem;
}
</style>
