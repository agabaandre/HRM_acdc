import { api } from './api'

export type PerformanceTab = 'dashboard' | 'my' | 'pending'
export type PerformancePhase = 'ppa' | 'midterm' | 'endterm'

export interface PerformanceEntryRecord {
  entry_id: string
  staff_id: number
  performance_period: string
  staff_contract_id: number
  supervisor_id: number
  supervisor2_id: number
  draft_status: number
  midterm_draft_status: number
  endterm_draft_status: number
}

export interface PerformanceObjective {
  objective: string
  timeline: string
  indicator: string
  weight: number | string
  self_appraisal: string
  appraiser_rating: number | string
}

export interface PerformanceFormState {
  staff_id: number
  performance_period: string
  staff_contract_id: number
  supervisor_id: number
  supervisor2_id: number
  objectives: Record<number, PerformanceObjective>
  training_recommended: 'Yes' | 'No'
  required_skills: Array<number | string>
  training_contributions: string
  recommended_trainings: string
  recommended_trainings_details: string
  comments: string
  midterm_comments: string
  midterm_training_review: string
  midterm_achievements: string
  midterm_non_achievements: string
  midterm_training_contributions: string
  midterm_recommended_trainings: string
  midterm_recommended_trainings_details: string
  midterm_recommended_skills: Array<number | string>
  midterm_competency: Record<string, number | string>
  endterm_comments: string
  endterm_training_review: string
  endterm_achievements: string
  endterm_non_achievements: string
  endterm_training_contributions: string
  endterm_recommended_trainings: string
  endterm_recommended_trainings_details: string
  endterm_recommended_skills: Array<number | string>
  endterm_competency: Record<string, number | string>
}

export interface PerformanceContract {
  staff_contract_id: number
  fname?: string
  lname?: string
  SAPNO?: string | null
  job_name?: string | null
  initiation_date?: string | null
  division_id?: number | null
  division_name?: string | null
  first_supervisor?: number | null
  second_supervisor?: number | null
  funder_id?: number | null
  funder?: string | null
  contract_type_id?: number | null
  contract_type?: string | null
}

export interface PerformanceSkillCatalogItem {
  id: number
  skill: string
  category_id: number
}

export interface PerformanceCompetencyCatalogItem {
  id: number
  description: string
  annotation: string
  score_5: string
  score_4: string
  score_3: string
  score_2: string
  score_1: string
  category: string
  version?: number
}

export interface PerformanceWorkflowState {
  step: string
  label: string
  status_key: string
  can_act: boolean
  actor_staff_id: number | null
}

export interface PerformanceWorkflowTimelineStep {
  key: string
  label: string
  status: 'done' | 'current' | 'pending'
  actor: string
  hint: string
}

export interface PerformanceTrailEntry {
  staff_id: number
  staff_name?: string | null
  photo_url?: string | null
  staff_fname?: string | null
  staff_lname?: string | null
  action: string
  comments?: string | null
  created_at?: string | null
}

export interface PerformanceSubmissionWindow {
  open: boolean
  label: string
  message: string
}

export interface PerformanceFormPayload {
  phase: PerformancePhase
  phase_label: string
  entry: PerformanceEntryRecord
  form: PerformanceFormState
  contract: PerformanceContract
  contract_missing: boolean
  catalogs: {
    skills: PerformanceSkillCatalogItem[]
    competency_groups: Record<string, PerformanceCompetencyCatalogItem[]>
    competency_labels: Record<string, string>
  }
  workflow: {
    state: PerformanceWorkflowState | null
    timeline: PerformanceWorkflowTimelineStep[]
    trail: PerformanceTrailEntry[]
  }
  submission_window: PerformanceSubmissionWindow
  submission_open: boolean
  readonly: string
  midreadonly: string
  endreadonly: string
  is_owner: boolean
  can_save: boolean
  can_approve: boolean
  can_return: boolean
  can_consent: boolean
  midterm_exists: boolean
  endterm_exists: boolean
  ppa_approved: boolean
  period_label: string
  period_end_year: number
}

export interface PerformanceWorkflowActionInput {
  comments?: string
  supervisor2_agreement?: boolean
  accept_rating?: boolean
}

export interface PerformanceSelfActions {
  staff_id: number
  entry_id?: string
  ppa_exists: boolean
  ppa_approved: boolean
  midterm_exists?: boolean
  endterm_exists?: boolean
  create_ppa_url?: string | null
  current_ppa_url?: string | null
  midterm_url?: string | null
  endterm_url?: string | null
  midterm_label?: string
  endterm_label?: string
  show_create_ppa: boolean
  show_current_ppa: boolean
  show_midterm: boolean
  show_endterm: boolean
  midterm_window_open?: boolean
  endterm_window_open?: boolean
}

export interface PerformanceHubData {
  summary: {
    total: number
    approved: number
    submitted: number
    draft: number
    without_ppa: number
  }
  periods: string[]
  period: string
  divisions: Array<{ division_id: number; division_name: string }>
  pending: Array<Record<string, unknown>>
  pending_count: number
  workflow_summary: Record<string, string>
  submission_windows: Record<string, { open: boolean; label: string; message: string }>
  ppa_submission_open: boolean
  midterm_submission_open?: boolean
  endterm_submission_open?: boolean
  create_ppa_url?: string | null
  self_actions?: PerformanceSelfActions | null
  my_ppas: {
    data: Array<Record<string, unknown>>
    meta: { current_page: number; last_page: number; total: number }
  } | null
}

export async function fetchPerformanceHub(params: {
  tab?: PerformanceTab
  period?: string
  division_id?: number | null
  page?: number
  per_page?: number
} = {}): Promise<PerformanceHubData> {
  const { data } = await api.get<{ data: PerformanceHubData }>('/api/v1/performance/hub', {
    params: {
      tab: params.tab || 'dashboard',
      period: params.period || undefined,
      division_id: params.division_id || undefined,
      page: params.page || undefined,
      per_page: params.per_page || undefined,
    },
  })
  return data.data
}

function unwrapPerformanceForm(data: { data: PerformanceFormPayload }): PerformanceFormPayload {
  return data.data
}

export async function createPerformanceEntry(params: {
  period: string
  staff_id?: number | null
}): Promise<PerformanceFormPayload> {
  const { data } = await api.post<{ data: PerformanceFormPayload }>('/api/v1/performance/entries', {
    period: params.period,
    staff_id: params.staff_id ?? undefined,
  })
  return unwrapPerformanceForm(data)
}

export async function fetchPerformanceEntry(
  entryId: string,
  phase: PerformancePhase,
): Promise<PerformanceFormPayload> {
  const { data } = await api.get<{ data: PerformanceFormPayload }>(`/api/v1/performance/entries/${entryId}`, {
    params: { phase },
  })
  return unwrapPerformanceForm(data)
}

export async function savePerformanceDraft(
  entryId: string,
  phase: PerformancePhase,
  payload: PerformanceFormState,
): Promise<PerformanceFormPayload> {
  const { data } = await api.put<{ data: PerformanceFormPayload }>(`/api/v1/performance/entries/${entryId}`, {
    ...payload,
    phase,
  })
  return unwrapPerformanceForm(data)
}

export async function submitPerformanceEntry(
  entryId: string,
  phase: PerformancePhase,
  payload: PerformanceFormState,
): Promise<PerformanceFormPayload> {
  const { data } = await api.post<{ data: PerformanceFormPayload }>(
    `/api/v1/performance/entries/${entryId}/submit`,
    {
      ...payload,
      phase,
    },
  )
  return unwrapPerformanceForm(data)
}

export async function approvePerformanceEntry(
  entryId: string,
  phase: PerformancePhase,
  payload: PerformanceWorkflowActionInput = {},
): Promise<PerformanceFormPayload> {
  const { data } = await api.post<{ data: PerformanceFormPayload }>(
    `/api/v1/performance/entries/${entryId}/approve`,
    {
      phase,
      ...payload,
    },
  )
  return unwrapPerformanceForm(data)
}

export async function returnPerformanceEntry(
  entryId: string,
  phase: PerformancePhase,
  payload: Pick<PerformanceWorkflowActionInput, 'comments'>,
): Promise<PerformanceFormPayload> {
  const { data } = await api.post<{ data: PerformanceFormPayload }>(
    `/api/v1/performance/entries/${entryId}/return`,
    {
      phase,
      ...payload,
    },
  )
  return unwrapPerformanceForm(data)
}

export async function consentPerformanceEntry(
  entryId: string,
  payload: PerformanceWorkflowActionInput = {},
): Promise<PerformanceFormPayload> {
  const { data } = await api.post<{ data: PerformanceFormPayload }>(
    `/api/v1/performance/entries/${entryId}/consent`,
    payload,
  )
  return unwrapPerformanceForm(data)
}
