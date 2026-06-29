<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { RouterLink } from 'vue-router'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError, isCheckboxChecked, type CheckboxValue, type SelectNumberItem } from '../../lib/helpdeskForm'
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
const groupDraft = reactive<Record<number, { category_ids: number[]; member_user_ids: number[]; is_active: boolean }>>({})
const newGroupForm = reactive({
  open: false,
  name: '',
  description: '',
  category_ids: [] as number[],
  member_user_ids: [] as number[],
})
const selection = ref<Record<number, number[]>>({})
const groupSelection = ref<Record<number, number[]>>({})
const kbToggle = ref<Record<number, boolean>>({})
const reassignToggle = ref<Record<number, boolean>>({})
const deleteAttachmentToggle = ref<Record<number, boolean>>({})
const changeCategoryToggle = ref<Record<number, boolean>>({})
const itAssetsToggle = ref<Record<number, boolean>>({})
const licensesToggle = ref<Record<number, boolean>>({})
const swSubmitToggle = ref<Record<number, boolean>>({})
const swApproveToggle = ref<Record<number, boolean>>({})
const swManageToggle = ref<Record<number, boolean>>({})
const adminToggle = ref<Record<number, boolean>>({})
const supervisorToggle = ref<Record<number, boolean>>({})
const staffPermissions = ref<StaffPermissionRow[]>([])
const staffPermAdmin = ref<Record<number, boolean>>({})
const staffPermSupervisor = ref<Record<number, boolean>>({})
const staffPermKb = ref<Record<number, boolean>>({})
const staffPermReassign = ref<Record<number, boolean>>({})
const staffPermDeleteAttachment = ref<Record<number, boolean>>({})
const staffPermChangeCategory = ref<Record<number, boolean>>({})
const staffPermItAssets = ref<Record<number, boolean>>({})
const staffPermLicenses = ref<Record<number, boolean>>({})
const staffPermSwSubmit = ref<Record<number, boolean>>({})
const staffPermSwApprove = ref<Record<number, boolean>>({})
const staffPermSwManage = ref<Record<number, boolean>>({})
const pickerOpen = ref(false)
const candidates = ref<CandidateRow[]>([])
const candidatesLoading = ref(false)
const candidatesLoaded = ref(false)
const candidatesMessage = ref<string | null>(null)
const candidateSearch = ref('')
const onlyUnassigned = ref(true)
const busyStaffId = ref<number | null>(null)
const savingGroupId = ref<number | null>(null)

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

function toggleIdInList(list: number[], id: number, value: CheckboxValue): void {
  const checked = isCheckboxChecked(value)
  const i = list.indexOf(id)
  if (checked && i < 0) {
    list.push(id)
  } else if (!checked && i >= 0) {
    list.splice(i, 1)
  }
}

async function loadCats() {
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
}

function hydrateGroupDrafts(list: SupportGroupRow[]) {
  const draft: Record<number, { category_ids: number[]; member_user_ids: number[]; is_active: boolean }> = {}
  for (const g of list) {
    draft[g.id] = {
      category_ids: (g.categories ?? []).map((c) => c.id),
      member_user_ids: (g.members ?? []).map((m) => m.id),
      is_active: g.is_active,
    }
  }
  Object.assign(groupDraft, draft)
}

async function loadGroups() {
  const { data } = await api.get<{ data: SupportGroupRow[] }>('/api/v1/admin/support-groups')
  const list = Array.isArray(data.data) ? data.data : []
  groups.value = list
  hydrateGroupDrafts(list)
}

async function loadAgents() {
  const { data } = await api.get<{ data: AgentRow[] }>('/api/v1/admin/agents')
  const list = Array.isArray(data.data) ? data.data : []
  agents.value = list
  const map: Record<number, number[]> = {}
  const grp: Record<number, number[]> = {}
  const kb: Record<number, boolean> = {}
  const reassign: Record<number, boolean> = {}
  const deleteAttachment: Record<number, boolean> = {}
  const changeCategory: Record<number, boolean> = {}
  const admin: Record<number, boolean> = {}
  const supervisor: Record<number, boolean> = {}
  for (const a of list) {
    map[a.id] = (a.categories ?? []).map((c) => c.id)
    grp[a.id] = (a.support_groups ?? []).map((g) => g.id)
    kb[a.id] = !!a.can_manage_kb
    reassign[a.id] = !!a.can_reassign_tickets
    deleteAttachment[a.id] = !!a.can_delete_request_attachments
    changeCategory[a.id] = !!a.can_change_ticket_category
    itAssetsToggle.value[a.id] = !!a.can_manage_it_assets
    licensesToggle.value[a.id] = !!a.can_manage_licenses
    swSubmitToggle.value[a.id] = !!a.can_submit_software_requests
    swApproveToggle.value[a.id] = !!a.can_approve_software_requests
    swManageToggle.value[a.id] = !!a.can_manage_software_requests
    admin[a.id] = !!a.grant_helpdesk_admin
    supervisor[a.id] = !!a.grant_supervisor_access
  }
  selection.value = map
  groupSelection.value = grp
  kbToggle.value = kb
  reassignToggle.value = reassign
  deleteAttachmentToggle.value = deleteAttachment
  changeCategoryToggle.value = changeCategory
  adminToggle.value = admin
  supervisorToggle.value = supervisor
}

async function loadStaffPermissions() {
  const { data } = await api.get<{ data: StaffPermissionRow[] }>('/api/v1/admin/staff-permissions')
  const list = Array.isArray(data.data) ? data.data : []
  staffPermissions.value = list
  const admin: Record<number, boolean> = {}
  const supervisor: Record<number, boolean> = {}
  const kb: Record<number, boolean> = {}
  const reassign: Record<number, boolean> = {}
  const deleteAttachment: Record<number, boolean> = {}
  const changeCategory: Record<number, boolean> = {}
  for (const row of list) {
    admin[row.id] = !!row.grant_helpdesk_admin
    supervisor[row.id] = !!row.grant_supervisor_access
    kb[row.id] = !!row.can_manage_kb
    reassign[row.id] = !!row.can_reassign_tickets
    deleteAttachment[row.id] = !!row.can_delete_request_attachments
    changeCategory[row.id] = !!row.can_change_ticket_category
    staffPermItAssets.value[row.id] = !!row.can_manage_it_assets
    staffPermLicenses.value[row.id] = !!row.can_manage_licenses
    staffPermSwSubmit.value[row.id] = !!row.can_submit_software_requests
    staffPermSwApprove.value[row.id] = !!row.can_approve_software_requests
    staffPermSwManage.value[row.id] = !!row.can_manage_software_requests
  }
  staffPermAdmin.value = admin
  staffPermSupervisor.value = supervisor
  staffPermKb.value = kb
  staffPermReassign.value = reassign
  staffPermDeleteAttachment.value = deleteAttachment
  staffPermChangeCategory.value = changeCategory
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

async function saveGroup(group: SupportGroupRow) {
  const draft = groupDraft[group.id]
  if (!draft) return
  savingGroupId.value = group.id
  try {
    await api.put(`/api/v1/admin/support-groups/${group.id}`, {
      name: group.name,
      description: group.description,
      sort_order: group.sort_order,
      is_active: draft.is_active,
      category_ids: draft.category_ids.map((id) => Number(id)),
      member_user_ids: draft.member_user_ids.map((id) => Number(id)),
    })
    notifySuccess(`Saved ${group.name}`)
    await loadGroups()
    await loadAgents()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to save support group.'))
  } finally {
    savingGroupId.value = null
  }
}

async function onCreateGroup(_event: FormSubmitEvent<typeof newGroupForm>) {
  const name = newGroupForm.name.trim()
  savingGroupId.value = -1
  try {
    await api.post('/api/v1/admin/support-groups', {
      name,
      description: newGroupForm.description.trim() || null,
      category_ids: newGroupForm.category_ids.map((id) => Number(id)),
      member_user_ids: newGroupForm.member_user_ids.map((id) => Number(id)),
      is_active: true,
    })
    notifySuccess(`Created ${name}`)
    newGroupForm.open = false
    newGroupForm.name = ''
    newGroupForm.description = ''
    newGroupForm.category_ids = []
    newGroupForm.member_user_ids = []
    await loadGroups()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to create support group.'))
  } finally {
    savingGroupId.value = null
  }
}

function validateNewGroup(state: typeof newGroupForm): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Enter a group name')
  if (nameErr) {
    errors.push(nameErr)
  }
  return errors
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
    await api.put(`/api/v1/admin/staff-permissions/${userId}`, {
      grant_helpdesk_admin: !!staffPermAdmin.value[userId],
      grant_supervisor_access: !!staffPermSupervisor.value[userId],
      can_manage_kb: !!staffPermKb.value[userId],
      can_reassign_tickets: !!staffPermReassign.value[userId],
      can_delete_request_attachments: !!staffPermDeleteAttachment.value[userId],
      can_change_ticket_category: !!staffPermChangeCategory.value[userId],
      can_manage_it_assets: !!staffPermItAssets.value[userId],
      can_manage_licenses: !!staffPermLicenses.value[userId],
      can_submit_software_requests: !!staffPermSwSubmit.value[userId],
      can_approve_software_requests: !!staffPermSwApprove.value[userId],
      can_manage_software_requests: !!staffPermSwManage.value[userId],
    })
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
      can_manage_kb: !!kbToggle.value[userId],
      can_reassign_tickets: !!reassignToggle.value[userId],
      can_delete_request_attachments: !!deleteAttachmentToggle.value[userId],
      can_change_ticket_category: !!changeCategoryToggle.value[userId],
      can_manage_it_assets: !!itAssetsToggle.value[userId],
      can_manage_licenses: !!licensesToggle.value[userId],
      can_submit_software_requests: !!swSubmitToggle.value[userId],
      can_approve_software_requests: !!swApproveToggle.value[userId],
      can_manage_software_requests: !!swManageToggle.value[userId],
      grant_helpdesk_admin: !!adminToggle.value[userId],
      grant_supervisor_access: !!supervisorToggle.value[userId],
    })
    notifySuccess(`Saved settings for agent #${userId}`)
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
  try {
    await api.delete(`/api/v1/admin/agents/designate/${a.staff_id}`)
    notifySuccess(`${a.name} removed from agents.`)
    await loadAgents()
    await loadGroups()
    const match = candidates.value.find((c) => c.staff_id === a.staff_id)
    if (match) {
      match.current_role = 'user'
      match.is_designated_agent = false
    }
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to remove agent.'))
  }
}

function toggleCategoryInDraft(groupId: number, catId: number, value: CheckboxValue) {
  const checked = isCheckboxChecked(value)
  const draft = groupDraft[groupId]
  if (!draft) return
  const set = new Set(draft.category_ids)
  if (checked) set.add(catId)
  else set.delete(catId)
  draft.category_ids = [...set]
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
          Empty category lists mean <strong>all categories</strong> for that group or agent.
        </p>
      </div>
      <UButton v-if="activeTab === 'agents'" type="button" color="primary" @click="openPicker">
        + Add agent
      </UButton>
      <UButton v-else-if="activeTab === 'groups'" type="button" color="primary" @click="newGroupForm.open = true">
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
      <UCard v-if="newGroupForm.open" class="card--new">
        <template #header>
          <h3>New support group</h3>
        </template>
        <UForm
          :state="newGroupForm"
          :validate="validateNewGroup"
          class="hd-form hd-form--grid"
          @submit="onCreateGroup"
        >
          <UFormField label="Name" name="name" required class="full">
            <UInput v-model="newGroupForm.name" type="text" placeholder="e.g. Field support" class="w-full" />
          </UFormField>
          <UFormField label="Description" name="description" class="full">
            <UTextarea v-model="newGroupForm.description" :rows="2" placeholder="Optional summary for admins" class="w-full" />
          </UFormField>
          <fieldset class="full cat-fieldset">
            <legend>Issue categories</legend>
            <div class="cat-grid">
              <UCheckbox
                v-for="c in cats"
                :key="c.id"
                :model-value="newGroupForm.category_ids.includes(c.id)"
                :label="c.name"
                class="cat-check"
                @update:model-value="(value: CheckboxValue) => toggleIdInList(newGroupForm.category_ids, c.id, value)"
              />
            </div>
            <p class="hint">Leave all unchecked to route every category to this group.</p>
          </fieldset>
          <UFormField label="Members" name="member_user_ids" class="full">
            <USelect
              v-model="newGroupForm.member_user_ids"
              multiple
              :items="agentSelectItems"
              placeholder="Select agents…"
              class="w-full"
            />
          </UFormField>
          <div class="full hd-form-actions">
            <UButton type="submit" color="primary" :loading="savingGroupId === -1">Create group</UButton>
            <UButton type="button" color="neutral" variant="outline" @click="newGroupForm.open = false">Cancel</UButton>
          </div>
        </UForm>
      </UCard>

      <div v-if="groups.length" class="group-grid">
        <article v-for="g in groups" :key="g.id" class="card group-card">
          <header class="group-card-head">
            <div>
              <h3>{{ g.name }}</h3>
              <p v-if="g.description" class="group-desc">{{ g.description }}</p>
              <div class="badges">
                <span v-if="g.is_system" class="badge badge-system">Default</span>
                <span class="badge" :class="groupDraft[g.id]?.is_active ? 'badge-on' : 'badge-off'">
                  {{ groupDraft[g.id]?.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span class="badge badge-muted">{{ g.members_count }} member{{ g.members_count === 1 ? '' : 's' }}</span>
              </div>
            </div>
          </header>

          <fieldset class="cat-fieldset">
            <legend>Categories</legend>
            <div class="cat-grid">
              <UCheckbox
                v-for="c in cats"
                :key="c.id"
                :model-value="(groupDraft[g.id]?.category_ids ?? []).includes(c.id)"
                :label="c.name"
                class="cat-check"
                @update:model-value="(value: CheckboxValue) => toggleCategoryInDraft(g.id, c.id, value)"
              />
            </div>
          </fieldset>

          <UFormField label="Members">
            <USelect
              v-if="groupDraft[g.id]"
              v-model="groupDraft[g.id].member_user_ids"
              multiple
              :items="agentSelectItems"
              placeholder="Select members…"
              class="w-full"
            />
          </UFormField>

          <UFormField v-if="groupDraft[g.id]" name="is_active">
            <USwitch v-model="groupDraft[g.id].is_active" label="Group is active for routing" />
          </UFormField>

          <div class="card-actions">
            <UButton type="button" color="primary" size="sm" :loading="savingGroupId === g.id" @click="saveGroup(g)">
              Save group
            </UButton>
            <UButton v-if="!g.is_system" type="button" color="error" variant="link" size="sm" @click="deleteGroup(g)">Delete</UButton>
          </div>
        </article>
      </div>
      <div v-else class="empty-state">
        <p class="empty-title">No support groups yet</p>
        <p class="empty-text">Create groups to share category routing across agents. Default groups are seeded on deploy.</p>
        <UButton type="button" color="primary" @click="newGroupForm.open = true">+ New group</UButton>
      </div>
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

      <div v-if="agents.length" class="agent-list">
        <article v-for="a in agents" :key="a.id" class="card agent-card">
          <header class="agent-card-head">
            <div>
              <h3>{{ a.name }}</h3>
              <p class="agent-email">{{ a.email }} · Staff ID {{ a.staff_id ?? '—' }}</p>
            </div>
            <div class="effective-pill" :title="effectiveLabel(a.effective_categories)">
              Routes: {{ effectiveLabel(a.effective_categories) }}
            </div>
          </header>

          <div class="agent-cols">
            <div class="agent-col">
              <h4>Support groups</h4>
              <USelect
                v-model="groupSelection[a.id]"
                multiple
                :items="activeGroupSelectItems"
                placeholder="Select groups…"
                class="w-full"
              />
              <p v-if="a.inherited_categories.length" class="inherited">
                Inherited:
                <span v-for="c in a.inherited_categories" :key="c.id" class="chip chip--inherited">{{ c.name }}</span>
              </p>
            </div>

            <div class="agent-col">
              <h4>Direct categories</h4>
              <USelect
                v-model="selection[a.id]"
                multiple
                :items="categorySelectItems"
                placeholder="Select categories…"
                class="w-full"
              />
              <p v-if="!(selection[a.id] ?? []).length" class="hint">No direct filter — uses group inheritance or all categories.</p>
            </div>

            <div class="agent-col">
              <h4>Permissions</h4>
              <UCheckbox v-model="adminToggle[a.id]" label="Helpdesk admin" class="perm-toggle" />
              <UCheckbox v-model="supervisorToggle[a.id]" label="Supervisor access" class="perm-toggle" />
              <UCheckbox v-model="kbToggle[a.id]" label="Manage FAQs" class="perm-toggle" />
              <UCheckbox v-model="reassignToggle[a.id]" label="Reassign tickets" class="perm-toggle" />
              <UCheckbox v-model="deleteAttachmentToggle[a.id]" label="Delete request attachments" class="perm-toggle" />
              <UCheckbox v-model="changeCategoryToggle[a.id]" label="Change ticket category" class="perm-toggle" />
              <p class="perm-group-label">Tools</p>
              <UCheckbox v-model="itAssetsToggle[a.id]" label="Manage IT assets" class="perm-toggle" />
              <UCheckbox v-model="licensesToggle[a.id]" label="Manage licenses" class="perm-toggle" />
              <UCheckbox v-model="swSubmitToggle[a.id]" label="Submit software requests" class="perm-toggle" />
              <UCheckbox v-model="swApproveToggle[a.id]" label="Approve software requests" class="perm-toggle" />
              <UCheckbox v-model="swManageToggle[a.id]" label="Manage software requests" class="perm-toggle" />
            </div>
          </div>

          <div class="card-actions">
            <UButton type="button" color="primary" size="sm" @click="saveAgent(a.id)">Save agent</UButton>
            <UButton type="button" color="error" variant="link" size="sm" @click="removeAgent(a)">Remove</UButton>
          </div>
        </article>
      </div>
      <div v-else class="empty-state">
        <p class="empty-title">No agents yet</p>
        <p class="empty-text">Add agents from the staff directory, then assign them to support groups.</p>
        <UButton type="button" color="primary" @click="openPicker">+ Add agent</UButton>
      </div>
    </div>

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
            <td>
              <UCheckbox v-model="staffPermAdmin[row.id]" label="Helpdesk admin" class="perm-toggle" />
              <UCheckbox v-model="staffPermSupervisor[row.id]" label="Supervisor" class="perm-toggle" />
              <UCheckbox v-model="staffPermKb[row.id]" label="Manage FAQs" class="perm-toggle" />
              <UCheckbox v-model="staffPermReassign[row.id]" label="Reassign" class="perm-toggle" />
              <UCheckbox v-model="staffPermDeleteAttachment[row.id]" label="Delete attachments" class="perm-toggle" />
              <UCheckbox v-model="staffPermChangeCategory[row.id]" label="Change category" class="perm-toggle" />
              <UCheckbox v-model="staffPermItAssets[row.id]" label="IT assets" class="perm-toggle" />
              <UCheckbox v-model="staffPermLicenses[row.id]" label="Licenses" class="perm-toggle" />
              <UCheckbox v-model="staffPermSwSubmit[row.id]" label="SW requests (submit)" class="perm-toggle" />
              <UCheckbox v-model="staffPermSwApprove[row.id]" label="SW requests (approve)" class="perm-toggle" />
              <UCheckbox v-model="staffPermSwManage[row.id]" label="SW requests (manage)" class="perm-toggle" />
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
  border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;
  padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;
}
.card--new { background: #f8fafc; border-style: dashed; }
.group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
.agent-list { display: flex; flex-direction: column; gap: 1rem; }
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
.tbl th, .tbl td { text-align: left; padding: 0.6rem 0.55rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.tbl th { background: #f8fafc; font-size: 0.74rem; text-transform: uppercase; color: #475569; }
.agent-name { font-weight: 600; }
.empty-state { text-align: center; padding: 2rem 1rem; border: 2px dashed #cbd5e1; border-radius: 4px; background: #f8fafc; }
.empty-title { margin: 0 0 0.5rem; font-weight: 700; }
.empty-text { margin: 0 0 1rem; color: #475569; font-size: 0.9rem; }
.picker { padding: 0.85rem 1rem; border-radius: 4px; border: 1px solid #e2e8f0; background: #f8fafc; }
.picker-head { display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.65rem; }
.picker-hint { margin: 0; font-size: 0.85rem; color: #64748b; }
.picker-toolbar { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; margin-bottom: 0.5rem; }
.search-wrap { flex: 1 1 16rem; }
.search-input { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 4px; }
.picker-table-wrap { border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; overflow: auto; max-height: 16rem; }
.picker-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.picker-table th { background: #f1f5f9; text-align: left; padding: 0.5rem 0.7rem; font-size: 0.74rem; }
.picker-table td { padding: 0.5rem 0.7rem; border-bottom: 1px solid #f1f5f9; }
.cand-name { font-weight: 600; }
.cand-sub { font-size: 0.78rem; color: #64748b; }
.btn-add { padding: 0.32rem 0.7rem; border-radius: 4px; border: none; background: #0d7a3a; color: #fff; font-weight: 600; cursor: pointer; }
.msg-warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 0.55rem 0.75rem; border-radius: 4px; font-size: 0.86rem; }
.msg-link { margin-left: 0.4rem; font-weight: 600; color: #92400e; }
.muted { color: #64748b; font-size: 0.85rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.filter-toggle { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; }
</style>
