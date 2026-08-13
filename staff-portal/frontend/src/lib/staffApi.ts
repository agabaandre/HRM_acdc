import { api } from './api'
import { cachedGet, clearApiCache } from './apiCache'

export type StaffPreset = 'active' | 'due' | 'expired' | 'former' | 'renewal' | 'all'
export type StaffCategory = 'main_staff' | 'other_staff' | 'all'

export interface StaffListRow {
  staff_id: number
  SAPNO?: string | null
  title?: string | null
  fname?: string | null
  lname?: string | null
  oname?: string | null
  photo?: string | null
  gender?: string | null
  date_of_birth?: string | null
  initiation_date?: string | null
  work_email?: string | null
  tel_1?: string | null
  tel_2?: string | null
  whatsapp?: string | null
  division_name?: string | null
  job_name?: string | null
  job_acting?: string | null
  duty_station_name?: string | null
  grade?: string | null
  contract_type?: string | null
  category?: StaffCategory | null
  contract_status?: string | null
  status_id?: number | null
  start_date?: string | null
  end_date?: string | null
  funder?: string | null
  nationality?: string | null
  region_name?: string | null
  first_supervisor_name?: string | null
  second_supervisor_name?: string | null
  [key: string]: unknown
}

export interface StaffListResponse {
  data: StaffListRow[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    preset: string
    category: StaffCategory
    filter_counts: Record<string, number>
  }
}

export interface StaffShowResponse {
  data: {
    staff: Record<string, unknown>
    contracts: StaffContractRow[]
    can_manage: boolean
    can_manage_contracts: boolean
  }
}

export interface StaffContractRow {
  staff_contract_id: number
  job_id?: number | null
  job_name?: string | null
  job_acting_id?: number | null
  job_acting?: string | null
  grade_id?: string | null
  grade?: string | null
  contracting_institution_id?: number | null
  contracting_institution?: string | null
  funder_id?: number | null
  funder?: string | null
  first_supervisor?: number | null
  first_supervisor_name?: string | null
  second_supervisor?: number | null
  second_supervisor_name?: string | null
  contract_type_id?: number | null
  contract_type?: string | null
  duty_station_id?: number | null
  duty_station_name?: string | null
  division_id?: number | null
  division_name?: string | null
  unit_id?: number | null
  status_id?: number | null
  status_label?: string | null
  start_date?: string | null
  end_date?: string | null
  comments?: string | null
  file_name?: string | null
  contract_file_url?: string | null
  has_signed_contract?: boolean
  other_associated_divisions?: number[] | string | null
  [key: string]: unknown
}

export type BirthdayRange = 'today' | 'tomorrow' | 'next_7' | 'next_30'

export interface BirthdayRow {
  staff_id: number
  title?: string | null
  fname?: string | null
  lname?: string | null
  oname?: string | null
  date_of_birth?: string | null
  next_birthday?: string | null
  age?: number | null
  gender?: string | null
  work_email?: string | null
  division_name?: string | null
  job_name?: string | null
  grade?: string | null
  photo?: string | null
}

export interface BirthdayListResponse {
  data: BirthdayRow[]
  meta: {
    range: BirthdayRange
    from: string
    to: string
    total: number
  }
}

export interface DataQualityResponse {
  data: {
    counts: {
      missing_email: number
      missing_dob: number
      missing_sap: number
    }
    sample: Array<{
      staff_id: number
      fname?: string | null
      lname?: string | null
      work_email?: string | null
      date_of_birth?: string | null
      sap_number?: string | null
    }>
  }
}

export interface StaffLookupOption {
  [key: string]: unknown
}

export interface StaffUnitOption {
  unit_id: number
  division_id?: number | null
  unit_name?: string | null
}

export interface StaffSupervisorOption {
  staff_id: number
  fname?: string | null
  lname?: string | null
}

export interface StaffFormLookups {
  jobs: StaffLookupOption[]
  jobsActing: StaffLookupOption[]
  grades: StaffLookupOption[]
  institutions: StaffLookupOption[]
  funders: StaffLookupOption[]
  contractTypes: StaffLookupOption[]
  dutyStations: StaffLookupOption[]
  divisions: StaffLookupOption[]
  units: StaffUnitOption[]
  statuses: StaffLookupOption[]
  nationalities: StaffLookupOption[]
  supervisors: StaffSupervisorOption[]
}

export interface StaffCreatePayload {
  SAPNO?: string
  title: string
  fname: string
  lname: string
  oname?: string
  date_of_birth: string
  gender: string
  nationality_id: number | ''
  initiation_date: string
  tel_1: string
  tel_2?: string
  whatsapp?: string
  work_email: string
  private_email?: string
  physical_location?: string
  job_id: number | ''
  job_acting_id?: number | '' | null
  grade_id: string
  contracting_institution_id: number | ''
  funder_id: number | ''
  first_supervisor: number | ''
  second_supervisor?: number | '' | null
  contract_type_id: number | ''
  duty_station_id: number | ''
  division_id: number | ''
  unit_id?: number | '' | null
  other_associated_divisions?: number[]
  start_date: string
  end_date: string
  comments?: string
  status_id?: number
}

export interface StaffContractPayload {
  job_id: number | ''
  job_acting_id?: number | '' | null
  grade_id: string
  contracting_institution_id: number | ''
  funder_id: number | ''
  first_supervisor: number | ''
  second_supervisor?: number | '' | null
  contract_type_id: number | ''
  duty_station_id: number | ''
  division_id: number | ''
  unit_id?: number | '' | null
  other_associated_divisions?: number[]
  start_date: string
  end_date: string
  status_id: number | ''
  comments?: string
}

export interface StaffFilterOption {
  id: number | string
  name: string
  region_id?: number | null
}

export interface StaffFilterOptions {
  regions: StaffFilterOption[]
  nationalities: StaffFilterOption[]
  divisions: StaffFilterOption[]
  duty_stations: StaffFilterOption[]
  funders: StaffFilterOption[]
  jobs: StaffFilterOption[]
  grades: StaffFilterOption[]
  genders: StaffFilterOption[]
}

export interface StaffListFilters {
  name?: string
  sapno?: string
  gender?: string
  region_id?: number | null
  nationality_id?: number | null
  division_id?: number[]
  duty_station_id?: number[]
  funder_id?: number[]
  job_id?: number[]
  grade_id?: number[]
}

export async function fetchStaffList(params: {
  q?: string
  preset?: StaffPreset
  category?: StaffCategory
  page?: number
  per_page?: number
} & StaffListFilters = {}): Promise<StaffListResponse> {
  const { data } = await api.get<StaffListResponse>('/api/v1/staff', {
    params,
    paramsSerializer: { indexes: null },
  })
  return data
}

export async function fetchStaffFilterOptions(): Promise<StaffFilterOptions> {
  const data = await cachedGet<{ data: StaffFilterOptions }>(
    'staff:filter-options',
    '/api/v1/staff/filter-options',
    5 * 60_000,
  )
  return data.data
}

export async function fetchStaff(id: number): Promise<StaffShowResponse['data']> {
  const { data } = await api.get<StaffShowResponse>(`/api/v1/staff/${id}`)
  return data.data
}

export async function fetchBirthdays(range: BirthdayRange = 'today'): Promise<BirthdayListResponse> {
  const { data } = await api.get<BirthdayListResponse>('/api/v1/staff/birthdays', {
    params: { range },
  })
  return data
}

export async function fetchDataQuality(): Promise<DataQualityResponse['data']> {
  const { data } = await api.get<DataQualityResponse>('/api/v1/staff/data-quality')
  return data.data
}

export type SignatureScope = 'approvers' | 'current'
export type SignatureStatusFilter = 'all' | 'valid' | 'missing' | 'broken'

export interface SignatureManagerRow {
  staff_id: number
  SAPNO?: string | null
  full_name: string
  signature_text: string
  signature_status: 'valid' | 'missing' | 'broken'
  signature_status_label: string
  signature_url?: string | null
  photo_url?: string | null
  can_replace_without_override?: boolean
}

export interface SignatureManagerResponse {
  data: SignatureManagerRow[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    stats: { total: number; valid: number; missing: number; broken: number }
    approver_count: number
    approver_cache: { ok?: boolean; count?: number; updated_at?: string | null }
    filters: { staff_name: string; scope: SignatureScope; signature_status: SignatureStatusFilter }
  }
}

export async function fetchSignatureManager(params: {
  staff_name?: string
  scope?: SignatureScope
  signature_status?: SignatureStatusFilter
  page?: number
  per_page?: number
} = {}): Promise<SignatureManagerResponse> {
  const { data } = await api.get<SignatureManagerResponse>('/api/v1/staff/signatures', { params })
  return data
}

export async function refreshSignatureApprovers(): Promise<{
  approver_count: number
  approver_cache: SignatureManagerResponse['meta']['approver_cache']
  message?: string
}> {
  const { data } = await api.post<{
    data: { approver_count: number; approver_cache: SignatureManagerResponse['meta']['approver_cache'] }
    message?: string
  }>('/api/v1/staff/signatures/refresh-approvers')
  return { ...data.data, message: data.message }
}

export async function bulkSaveSignatures(
  signatures: Array<{ staff_id: number; signature_data_url: string; allow_override?: boolean }>,
): Promise<{ saved: number; skipped: number; failed: number }> {
  const { data } = await api.post<{ data: { saved: number; skipped: number; failed: number } }>(
    '/api/v1/staff/signatures/bulk',
    { signatures },
  )
  return data.data
}

export async function uploadStaffSignature(
  staffId: number,
  file: File,
  allowOverride = false,
): Promise<{ filename: string; signature_url: string }> {
  const form = new FormData()
  form.append('staff_id', String(staffId))
  form.append('signature', file)
  if (allowOverride) form.append('allow_override', '1')
  const { data } = await api.post<{ data: { filename: string; signature_url: string } }>(
    '/api/v1/staff/signatures/upload',
    form,
  )
  return data.data
}

export interface NextOfKinEntry {
  name: string
  relationship_id: number
  relationship_name: string
  phone: string
  email: string
}

export interface NextOfKinRow {
  staff_id: number
  SAPNO?: string | null
  title?: string | null
  fname?: string | null
  lname?: string | null
  oname?: string | null
  full_name: string
  photo?: string | null
  photo_url?: string | null
  work_email?: string | null
  tel_1?: string | null
  tel_2?: string | null
  whatsapp?: string | null
  private_email?: string | null
  physical_location?: string | null
  residential_address_duty_station?: string | null
  number_of_dependants?: number | string | null
  job_name?: string | null
  duty_station_name?: string | null
  division_name?: string | null
  contract_status_label?: string | null
  grade?: string | null
  next_of_kin: NextOfKinEntry[]
}

export interface NextOfKinResponse {
  data: NextOfKinRow[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    kin_relationships: Array<{ id: number; name: string }>
  }
}

export async function fetchNextOfKinReport(
  params: {
    page?: number
    per_page?: number
  } & StaffListFilters = {},
): Promise<NextOfKinResponse> {
  const { data } = await api.get<NextOfKinResponse>('/api/v1/staff/next-of-kin', {
    params,
    paramsSerializer: { indexes: null },
  })
  return data
}

export async function fetchStaffFormLookups(): Promise<StaffFormLookups> {
  const data = await cachedGet<{ data: StaffFormLookups }>(
    'staff:form-lookups-v2',
    '/api/v1/staff/form-lookups',
    5 * 60_000,
  )
  return data.data
}

/** Call after staff/settings mutations that change dropdown options. */
export function invalidateStaffFormLookupsCache(): void {
  clearApiCache('staff:form-lookups-v2')
}

function appendContractFields(form: FormData, payload: StaffContractPayload | StaffCreatePayload): void {
  for (const [key, value] of Object.entries(payload)) {
    if (key === 'other_associated_divisions') {
      const ids = Array.isArray(value) ? value : []
      ids.forEach((id) => form.append('other_associated_divisions[]', String(id)))
      continue
    }
    if (value === undefined || value === null) continue
    form.append(key, String(value))
  }
}

export async function createStaff(
  payload: StaffCreatePayload,
  contractFile?: File | null,
): Promise<{ staff_id: number; contract_id: number }> {
  if (contractFile) {
    const form = new FormData()
    appendContractFields(form, payload)
    form.append('contract_file', contractFile)
    const { data } = await api.post<{ data: { staff_id: number; contract_id: number } }>('/api/v1/staff', form)
    return data.data
  }
  const { data } = await api.post<{ data: { staff_id: number; contract_id: number } }>('/api/v1/staff', payload)
  return data.data
}

export async function createContract(
  staffId: number,
  payload: StaffContractPayload,
  contractFile?: File | null,
): Promise<{ contract_id: number }> {
  if (contractFile) {
    const form = new FormData()
    appendContractFields(form, payload)
    form.append('contract_file', contractFile)
    const { data } = await api.post<{ data: { contract_id: number } }>(
      `/api/v1/staff/${staffId}/contracts`,
      form,
    )
    return data.data
  }
  const { data } = await api.post<{ data: { contract_id: number } }>(`/api/v1/staff/${staffId}/contracts`, payload)
  return data.data
}

export async function updateContract(
  staffId: number,
  contractId: number,
  payload: StaffContractPayload,
  contractFile?: File | null,
): Promise<{ contract_id: number }> {
  if (contractFile) {
    const form = new FormData()
    appendContractFields(form, payload)
    form.append('contract_file', contractFile)
    // POST alias — PHP ignores multipart bodies on PUT.
    const { data } = await api.post<{ data: { contract_id: number } }>(
      `/api/v1/staff/${staffId}/contracts/${contractId}`,
      form,
    )
    return data.data
  }
  const { data } = await api.put<{ data: { contract_id: number } }>(
    `/api/v1/staff/${staffId}/contracts/${contractId}`,
    payload,
  )
  return data.data
}

export interface StaffBiodataPayload {
  SAPNO?: string
  title: string
  fname: string
  lname: string
  oname?: string
  date_of_birth: string
  gender: string
  nationality_id: number | ''
  initiation_date: string
  tel_1: string
  tel_2?: string
  whatsapp?: string
  work_email: string
  private_email?: string
  physical_location?: string
}

export async function updateStaffBiodata(
  staffId: number,
  payload: StaffBiodataPayload,
): Promise<Record<string, unknown>> {
  const { data } = await api.put<{ data: { staff: Record<string, unknown> }; message?: string }>(
    `/api/v1/staff/${staffId}`,
    payload,
  )
  return data.data.staff
}

export interface StaffAuditChangeRow {
  field: string
  old: string
  new: string
  type: 'added' | 'removed' | 'changed' | string
}

export interface StaffAuditTrailRow {
  id: number
  user_id: number
  actor_name: string
  actor_email?: string | null
  created_at?: string | null
  event_type?: string | null
  target_table?: string | null
  target_id?: string | null
  target_label?: string | null
  http_method?: string | null
  request_uri?: string | null
  old_values?: Record<string, unknown> | null
  new_values?: Record<string, unknown> | null
  changes: StaffAuditChangeRow[]
}

export async function fetchStaffAuditTrail(
  staffId: number,
  limit = 100,
): Promise<{ data: StaffAuditTrailRow[]; meta: { structured_columns: boolean; limit: number } }> {
  const { data } = await api.get<{
    data: StaffAuditTrailRow[]
    meta: { structured_columns: boolean; limit: number }
  }>(`/api/v1/staff/${staffId}/audit-trail`, { params: { limit } })
  return data
}
