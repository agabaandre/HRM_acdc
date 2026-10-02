import { api } from './api'

export type RiskRow = {
  id: number
  name: string
  division_id: number | null
  division_name?: string | null
  directorate_id: number | null
  enterprise_theme_id: number | null
  risk_type_id: number | null
  status_id: number | null
  inherent_likelihood: number | null
  inherent_impact: number | null
  inherent_score: number | null
  inherent_rating: string | null
  residual_score: number | null
  residual_rating: string | null
  risk_movement: string | null
  workflow_state: string
  consequence?: string | null
  root_causes?: string | null
  mitigation?: string | null
  management_response?: string | null
  timeline?: string | null
  action_update?: string | null
  date_of_update?: string | null
  oio_verification_notes?: string | null
  mitigation_effectiveness_id?: number | null
  enterprise_theme_name?: string | null
  risk_type_name?: string | null
  status_name?: string | null
  mitigation_effectiveness_name?: string | null
  division_name?: string | null
  inherent_fill_color?: string | null
  inherent_text_color?: string | null
  residual_fill_color?: string | null
  residual_text_color?: string | null
  source_business_unit?: string | null
  unmapped_business_unit?: string | null
}

export type RiskLookups = {
  likelihoods: Array<{ id: number; label: string; score: number }>
  impacts: Array<{ id: number; label: string; score: number }>
  risk_types: Array<{ id: number; name: string }>
  enterprise_themes: Array<{ id: number; name: string }>
  statuses: Array<{ id: number; name: string }>
  mitigation_effectiveness: Array<{ id: number; name: string; likelihood_reduction: number; is_assessed: boolean }>
  rating_bands: Array<{
    id?: number
    rating: string
    band_key: string
    min_score: number
    max_score: number
    fill_color: string
    text_color: string
    sort_order?: number
  }>
  active_rating_key_version?: number
}

export type RiskListRow = RiskRow & {
  counter?: number
  risk_type_name?: string | null
  enterprise_theme_name?: string | null
  division_name?: string | null
  directorate_name?: string | null
  likelihood_score?: number | null
  likelihood_label?: string | null
  impact_score?: number | null
  impact_label?: string | null
  inherent_rating_key?: string | null
  residual_rating_key?: string | null
  rating_key_version?: number | null
  inherent_fill_color?: string | null
  inherent_text_color?: string | null
  residual_fill_color?: string | null
  residual_text_color?: string | null
  review_year?: number | null
  review_quarter?: number | null
}

export type RiskListMeta = {
  division_page: number
  division_pages: number
  total_divisions: number
  division_id: number | null
  division_name: string | null
  total_rows: number
  show_all?: boolean
  filters: {
    year: number | null
    quarter: number | null
    division_id: number | null
    directorate_id: number | null
    enterprise_theme_id: number | null
    show_all?: boolean
  }
  divisions: Array<{ division_id: number | null; division_name: string }>
  directorates: Array<{ directorate_id: number; directorate_name: string }>
}

export type RiskListFilters = {
  division_id?: number | null
  directorate_id?: number | null
  enterprise_theme_id?: number | null
  year?: number | null
  quarter?: number | null
  division_page?: number
  show_all?: boolean
}

export async function fetchRisks(filters: RiskListFilters = {}): Promise<{ data: RiskListRow[]; meta: RiskListMeta }> {
  const params: Record<string, number | 1> = {}
  if (filters.division_id != null && filters.division_id > 0) params.division_id = filters.division_id
  if (filters.directorate_id != null && filters.directorate_id > 0) params.directorate_id = filters.directorate_id
  if (filters.enterprise_theme_id != null && filters.enterprise_theme_id > 0) {
    params.enterprise_theme_id = filters.enterprise_theme_id
  }
  if (filters.year != null && filters.year > 0) params.year = filters.year
  if (filters.quarter != null && filters.quarter >= 1 && filters.quarter <= 4) params.quarter = filters.quarter
  if (filters.division_page != null && filters.division_page > 0) params.division_page = filters.division_page
  if (filters.show_all) params.show_all = 1

  const { data } = await api.get<{ data: RiskListRow[]; meta: RiskListMeta }>('/api/v1/risks', { params })
  return { data: data.data, meta: data.meta }
}

export async function fetchRisk(id: number) {
  const { data } = await api.get<{
    data: RiskRow
    owners: Array<{
      staff_id: number
      is_default_hod: boolean
      staff_name?: string | null
      job_title?: string | null
      staff_label?: string | null
    }>
    audit: Array<{
      id: number
      action: string
      actor_staff_id: number | null
      before_json: string | null
      after_json: string | null
      created_at: string
    }>
  }>(`/api/v1/risks/${id}`)
  return data
}

export async function createRisk(payload: Record<string, unknown>) {
  const { data } = await api.post<{ data: RiskRow }>('/api/v1/risks', payload)
  return data.data
}

export async function updateRisk(id: number, payload: Record<string, unknown>) {
  const { data } = await api.put<{ data: RiskRow }>(`/api/v1/risks/${id}`, payload)
  return data.data
}

export async function fetchLookups(): Promise<RiskLookups> {
  const { data } = await api.get<RiskLookups>('/api/v1/lookups')
  return data
}
