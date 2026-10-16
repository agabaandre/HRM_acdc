<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'
import RiskApprovalTrail, { type RiskApprovalStep } from '@/components/risks/RiskApprovalTrail.vue'

type ApprovalRow = {
  id: number
  risk_id: number
  risk_name: string
  division_id: number | null
  division_name: string | null
  role: string
  step_order: number
  status: string
  workflow_state?: string | null
  inherent_score?: number | null
  inherent_rating?: string | null
  residual_score?: number | null
  residual_rating?: string | null
  owners?: string[]
  submitted_by?: string | null
  date_received?: string | null
  days_waiting?: number
  is_stale?: boolean
}

type ApprovalsMeta = {
  total_pending: number
  stale_count: number
  stale_days: number
  by_role: Record<string, number>
  divisions: Array<{ division_id: number; division_name: string | null }>
}

type FeedbackRecipient = {
  staff_id: number
  name?: string
  role: string
  level: number
}

const router = useRouter()
const locale = useLocaleStore()

const rows = ref<ApprovalRow[]>([])
const meta = ref<ApprovalsMeta>({
  total_pending: 0,
  stale_count: 0,
  stale_days: 7,
  by_role: {},
  divisions: [],
})
const loading = ref(true)
const error = ref<string | null>(null)
const actionError = ref<string | null>(null)
const approvingId = ref<number | null>(null)
const divisionFilter = ref<number | null>(null)
const roleFilter = ref<string | null>(null)
const expandedRiskId = ref<number | null>(null)
const trailByRisk = ref<Record<number, RiskApprovalStep[]>>({})
const trailLoadingId = ref<number | null>(null)

const feedbackOpen = ref(false)
const feedbackFor = ref<number | null>(null)
const feedbackRiskName = ref('')
const feedbackMsg = ref('')
const recipients = ref<FeedbackRecipient[]>([])
const selectedRecipients = ref<number[]>([])
const feedbackSaving = ref(false)
const feedbackError = ref<string | null>(null)

const roleLabel = (role: string) => {
  const map: Record<string, string> = {
    risk_focal: locale.t('rr.role_risk_focal', 'Risk focal'),
    hod: locale.t('rr.role_hod', 'Head of Division'),
    director: locale.t('rr.role_director', 'Director'),
    sm_focal: locale.t('rr.role_sm_focal', 'SM focal'),
    extra: locale.t('rr.role_extra', 'Additional reviewer'),
    oio: locale.t('rr.role_oio', 'OIO review'),
  }
  return map[role] || role.replace(/_/g, ' ')
}

const divisionOptions = computed(() => [
  { label: locale.t('rr.all_divisions', 'All divisions'), value: null as number | null },
  ...meta.value.divisions.map((d) => ({
    label: d.division_name || locale.t('rr.division_number', 'Division {id}', { id: d.division_id }),
    value: d.division_id,
  })),
])

const roleOptions = computed(() => {
  const roles = Object.keys(meta.value.by_role || {})
  return [
    { label: locale.t('rr.all_roles', 'All roles'), value: null as string | null },
    ...roles.map((role) => ({
      label: `${roleLabel(role)} (${meta.value.by_role[role]})`,
      value: role,
    })),
  ]
})

const filteredRows = computed(() =>
  rows.value.filter((row) => {
    if (divisionFilter.value != null && row.division_id !== divisionFilter.value) return false
    if (roleFilter.value != null && row.role !== roleFilter.value) return false
    return true
  }),
)

const staleRows = computed(() => rows.value.filter((r) => r.is_stale))

const kpiCards = computed(() => [
  {
    key: 'total',
    label: locale.t('rr.approvals_kpi_total', 'Total pending'),
    value: meta.value.total_pending,
    tone: undefined as string | undefined,
  },
  {
    key: 'stale',
    label: locale.t('rr.approvals_kpi_stale', 'Overdue'),
    value: meta.value.stale_count,
    tone: meta.value.stale_count > 0 ? 'critical' : undefined,
  },
  {
    key: 'hod',
    label: locale.t('rr.role_hod', 'Head of Division'),
    value: meta.value.by_role.hod || 0,
    tone: undefined,
  },
  {
    key: 'director',
    label: locale.t('rr.role_director', 'Director'),
    value: meta.value.by_role.director || 0,
    tone: undefined,
  },
])

function formatReceived(iso?: string | null): string {
  if (!iso) return locale.t('rr.none_dash', '—')
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

function scoreLabel(rating?: string | null, score?: number | null): string {
  const dash = locale.t('rr.none_dash', '—')
  if (score == null && !rating) return dash
  if (rating && score != null) return `${rating}(${score})`
  if (score != null) return String(score)
  return rating || dash
}

async function load() {
  loading.value = true
  error.value = null
  actionError.value = null
  try {
    const { data } = await api.get<{ data: ApprovalRow[]; meta?: ApprovalsMeta }>('/api/v1/my-approvals')
    rows.value = data.data || []
    meta.value = {
      total_pending: data.meta?.total_pending ?? rows.value.length,
      stale_count: data.meta?.stale_count ?? rows.value.filter((r) => r.is_stale).length,
      stale_days: data.meta?.stale_days ?? 7,
      by_role: data.meta?.by_role ?? {},
      divisions: data.meta?.divisions ?? [],
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_approvals_error', 'Could not load approvals'))
  } finally {
    loading.value = false
  }
}

async function approve(id: number) {
  approvingId.value = id
  actionError.value = null
  try {
    await api.post(`/api/v1/approvals/${id}/approve`)
    if (expandedRiskId.value) {
      delete trailByRisk.value[expandedRiskId.value]
    }
    await load()
  } catch (e) {
    actionError.value = apiErrorMessage(e, locale.t('rr.approve_failed', 'Approve failed'))
  } finally {
    approvingId.value = null
  }
}

async function openFeedback(row: ApprovalRow) {
  feedbackFor.value = row.id
  feedbackRiskName.value = row.risk_name
  feedbackMsg.value = ''
  selectedRecipients.value = []
  feedbackError.value = null
  feedbackOpen.value = true
  try {
    const { data } = await api.get<{ data: FeedbackRecipient[] }>(
      `/api/v1/approvals/${row.id}/eligible-recipients`,
    )
    recipients.value = data.data || []
  } catch (e) {
    feedbackError.value = apiErrorMessage(
      e,
      locale.t('rr.load_recipients_error', 'Could not load feedback recipients'),
    )
    recipients.value = []
  }
}

function closeFeedback() {
  feedbackOpen.value = false
  feedbackFor.value = null
  feedbackError.value = null
  recipients.value = []
  selectedRecipients.value = []
}

async function sendFeedback() {
  if (feedbackFor.value == null) return
  if (selectedRecipients.value.length < 1 || !feedbackMsg.value.trim()) {
    feedbackError.value = locale.t(
      'rr.feedback_required',
      'Select at least one recipient and enter a message.',
    )
    return
  }
  feedbackSaving.value = true
  feedbackError.value = null
  try {
    await api.post(`/api/v1/approvals/${feedbackFor.value}/request-feedback`, {
      recipient_staff_ids: selectedRecipients.value,
      message: feedbackMsg.value.trim(),
    })
    closeFeedback()
    await load()
  } catch (e) {
    feedbackError.value = apiErrorMessage(e, locale.t('rr.feedback_failed', 'Could not send feedback request'))
  } finally {
    feedbackSaving.value = false
  }
}

function openRisk(riskId: number) {
  void router.push({ name: 'risk-show', params: { id: riskId } })
}

async function toggleTrail(riskId: number) {
  if (expandedRiskId.value === riskId) {
    expandedRiskId.value = null
    return
  }
  expandedRiskId.value = riskId
  if (trailByRisk.value[riskId]) return
  trailLoadingId.value = riskId
  try {
    const { data } = await api.get<{ data: RiskApprovalStep[] }>(`/api/v1/risks/${riskId}/approvals`)
    trailByRisk.value = { ...trailByRisk.value, [riskId]: data.data || [] }
  } catch (e) {
    actionError.value = apiErrorMessage(e, locale.t('rr.load_trail_error', 'Could not load approval trail'))
    trailByRisk.value = { ...trailByRisk.value, [riskId]: [] }
  } finally {
    trailLoadingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page rr-approvals">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.approvals_title', 'My approvals') }}</h1>
        <p class="rr-page__sub">
          {{
            locale.t(
              'rr.approvals_sub',
              'Items waiting at your workflow step — open a risk to review, then approve or request feedback.',
            )
          }}
        </p>
      </div>
      <div class="rr-page__actions">
        <button type="button" class="rr-btn" :disabled="loading" @click="load">
          {{ locale.t('rr.refresh', 'Refresh') }}
        </button>
      </div>
    </header>

    <RrSkeleton v-if="loading" variant="cards" :rows="4" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else>
      <div v-if="staleRows.length" class="rr-approvals__stale">
        <strong>{{ locale.t('rr.approvals_stale_title', 'Overdue approvals') }}</strong>
        <span class="rr-muted">
          {{
            locale.t(
              'rr.approvals_stale_hint',
              'Waiting more than {days} days at your step:',
              { days: meta.stale_days },
            )
          }}
        </span>
        <div class="rr-approvals__stale-chips">
          <button
            v-for="row in staleRows"
            :key="row.id"
            type="button"
            class="rr-approvals__stale-chip"
            @click="openRisk(row.risk_id)"
          >
            {{ row.risk_name }}
            <span>· {{ row.days_waiting }}d</span>
          </button>
        </div>
      </div>

      <section class="rr-kpi">
        <div class="rr-kpi__title">{{ locale.t('rr.approvals_queue', 'Approval queue') }}</div>
        <div class="rr-kpi__grid">
          <div
            v-for="card in kpiCards"
            :key="card.key"
            class="rr-kpi__card"
            :class="card.tone ? `rr-kpi__card--${card.tone}` : undefined"
          >
            <div class="rr-kpi__value">{{ card.value }}</div>
            <div class="rr-kpi__label">{{ card.label }}</div>
          </div>
        </div>
      </section>

      <section class="rr-card rr-filters">
        <div class="rr-filters__grid">
          <UFormField :label="locale.t('rr.filter_division', 'Division')">
            <USelect
              v-model="divisionFilter"
              :items="divisionOptions"
              hide-details
            />
          </UFormField>
          <UFormField :label="locale.t('rr.filter_role', 'Workflow role')">
            <USelect
              v-model="roleFilter"
              :items="roleOptions"
              hide-details
            />
          </UFormField>
        </div>
        <p class="rr-muted rr-filters__hint">
          {{
            locale.t(
              'rr.approvals_filter_hint',
              'KPI counts reflect your full queue; filters only change the table below.',
            )
          }}
        </p>
      </section>

      <p v-if="actionError" class="rr-error">{{ actionError }}</p>

      <section class="rr-card rr-approvals__table-card">
        <div class="rr-approvals__table-head">
          <h2>{{ locale.t('rr.pending_items', 'Pending items') }}</h2>
          <span class="rr-approvals__open-badge">
            {{ locale.t('rr.open_count', '{count} open', { count: filteredRows.length }) }}
          </span>
        </div>

        <div v-if="filteredRows.length" class="rr-report-table-wrap">
          <v-table density="comfortable" hover class="rr-v-table rr-approvals-table">
            <thead>
              <tr>
                <th style="width: 3rem">#</th>
                <th>{{ locale.t('rr.col_risk', 'Risk') }}</th>
                <th>{{ locale.t('rr.col_division', 'Division') }}</th>
                <th>{{ locale.t('rr.col_owners', 'Owners') }}</th>
                <th>{{ locale.t('rr.col_received', 'Date received') }}</th>
                <th>{{ locale.t('rr.col_score', 'Inherent') }}</th>
                <th class="text-center">{{ locale.t('rr.col_level', 'Level') }}</th>
                <th>{{ locale.t('rr.col_workflow_role', 'Workflow role') }}</th>
                <th class="text-center">{{ locale.t('rr.col_actions', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, idx) in filteredRows" :key="row.id">
                <tr :class="{ 'is-stale': row.is_stale }">
                  <td>{{ idx + 1 }}</td>
                  <td>
                    <button type="button" class="rr-link rr-approvals__risk-link" @click="openRisk(row.risk_id)">
                      {{ row.risk_name }}
                    </button>
                    <div v-if="row.is_stale" class="rr-approvals__overdue">
                      {{ locale.t('rr.overdue_days', '{n} days waiting', { n: row.days_waiting ?? 0 }) }}
                    </div>
                  </td>
                  <td>{{ row.division_name || locale.t('rr.none_dash', '—') }}</td>
                  <td>{{ row.submitted_by || locale.t('rr.none_dash', '—') }}</td>
                  <td>
                    <div>{{ formatReceived(row.date_received) }}</div>
                    <div class="rr-muted rr-approvals__wait">
                      {{ locale.t('rr.days_waiting', '{n}d waiting', { n: row.days_waiting ?? 0 }) }}
                    </div>
                  </td>
                  <td>{{ scoreLabel(row.inherent_rating, row.inherent_score) }}</td>
                  <td class="text-center">
                    <span class="rr-approvals__level">L{{ row.step_order }}</span>
                  </td>
                  <td>
                    <span class="rr-approvals__role">{{ roleLabel(row.role) }}</span>
                  </td>
                  <td class="rr-approvals__actions">
                    <button type="button" class="rr-btn rr-btn--primary" :disabled="approvingId === row.id" @click="approve(row.id)">
                      {{ approvingId === row.id ? locale.t('rr.approving', 'Approving…') : locale.t('rr.approve', 'Approve') }}
                    </button>
                    <button type="button" class="rr-btn" @click="openFeedback(row)">
                      {{ locale.t('rr.request_feedback', 'Request feedback') }}
                    </button>
                    <button type="button" class="rr-btn" @click="openRisk(row.risk_id)">
                      {{ locale.t('rr.open', 'Open') }}
                    </button>
                    <button
                      type="button"
                      class="rr-btn"
                      :class="{ 'is-open': expandedRiskId === row.risk_id }"
                      @click="toggleTrail(row.risk_id)"
                    >
                      {{ locale.t('rr.trail', 'Trail') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="expandedRiskId === row.risk_id" class="rr-approvals__trail-row">
                  <td colspan="9">
                    <p v-if="trailLoadingId === row.risk_id" class="rr-muted">
                      {{ locale.t('rr.loading', 'Loading…') }}
                    </p>
                    <RiskApprovalTrail
                      v-else
                      :steps="trailByRisk[row.risk_id] || []"
                      :workflow-state="row.workflow_state"
                      :can-approve-id="row.id"
                      :approving="approvingId === row.id"
                      @approve="approve"
                    />
                  </td>
                </tr>
              </template>
            </tbody>
          </v-table>
        </div>
        <p v-else class="rr-muted rr-approvals__empty">
          {{
            rows.length
              ? locale.t('rr.no_approvals_filter', 'No pending approvals for this filter.')
              : locale.t('rr.no_pending_approvals', 'No pending approvals.')
          }}
        </p>
      </section>
    </template>

    <v-dialog :model-value="feedbackOpen" max-width="560" scrollable persistent @update:model-value="(v) => { if (!v) closeFeedback() }">
      <v-card>
        <v-card-title>{{ locale.t('rr.request_feedback', 'Request feedback') }}</v-card-title>
        <v-card-subtitle v-if="feedbackRiskName">{{ feedbackRiskName }}</v-card-subtitle>
        <v-card-text>
          <p v-if="feedbackError" class="rr-error">{{ feedbackError }}</p>
          <div class="rr-form-fields">
            <UFormField :label="locale.t('rr.feedback_recipients', 'Recipients (lower levels only)')">
              <USelectMenu
                v-model="selectedRecipients"
                :items="recipients.map((p) => ({
                  label: `${p.name || locale.t('rr.staff_number', 'Staff #{id}', { id: p.staff_id })} · ${roleLabel(p.role)} (L${p.level})`,
                  value: p.staff_id,
                }))"
                value-key="value"
                multiple
                clearable
                :placeholder="locale.t('rr.select_recipients', 'Select recipients…')"
                hide-details
              />
            </UFormField>
            <UFormField :label="locale.t('rr.feedback_message', 'Message')">
              <UTextarea v-model="feedbackMsg" :rows="4" hide-details />
            </UFormField>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="feedbackSaving" @click="closeFeedback">
            {{ locale.t('rr.cancel', 'Cancel') }}
          </v-btn>
          <v-btn color="primary" variant="flat" :loading="feedbackSaving" @click="sendFeedback">
            {{ locale.t('rr.send_feedback', 'Send feedback') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.rr-approvals__stale {
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid #f59e0b;
  border-left-width: 4px;
  border-radius: 6px;
  background: #fffbeb;
  display: grid;
  gap: 0.45rem;
}
.rr-approvals__stale-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.rr-approvals__stale-chip {
  border: 1px solid #fbbf24;
  background: #fff;
  color: #92400e;
  border-radius: 999px;
  padding: 0.2rem 0.65rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}
.rr-approvals__stale-chip:hover {
  background: #fef3c7;
}
.rr-filters__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}
.rr-filters__hint {
  margin: 0.65rem 0 0;
}
.rr-kpi {
  background: #fff;
  border: 1px solid #c5d0da;
  border-radius: 6px;
  margin-bottom: 1rem;
  overflow: hidden;
}
.rr-kpi__title {
  color: #fff;
  background: #1e3a5f;
  padding: 0.55rem 0.85rem;
  font-size: 0.95rem;
  font-weight: 700;
}
.rr-kpi__grid {
  background: #e8f1fb;
  grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
  display: grid;
}
.rr-kpi__card {
  text-align: center;
  border-bottom: 1px solid #c5d0da;
  border-right: 1px solid #c5d0da;
  padding: 0.75rem 0.5rem;
}
.rr-kpi__value {
  color: #1e3a5f;
  font-variant-numeric: tabular-nums;
  font-size: 1.35rem;
  font-weight: 700;
}
.rr-kpi__label {
  color: #475569;
  font-size: 0.78rem;
  margin-top: 0.15rem;
}
.rr-kpi__card--critical .rr-kpi__value {
  color: #c00000;
}
.rr-approvals__table-card {
  padding: 0;
  overflow: hidden;
}
.rr-approvals__table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e2e8f0;
}
.rr-approvals__table-head h2 {
  margin: 0;
  font-size: 1rem;
}
.rr-approvals__open-badge {
  background: #119a48;
  color: #fff;
  border-radius: 999px;
  padding: 0.15rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 700;
}
.rr-approvals-table tr.is-stale td {
  background: #fffbeb;
}
.rr-approvals-table tr.is-stale td:first-child {
  box-shadow: inset 4px 0 0 #f59e0b;
}
.rr-approvals__risk-link {
  font-weight: 600;
  text-align: left;
}
.rr-approvals__overdue {
  color: #b45309;
  font-size: 0.72rem;
  font-weight: 700;
  margin-top: 0.15rem;
}
.rr-approvals__wait {
  font-size: 0.75rem;
}
.rr-approvals__level {
  display: inline-block;
  min-width: 2rem;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  background: #dbeafe;
  color: #1d4ed8;
  font-weight: 700;
  font-size: 0.78rem;
}
.rr-approvals__role {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  background: #ffedd5;
  color: #c2410c;
  font-size: 0.78rem;
  font-weight: 600;
}
.rr-approvals__actions {
  white-space: nowrap;
}
.rr-approvals__actions .rr-btn {
  margin: 0.15rem;
}
.rr-approvals__actions .rr-btn.is-open {
  border-color: #119a48;
  color: #0f766e;
}
.rr-approvals__trail-row td {
  background: #f8fafc;
  padding: 0.75rem 1rem !important;
}
.rr-approvals__empty {
  padding: 1.25rem 1rem;
  margin: 0;
}
</style>
