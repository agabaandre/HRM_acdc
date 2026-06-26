<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import TicketReassignModal, {
  type ReassignTicketRef,
} from '../components/tickets/TicketReassignModal.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { canReassignTickets, ticketStatusAllowsReassign } from '../lib/canReassignTickets'
import {
  canChangeTicketCategory,
  ticketStatusAllowsCategoryChange,
} from '../lib/canChangeTicketCategory'
import { PER_PAGE_ITEMS, type PageSize, type SelectNumberItem } from '../lib/helpdeskForm'
import { notifyError } from '../lib/notify'
import { useAuthStore } from '../stores/auth'
import {
  formatTableCountLabel,
  priorityMeta,
  rowIndex,
  statusMeta,
} from '../lib/ticketTableMeta'

interface AssigneeBrief {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
}

interface TicketCategoryBrief {
  id: number
  name: string
}

interface TicketRow {
  id: number
  ticket_number: string
  subject: string
  status: string
  priority: string
  requester_name?: string | null
  category?: TicketCategoryBrief | null
  assignee?: AssigneeBrief | null
}

const auth = useAuthStore()
const rows = ref<TicketRow[]>([])
const loading = ref(false)
const searchState = reactive<{ q: string; perPage: PageSize }>({ q: '', perPage: 20 })
const q = computed({
  get: () => searchState.q,
  set: (v: string) => {
    searchState.q = v
  },
})
const perPage = computed({
  get: () => searchState.perPage,
  set: (v: PageSize) => {
    searchState.perPage = v
  },
})
const total = ref(0)
const lastPage = ref(1)
const page = ref(1)
const reassignTicket = ref<ReassignTicketRef | null>(null)
const cats = ref<{ id: number; name: string }[]>([])
const categoryUpdatingId = ref<number | null>(null)

const canReassign = computed(() => canReassignTickets(auth.me?.profile))
const canEditCategory = computed(() => canChangeTicketCategory(auth.me?.profile))

const categoryItems = computed((): SelectNumberItem[] =>
  cats.value.map((c) => ({ label: c.name, value: c.id })),
)

const tableColspan = computed(() => (canReassign.value ? 9 : 8))

const hasPrev = computed(() => page.value > 1)
const hasNext = computed(() => page.value < lastPage.value)
const tableCountLabel = computed(() =>
  formatTableCountLabel(rows.value.length, total.value, page.value, perPage.value),
)

function counterFor(idx: number): number {
  return rowIndex(page.value, perPage.value, idx)
}

function canReassignRow(row: TicketRow): boolean {
  return canReassign.value && ticketStatusAllowsReassign(row.status)
}

function canEditCategoryRow(row: TicketRow): boolean {
  return canEditCategory.value && ticketStatusAllowsCategoryChange(row.status)
}

async function loadCategories() {
  if (!canEditCategory.value) {
    return
  }
  try {
    const { data } = await api.get<{ data: { id: number; name: string }[] }>('/api/v1/categories')
    cats.value = Array.isArray(data.data) ? data.data : []
  } catch {
    cats.value = []
  }
}

async function updateTicketCategory(row: TicketRow, categoryId: number | undefined) {
  if (!categoryId || categoryId === row.category?.id || categoryUpdatingId.value != null) {
    return
  }
  categoryUpdatingId.value = row.id
  try {
    const { data } = await api.patch(`/api/v1/tickets/${row.id}`, { category_id: categoryId })
    const updated = data.data as TicketRow
    const idx = rows.value.findIndex((r) => r.id === row.id)
    if (idx >= 0) {
      rows.value[idx] = { ...rows.value[idx], category: updated.category ?? null }
    }
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not update category'))
  } finally {
    categoryUpdatingId.value = null
  }
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
  try {
    const { data } = await api.get('/api/v1/tickets', {
      params: {
        q: q.value.trim() || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })
    rows.value = data.data as TicketRow[]
    total.value = Number(data.meta?.total ?? rows.value.length ?? 0)
    lastPage.value = Math.max(1, Number(data.meta?.last_page ?? 1))
    page.value = Math.max(1, Number(data.meta?.current_page ?? page.value))
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load tickets'))
  } finally {
    loading.value = false
  }
}

function doSearch() {
  page.value = 1
  load()
}

function resetSearch() {
  q.value = ''
  page.value = 1
  load()
}

watch(() => searchState.perPage, () => {
  page.value = 1
  load()
})

onMounted(async () => {
  await loadCategories()
  await load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Tickets" back-to="/" back-label="← Overview" />
    <div class="tools">
      <UForm :state="searchState" class="hd-search-form searchbar" @submit="doSearch">
        <UFormField name="q" class="hd-form-toolbar-grow">
          <UInput
            v-model="searchState.q"
            type="search"
            icon="i-lucide-search"
            placeholder="Search by ticket #, subject, requester, assignee, category, status…"
            aria-label="Search tickets"
            class="w-full"
          />
        </UFormField>
        <UButton type="submit" color="primary">Search</UButton>
        <UButton type="button" color="neutral" variant="outline" @click="resetSearch">Clear</UButton>
      </UForm>
      <UFormField label="Per page" name="perPage" class="meta">
        <USelect
          v-model="searchState.perPage"
          :items="PER_PAGE_ITEMS"
          class="w-full"
        />
      </UFormField>
    </div>
    <div class="cbp-card table-section">
      <p class="table-count" role="status">
        Showing <strong>{{ tableCountLabel }}</strong>
      </p>
      <div class="table-scroll">
        <table class="ticket-table">
          <thead>
            <tr>
              <th class="col-idx" scope="col">#</th>
              <th class="col-id" scope="col">Ticket</th>
              <th class="col-subj" scope="col">Subject</th>
              <th class="col-req" scope="col">Requester</th>
              <th class="col-assignee" scope="col">Assigned to</th>
              <th class="col-cat" scope="col">Category</th>
              <th class="col-status" scope="col">Status</th>
              <th class="col-priority" scope="col">Priority</th>
              <th v-if="canReassign" class="col-action" scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="tableColspan" class="cell-loading">Loading…</td>
            </tr>
            <template v-else>
            <tr v-for="(t, idx) in rows" :key="t.id">
              <td class="col-idx">
                <span class="row-counter">{{ counterFor(idx) }}</span>
              </td>
              <td class="col-id">
                <RouterLink :to="`/tickets/${t.id}`" class="ticket-link">{{ t.ticket_number }}</RouterLink>
              </td>
              <td class="col-subj">
                <RouterLink :to="`/tickets/${t.id}`" class="row-subj-line">{{ t.subject }}</RouterLink>
              </td>
              <td class="col-req">
                <div class="row-person">
                  <CbpAvatar size="sm" :name="t.requester_name || 'Requester'" :image-url="null" />
                  <span class="row-person-name">{{ t.requester_name || '—' }}</span>
                </div>
              </td>
              <td class="col-assignee">
                <div v-if="t.assignee" class="row-person">
                  <CbpAvatar size="sm" :name="t.assignee.name" :image-url="t.assignee.avatar_url ?? null" />
                  <span class="row-person-name">{{ t.assignee.name }}</span>
                </div>
                <span v-else class="cell-empty">—</span>
              </td>
              <td class="col-cat">
                <USelect
                  v-if="canEditCategoryRow(t)"
                  :model-value="t.category?.id"
                  :items="categoryItems"
                  :disabled="categoryUpdatingId === t.id || categoryItems.length === 0"
                  placeholder="Select category"
                  size="sm"
                  class="w-full category-select"
                  value-key="value"
                  @update:model-value="updateTicketCategory(t, $event as number | undefined)"
                />
                <span v-else class="cell-cat-name">{{ t.category?.name || '—' }}</span>
              </td>
              <td class="col-status">
                <span
                  class="pill"
                  :style="{ background: statusMeta(t.status).bg, color: statusMeta(t.status).color }"
                >
                  {{ statusMeta(t.status).label }}
                </span>
              </td>
              <td class="col-priority">
                <span
                  class="pill"
                  :style="{ background: priorityMeta(t.priority).bg, color: priorityMeta(t.priority).color }"
                >
                  {{ priorityMeta(t.priority).label }}
                </span>
              </td>
              <td v-if="canReassign" class="col-action">
                <UButton
                  v-if="canReassignRow(t)"
                  type="button"
                  color="neutral"
                  variant="outline"
                  size="xs"
                  label="Reassign"
                  title="Reassign this ticket to another agent"
                  @click.stop="openReassign(t)"
                />
                <span v-else class="cell-empty">—</span>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td :colspan="tableColspan" class="cell-empty-msg">
                No tickets yet — create one from <RouterLink to="/tickets/new">New ticket</RouterLink>.
              </td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="pager">
        <UButton type="button" color="neutral" variant="outline" size="sm" :disabled="!hasPrev || loading" @click="page -= 1; load()">
          Previous
        </UButton>
        <span>Page {{ page }} of {{ lastPage }}</span>
        <UButton type="button" color="neutral" variant="outline" size="sm" :disabled="!hasNext || loading" @click="page += 1; load()">
          Next
        </UButton>
      </div>
    </div>

    <TicketReassignModal
      :ticket="reassignTicket"
      @close="closeReassign"
      @reassigned="load"
    />
  </div>
</template>

<style scoped>
.tools {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}
.searchbar {
  display: flex;
  gap: 0.5rem;
  flex: 1;
  min-width: min(36rem, 100%);
}
.searchbar input {
  flex: 1;
  min-width: 14rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.45rem 0.65rem;
}
.searchbar button {
  border: 1px solid #119a48;
  background: #119a48;
  color: #fff;
  border-radius: 4px;
  padding: 0.45rem 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.searchbar button.ghost {
  border-color: #cbd5e1;
  background: #fff;
  color: #334155;
}
.meta label {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: #475569;
  font-size: 0.88rem;
}
.meta select {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
}
.table-section {
  padding: 1rem 1.1rem;
}
.err {
  color: #b91c1c;
}
.pager {
  margin-top: 0.75rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  align-items: center;
}
.pager button {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 4px;
  padding: 0.35rem 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.pager button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.col-action {
  text-align: right;
  white-space: nowrap;
}
.col-cat {
  min-width: 9rem;
  max-width: 12rem;
}
.cell-cat-name {
  font-size: 0.82rem;
  color: #334155;
}
.category-select {
  min-width: 8.5rem;
}
.reassign-btn {
  padding: 0.25rem 0.45rem;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #1e293b;
  font-size: 0.68rem;
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
</style>
