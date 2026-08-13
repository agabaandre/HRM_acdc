import { api } from './api'

export type PayrollSettings = {
  id: number
  default_currency: string
  enabled_currencies: string[]
  period_close_day: number
  jurisdiction_default?: string | null
}

export type WageType = {
  id: number
  code: string
  name: string
  category: string
  calc_method: string
  percent_base?: string | null
  default_amount?: number | null
  taxable: boolean
  pre_tax: boolean
  is_system: boolean
  is_active: boolean
  sort_order: number
}

export type TaxBand = {
  id?: number
  from_amount: number
  to_amount?: number | null
  rate_percent: number
  fixed_amount?: number
}

export type TaxRule = {
  id: number
  code: string
  name: string
  jurisdiction_code?: string | null
  effective_from: string
  effective_to?: string | null
  applies_to: string
  wage_type_id?: number | null
  is_active: boolean
  bands?: TaxBand[]
}

export type StaffPay = {
  id: number
  staff_id: number
  currency: string
  basic_salary: number
  bank_name?: string | null
  bank_account?: string | null
  bank_branch?: string | null
  tax_identifier?: string | null
  pay_status: string
  notes?: string | null
  staff_name?: string | null
  sap_number?: string | null
  work_email?: string | null
}

export type StaffWageItem = {
  id: number
  staff_id: number
  wage_type_id: number
  amount?: number | null
  percent?: number | null
  currency?: string | null
  start_date?: string | null
  end_date?: string | null
  is_active: boolean
  wage_type?: WageType | null
}

export type StaffPayBundle = {
  staff?: {
    staff_id: number
    staff_name?: string | null
    sap_number?: string | null
    work_email?: string | null
  }
  pay: StaffPay | null
  wage_items: StaffWageItem[]
}

export type PayrollPeriod = {
  id: number
  year: number
  month: number
  label: string
  status: string
  fx_rates?: Array<{ id: number; currency: string; rate_to_default: number }>
}

export type PayrollRun = {
  id: number
  period_id: number
  status: string
  off_cycle: boolean
  title?: string | null
  staff_count: number
  total_gross_default: number
  total_net_default: number
  simulated_at?: string | null
  posted_at?: string | null
  period?: PayrollPeriod
  lines?: PayrollRunLine[]
}

export type PayrollRunLine = {
  id: number
  run_id: number
  staff_id: number
  staff_name?: string | null
  sap_number?: string | null
  currency: string
  basic: number
  gross: number
  taxable: number
  tax: number
  deductions: number
  benefits: number
  net: number
  net_default: number
  items?: Array<{ id: number; category: string; amount: number; wage_type?: WageType | null }>
}

export type Payslip = {
  id: number
  staff_id: number
  staff_name?: string | null
  sap_number?: string | null
  work_email?: string | null
  period_id: number
  run_id: number
  generated_at?: string | null
  emailed_at?: string | null
  period?: PayrollPeriod
  ytd?: Record<string, number>
}

export type PayrollLoan = {
  id: number
  staff_id: number
  type: string
  currency: string
  principal: number
  interest_rate: number
  installment_amount?: number | null
  installment_count?: number | null
  status: string
  notes?: string | null
  schedules?: Array<{ id: number; sequence: number; amount: number; status: string; due_period_id?: number | null }>
}

export type DashboardData = {
  open_period?: PayrollPeriod | null
  last_run?: PayrollRun | null
  pending_loan_approvals: number
  staff_missing_pay_master: number
  active_staff_count: number
  staff_with_pay_count: number
}

export async function fetchPayrollDashboard() {
  const { data } = await api.get<{ data: DashboardData }>('/api/v1/payroll/dashboard')
  return data.data
}

export async function fetchPayrollSettings() {
  const { data } = await api.get<{ data: PayrollSettings }>('/api/v1/payroll/settings')
  return data.data
}

export async function savePayrollSettings(payload: Partial<PayrollSettings>) {
  const { data } = await api.put<{ data: PayrollSettings }>('/api/v1/payroll/settings', payload)
  return data.data
}

export async function fetchWageTypes() {
  const { data } = await api.get<{ data: WageType[] }>('/api/v1/payroll/wage-types')
  return data.data
}

export async function createWageType(payload: Partial<WageType>) {
  const { data } = await api.post<{ data: WageType }>('/api/v1/payroll/wage-types', payload)
  return data.data
}

export async function updateWageType(id: number, payload: Partial<WageType>) {
  const { data } = await api.put<{ data: WageType }>(`/api/v1/payroll/wage-types/${id}`, payload)
  return data.data
}

export async function fetchTaxRules() {
  const { data } = await api.get<{ data: TaxRule[] }>('/api/v1/payroll/tax-rules')
  return data.data
}

export async function createTaxRule(payload: Partial<TaxRule> & { bands?: TaxBand[] }) {
  const { data } = await api.post<{ data: TaxRule }>('/api/v1/payroll/tax-rules', payload)
  return data.data
}

export async function updateTaxRule(id: number, payload: Partial<TaxRule> & { bands?: TaxBand[] }) {
  const { data } = await api.put<{ data: TaxRule }>(`/api/v1/payroll/tax-rules/${id}`, payload)
  return data.data
}

export async function fetchStaffPayDirectory() {
  const { data } = await api.get<{ data: StaffPay[] }>('/api/v1/payroll/staff-pay')
  return data.data
}

export async function fetchStaffPay(staffId: number) {
  const { data } = await api.get<{ data: StaffPayBundle }>(`/api/v1/payroll/staff/${staffId}/pay`)
  return data.data
}

export async function saveStaffPay(staffId: number, payload: Partial<StaffPay>) {
  const { data } = await api.put<{ data: StaffPay }>(`/api/v1/payroll/staff/${staffId}/pay`, payload)
  return data.data
}

export async function createStaffWageItem(staffId: number, payload: Partial<StaffWageItem>) {
  const { data } = await api.post<{ data: StaffWageItem }>(
    `/api/v1/payroll/staff/${staffId}/wage-items`,
    payload,
  )
  return data.data
}

export async function updateStaffWageItem(staffId: number, id: number, payload: Partial<StaffWageItem>) {
  const { data } = await api.put<{ data: StaffWageItem }>(
    `/api/v1/payroll/staff/${staffId}/wage-items/${id}`,
    payload,
  )
  return data.data
}

export async function deleteStaffWageItem(staffId: number, id: number) {
  await api.delete(`/api/v1/payroll/staff/${staffId}/wage-items/${id}`)
}

export async function fetchPeriods() {
  const { data } = await api.get<{ data: PayrollPeriod[] }>('/api/v1/payroll/periods')
  return data.data
}

export async function createPeriod(payload: { year: number; month: number; label?: string }) {
  const { data } = await api.post<{ data: PayrollPeriod }>('/api/v1/payroll/periods', payload)
  return data.data
}

export async function savePeriodFx(id: number, rates: Array<{ currency: string; rate_to_default: number }>) {
  const { data } = await api.put<{ data: PayrollPeriod }>(`/api/v1/payroll/periods/${id}/fx`, { rates })
  return data.data
}

export async function fetchRuns() {
  const { data } = await api.get<{ data: PayrollRun[] }>('/api/v1/payroll/runs')
  return data.data
}

export async function createRun(payload: { period_id: number; off_cycle?: boolean; title?: string }) {
  const { data } = await api.post<{ data: PayrollRun }>('/api/v1/payroll/runs', payload)
  return data.data
}

export async function fetchRun(id: number) {
  const { data } = await api.get<{ data: PayrollRun }>(`/api/v1/payroll/runs/${id}`)
  return data.data
}

export async function simulateRun(id: number) {
  const { data } = await api.post<{ data: PayrollRun }>(`/api/v1/payroll/runs/${id}/simulate`)
  return data.data
}

export async function postRun(id: number, allowNegativeNet = false) {
  const { data } = await api.post<{ data: PayrollRun }>(`/api/v1/payroll/runs/${id}/post`, {
    allow_negative_net: allowNegativeNet,
  })
  return data.data
}

export async function fetchPayslips(params?: Record<string, string | number>) {
  const { data } = await api.get<{ data: Payslip[] }>('/api/v1/payroll/payslips', { params })
  return data.data
}

export function payslipPdfUrl(id: number) {
  return `/api/v1/payroll/payslips/${id}/pdf`
}

export async function fetchLoans(params?: Record<string, string | number | boolean>) {
  const { data } = await api.get<{ data: PayrollLoan[] }>('/api/v1/payroll/loans', { params })
  return data.data
}

export async function createLoan(payload: Record<string, unknown>) {
  const { data } = await api.post<{ data: PayrollLoan }>('/api/v1/payroll/loans', payload)
  return data.data
}

export async function decideLoan(id: number, decision: 'approve' | 'reject', reason?: string) {
  const { data } = await api.post<{ data: PayrollLoan }>(`/api/v1/payroll/loans/${id}/decide`, {
    decision,
    reason,
  })
  return data.data
}

export async function disburseLoan(id: number, payload: Record<string, unknown>) {
  const { data } = await api.post<{ data: PayrollLoan }>(`/api/v1/payroll/loans/${id}/disburse`, payload)
  return data.data
}
