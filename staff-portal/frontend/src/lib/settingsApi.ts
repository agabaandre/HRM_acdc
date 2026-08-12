import { api } from './api'

export interface SettingsHubCard {
  to: string
  label: string
  icon: string
  special?: boolean
}

export interface LookupColumnMeta {
  label: string
  required?: boolean
  type?: 'text' | 'number' | 'checkbox' | 'select'
  options?: Record<string, string>
}

export async function fetchSettingsHub(): Promise<SettingsHubCard[]> {
  const { data } = await api.get<{ data: SettingsHubCard[] }>('/api/v1/settings/hub')
  return data.data
}

export async function fetchLookupRows(
  table: string,
  params: { q?: string; page?: number; per_page?: number } = {},
) {
  const { data } = await api.get(`/api/v1/settings/lookups/${table}`, { params })
  return data as {
    data: Record<string, unknown>[]
    meta: {
      read_only: boolean
      label: string
      pk: string
      columns?: Record<string, LookupColumnMeta>
      current_page?: number
      last_page?: number
      per_page?: number
      total?: number
    }
  }
}

export async function createLookupRow(table: string, payload: Record<string, unknown>) {
  await api.post(`/api/v1/settings/lookups/${table}`, payload)
}

export async function updateLookupRow(table: string, id: string | number, payload: Record<string, unknown>) {
  await api.put(`/api/v1/settings/lookups/${table}/${id}`, payload)
}

export async function deleteLookupRow(table: string, id: string | number) {
  await api.delete(`/api/v1/settings/lookups/${table}/${id}`)
}

export async function fetchPerformanceSettings() {
  const { data } = await api.get('/api/v1/settings/performance')
  return data.data as {
    settings: Record<string, boolean | number | null>
    workflow_preview: Record<string, string[]>
    window_statuses: Record<string, { open: boolean; label: string; message: string }>
    month_options: Record<number, string>
    current_month_label: string
  }
}

export async function savePerformanceSettings(payload: Record<string, unknown>) {
  await api.put('/api/v1/settings/performance', payload)
}
