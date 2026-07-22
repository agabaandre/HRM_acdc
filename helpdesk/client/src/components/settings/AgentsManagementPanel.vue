<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { RouterLink } from 'vue-router'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError, type SelectNumberItem } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess, notifyWarning } from '../../lib/notify'

interface Cat {
  id: number
  name: string
}

interface SupportGroupRow {
  id: number
  name: string
  slug: string
  description: string | null
  sort_order: number
  is_active: boolean
  is_system: boolean
  members_count: number
  categories: Cat[]
  members: { id: number; name: string; email: string }[]
}

interface AgentRow {
  id: number
  name: string
  email: string
  staff_id: number | null
  can_manage_kb: boolean
  can_reassign_tickets: boolean
  can_delete_request_attachments: boolean
  can_change_ticket_category: boolean
  can_manage_it_assets: boolean
  can_manage_licenses: boolean
  can_submit_software_requests: boolean
  can_approve_software_requests: boolean
  can_manage_software_requests: boolean
  grant_helpdesk_admin: boolean
  grant_supervisor_access: boolean
  is_designated_agent?: boolean
  is_agent_disabled?: boolean
  routing_eligible?: boolean
  role?: string | null
  categories: Cat[]
  support_groups: { id: number; name: string; slug: string }[]
  inherited_categories: Cat[]
  effective_categories: Cat[]
}

interface StaffPermissionRow {
  id: number
  name: string
  email: string
  staff_id: number | null
  role: string | null
  staff_portal_role: number | null
  grant_helpdesk_admin: boolean
  grant_supervisor_access: boolean
  can_manage_kb: boolean
  can_reassign_tickets: boolean
  can_delete_request_attachments: boolean
  can_change_ticket_category: boolean
  can_manage_it_assets: boolean
  can_manage_licenses: boolean
  can_submit_software_requests: boolean
  can_approve_software_requests: boolean
  can_manage_software_requests: boolean
}

interface CandidateRow {
  staff_id: number
  name: string
  work_email: string | null
  duty_station_name: string | null
  division_id: number
  division_name: string
  has_user: boolean
  current_role: string | null
  is_designated_agent: boolean
}

type SettingsTab = 'groups' | 'agents' | 'permissions'

const activeTab = ref<SettingsTab>('groups')
const cats = ref<Cat[]>([])
const groups = ref<SupportGroupRow[]>([])
const agents = ref<AgentRow[]>([])
const groupModalOpen = ref(false)
const groupEditingId = ref<number | null>(null)
const groupEditingIsSystem = ref(false)
const groupForm = reactive({
  name: '',
  description: '',
  sort_order: 0,
  category_ids: [] as number[],
  member_user_ids: [] as number[],
  is_active: true,
})
const selection = ref<Record<number, number[]>>({})
const groupSelection = ref<Record<number, number[]>>({})
const agentPermSelection = ref<Record<number, string[]>>({})
const staffPermissions = ref<StaffPermissionRow[]>([])
const staffPermSelection = ref<Record<number, string[]>>({})

const groupHeaders: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '200px' },
  { title: 'Categories', key: 'categories', sortable: false, minWidth: '200px' },
  { title: 'Members', key: 'members_count', sortable: false, width: '100px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const STAFF_OVERRIDE_OPTIONS = [
  { key: 'grant_helpdesk_admin', label: 'Helpdesk admin' },
  { key: 'grant_supervisor_access', label: 'Supervisor' },
  { key: 'can_manage_kb', label: 'Manage FAQs' },
  { key: 'can_reassign_tickets', label: 'Reassign tickets' },
  { key: 'can_delete_request_attachments', label: 'Delete attachments' },
  { key: 'can_change_ticket_category', label: 'Change category' },
  { key: 'can_manage_it_assets', label: 'IT assets' },
  { key: 'can_manage_licenses', label: 'Licenses' },
  { key: 'can_submit_software_requests', label: 'SW requests (submit)' },
  { key: 'can_approve_software_requests', label: 'SW requests (approve)' },
  { key: 'can_manage_software_requests', label: 'SW requests (manage)' },
] as const

const staffOverrideSelectItems = STAFF_OVERRIDE_OPTIONS.map((o) => ({
  label: o.label,
  value: o.key,
}))

type StaffOverrideKey = (typeof STAFF_OVERRIDE_OPTIONS)[number]['key']

function agentOverrideKeysFromAgent(a: AgentRow): string[] {
  const keys: string[] = []
  for (const opt of STAFF_OVERRIDE_OPTIONS) {
    if ((a as unknown as Record<string, unknown>)[opt.key]) {
      keys.push(opt.key)
    }
  }
  return keys
}

function staffOverrideKeysFromRow(row: StaffPermissionRow): string[] {
  const keys: string[] = []
  for (const opt of STAFF_OVERRIDE_OPTIONS) {
    if (row[opt.key as keyof StaffPermissionRow]) {
      keys.push(opt.key)
    }
  }
  return keys
}

function overridePayloadFromKeys(selectedKeys: string[]): Record<StaffOverrideKey, boolean> {
  const selected = new Set(selectedKeys)
  const payload = {} as Record<StaffOverrideKey, boolean>
  for (const opt of STAFF_OVERRIDE_OPTIONS) {
    payload[opt.key] = selected.has(opt.key)
  }
  return payload
}

function staffOverridePayload(userId: number): Record<StaffOverrideKey, boolean> {
  return overridePayloadFromKeys(staffPermSelection.value[userId] ?? [])
}

function agentOverridePayload(userId: number): Record<StaffOverrideKey, boolean> {
  return overridePayloadFromKeys(agentPermSelection.value[userId] ?? [])
}

const pickerOpen = ref(false)
const candidates = ref<CandidateRow[]>([])
const candidatesLoading = ref(false)
const candidatesLoaded = ref(false)
const candidatesMessage = ref<string | null>(null)
const candidateSearch = ref('')
const onlyUnassigned = ref(true)
const busyStaffId = ref<number | null>(null)
const savingGroupId = ref<number | null>(null)
const configuringAgentId = ref<number | null>(null)
const busyAgentId = ref<number | null>(null)
const agentSearch = ref('')

const agentOptions = computed(() =>
  agents.value.map((a) => ({ id: a.id, label: `${a.name} (${a.email})` })),
)

const agentSelectItems = computed((): SelectNumberItem[] =>
  agentOptions.value.map((o) => ({ label: o.label, value: o.id })),
)

const categorySelectItems = computed((): SelectNumberItem[] =>
  cats.value.map((c) => ({ label: c.name, value: c.id })),
)

const activeGroupSelectItems = computed((): SelectNumberItem[] =>
  groups.value.filter((g) => g.is_active).map((g) => ({ label: g.name, value: g.id })),
)

const groupModalTitle = computed(() => (groupEditingId.value ? 'Edit support group' : 'Add support group'))

function categoriesLabel(group: SupportGroupRow): string {
  const list = group.categories ?? []
  if (list.length === 0) return 'All categories (catch-all)'
  if (list.length <= 3) return list.map((c) => c.name).join(', ')
  return `${list.slice(0, 2).map((c) => c.name).join(', ')} +${list.length - 2}`
}

async function loadCats() {
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
}

async function loadGroups() {
  const { data } = await api.get<{ data: SupportGroupRow[] }>('/api/v1/admin/support-groups')
  groups.value = Array.isArray(data.data) ? data.data : []
}

async function loadAgents() {
  const { data } = await api.get<{ data: AgentRow[] }>('/api/v1/admin/agents')
  const list = Array.isArray(data.data) ? data.data : []
  agents.value = list
  const map: Record<number, number[]> = {}
  const grp: Record<number, number[]> = {}
  const perms: Record<number, string[]> = {}
  for (const a of list) {
    map[a.id] = (a.categories ?? []).map((c) => c.id)
    grp[a.id] = (a.support_groups ?? []).map((g) => g.id)
    perms[a.id] = agentOverrideKeysFromAgent(a)
  }
  selection.value = map
  groupSelection.value = grp
  agentPermSelection.value = perms
}

async function loadStaffPermissions() {
  const { data } = await api.get<{ data: StaffPermissionRow[] }>('/api/v1/admin/staff-permissions')
  const list = Array.isArray(data.data) ? data.data : []
  staffPermissions.value = list
  const map: Record<number, string[]> = {}
  for (const row of list) {
    map[row.id] = staffOverrideKeysFromRow(row)
  }
  staffPermSelection.value = map
}

async function loadAll() {
  try {
    await loadGroups()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load support groups.'))
    groups.value = []
  }
  try {
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load agents.'))
    agents.value = []
  }
  try {
    await loadStaffPermissions()
  } catch {
    staffPermissions.value = []
  }
  try {
    await loadCats()
  } catch (e: unknown) {
    notifyWarning(apiErrorMessage(e, 'Failed to load categories.'))
    cats.value = []
  }
}

function resetGroupForm() {
  groupForm.name = ''
  groupForm.description = ''
  groupForm.sort_order = 0
  groupForm.category_ids = []
  groupForm.member_user_ids = []
  groupForm.is_active = true
}

function openCreateGroupModal() {
  groupEditingId.value = null
  groupEditingIsSystem.value = false
  resetGroupForm()
  groupModalOpen.value = true
}

function openEditGroupModal(group: SupportGroupRow) {
  groupEditingId.value = group.id
  groupEditingIsSystem.value = group.is_system
  groupForm.name = group.name
  groupForm.description = group.description ?? ''
  groupForm.sort_order = group.sort_order
  groupForm.category_ids = (group.categories ?? []).map((c) => c.id)
  groupForm.member_user_ids = (group.members ?? []).map((m) => m.id)
  groupForm.is_active = group.is_active
  groupModalOpen.value = true
}

function closeGroupModal() {
  groupModalOpen.value = false
  groupEditingId.value = null
  groupEditingIsSystem.value = false
  resetGroupForm()
}

function validateGroupForm(state: typeof groupForm): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Enter a group name')
  if (nameErr) {
    errors.push(nameErr)
  }
  return errors
}

async function saveGroupModal(_event?: FormSubmitEvent<typeof groupForm>) {
  const name = groupForm.name.trim()
  if (!name) {
    notifyError('Enter a group name')
    return
  }
  const payload = {
    name,
    description: groupForm.description.trim() || null,
    sort_order: groupForm.sort_order,
    is_active: groupForm.is_active,
    category_ids: groupForm.category_ids.map((id) => Number(id)),
    member_user_ids: groupForm.member_user_ids.map((id) => Number(id)),
  }

  savingGroupId.value = groupEditingId.value ?? -1
  try {
    if (groupEditingId.value) {
      await api.put(`/api/v1/admin/support-groups/${groupEditingId.value}`, payload)
      notifySuccess(`Saved ${name}`)
    } else {
      await api.post('/api/v1/admin/support-groups', payload)
      notifySuccess(`Created ${name}`)
    }
    closeGroupModal()
    await loadGroups()
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, groupEditingId.value ? 'Failed to save support group.' : 'Failed to create support group.'))
  } finally {
    savingGroupId.value = null
  }
}

async function deleteGroup(group: SupportGroupRow) {
  if (group.is_system) {
    notifyError('System groups cannot be deleted. Deactivate instead.')
    return
  }
  if (!window.confirm(`Delete support group “${group.name}”?`)) return
  try {
    await api.delete(`/api/v1/admin/support-groups/${group.id}`)
    notifySuccess(`Deleted ${group.name}`)
    await loadGroups()
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to delete group.'))
  }
}

async function saveStaffPermissions(userId: number) {
  try {
    await api.put(`/api/v1/admin/staff-permissions/${userId}`, staffOverridePayload(userId))
    notifySuccess(`Saved permission overrides for user #${userId}`)
    await loadStaffPermissions()
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  }
}

function portalRoleLabel(role: number | null): string {
  if (!role) return '—'
  if (role === 10) return '10 (System admin)'
  return String(role)
}

function effectiveLabel(catsList: Cat[]): string {
  if (!catsList.length) return 'All categories'
  return catsList.map((c) => c.name).join(', ')
}

async function saveAgent(userId: number) {
  try {
    await api.put(`/api/v1/admin/agents/${userId}`, {
      category_ids: (selection.value[userId] ?? []).map((id) => Number(id)),
      support_group_ids: (groupSelection.value[userId] ?? []).map((id) => Number(id)),
      ...agentOverridePayload(userId),
    })
    notifySuccess(`Saved settings for agent #${userId}`)
    configuringAgentId.value = null
    await loadAgents()
    await loadGroups()
    await loadStaffPermissions()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  }
}

async function openPicker() {
  activeTab.value = 'agents'
  pickerOpen.value = true
  if (!candidatesLoaded.value) await loadCandidates()
}

async function loadCandidates() {
  candidatesLoading.value = true
  candidatesMessage.value = null
  try {
    const { data } = await api.get<{
      data: { candidates: CandidateRow[]; division_ids: number[] }
      meta?: { message?: string }
    }>('/api/v1/admin/agents/division-candidates')
    candidates.value = Array.isArray(data.data?.candidates) ? data.data.candidates : []
    candidatesMessage.value = data.meta?.message ?? null
    candidatesLoaded.value = true
  } catch (e: unknown) {
    notifyWarning(apiErrorMessage(e, 'Failed to load staff from configured divisions.'))
    candidates.value = []
  } finally {
    candidatesLoading.value = false
  }
}

const filteredCandidates = computed<CandidateRow[]>(() => {
  const q = candidateSearch.value.trim().toLowerCase()
  return candidates.value.filter((c) => {
    if (onlyUnassigned.value && (c.current_role === 'agent' || c.is_designated_agent)) return false
    if (q === '') return true
    const hay = `${c.name} ${c.work_email ?? ''} ${c.division_name} ${c.staff_id}`.toLowerCase()
    return hay.includes(q)
  })
})

async function addAgent(c: CandidateRow) {
  if (!c.work_email) {
    notifyError(`${c.name} has no work email in the directory — cannot add as agent.`)
    return
  }
  busyStaffId.value = c.staff_id
  try {
    await api.post('/api/v1/admin/agents/designate', {
      staff_id: c.staff_id,
      work_email: c.work_email,
      name: c.name,
      division_id: c.division_id || null,
      duty_station: c.duty_station_name || null,
    })
    c.is_designated_agent = true
    c.has_user = true
    if (!c.current_role || c.current_role === 'user') {
      c.current_role = 'agent'
    }
    notifySuccess(`${c.name} added — assign support groups and categories below.`)
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to add agent.'))
  } finally {
    busyStaffId.value = null
  }
}

async function removeAgent(a: AgentRow) {
  if (!a.staff_id) {
    notifyError('This user has no staff_id and cannot be unmarked from here.')
    return
  }
  if (!window.confirm(`Remove ${a.name} from agents? Their assigned tickets are kept; they go back to "user" role.`)) {
    return
  }
  busyAgentId.value = a.id
  try {
    await api.delete(`/api/v1/admin/agents/designate/${a.staff_id}`)
    notifySuccess(`${a.name} removed from agents.`)
    if (configuringAgentId.value === a.id) configuringAgentId.value = null
    await loadAgents()
    await loadGroups()
    const match = candidates.value.find((c) => c.staff_id === a.staff_id)
    if (match) {
      match.current_role = 'user'
      match.is_designated_agent = false
    }
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to remove agent.'))
  } finally {
    busyAgentId.value = null
  }
}

async function toggleAgentDisabled(a: AgentRow) {
  const next = !a.is_agent_disabled
  const label = next ? 'disable' : 'enable'
  if (!window.confirm(`${next ? 'Disable' : 'Enable'} ${a.name} for ticket routing? They remain listed as agents.`)) {
    return
  }
  busyAgentId.value = a.id
  try {
    const { data } = await api.put<{ data: { is_agent_disabled: boolean; routing_eligible: boolean } }>(
      `/api/v1/admin/agents/${a.id}/disabled`,
      { is_agent_disabled: next },
    )
    a.is_agent_disabled = !!data.data?.is_agent_disabled
    a.routing_eligible = !!data.data?.routing_eligible
    notifySuccess(`${a.name} ${label}d for routing.`)
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, `Failed to ${label} agent.`))
  } finally {
    busyAgentId.value = null
  }
}

const filteredAgents = computed(() => {
  const q = agentSearch.value.trim().toLowerCase()
  if (!q) return agents.value
  return agents.value.filter((a) => {
    const hay = `${a.name} ${a.email} ${a.staff_id ?? ''} ${a.role ?? ''}`.toLowerCase()
    return hay.includes(q)
  })
})

const configuringAgent = computed(() =>
  agents.value.find((a) => a.id === configuringAgentId.value) ?? null,
)

const configureModalOpen = computed({
  get: () => configuringAgentId.value != null,
  set: (open: boolean) => {
    if (!open) configuringAgentId.value = null
  },
})

function toggleConfigureAgent(a: AgentRow) {
  configuringAgentId.value = configuringAgentId.value === a.id ? null : a.id
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <section class="routing-hub" aria-labelledby="agents-heading">
    <header class="hub-header">
      <div>
        <h2 id="agents-heading">Agents &amp; support groups</h2>
        <p class="lede">
          Organise routing with <strong>support groups</strong> (shared categories and members), then fine-tune
          <strong>per-agent</strong> category access. Tickets can be assigned to a group, an agent, or both.
          Agents need at least one category (direct or via a support group) to receive routed tickets —
          empty category access means <strong>not eligible</strong>. Onboarding-division staff stay listed,
          but tickets they create are auto-routed to eligible agents.
        </p>
      </div>
      <UButton v-if="activeTab === 'agents'" type="button" color="primary" @click="openPicker">
        + Add agent
      </UButton>
      <UButton v-else-if="activeTab === 'groups'" type="button" color="primary" @click="openCreateGroupModal">
        + New group
      </UButton>
    </header>

    <nav class="hub-tabs" aria-label="Routing settings sections">
      <button type="button" class="hub-tab" :class="{ active: activeTab === 'groups' }" @click="activeTab = 'groups'">
        Support groups
        <span class="tab-count">{{ groups.length }}</span>
      </button>
      <button type="button" class="hub-tab" :class="{ active: activeTab === 'agents' }" @click="activeTab = 'agents'">
        Agents
        <span class="tab-count">{{ agents.length }}</span>
      </button>
      <button
        type="button"
        class="hub-tab"
        :class="{ active: activeTab === 'permissions' }"
        @click="activeTab = 'permissions'"
      >
        Permission overrides
        <span v-if="staffPermissions.length" class="tab-count">{{ staffPermissions.length }}</span>
      </button>
    </nav>

    <!-- Support groups -->
    <div v-show="activeTab === 'groups'" class="tab-panel">
      <div class="toolbar">
        <p class="toolbar-hint muted">
          Edit each group in a modal (categories, members, active). Leave categories empty for a catch-all group.
        </p>
        <UButton color="primary" @click="openCreateGroupModal">Add group</UButton>
      </div>

      <v-card v-if="groups.length" class="group-table-card" elevation="10">
        <v-data-table
          :headers="groupHeaders"
          :items="groups"
          item-value="id"
          density="comfortable"
          class="hd-data-table"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <strong>{{ item.name }}</strong>
            <div v-if="item.description" class="group-desc">{{ item.description }}</div>
            <div class="badges">
              <span v-if="item.is_system" class="badge badge-system">Default</span>
              <span class="badge badge-muted">{{ item.slug }}</span>
            </div>
          </template>
          <template #item.categories="{ item }">
            <span class="routes-cell" :title="categoriesLabel(item)">{{ categoriesLabel(item) }}</span>
          </template>
          <template #item.members_count="{ item }">
            {{ item.members_count }}
          </template>
          <template #item.is_active="{ item }">
            <span class="status-pill" :class="item.is_active ? 'status-pill--on' : 'status-pill--off'">
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </span>
          </template>
          <template #item.actions="{ item }">
            <div class="action-row">
              <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditGroupModal(item)">
                Edit
              </UButton>
              <UButton
                v-if="!item.is_system"
                type="button"
                color="error"
                variant="soft"
                size="small"
                @click="deleteGroup(item)"
              >
                Delete
              </UButton>
            </div>
          </template>
        </v-data-table>
      </v-card>
      <div v-else class="empty-state">
        <p class="empty-title">No support groups yet</p>
        <p class="empty-text">Create groups to share category routing across agents. Default groups are seeded on deploy.</p>
        <UButton type="button" color="primary" @click="openCreateGroupModal">+ New group</UButton>
      </div>

      <UModal
        v-model:open="groupModalOpen"
        :title="groupModalTitle"
        :ui="{ content: 'max-w-xl' }"
      >
        <template #body>
          <UForm :state="groupForm" :validate="validateGroupForm" class="hd-form hd-form--grid" @submit="saveGroupModal">
            <UFormField label="Name" name="name" required class="span-2">
              <UInput v-model="groupForm.name" type="text" placeholder="e.g. Field support" class="w-full" />
            </UFormField>
            <UFormField label="Description" name="description" class="span-2" stacked-label>
              <UTextarea v-model="groupForm.description" :rows="2" placeholder="Optional summary for admins" class="w-full" />
            </UFormField>
            <UFormField
              label="Issue categories"
              name="category_ids"
              class="span-2"
              stacked-label
              description="Leave empty to route every category (catch-all)"
            >
              <USelect
                v-model="groupForm.category_ids"
                multiple
                :items="categorySelectItems"
                placeholder="Select categories…"
                class="w-full"
              />
            </UFormField>
            <UFormField label="Members" name="member_user_ids" class="span-2">
              <USelect
                v-model="groupForm.member_user_ids"
                multiple
                :items="agentSelectItems"
                placeholder="Select agents…"
                class="w-full"
              />
            </UFormField>
            <UFormField label="Sort order" name="sort_order">
              <UInput v-model.number="groupForm.sort_order" type="number" min="0" />
            </UFormField>
            <UFormField name="is_active">
              <USwitch v-model="groupForm.is_active" label="Group is active for routing" />
            </UFormField>
            <p v-if="groupEditingIsSystem" class="hint span-2">Default system group — deactivate instead of deleting.</p>
          </UForm>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" :disabled="savingGroupId !== null" @click="closeGroupModal">Cancel</UButton>
          <UButton
            color="primary"
            :loading="savingGroupId !== null"
            :label="groupEditingId ? 'Save changes' : 'Create group'"
            @click="saveGroupModal()"
          />
        </template>
      </UModal>
    </div>

    <!-- Agents -->
    <div v-show="activeTab === 'agents'" class="tab-panel">
      <section v-if="pickerOpen" class="picker">
        <header class="picker-head">
          <div>
            <h3>Add agent from directory</h3>
            <p class="picker-hint">
              Staff from
              <RouterLink to="/settings/general">default agent divisions</RouterLink>.
              After adding, subscribe them to support groups on this page.
            </p>
          </div>
          <UButton type="button" color="neutral" variant="outline" size="sm" @click="pickerOpen = false">Close</UButton>
        </header>
        <p v-if="candidatesLoading" class="muted">Loading directory…</p>
        <p v-else-if="candidatesMessage" class="msg msg-warn">
          {{ candidatesMessage }}
          <RouterLink to="/settings/general" class="msg-link">Open General settings →</RouterLink>
        </p>
        <template v-else-if="candidates.length">
          <div class="picker-toolbar">
            <UFormField name="candidateSearch" class="search-wrap">
              <UInput
                v-model="candidateSearch"
                type="search"
                icon="i-lucide-search"
                placeholder="Search staff…"
                autocomplete="off"
                aria-label="Search staff"
                class="w-full"
              />
            </UFormField>
            <UCheckbox v-model="onlyUnassigned" label="Hide existing agents" />
            <UButton type="button" color="neutral" variant="outline" size="sm" :loading="candidatesLoading" @click="loadCandidates">Reload</UButton>
          </div>
          <div class="picker-table-wrap">
            <table class="picker-table">
              <thead>
                <tr><th>Staff</th><th>Division</th><th>Role</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="c in filteredCandidates" :key="c.staff_id">
                  <td>
                    <div class="cand-name">{{ c.name }}</div>
                    <div class="cand-sub">{{ c.work_email || 'No email' }} · SID {{ c.staff_id }}</div>
                  </td>
                  <td><span class="badge badge-div">{{ c.division_name }}</span></td>
                  <td>
                    {{
                      c.is_designated_agent
                        ? (c.current_role === 'admin' ? 'Admin (agent)' : 'Agent')
                        : (c.current_role === 'agent' ? 'Agent' : c.current_role || 'User')
                    }}
                  </td>
                  <td class="cand-actions">
                    <UButton
                      v-if="!c.is_designated_agent"
                      type="button"
                      color="primary"
                      size="xs"
                      :disabled="!c.work_email"
                      :loading="busyStaffId === c.staff_id"
                      @click="addAgent(c)"
                    >
                      Add
                    </UButton>
                    <span v-else class="muted">Already agent</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>

      <div v-if="agents.length" class="agent-table-wrap">
        <div class="agent-toolbar">
          <UFormField name="agentSearch" class="search-wrap">
            <UInput
              v-model="agentSearch"
              type="search"
              icon="i-lucide-search"
              placeholder="Search agents…"
              autocomplete="off"
              aria-label="Search agents"
              class="w-full"
            />
          </UFormField>
          <span class="muted">{{ filteredAgents.length }} of {{ agents.length }}</span>
        </div>

        <table class="agent-table">
          <thead>
            <tr>
              <th>Agent</th>
              <th>Routes</th>
              <th>Status</th>
              <th>Routing</th>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="a in filteredAgents"
              :key="a.id"
              :class="{ 'row-disabled': a.is_agent_disabled, 'row-active-config': configuringAgentId === a.id }"
            >
              <td>
                <div class="agent-name">{{ a.name }}</div>
                <div class="agent-email">{{ a.email }} · Staff ID {{ a.staff_id ?? '—' }}</div>
              </td>
              <td>
                <span class="routes-cell" :title="effectiveLabel(a.effective_categories)">
                  {{ effectiveLabel(a.effective_categories) }}
                </span>
              </td>
              <td>
                <span class="status-pill" :class="a.is_agent_disabled ? 'status-pill--off' : 'status-pill--on'">
                  {{ a.is_agent_disabled ? 'Disabled' : 'Active' }}
                </span>
              </td>
              <td>
                <span class="status-pill" :class="a.routing_eligible ? 'status-pill--on' : 'status-pill--warn'">
                  {{ a.routing_eligible ? 'Eligible' : 'Not eligible' }}
                </span>
              </td>
              <td class="col-actions">
                <div class="action-row">
                  <UButton type="button" color="neutral" variant="outline" size="xs" @click="toggleConfigureAgent(a)">
                    Configure
                  </UButton>
                  <UButton
                    type="button"
                    :color="a.is_agent_disabled ? 'success' : 'warning'"
                    variant="soft"
                    size="xs"
                    :loading="busyAgentId === a.id"
                    @click="toggleAgentDisabled(a)"
                  >
                    {{ a.is_agent_disabled ? 'Enable' : 'Disable' }}
                  </UButton>
                  <UButton
                    type="button"
                    color="error"
                    variant="soft"
                    size="xs"
                    :loading="busyAgentId === a.id"
                    @click="removeAgent(a)"
                  >
                    Remove
                  </UButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-state">
        <p class="empty-title">No agents yet</p>
        <p class="empty-text">Add agents from the staff directory, then assign them to support groups.</p>
        <UButton type="button" color="primary" @click="openPicker">+ Add agent</UButton>
      </div>
    </div>

    <UModal
      v-model:open="configureModalOpen"
      :title="configuringAgent ? `Configure ${configuringAgent.name}` : 'Configure agent'"
      :description="configuringAgent ? `${configuringAgent.email} · Staff ID ${configuringAgent.staff_id ?? '—'}` : undefined"
      :ui="{ content: 'max-w-2xl' }"
    >
      <template v-if="configuringAgent" #body>
        <div class="config-modal-body">
          <div class="effective-pill" :title="effectiveLabel(configuringAgent.effective_categories)">
            Routes: {{ effectiveLabel(configuringAgent.effective_categories) }}
          </div>

          <UFormField label="Support groups">
            <USelect
              v-model="groupSelection[configuringAgent.id]"
              multiple
              :items="activeGroupSelectItems"
              placeholder="Select groups…"
              class="w-full"
            />
            <p v-if="configuringAgent.inherited_categories.length" class="inherited">
              Inherited:
              <span v-for="c in configuringAgent.inherited_categories" :key="c.id" class="chip chip--inherited">{{ c.name }}</span>
            </p>
          </UFormField>

          <UFormField label="Direct categories">
            <USelect
              v-model="selection[configuringAgent.id]"
              multiple
              :items="categorySelectItems"
              placeholder="Select categories…"
              class="w-full"
            />
            <p v-if="!(selection[configuringAgent.id] ?? []).length" class="hint">
              No direct categories — agent is only eligible via support-group categories (or a catch-all group).
            </p>
          </UFormField>

          <UFormField label="Permissions">
            <USelect
              v-model="agentPermSelection[configuringAgent.id]"
              multiple
              :items="staffOverrideSelectItems"
              placeholder="Select permissions…"
              class="w-full"
            />
          </UFormField>
        </div>
      </template>
      <template v-if="configuringAgent" #footer>
        <UButton type="button" color="neutral" variant="outline" @click="configureModalOpen = false">Cancel</UButton>
        <UButton type="button" color="primary" @click="saveAgent(configuringAgent.id)">Save agent</UButton>
      </template>
    </UModal>

    <!-- Permissions -->
    <div v-show="activeTab === 'permissions'" class="tab-panel">
      <p v-if="!staffPermissions.length" class="muted">No permission overrides — staff appear here after they sign in.</p>
      <table v-else class="tbl">
        <thead>
          <tr><th>Staff</th><th>Portal role</th><th>Helpdesk role</th><th>Overrides</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="row in staffPermissions" :key="row.id">
            <td>
              <div class="agent-name">{{ row.name }}</div>
              <div class="agent-email">{{ row.email }}</div>
            </td>
            <td>{{ portalRoleLabel(row.staff_portal_role) }}</td>
            <td>{{ row.role ?? '—' }}</td>
            <td class="overrides-cell">
              <USelect
                v-model="staffPermSelection[row.id]"
                multiple
                :items="staffOverrideSelectItems"
                placeholder="Select overrides…"
                class="w-full overrides-select"
              />
            </td>
            <td><UButton type="button" color="primary" size="xs" @click="saveStaffPermissions(row.id)">Save</UButton></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.routing-hub { display: flex; flex-direction: column; gap: 1rem; }
.hub-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 0.85rem; flex-wrap: wrap;
}
.hub-header h2 { font-size: 1.15rem; margin: 0 0 0.35rem; }
.lede { color: #475569; line-height: 1.5; margin: 0; font-size: 0.9rem; max-width: 52rem; }
.hub-tabs {
  display: flex; flex-wrap: wrap; gap: 0.35rem;
  border-bottom: 1px solid #e2e8f0; padding-bottom: 0.35rem;
}
.hub-tab {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.45rem 0.85rem; border: none; border-radius: 4px 8px 0 0;
  background: transparent; color: #64748b; font-weight: 600; font-size: 0.88rem; cursor: pointer;
}
.hub-tab.active { background: #fff; color: #0d7a3a; box-shadow: inset 0 -2px 0 #0d7a3a; }
.tab-count {
  font-size: 0.72rem; background: #f1f5f9; color: #475569;
  padding: 0.1rem 0.45rem; border-radius: 999px;
}
.hub-tab.active .tab-count { background: #e8f5ee; color: #0d7a3a; }
.tab-panel { display: flex; flex-direction: column; gap: 1rem; }
.card {
  border: none; border-radius: 12px; background: #fff;
  box-shadow: rgba(145, 158, 171, 0.12) 0 12px 24px -4px, rgba(145, 158, 171, 0.2) 0 0 2px 0;
  padding: 1.1rem 1.15rem; display: flex; flex-direction: column; gap: 0.75rem;
}
.card--new { background: #f8fafc; border: 1px dashed #dfe5ef; box-shadow: none; }
.group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
.agent-list { display: flex; flex-direction: column; gap: 1rem; }
.agent-table-wrap {
  display: flex; flex-direction: column; gap: 0.85rem;
  border: none; border-radius: 12px; background: #fff; overflow: hidden;
  box-shadow: rgba(145, 158, 171, 0.12) 0 12px 24px -4px, rgba(145, 158, 171, 0.2) 0 0 2px 0;
  padding: 0.85rem;
}
.agent-toolbar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.agent-toolbar .search-wrap { flex: 1; min-width: 12rem; max-width: 22rem; }
.agent-table {
  width: 100%; border-collapse: collapse; background: transparent;
  border: none; border-radius: 0; overflow: hidden;
}
.agent-table th, .agent-table td {
  text-align: left; padding: 0.75rem 1rem; border-bottom: 1px solid rgba(223, 229, 239, 0.85);
  vertical-align: top; font-size: 0.875rem;
}
.agent-table th { background: #f8fafc; color: #3a4752; font-weight: 600; }
.agent-table tr:last-child td { border-bottom: 0; }
.agent-table .row-disabled { background: #f8fafc; opacity: 0.92; }
.agent-table .row-active-config { background: #f0fdf4; }
.agent-table .col-actions { width: 1%; white-space: nowrap; }
.action-row { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: flex-end; }
.routes-cell {
  display: inline-block; max-width: 18rem; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap; color: #334155;
}
.status-pill {
  display: inline-flex; align-items: center; border-radius: 999px;
  padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 600;
}
.status-pill--on { background: #dcfce7; color: #166534; }
.status-pill--off { background: #e2e8f0; color: #475569; }
.status-pill--warn { background: #fef3c7; color: #92400e; }
.config-modal-body {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.config-modal-body .effective-pill {
  align-self: flex-start;
}
.group-card-head h3, .agent-card-head h3 { margin: 0; font-size: 1rem; }
.group-desc, .agent-email { margin: 0.25rem 0 0; font-size: 0.82rem; color: #64748b; }
.badges { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.45rem; }
.badge {
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
  padding: 0.15rem 0.5rem; border-radius: 999px;
}
.badge-system { background: #e0e7ff; color: #3730a3; }
.badge-on { background: #dcfce7; color: #166534; }
.badge-off { background: #fef2f2; color: #991b1b; }
.badge-muted { background: #f1f5f9; color: #64748b; text-transform: none; }
.badge-div { background: #eef2ff; color: #3730a3; text-transform: none; font-weight: 600; }
.cat-fieldset { border: none; margin: 0; padding: 0; }
.cat-fieldset legend { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.35rem; }
.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.25rem 0.5rem; }
.cat-check { font-size: 0.82rem; color: #334155; display: flex; align-items: center; gap: 0.35rem; font-weight: 500; }
.multi {
  width: 100%; min-height: 100px; padding: 0.35rem 0.5rem;
  border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.85rem;
}
.multi--compact { min-height: 72px; }
.hint { margin: 0.25rem 0 0; font-size: 0.75rem; color: #64748b; }
.card-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.agent-card-head {
  display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; align-items: flex-start;
}
.effective-pill {
  font-size: 0.75rem; background: #f1f5f9; color: #334155;
  padding: 0.35rem 0.55rem; border-radius: 4px; max-width: 280px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.agent-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; }
.agent-col h4 { margin: 0 0 0.35rem; font-size: 0.78rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; }
.inherited { margin: 0.35rem 0 0; font-size: 0.78rem; color: #475569; display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; }
.chip { display: inline-block; padding: 0.12rem 0.45rem; border-radius: 4px; font-size: 0.72rem; font-weight: 600; }
.chip--inherited { background: #e8f5ee; color: #0d7a3a; }
.form-grid { display: grid; gap: 0.65rem; }
.form-grid label { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.82rem; font-weight: 600; color: #334155; }
.form-grid .full { grid-column: 1 / -1; }
.form-grid input, .form-grid textarea { padding: 0.45rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.88rem; font-weight: 400; }
.row-check { flex-direction: row; align-items: center; gap: 0.45rem; font-weight: 600; font-size: 0.85rem; }
.primary {
  padding: 0.55rem 1.1rem; border-radius: 4px; border: none;
  background: linear-gradient(135deg, #0d7a3a, #065f2c); color: #fff;
  font-weight: 700; font-size: 0.9rem; cursor: pointer; white-space: nowrap;
}
.btn { padding: 0.4rem 0.85rem; border-radius: 4px; border: none; background: #119a48; color: #fff; font-weight: 700; cursor: pointer; }
.btn-link-danger { background: transparent; color: #b91c1c; border: none; font-weight: 600; font-size: 0.82rem; cursor: pointer; }
.ghost { padding: 0.4rem 0.85rem; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-weight: 600; cursor: pointer; }
.ghost-sm { padding: 0.32rem 0.7rem; font-size: 0.8rem; }
.perm-toggle { display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; color: #3a4452; margin-bottom: 0.3rem; cursor: pointer; }
.perm-group-label { margin: 0.5rem 0 0.15rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; }
.tbl { width: 100%; border-collapse: collapse; font-size: 0.88rem; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; }
.tbl th, .tbl td { text-align: left; padding: 0.6rem 0.55rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.tbl th { background: #f8fafc; font-size: 0.74rem; text-transform: uppercase; color: #475569; }
.overrides-cell { min-width: 16rem; max-width: 28rem; }
.overrides-select { min-width: 14rem; }
.agent-name { font-weight: 600; }
.empty-state { text-align: center; padding: 2rem 1rem; border: 2px dashed #cbd5e1; border-radius: 4px; background: #f8fafc; }
.empty-title { margin: 0 0 0.5rem; font-weight: 700; }
.empty-text { margin: 0 0 1rem; color: #475569; font-size: 0.9rem; }
.picker {
  padding: 1rem 1.1rem; border-radius: 12px; border: none; background: #fff;
  box-shadow: rgba(145, 158, 171, 0.12) 0 12px 24px -4px, rgba(145, 158, 171, 0.2) 0 0 2px 0;
}
.picker-head { display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.65rem; }
.picker-hint { margin: 0; font-size: 0.85rem; color: #768b9e; }
.picker-toolbar { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; margin-bottom: 0.5rem; }
.search-wrap { flex: 1 1 16rem; }
.search-input { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid #dfe5ef; border-radius: 8px; }
.picker-table-wrap {
  border: none; border-radius: 10px; background: #fff; overflow: auto; max-height: 16rem;
  box-shadow: rgba(145, 158, 171, 0.08) 0 4px 12px -2px, rgba(145, 158, 171, 0.14) 0 0 1px 0;
}
.picker-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.picker-table th { background: #f8fafc; text-align: left; padding: 0.65rem 0.85rem; font-size: 0.8125rem; font-weight: 600; color: #3a4752; }
.picker-table td { padding: 0.65rem 0.85rem; border-bottom: 1px solid rgba(223, 229, 239, 0.85); }
.cand-name { font-weight: 600; }
.cand-sub { font-size: 0.78rem; color: #64748b; }
.btn-add { padding: 0.32rem 0.7rem; border-radius: 4px; border: none; background: #0d7a3a; color: #fff; font-weight: 600; cursor: pointer; }
.msg-warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 0.55rem 0.75rem; border-radius: 4px; font-size: 0.86rem; }
.msg-link { margin-left: 0.4rem; font-weight: 600; color: #92400e; }
.muted { color: #64748b; font-size: 0.85rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.filter-toggle { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; }
</style>
