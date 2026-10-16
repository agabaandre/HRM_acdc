import { api } from './api'

export interface AdHubLink {
  to: string
  label: string
  description?: string
  icon?: string
  count?: number | null
}

export interface AdHubSummary {
  to_disable: number
  disabled: number
}

export interface AdHubData {
  summary: AdHubSummary
  links: AdHubLink[]
}

export interface AdAccountRow {
  staff_id: number
  fname?: string | null
  lname?: string | null
  work_email?: string | null
  email_status?: number | null
  email_disabled_at?: string | null
  email_disabled_by?: string | null
  division_name?: string | null
  status_id?: number | null
  [key: string]: unknown
}

export interface AdListResponse {
  data: AdAccountRow[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

export async function fetchAdHub(): Promise<AdHubData> {
  const { data } = await api.get<{ data: AdHubData }>('/api/v1/admanager/hub')
  return {
    summary: data.data.summary ?? { to_disable: 0, disabled: 0 },
    links: data.data.links || [],
  }
}

export async function fetchExpired(params: {
  q?: string
  page?: number
  per_page?: number
} = {}): Promise<AdListResponse> {
  const { data } = await api.get<AdListResponse>('/api/v1/admanager/expired', { params })
  return data
}

export async function fetchDisabled(params: {
  q?: string
  page?: number
  per_page?: number
} = {}): Promise<AdListResponse> {
  const { data } = await api.get<AdListResponse>('/api/v1/admanager/disabled', { params })
  return data
}

export async function disableAdAccount(staffId: number): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>(`/api/v1/admanager/${staffId}/disable`)
  return data
}

export async function enableAdAccount(staffId: number): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>(`/api/v1/admanager/${staffId}/enable`)
  return data
}
