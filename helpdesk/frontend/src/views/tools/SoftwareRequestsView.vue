<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'
import { useAuthStore } from '../../stores/auth'

interface TeamMember {
  id?: number
  member_name: string
  member_email?: string
  staff_id?: number
  role: string
}

interface Approval {
  id: number
  approval_role: string
  decision?: string
  approver_name?: string
  notes?: string
  decided_at?: string
}

interface SoftwareRequest {
  id: number
  request_number: string
  requester_name: string
  department?: string
  email?: string
  phone?: string
  request_title: string
  problem_statement?: string
  proposed_solution?: string
  business_justification?: string
  affected_stakeholders?: string
  mandate_alignment?: string
  priority: string
  desired_timeline?: string
  budget_estimate?: string | number
  existing_alternatives?: string
  additional_comments?: string
  status: string
  decision?: string
  received_at?: string
  assigned_ba_name?: string
  project_id?: string
  project_team_formed_at?: string
  team_members?: TeamMember[]
  approvals?: Approval[]
}

const auth = useAuthStore()
const rows = ref<SoftwareRequest[]>([])
const loading = ref(true)
const showForm = ref(false)
const selected = ref<SoftwareRequest | null>(null)
const filterStatus = ref('')
const busy = ref(false)

const canManage = computed(
  () =>
    !!auth.me?.profile?.is_helpdesk_admin ||
    !!auth.me?.profile?.can_manage_software_requests ||
    !!auth.me?.profile?.can_approve_software_requests,
)

const form = reactive({
  requester_name: '',
  department: '',
  email: '',
  phone: '',
  request_title: '',
  problem_statement: '',
  proposed_solution: '',
  business_justification: '',
  affected_stakeholders: '',
  mandate_alignment: '',
  priority: 'medium',
  desired_timeline: '',
  budget_estimate: 0,
  existing_alternatives: '',
  additional_comments: '',
})

const approveForm = reactive({
  approval_role: 'team_lead',
  decision: 'approved',
  notes: '',
  assigned_ba_name: '',
  project_id: '',
})

const teamDraft = ref<TeamMember[]>([{ member_name: '', member_email: '', role: 'member' }])

const statusItems = [
  { label: 'All statuses', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Approved', value: 'approved' },
  { label: 'Deferred', value: 'deferred' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Team formed', value: 'team_formed' },
]

const priorityItems = ['critical', 'high', 'medium', 'low'].map((v) => ({ label: v, value: v }))

function resetForm() {
  const me = auth.me
  Object.assign(form, {
    requester_name: me?.name ?? '',
    department: '',
    email: me?.email ?? '',
    phone: '',
    request_title: '',
    problem_statement: '',
    proposed_solution: '',
    business_justification: '',
    affected_stakeholders: '',
    mandate_alignment: '',
    priority: 'medium',
    desired_timeline: '',
    budget_estimate: 0,
    existing_alternatives: '',
    additional_comments: '',
  })
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: SoftwareRequest[] }>('/api/v1/tools/software-requests', {
      params: { status: filterStatus.value || undefined, per_page: 50 },
    })
    const paginated = data as { data?: SoftwareRequest[] }
    rows.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load software requests.'))
  } finally {
    loading.value = false
  }
}

async function openDetail(row: SoftwareRequest) {
  try {
    const { data } = await api.get<{ data: SoftwareRequest }>(`/api/v1/tools/software-requests/${row.id}`)
    selected.value = data.data
    teamDraft.value =
      data.data.team_members?.length
        ? data.data.team_members.map((m) => ({ ...m }))
        : [{ member_name: '', member_email: '', role: 'member' }]
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load request.'))
  }
}

function openCreate() {
  resetForm()
  showForm.value = true
}

async function saveDraft(submit: boolean) {
  busy.value = true
  try {
    await api.post('/api/v1/tools/software-requests', { ...form, submit })
    notifySuccess(submit ? 'Request submitted.' : 'Draft saved.')
    showForm.value = false
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function submitApproval() {
  if (!selected.value) return
  busy.value = true
  try {
    const { data } = await api.post<{ data: SoftwareRequest }>(
      `/api/v1/tools/software-requests/${selected.value.id}/approve`,
      {
        approval_role: approveForm.approval_role,
        decision: approveForm.decision,
        notes: approveForm.notes || null,
        assigned_ba_name: approveForm.assigned_ba_name || null,
        project_id: approveForm.project_id || null,
      },
    )
    selected.value = data.data
    notifySuccess('Approval recorded.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Approval failed.'))
  } finally {
    busy.value = false
  }
}

async function saveTeam() {
  if (!selected.value) return
  const members = teamDraft.value.filter((m) => m.member_name.trim())
  if (!members.length) {
    notifyError('Add at least one team member.')
    return
  }
  busy.value = true
  try {
    const { data } = await api.post<{ data: SoftwareRequest }>(
      `/api/v1/tools/software-requests/${selected.value.id}/team`,
      { members },
    )
    selected.value = data.data
    notifySuccess('Project team saved.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to save team.'))
  } finally {
    busy.value = false
  }
}

function addTeamRow() {
  teamDraft.value.push({ member_name: '', member_email: '', role: 'member' })
}

function statusBadge(status: string) {
  return `badge badge-${status}`
}

onMounted(() => {
  resetForm()
  void load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Software requests" back-to="/" back-label="← Overview">
      <template #lede>
        Submit new software requirements using the Africa CDC request form. Approvers review requests, assign a BA, and form the project team.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="toolbar">
        <UFormField v-if="canManage" label="Status" class="toolbar-field">
          <USelect v-model="filterStatus" :items="statusItems" class="w-full" @update:model-value="load" />
        </UFormField>
        <div class="toolbar-spacer" />
        <UButton color="primary" @click="openCreate">+ New request</UButton>
      </div>

      <UCard v-if="showForm" class="form-panel">
        <template #header><h3>New software request</h3></template>
        <p class="form-intro">Fields mirror the official Software Request Form (requester, business case, timeline, and budget).</p>
        <div class="hd-form hd-form--grid">
          <UFormField label="Requester name" required><UInput v-model="form.requester_name" class="w-full" /></UFormField>
          <UFormField label="Department"><UInput v-model="form.department" class="w-full" /></UFormField>
          <UFormField label="Email"><UInput v-model="form.email" type="email" class="w-full" /></UFormField>
          <UFormField label="Phone"><UInput v-model="form.phone" class="w-full" /></UFormField>
          <UFormField label="Request title" required class="span-3"><UInput v-model="form.request_title" class="w-full" /></UFormField>
          <UFormField label="Problem statement" class="span-3"><UTextarea v-model="form.problem_statement" :rows="3" class="w-full" /></UFormField>
          <UFormField label="Proposed solution" class="span-3"><UTextarea v-model="form.proposed_solution" :rows="3" class="w-full" /></UFormField>
          <UFormField label="Business justification" class="span-3"><UTextarea v-model="form.business_justification" :rows="3" class="w-full" /></UFormField>
          <UFormField label="Affected stakeholders" class="span-3"><UTextarea v-model="form.affected_stakeholders" :rows="2" class="w-full" /></UFormField>
          <UFormField label="Mandate alignment"><UInput v-model="form.mandate_alignment" class="w-full" /></UFormField>
          <UFormField label="Priority">
            <USelect v-model="form.priority" :items="priorityItems" class="w-full" />
          </UFormField>
          <UFormField label="Desired timeline"><UInput v-model="form.desired_timeline" class="w-full" /></UFormField>
          <UFormField label="Budget estimate"><UInput v-model.number="form.budget_estimate" type="number" class="w-full" /></UFormField>
          <UFormField label="Existing alternatives" class="span-3"><UTextarea v-model="form.existing_alternatives" :rows="2" class="w-full" /></UFormField>
          <UFormField label="Additional comments" class="span-3"><UTextarea v-model="form.additional_comments" :rows="2" class="w-full" /></UFormField>
        </div>
        <div class="form-actions">
          <UButton color="neutral" variant="outline" :disabled="busy" @click="showForm = false">Cancel</UButton>
          <UButton color="neutral" variant="outline" :loading="busy" @click="saveDraft(false)">Save draft</UButton>
          <UButton color="primary" :loading="busy" @click="saveDraft(true)">Submit request</UButton>
        </div>
      </UCard>

      <div class="layout-split">
        <div class="table-wrap cbp-card">
          <p v-if="loading" class="muted">Loading…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>ID</th><th>Title</th><th>Requester</th><th>Priority</th><th>Status</th><th>Received</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="clickable"
                :class="{ selected: selected?.id === row.id }"
                @click="openDetail(row)"
              >
                <td><strong>{{ row.request_number }}</strong></td>
                <td class="wrap">{{ row.request_title }}</td>
                <td>{{ row.requester_name }}</td>
                <td>{{ row.priority }}</td>
                <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
                <td>{{ row.received_at?.slice(0, 10) ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !rows.length" class="muted empty">No requests yet.</p>
        </div>

        <UCard v-if="selected" class="detail-panel">
          <template #header>
            <div>
              <strong>{{ selected.request_number }}</strong>
              <span :class="statusBadge(selected.status)">{{ selected.status }}</span>
            </div>
          </template>

          <dl class="detail-dl">
            <dt>Title</dt><dd>{{ selected.request_title }}</dd>
            <dt>Requester</dt><dd>{{ selected.requester_name }} · {{ selected.department || '—' }}</dd>
            <dt>Contact</dt><dd>{{ selected.email || '—' }} · {{ selected.phone || '—' }}</dd>
            <dt>Problem</dt><dd class="pre">{{ selected.problem_statement || '—' }}</dd>
            <dt>Proposed solution</dt><dd class="pre">{{ selected.proposed_solution || '—' }}</dd>
            <dt>Business justification</dt><dd class="pre">{{ selected.business_justification || '—' }}</dd>
            <dt>Stakeholders</dt><dd class="pre">{{ selected.affected_stakeholders || '—' }}</dd>
            <dt>Timeline / budget</dt>
            <dd>{{ selected.desired_timeline || '—' }} · {{ selected.budget_estimate ? `$${selected.budget_estimate}` : '—' }}</dd>
          </dl>

          <section v-if="selected.approvals?.length" class="sub-section">
            <h4>Approvals</h4>
            <ul>
              <li v-for="a in selected.approvals" :key="a.id">
                {{ a.approval_role }}: {{ a.decision ?? 'pending' }}
                <span v-if="a.approver_name"> by {{ a.approver_name }}</span>
              </li>
            </ul>
          </section>

          <section v-if="canManage" class="sub-section">
            <h4>Official use — review &amp; decision</h4>
            <div class="hd-form hd-form--grid">
              <UFormField label="Approval role">
                <USelect
                  v-model="approveForm.approval_role"
                  :items="[
                    { label: 'Team lead', value: 'team_lead' },
                    { label: 'Business analyst', value: 'business_analyst' },
                    { label: 'Project lead', value: 'project_lead' },
                  ]"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Decision">
                <USelect
                  v-model="approveForm.decision"
                  :items="[
                    { label: 'Approved', value: 'approved' },
                    { label: 'Deferred', value: 'deferred' },
                    { label: 'Rejected', value: 'rejected' },
                  ]"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Assigned BA" class="span-3"><UInput v-model="approveForm.assigned_ba_name" class="w-full" /></UFormField>
              <UFormField label="Project ID" class="span-3"><UInput v-model="approveForm.project_id" class="w-full" /></UFormField>
              <UFormField label="Notes" class="span-3"><UTextarea v-model="approveForm.notes" :rows="2" class="w-full" /></UFormField>
            </div>
            <UButton color="primary" size="sm" :loading="busy" @click="submitApproval">Record decision</UButton>
          </section>

          <section v-if="canManage && ['approved', 'team_formed'].includes(selected.status)" class="sub-section">
            <h4>Project team</h4>
            <div v-for="(m, i) in teamDraft" :key="i" class="team-row">
              <UInput v-model="m.member_name" placeholder="Name" class="w-full" />
              <UInput v-model="m.member_email" placeholder="Email" class="w-full" />
              <UInput v-model="m.role" placeholder="Role" class="w-full" />
            </div>
            <div class="team-actions">
              <UButton size="xs" variant="outline" @click="addTeamRow">+ Member</UButton>
              <UButton color="primary" size="sm" :loading="busy" @click="saveTeam">Save team</UButton>
            </div>
            <p v-if="selected.project_team_formed_at" class="hint">Team formed {{ selected.project_team_formed_at.slice(0, 10) }}</p>
          </section>
        </UCard>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
.toolbar-field { min-width: 160px; }
.toolbar-spacer { flex: 1; }
.form-panel { border-style: dashed; }
.form-intro { margin: 0 0 0.75rem; color: #64748b; font-size: 0.88rem; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; flex-wrap: wrap; }
.layout-split { display: grid; grid-template-columns: 1fr; gap: 1rem; }
@media (min-width: 960px) {
  .layout-split { grid-template-columns: 1.1fr 0.9fr; align-items: start; }
}
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f8fafc; }
.clickable.selected { background: #ecfdf5; }
.wrap { max-width: 200px; }
.badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: #f1f5f9; margin-left: 0.35rem; }
.badge-approved, .badge-team_formed { background: #dcfce7; color: #166534; }
.badge-submitted { background: #dbeafe; color: #1d4ed8; }
.badge-rejected { background: #fef2f2; color: #b91c1c; }
.badge-draft { background: #f1f5f9; color: #475569; }
.detail-panel { position: sticky; top: 1rem; }
.detail-dl { display: grid; grid-template-columns: 7rem 1fr; gap: 0.35rem 0.75rem; font-size: 0.85rem; margin: 0; }
.detail-dl dt { color: #64748b; font-weight: 600; }
.detail-dl dd { margin: 0; }
.pre { white-space: pre-wrap; }
.sub-section { margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0; }
.sub-section h4 { margin: 0 0 0.5rem; font-size: 0.85rem; text-transform: uppercase; color: #64748b; }
.team-row { display: grid; grid-template-columns: 1fr 1fr 0.7fr; gap: 0.35rem; margin-bottom: 0.35rem; }
.team-actions { display: flex; gap: 0.5rem; margin-top: 0.35rem; }
.hint { font-size: 0.78rem; color: #64748b; margin: 0.35rem 0 0; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; }
</style>
