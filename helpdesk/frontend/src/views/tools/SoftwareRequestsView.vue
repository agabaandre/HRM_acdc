<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess, notifyWarning } from '../../lib/notify'
import { htmlContainsDataUriImages, isHtmlContent } from '../../lib/richText'
import { useAuthStore } from '../../stores/auth'

const CbpRichTextEditor = defineAsyncComponent(
  () => import('../../components/common/CbpRichTextEditor.vue'),
)

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
  division_id?: number | null
  directorate_id?: number | null
  division_name?: string | null
  directorate_name?: string | null
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
  hod_staff_id?: number | null
  hod_name?: string | null
  team_members?: TeamMember[]
  approvals?: Approval[]
}

type TabId = 'new' | 'requests'

const auth = useAuthStore()
const route = useRoute()
const activeTab = ref<TabId>('requests')
const rows = ref<SoftwareRequest[]>([])
const loading = ref(true)
const selected = ref<SoftwareRequest | null>(null)
const busy = ref(false)
const inlineImageBusy = ref(false)
const problemEditorRef = ref<InstanceType<typeof CbpRichTextEditor> | null>(null)
const solutionEditorRef = ref<InstanceType<typeof CbpRichTextEditor> | null>(null)
const justificationEditorRef = ref<InstanceType<typeof CbpRichTextEditor> | null>(null)

const filterQ = ref('')
const filterStatus = ref('')
const filterPriority = ref('')

const orgPreview = reactive({
  division_name: '',
  directorate_name: '',
  division_id: null as number | null,
  directorate_id: null as number | null,
})

const canSubmit = computed(
  () =>
    !!auth.me?.profile?.is_helpdesk_admin ||
    auth.me?.profile?.can_submit_software_requests !== false,
)

const canManage = computed(
  () =>
    !!auth.me?.profile?.is_helpdesk_admin ||
    !!auth.me?.profile?.can_manage_software_requests ||
    !!auth.me?.profile?.can_approve_software_requests,
)

const isHodForSelected = computed(() => {
  const staffId = auth.me?.profile?.staff_id
  if (!selected.value || !staffId) return false
  return selected.value.status === 'pending_hod' && Number(selected.value.hod_staff_id) === Number(staffId)
})

const canProcessSelected = computed(() => {
  if (!selected.value || !canManage.value) return false
  return ['hod_approved', 'approved', 'deferred', 'team_formed', 'submitted'].includes(selected.value.status)
})

const hodNotes = ref('')

const form = reactive({
  requester_name: '',
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
  approval_role: 'review_board',
  decision: 'approved',
  notes: '',
  assigned_ba_name: '',
  project_id: '',
})

const teamDraft = ref<TeamMember[]>([{ member_name: '', member_email: '', role: 'member' }])

const statusItems = [
  { label: 'All statuses', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Pending HoD', value: 'pending_hod' },
  { label: 'HoD approved', value: 'hod_approved' },
  { label: 'HoD rejected', value: 'hod_rejected' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Approved', value: 'approved' },
  { label: 'Deferred', value: 'deferred' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Team formed', value: 'team_formed' },
]

const priorityItems = [
  { label: 'All priorities', value: '' },
  ...['critical', 'high', 'medium', 'low'].map((v) => ({ label: v, value: v })),
]

const formPriorityItems = ['critical', 'high', 'medium', 'low'].map((v) => ({ label: v, value: v }))

function resetForm() {
  const me = auth.me
  Object.assign(form, {
    requester_name: me?.name ?? '',
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

async function loadOrgPreview() {
  const profile = auth.me?.profile
  const divisionId = profile?.division_id ?? null
  const directorateId = profile?.directorate_id ?? null
  orgPreview.division_id = divisionId
  orgPreview.directorate_id = directorateId
  orgPreview.division_name = ''
  orgPreview.directorate_name = ''

  if (!divisionId && !directorateId) return

  try {
    const { data } = await api.get<{
      data: {
        divisions?: { id: number; name: string; directorate_id?: number }[]
        directorates?: { id: number; name: string }[]
      }
    }>('/api/v1/reference-data')
    const divisions = data.data?.divisions ?? []
    const directorates = data.data?.directorates ?? []
    if (divisionId) {
      const div = divisions.find((d) => d.id === divisionId)
      orgPreview.division_name = div?.name ?? `Division #${divisionId}`
      if (!directorateId && div?.directorate_id) {
        orgPreview.directorate_id = div.directorate_id
      }
    }
    const dirId = orgPreview.directorate_id
    if (dirId) {
      const dir = directorates.find((d) => d.id === dirId)
      orgPreview.directorate_name = dir?.name ?? `Directorate #${dirId}`
    }
  } catch {
    if (divisionId) orgPreview.division_name = `Division #${divisionId}`
    if (directorateId) orgPreview.directorate_name = `Directorate #${directorateId}`
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: SoftwareRequest[] }>('/api/v1/tools/software-requests', {
      params: {
        q: filterQ.value.trim() || undefined,
        status: filterStatus.value || undefined,
        priority: filterPriority.value || undefined,
        per_page: 50,
      },
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
    teamDraft.value = data.data.team_members?.length
      ? data.data.team_members.map((m) => ({ ...m }))
      : [{ member_name: '', member_email: '', role: 'member' }]
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load request.'))
  }
}

async function saveDraft(submit: boolean) {
  if (inlineImageBusy.value) {
    notifyWarning('An image is still uploading. Wait a moment and try again.')
    return
  }
  await Promise.all([
    problemEditorRef.value?.ensureImagesUploaded(),
    solutionEditorRef.value?.ensureImagesUploaded(),
    justificationEditorRef.value?.ensureImagesUploaded(),
  ])
  if (
    htmlContainsDataUriImages(form.problem_statement)
    || htmlContainsDataUriImages(form.proposed_solution)
    || htmlContainsDataUriImages(form.business_justification)
  ) {
    notifyWarning('An image is still uploading. Wait a moment and try again.')
    return
  }
  busy.value = true
  try {
    await api.post('/api/v1/tools/software-requests', { ...form, submit })
    notifySuccess(submit ? 'Request submitted.' : 'Draft saved.')
    resetForm()
    activeTab.value = 'requests'
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

function richFieldHtml(value: string | null | undefined): string | null {
  if (!value?.trim()) return null
  return isHtmlContent(value) ? value : null
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
    notifyError(apiErrorMessage(e, 'Approval failed. HoD approval may still be required.'))
  } finally {
    busy.value = false
  }
}

async function submitHod(approve: boolean) {
  if (!selected.value) return
  busy.value = true
  try {
    const path = approve ? 'hod-approve' : 'hod-reject'
    const { data } = await api.post<{ data: SoftwareRequest }>(
      `/api/v1/tools/software-requests/${selected.value.id}/${path}`,
      { notes: hodNotes.value || null },
    )
    selected.value = data.data
    notifySuccess(approve ? 'HoD approved.' : 'HoD rejected.')
    hodNotes.value = ''
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'HoD action failed.'))
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

function divisionLabel(row: SoftwareRequest) {
  return row.division_name || row.department || '—'
}

watch(activeTab, (tab) => {
  if (tab === 'new') {
    resetForm()
    void loadOrgPreview()
  } else {
    void load()
  }
})

onMounted(() => {
  resetForm()
  void loadOrgPreview()
  if (route.query.tab === 'new' && canSubmit.value) {
    activeTab.value = 'new'
  } else if (canSubmit.value && !canManage.value) {
    activeTab.value = 'new'
  }
  void load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Software requests" back-to="/" back-label="← Overview">
      <template #lede>
        Submit software requirements, track your requests, and (for reviewers) approve and form project teams.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist" aria-label="Software requests sections">
        <button
          v-if="canSubmit"
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'new' }"
          :aria-selected="activeTab === 'new'"
          @click="activeTab = 'new'"
        >
          New request
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'requests' }"
          :aria-selected="activeTab === 'requests'"
          @click="activeTab = 'requests'"
        >
          {{ canManage ? 'Manage requests' : 'My requests' }}
        </button>
      </div>

      <!-- Form tab -->
      <div v-show="activeTab === 'new' && canSubmit" class="tab-panel">
        <UCard class="form-panel">
          <template #header><h3>Submit a software request</h3></template>
          <p class="form-intro">
            Your division and directorate are filled from the Staff directory / SSO profile and cannot be edited here.
          </p>
          <div class="hd-form hd-form--grid">
            <UFormField label="Requester name" required><UInput v-model="form.requester_name" class="w-full" /></UFormField>
            <UFormField label="Division">
              <UInput :model-value="orgPreview.division_name || '—'" readonly class="w-full" />
            </UFormField>
            <UFormField label="Directorate">
              <UInput :model-value="orgPreview.directorate_name || '—'" readonly class="w-full" />
            </UFormField>
            <UFormField label="Email"><UInput v-model="form.email" type="email" class="w-full" /></UFormField>
            <UFormField label="Phone"><UInput v-model="form.phone" class="w-full" /></UFormField>
            <UFormField label="Request title" required class="span-3"><UInput v-model="form.request_title" class="w-full" /></UFormField>
            <UFormField label="Problem statement" class="span-3 hd-rich-field">
              <CbpRichTextEditor
                ref="problemEditorRef"
                v-model="form.problem_statement"
                :disabled="busy"
                :enable-images="false"
                :min-rows="4"
                placeholder="Describe the problem or need…"
                @uploading="inlineImageBusy = $event"
              />
            </UFormField>
            <UFormField label="Proposed solution" class="span-3 hd-rich-field">
              <CbpRichTextEditor
                ref="solutionEditorRef"
                v-model="form.proposed_solution"
                :disabled="busy"
                :enable-images="false"
                :min-rows="4"
                placeholder="What solution or approach are you proposing?"
                @uploading="inlineImageBusy = $event"
              />
            </UFormField>
            <UFormField label="Business justification" class="span-3 hd-rich-field">
              <CbpRichTextEditor
                ref="justificationEditorRef"
                v-model="form.business_justification"
                :disabled="busy"
                :enable-images="false"
                :min-rows="4"
                placeholder="Why is this needed? What outcome or mandate does it support?"
                @uploading="inlineImageBusy = $event"
              />
            </UFormField>
            <UFormField label="Affected stakeholders" class="span-3"><UTextarea v-model="form.affected_stakeholders" :rows="2" class="w-full" /></UFormField>
            <UFormField label="Mandate alignment"><UInput v-model="form.mandate_alignment" class="w-full" /></UFormField>
            <UFormField label="Priority">
              <USelect v-model="form.priority" :items="formPriorityItems" class="w-full" />
            </UFormField>
            <UFormField label="Desired timeline"><UInput v-model="form.desired_timeline" class="w-full" /></UFormField>
            <UFormField label="Budget estimate"><UInput v-model.number="form.budget_estimate" type="number" class="w-full" /></UFormField>
            <UFormField label="Existing alternatives" class="span-3"><UTextarea v-model="form.existing_alternatives" :rows="2" class="w-full" /></UFormField>
            <UFormField label="Additional comments" class="span-3"><UTextarea v-model="form.additional_comments" :rows="2" class="w-full" /></UFormField>
          </div>
          <div class="form-actions">
            <UButton color="neutral" variant="outline" :loading="busy" :disabled="inlineImageBusy" @click="saveDraft(false)">Save draft</UButton>
            <UButton color="primary" :loading="busy" :disabled="inlineImageBusy" @click="saveDraft(true)">Submit request</UButton>
          </div>
        </UCard>
      </div>

      <!-- Requests tab -->
      <div v-show="activeTab === 'requests'" class="tab-panel">
        <div class="filters">
          <UFormField label="Search" class="filter-grow">
            <UInput
              v-model="filterQ"
              type="search"
              icon="i-lucide-search"
              placeholder="Search ID, title, requester, division…"
              class="w-full"
              @keyup.enter="load()"
            />
          </UFormField>
          <UFormField label="Status" class="filter-sm">
            <USelect v-model="filterStatus" :items="statusItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Priority" class="filter-sm">
            <USelect v-model="filterPriority" :items="priorityItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UButton color="primary" variant="soft" class="filter-btn" @click="load()">Apply</UButton>
        </div>

        <div class="layout-split" :class="{ 'has-detail': !!selected }">
          <div class="table-wrap cbp-card">
            <p v-if="loading" class="muted">Loading…</p>
            <table v-else class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Requester</th>
                  <th>Division</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Received</th>
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
                  <td>{{ divisionLabel(row) }}</td>
                  <td>{{ row.priority }}</td>
                  <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
                  <td>{{ row.received_at?.slice(0, 10) ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-if="!loading && !rows.length" class="muted empty">No requests match your filters.</p>
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
              <dt>Requester</dt><dd>{{ selected.requester_name }}</dd>
              <dt>Division</dt><dd>{{ selected.division_name || selected.department || '—' }}</dd>
              <dt>Directorate</dt><dd>{{ selected.directorate_name || '—' }}</dd>
              <dt>HoD</dt><dd>{{ selected.hod_name || '—' }}</dd>
              <dt>Contact</dt><dd>{{ selected.email || '—' }} · {{ selected.phone || '—' }}</dd>
              <dt>Problem</dt>
              <dd>
                <div
                  v-if="richFieldHtml(selected.problem_statement)"
                  class="rich-text-content"
                  v-html="richFieldHtml(selected.problem_statement)"
                />
                <span v-else class="pre">{{ selected.problem_statement || '—' }}</span>
              </dd>
              <dt>Proposed solution</dt>
              <dd>
                <div
                  v-if="richFieldHtml(selected.proposed_solution)"
                  class="rich-text-content"
                  v-html="richFieldHtml(selected.proposed_solution)"
                />
                <span v-else class="pre">{{ selected.proposed_solution || '—' }}</span>
              </dd>
              <dt>Business justification</dt>
              <dd>
                <div
                  v-if="richFieldHtml(selected.business_justification)"
                  class="rich-text-content"
                  v-html="richFieldHtml(selected.business_justification)"
                />
                <span v-else class="pre">{{ selected.business_justification || '—' }}</span>
              </dd>
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

            <section v-if="isHodForSelected" class="sub-section">
              <h4>Head of Division approval</h4>
              <UFormField label="Notes">
                <UTextarea v-model="hodNotes" :rows="2" class="w-full" />
              </UFormField>
              <div class="team-actions">
                <UButton color="primary" size="sm" :loading="busy" @click="submitHod(true)">Approve</UButton>
                <UButton color="error" variant="outline" size="sm" :loading="busy" @click="submitHod(false)">Reject</UButton>
              </div>
            </section>

            <p v-if="canManage && selected.status === 'pending_hod'" class="hint">
              Waiting for Head of Division approval before review-board processing.
            </p>

            <section v-if="canProcessSelected" class="sub-section">
              <h4>Review board — decision</h4>
              <div class="hd-form hd-form--grid">
                <UFormField label="Approval role">
                  <USelect
                    v-model="approveForm.approval_role"
                    :items="[
                      { label: 'Review board', value: 'review_board' },
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
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.tabs { display: flex; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0; }
.tab {
  border: 0; background: transparent; padding: 0.55rem 0.9rem; cursor: pointer;
  font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab.active { color: #0d7a3a; border-bottom-color: #0d7a3a; }
.tab-panel { display: flex; flex-direction: column; gap: 1rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; }
.filter-grow { flex: 1; min-width: 14rem; }
.filter-sm { min-width: 10rem; }
.filter-btn { margin-bottom: 0.15rem; }
.form-panel { border-style: solid; }
.form-intro { margin: 0 0 0.75rem; color: #64748b; font-size: 0.88rem; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; flex-wrap: wrap; }
.layout-split { display: grid; grid-template-columns: 1fr; gap: 1rem; width: 100%; }
@media (min-width: 960px) {
  .layout-split.has-detail { grid-template-columns: minmax(0, 1.15fr) minmax(18rem, 0.85fr); align-items: start; }
}
.table-wrap { overflow-x: auto; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f8fafc; }
.clickable.selected { background: #ecfdf5; }
.wrap { max-width: 220px; }
.badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: #f1f5f9; margin-left: 0.35rem; }
.badge-approved, .badge-team_formed { background: #dcfce7; color: #166534; }
.badge-submitted { background: #dbeafe; color: #1d4ed8; }
.badge-rejected { background: #fef2f2; color: #b91c1c; }
.badge-draft { background: #f1f5f9; color: #475569; }
.badge-deferred { background: #fef3c7; color: #92400e; }
.detail-panel { position: sticky; top: 1rem; }
.detail-dl { display: grid; grid-template-columns: 7.5rem 1fr; gap: 0.35rem 0.75rem; font-size: 0.85rem; margin: 0; }
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
