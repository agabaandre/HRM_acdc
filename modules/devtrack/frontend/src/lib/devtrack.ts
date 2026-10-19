export type CatalogOption = { value: string; label: string; code?: string; short_label?: string }

export type DevTrackOptions = {
  work_classes: CatalogOption[]
  priority_categories: CatalogOption[]
  project_stages: CatalogOption[]
  rag_statuses: CatalogOption[]
  project_statuses: CatalogOption[]
  operational_statuses: CatalogOption[]
  module_statuses: CatalogOption[]
  enhancement_statuses: CatalogOption[]
  enhancement_work_types: CatalogOption[]
  release_statuses: CatalogOption[]
  work_request_statuses: CatalogOption[]
  data_classifications: CatalogOption[]
  named_roles: CatalogOption[]
  work_item_types: CatalogOption[]
  work_item_statuses: CatalogOption[]
  work_item_priorities: CatalogOption[]
  raid_types: CatalogOption[]
  raid_statuses: CatalogOption[]
  report_periods: CatalogOption[]
  sprint_statuses: CatalogOption[]
  urgency_levels: CatalogOption[]
  deployment_readiness: CatalogOption[]
  scoring_weights: Record<string, number>
}

export type TeamMember = { id: number; name: string; email: string; is_admin: boolean }

export type StaffMember = {
  id: number
  staff_id: number
  name: string
  email: string | null
  division_name?: string | null
  duty_station_name?: string | null
}

export type IndicatorSummary = {
  id: number
  code: string
  title: string
  directorate?: string | null
  programme?: string | null
}

export type ProjectSummary = {
  id: number
  code: string
  name: string
  description?: string | null
  work_class: string
  work_class_label: string
  work_class_code: string
  stage: string
  stage_label: string
  stage_full_label: string
  next_gate: string | null
  next_gate_approver: string | null
  rag_status: string
  rag_label: string
  rag_reason: string | null
  priority_category: string
  priority_label: string
  status: string
  status_label: string
  start_date: string | null
  target_end_date: string | null
  sprint_goal: string | null
  budget_status: string | null
  resource_status: string | null
  uat_progress_percent: number
  deployment_readiness: string
  indicator: IndicatorSummary | null
  work_request_id?: number | null
  work_request?: { id: number; reference: string; title: string } | null
  open_tasks: number
  done_tasks: number
  progress_percent: number
  open_raid: number
  is_archived?: boolean
  archived_at?: string | null
  archive_reason?: string | null
  archived_by?: number | null
  archived_by_user?: { id: number; name: string; email: string | null } | null
}

export type WorkItemRow = {
  id: number
  key: string
  project_id: number
  enhancement_id?: number | null
  project_module_id?: number | null
  project: { id: number; code: string; name: string } | null
  sprint_id: number | null
  sprint: { id: number; name: string } | null
  parent_id: number | null
  title: string
  description: string | null
  acceptance_criteria: string | null
  type: string
  type_label: string
  status: string
  status_label: string
  priority: string
  priority_label: string
  assignee: { id: number; name: string; email: string } | null
  assignee_id: number | null
  estimate_hours: number | null
  story_points: number | null
  due_date: string | null
  blocked_reason: string | null
  is_overdue: boolean
  children_count: number
}

export type ProjectModuleRow = {
  id: number
  project_id: number
  code: string
  name: string
  description: string | null
  status: string
  status_label: string
  stage: string
  stage_label: string
  stage_full_label?: string
  rag_status: string
  rag_reason: string | null
  priority_category: string | null
  priority_label?: string | null
  start_date: string | null
  target_end_date: string | null
  scope: string | null
  success_measures: string | null
  requesting_division?: string | null
  business_owner_staff_id?: number | null
  business_owner?: { id: number; name: string; email: string | null } | null
  responsible_developer_staff_id?: number | null
  responsible_developer?: { id: number; name: string; email: string | null } | null
  done_tasks: number
  total_tasks: number
  progress_percent: number
  project?: { id: number; code: string; name: string } | null
  work_items?: WorkItemRow[]
  enhancements?: EnhancementRow[]
}

export type EnhancementRow = {
  id: number
  reference: string
  project_id: number
  project_module_id: number | null
  name: string
  work_type: string
  work_type_label: string
  description: string | null
  scope: string | null
  requesting_division: string | null
  business_owner_staff_id: number | null
  business_owner: { id: number; name: string; email: string | null } | null
  responsible_developer_staff_id: number | null
  responsible_developer: { id: number; name: string; email: string | null } | null
  priority: string
  priority_label: string
  planned_start: string | null
  due_date: string | null
  status: string
  status_label: string
  acceptance_criteria: string | null
  done_tasks: number
  total_tasks: number
  progress_percent: number
  module?: { id: number; code: string; name: string } | null
  project?: { id: number; code: string; name: string } | null
  work_items?: WorkItemRow[]
  release_ids?: number[]
}

export type ReleaseRow = {
  id: number
  project_id: number
  version: string
  name: string | null
  description: string | null
  status: string
  status_label: string
  target_date: string | null
  released_at: string | null
  enhancements?: Array<{ id: number; reference: string; name: string }>
}

export type WorkRequestRow = {
  id: number
  reference: string
  title: string
  requesting_unit: string
  requester_name: string
  problem_statement: string
  work_class: string | null
  work_class_label: string | null
  priority_category: string | null
  priority_label: string | null
  weighted_score: number
  status: string
  status_label: string
  urgency: string
  urgency_label: string
  indicator: IndicatorSummary | null
  workplan_indicator_id: number | null
  project: { id: number; code: string; name: string } | null
  scores: Record<string, number>
  created_at: string
}

export type StatusReportRow = {
  id: number
  project_id: number | null
  project: { id: number; code: string; name: string } | null
  period: string
  period_label: string
  period_start: string
  period_end: string
  title: string
  rag_status: string
  rag_label: string
  summary: string | null
  accomplishments: string | null
  next_period_plan: string | null
  blockers: string | null
  decisions_required: string | null
  submitted_by: { id: number; name: string } | null
  submitted_at: string | null
}

export type RaidRow = {
  id: number
  project_id: number
  project: { id: number; code: string; name: string } | null
  type: string
  type_label: string
  title: string
  description: string | null
  severity: string
  severity_label: string
  status: string
  status_label: string
  owner: { id: number; name: string } | null
  owner_id: number | null
  due_date: string | null
  mitigation: string | null
}

export type SprintRow = {
  id: number
  project_id: number | null
  project: { id: number; code: string; name: string } | null
  name: string
  goal: string | null
  start_date: string
  end_date: string
  status: string
  status_label: string
  committed_points: number
  completed_points: number
  work_items_count: number
  work_items_done: number
  progress_percent: number
}

export function ragColor(status?: string | null): string {
  if (status === 'red') return 'error'
  if (status === 'amber') return 'warning'
  if (status === 'green') return 'success'
  return 'secondary'
}

export function statusColor(status?: string | null): string {
  if (status === 'done' || status === 'completed' || status === 'converted') return 'success'
  if (status === 'blocked' || status === 'cancelled' || status === 'declined') return 'error'
  if (status === 'in_progress' || status === 'mitigating' || status === 'acknowledged') return 'info'
  if (status === 'in_review' || status === 'under_review' || status === 'on_hold' || status === 'parked') return 'warning'
  if (status === 'not_started' || status === 'ready' || status === 'backlog') return 'secondary'
  return 'secondary'
}

export function optionLabel(options: CatalogOption[] | undefined, value?: string | null): string {
  if (!value) return '—'
  return options?.find((item) => item.value === value)?.label ?? value
}
