<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import TicketReassignModal, {
  type ReassignTicketRef,
} from '../components/tickets/TicketReassignModal.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { canReassignTickets, ticketStatusAllowsReassign } from '../lib/canReassignTickets'
import { type PageSize } from '../lib/helpdeskForm'
import { notifyError } from '../lib/notify'
import { useAuthStore } from '../stores/auth'
import { formatTableCountLabel, priorityMeta, statusMeta } from '../lib/ticketTableMeta'

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

const auth = useAuthStore()
const rows = ref<TicketRow[]>([])
const loading = ref(false)
const searchState = reactive<{ q: string }>({ q: '' })
const q = ref('')
const total = ref(0)
const page = ref(1)
const itemsPerPage = ref<PageSize>(20)
const sortBy = ref<SortItem[]>([{ key: 'id', order: 'desc' }])
const reassignTicket = ref<ReassignTicketRef | null>(null)

const itemsPerPageOptions = [10, 20, 50, 100] as const

const canReassign = computed(() => canReassignTickets(auth.me?.profile))

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
  page.value = 1
  void load()
}
</script>

<template>
  <div>
    <CbpPageHeading title="Tickets" back-to="/" back-label="← Overview" />

    <v-card class="hd-data-table-card hd-page-toolbar" variant="outlined">
      <v-card-text class="hd-page-toolbar__search">
        <UForm :state="searchState" class="hd-search-form" @submit="doSearch">
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
          <RouterLink :to="`/tickets/${item.id}`" class="hd-dt-subject-link">{{ item.subject }}</RouterLink>
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
            No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
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
</style>
