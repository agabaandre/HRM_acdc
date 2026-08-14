import { api } from './api'
import { cachedGet, clearApiCache } from './apiCache'

export interface LeaveTypeDto {
  leave_id: number
  leave_name: string
  code?: string | null
  leave_days: number
  is_accrued: boolean
  accrual_rate: number
  is_active: boolean
  requires_hr_approval: boolean
  requires_medical_certificate: boolean
  medical_report_after_days?: number | null
  max_instances?: number | null
  max_days_per_year?: number | null
  min_days_per_year?: number | null
  deduct_compensatory_first: boolean
  policy_notes?: string | null
  sort_order?: number | null
}

export interface LeaveBalanceDto {
  entitlement: number
  accrued: number
  opening: number
  carried_forward: number
  compensatory: number
  used: number
  pending: number
  available: number
  year: number
}

export interface LeaveBalanceRow {
  type: LeaveTypeDto
  balance: LeaveBalanceDto
}

export interface LeaveRequestDto {
  request_id: number
  staff_id: number
  staff_name?: string | null
  sap_number?: string | null
  leave_id: number
  leave_name?: string | null
  start_date?: string | null
  end_date?: string | null
  requested_days: number
  overall_status: string
  email_leave?: string | null
  mobile_leave?: string | null
  remarks?: string | null
}

const BALANCES_SESSION_KEY = 'staff-portal:leave:balances'

export function readLeaveBalancesSession(): LeaveBalanceRow[] | null {
  try {
    const raw = sessionStorage.getItem(BALANCES_SESSION_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as LeaveBalanceRow[]
    return Array.isArray(parsed) ? parsed : null
  } catch {
    return null
  }
}

export function writeLeaveBalancesSession(rows: LeaveBalanceRow[]): void {
  try {
    sessionStorage.setItem(BALANCES_SESSION_KEY, JSON.stringify(rows))
  } catch {
    /* ignore */
  }
}

export async function fetchLeaveBalances(): Promise<LeaveBalanceRow[]> {
  const data = await cachedGet<{ data: LeaveBalanceRow[] }>(
    'leave:balances',
    '/api/v1/leave/balances',
    60_000,
  )
  writeLeaveBalancesSession(data.data)
  return data.data
}

export async function fetchLeaveRequests(params: {
  scope?: 'mine' | 'all'
  status?: string
  start_date?: string
  end_date?: string
}): Promise<LeaveRequestDto[]> {
  const data = await cachedGet<{ data: LeaveRequestDto[] }>(
    'leave:requests',
    '/api/v1/leave/requests',
    20_000,
    params as Record<string, unknown>,
  )
  return data.data
}

const APPROVALS_SESSION_KEY = 'staff-portal:leave:approvals'

export function readLeaveApprovalsSession(): {
  data: LeaveRequestDto[]
  meta: { is_hr: boolean }
} | null {
  try {
    const raw = sessionStorage.getItem(APPROVALS_SESSION_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { data: LeaveRequestDto[]; meta: { is_hr: boolean } }
    if (!parsed || !Array.isArray(parsed.data)) return null
    return parsed
  } catch {
    return null
  }
}

function writeLeaveApprovalsSession(payload: {
  data: LeaveRequestDto[]
  meta: { is_hr: boolean }
}): void {
  try {
    sessionStorage.setItem(APPROVALS_SESSION_KEY, JSON.stringify(payload))
  } catch {
    /* ignore */
  }
}

export async function fetchLeaveApprovals(): Promise<{
  data: LeaveRequestDto[]
  meta: { is_hr: boolean }
}> {
  const data = await cachedGet<{ data: LeaveRequestDto[]; meta: { is_hr: boolean } }>(
    'leave:approvals',
    '/api/v1/leave/approvals',
    20_000,
  )
  writeLeaveApprovalsSession(data)
  return data
}

export async function decideLeaveRequest(
  id: number,
  payload: { role: string; action: 'approve' | 'reject'; message?: string },
): Promise<void> {
  await api.post(`/api/v1/leave/requests/${id}/decide`, payload)
  clearApiCache('leave:requests')
  clearApiCache('leave:balances')
  clearApiCache('leave:approvals')
  try {
    sessionStorage.removeItem(APPROVALS_SESSION_KEY)
  } catch {
    /* ignore */
  }
}

export async function fetchActiveLeaveTypes(): Promise<LeaveTypeDto[]> {
  const data = await cachedGet<{ data: LeaveTypeDto[] }>(
    'leave:types',
    '/api/v1/leave/types',
    5 * 60_000,
  )
  return data.data
}

export function invalidateLeaveTypesCache(): void {
  clearApiCache('leave:types')
}

export interface LeaveApplyRules {
  min_notice_days: number
  earliest_start_date: string
}

export async function fetchLeaveApplyRules(): Promise<LeaveApplyRules> {
  const { data } = await api.get<{ data: LeaveApplyRules }>('/api/v1/leave/apply-rules')
  return data.data
}

export async function fetchWorkingDays(start_date: string, end_date: string): Promise<number> {
  const { data } = await api.post<{ data: { requested_days: number } }>(
    '/api/v1/leave/working-days',
    { start_date, end_date },
  )
  return data.data.requested_days
}

/** Client-side working days (Mon–Fri), matching LeaveRequestService::workingDaysBetween. */
export function workingDaysBetween(start_date: string, end_date: string): number {
  const start = new Date(`${start_date}T00:00:00`)
  const end = new Date(`${end_date}T00:00:00`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || start > end) {
    return 0
  }
  let days = 0
  for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const weekday = d.getDay()
    if (weekday !== 0 && weekday !== 6) days += 1
  }
  return Math.max(1, days)
}

export async function fetchBalanceForType(leave_id: number): Promise<LeaveBalanceDto> {
  const { data } = await api.get<{ data: LeaveBalanceDto }>('/api/v1/leave/balance-for-type', {
    params: { leave_id },
  })
  return data.data
}

export interface LeaveSupportingOfficer {
  staff_id: number
  name: string
  work_email?: string | null
  sap_number?: string | null
  label: string
}

export async function fetchSupportingOfficers(): Promise<LeaveSupportingOfficer[]> {
  const data = await cachedGet<{ data: LeaveSupportingOfficer[] }>(
    'leave:supporting-officers',
    '/api/v1/leave/supporting-officers',
    5 * 60_000,
  )
  return data.data
}

export async function submitLeaveRequest(form: FormData): Promise<void> {
  await api.post('/api/v1/leave/requests', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  clearApiCache('leave:requests')
  clearApiCache('leave:balances')
}

export interface LeavePlanEntryDto {
  id?: number
  leave_id: number
  leave_name?: string | null
  start_date: string
  end_date: string
  planned_days: number
  remarks?: string | null
  sort_order?: number
}

export interface LeavePlanEntryInput {
  leave_id?: number
  start_date: string
  end_date: string
  planned_days?: number
  remarks?: string
}

export interface LeavePlanDto {
  id: number
  staff_id: number
  plan_year: number
  draft_status: number
  status_label: string
  submitted_at?: string | null
  notes?: string | null
  entries: LeavePlanEntryDto[]
  planned_days_total: number
  readonly: boolean
  can_save: boolean
  can_submit: boolean
  balance_hint?: {
    leave_id: number
    leave_name: string
    available: number
    entitlement: number
  } | null
  annual_leave?: {
    leave_id: number
    leave_name: string
  } | null
}

export async function fetchLeavePlan(year?: number): Promise<{
  data: LeavePlanDto
  meta: { year: number; year_options: number[] }
}> {
  const y = year ?? new Date().getFullYear()
  return cachedGet<{
    data: LeavePlanDto
    meta: { year: number; year_options: number[] }
  }>('leave:plan', '/api/v1/leave/plans', 45_000, { year: y })
}

export async function saveLeavePlanDraft(
  id: number,
  payload: { notes?: string; entries: LeavePlanEntryInput[] },
): Promise<{ message: string; data: LeavePlanDto }> {
  const { data } = await api.put<{ message: string; data: LeavePlanDto }>(
    `/api/v1/leave/plans/${id}`,
    payload,
  )
  clearApiCache('leave:plan')
  return data
}

export async function submitLeavePlan(
  id: number,
): Promise<{ message: string; data: LeavePlanDto }> {
  const { data } = await api.post<{ message: string; data: LeavePlanDto }>(
    `/api/v1/leave/plans/${id}/submit`,
  )
  clearApiCache('leave:plan')
  return data
}

export async function fetchLeavePolicy(): Promise<Record<string, unknown>> {
  const { data } = await api.get<{ data: Record<string, unknown> }>('/api/v1/leave/settings/policy')
  return data.data
}

export async function saveLeavePolicy(policy: Record<string, unknown>): Promise<void> {
  await api.put('/api/v1/leave/settings/policy', { policy })
}

export async function fetchSettingsLeaveTypes(): Promise<LeaveTypeDto[]> {
  const { data } = await api.get<{ data: LeaveTypeDto[] }>('/api/v1/leave/settings/types')
  return data.data
}

export async function saveLeaveType(
  payload: Partial<LeaveTypeDto> & { leave_name: string },
  leaveId?: number | null,
): Promise<void> {
  if (leaveId) {
    await api.put(`/api/v1/leave/settings/types/${leaveId}`, payload)
  } else {
    await api.post('/api/v1/leave/settings/types', payload)
  }
  invalidateLeaveTypesCache()
}

export interface LeaveAdminDirectoryRow {
  staff_id: number
  name: string
  work_email?: string | null
  sap_number?: string | null
  opening_types_configured: number
  active_leave_types: number
  balances_complete: boolean
}

export interface LeaveAdminDirectoryResponse {
  data: LeaveAdminDirectoryRow[]
  meta: { year: number; total: number; page: number; per_page: number }
}

export interface LeaveAdminBalanceEditRow {
  type: LeaveTypeDto
  balance: LeaveBalanceDto
  opening_days: number
  carried_forward_days: number
  compensatory_days: number
}

export async function fetchLeaveAdminDirectory(params: {
  q?: string
  year?: number
  page?: number
  per_page?: number
} = {}): Promise<LeaveAdminDirectoryResponse> {
  const { data } = await api.get<LeaveAdminDirectoryResponse>('/api/v1/leave/admin/balances', {
    params,
  })
  return data
}

export async function fetchLeaveAdminStaffBalances(
  staffId: number,
  year?: number,
): Promise<{
  staff: { staff_id: number; name: string; work_email?: string | null }
  year: number
  balances: LeaveAdminBalanceEditRow[]
}> {
  const { data } = await api.get<{
    data: {
      staff: { staff_id: number; name: string; work_email?: string | null }
      year: number
      balances: LeaveAdminBalanceEditRow[]
    }
  }>(`/api/v1/leave/admin/balances/${staffId}`, { params: { year } })
  return data.data
}

export async function saveLeaveAdminStaffBalances(
  staffId: number,
  payload: {
    year: number
    rows: Array<{
      leave_id: number
      opening_days: number
      carried_forward_days: number
      compensatory_days: number
      notes?: string | null
    }>
  },
): Promise<void> {
  await api.put(`/api/v1/leave/admin/balances/${staffId}`, payload)
}

export async function bulkFillLeaveBalances(payload: {
  year?: number
  overwrite?: boolean
  leave_ids?: number[]
}): Promise<{
  message: string
  data: {
    staff_processed: number
    rows_created: number
    rows_updated: number
    rows_skipped: number
    year: number
  }
}> {
  const { data } = await api.post<{
    message: string
    data: {
      staff_processed: number
      rows_created: number
      rows_updated: number
      rows_skipped: number
      year: number
    }
  }>('/api/v1/leave/admin/balances/bulk-fill', payload)
  return data
}
