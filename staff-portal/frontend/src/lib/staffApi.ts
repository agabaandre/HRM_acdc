import { api } from './api'

export type StaffPreset = 'active' | 'due' | 'expired' | 'former' | 'renewal' | 'all'
export type StaffCategory = 'main_staff' | 'other_staff' | 'all'

export interface StaffListRow {
  staff_id: number
  SAPNO?: string | null
  fname?: string | null
  lname?: string | null
  photo?: string | null
  work_email?: string | null
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
    contracts: Array<Record<string, unknown>>
    can_manage: boolean
    can_manage_contracts: boolean
  }
}

export interface BirthdayRow {
  staff_id: number
  fname?: string | null
  lname?: string | null
  date_of_birth?: string | null
  work_email?: string | null
  division_name?: string | null
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

export async function fetchStaffList(params: {
  q?: string
  preset?: StaffPreset
  category?: StaffCategory
  page?: number
  per_page?: number
} = {}): Promise<StaffListResponse> {
  const { data } = await api.get<StaffListResponse>('/api/v1/staff', { params })
  return data
}

export async function fetchStaff(id: number): Promise<StaffShowResponse['data']> {
  const { data } = await api.get<StaffShowResponse>(`/api/v1/staff/${id}`)
  return data.data
}

export async function fetchBirthdays(): Promise<BirthdayRow[]> {
  const { data } = await api.get<{ data: BirthdayRow[] }>('/api/v1/staff/birthdays')
  return data.data
}

export async function fetchDataQuality(): Promise<DataQualityResponse['data']> {
  const { data } = await api.get<DataQualityResponse>('/api/v1/staff/data-quality')
  return data.data
}
