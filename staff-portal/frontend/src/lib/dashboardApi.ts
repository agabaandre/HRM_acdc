import { cachedGet } from './apiCache'

const SESSION_KEY = 'staff-portal:dashboard:last'

export interface DashboardFilterOption {
  division_id?: number
  division_name?: string
  duty_station_id?: number
  duty_station_name?: string
  funder_id?: number
  funder?: string
  job_id?: number
  job_name?: string
}

export interface DashboardChartPair {
  [key: string]: string[] | number[]
}

export interface DashboardData {
  staff: number
  two_months: number
  staff_renewal: number
  expired: number
  data_points: Array<{ name: string | null; y: number }>
  staff_by_gender: Array<{ name: string | null; y: number }>
  staff_by_contract: { contract_type: string[]; value: number[] }
  staff_by_division: { division: string[]; value: number[] }
  staff_by_member_state: { member_states: string[]; value: number[] }
  staff_by_funder: { funder: string[]; value: number[] }
  birthdays?: Array<{
    id: number
    title: string
    start: string
    age?: number
    job_name?: string | null
    grade?: string | null
    division_name?: string | null
    duty_station_name?: string | null
  }>
}

export interface DashboardResponse {
  data: DashboardData
  meta: {
    filters: {
      divisions: Array<{ division_id: number; division_name: string }>
      duty_stations: Array<{ duty_station_id: number; duty_station_name: string }>
      funders: Array<{ funder_id: number; funder: string }>
      jobs: Array<{ job_id: number; job_name: string }>
    }
  }
}

export function readDashboardSession(): DashboardResponse | null {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY)
    if (!raw) return null
    return JSON.parse(raw) as DashboardResponse
  } catch {
    return null
  }
}

export function writeDashboardSession(payload: DashboardResponse): void {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(payload))
  } catch {
    /* quota / private mode */
  }
}

export async function fetchDashboard(params: {
  division_id?: number | null
  duty_station_id?: number | null
  funder_id?: number | null
  job_id?: number | null
} = {}): Promise<DashboardResponse> {
  const query = {
    division_id: params.division_id || undefined,
    duty_station_id: params.duty_station_id || undefined,
    funder_id: params.funder_id || undefined,
    job_id: params.job_id || undefined,
  }

  const res = await cachedGet<DashboardResponse>(
    'dashboard:snapshot',
    '/api/v1/dashboard',
    90_000,
    query,
  )
  writeDashboardSession(res)
  return res
}

export async function fetchDashboardJobs(): Promise<Array<{ job_id: number; job_name: string }>> {
  const res = await cachedGet<{ data: Array<{ job_id: number; job_name: string }> }>(
    'dashboard:filter-jobs',
    '/api/v1/dashboard/filter-jobs',
    300_000,
  )
  return res.data || []
}
