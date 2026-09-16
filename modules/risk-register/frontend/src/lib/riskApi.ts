import { api } from './api'

export type RiskRow = {
  id: number
  name: string
  division_id: number | null
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
  rating_bands: Array<{ id: number; rating: string; min_score: number; max_score: number }>
}

export async function fetchRisks(divisionId?: number | null): Promise<RiskRow[]> {
  const params: Record<string, number> = {}
  if (divisionId != null && divisionId > 0) params.division_id = divisionId
  const { data } = await api.get<{ data: RiskRow[] }>('/api/v1/risks', { params })
  return data.data
}

export async function fetchRisk(id: number) {
  const { data } = await api.get<{
    data: RiskRow
    owners: Array<{ staff_id: number; is_default_hod: boolean }>
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
