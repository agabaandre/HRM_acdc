import { api } from './api'

export interface WorkplanRow {
  id: number
  division_id?: number | null
  division_name?: string | null
  division_short_name?: string | null
  pra_division_code?: string | null
  pra_indicator_id?: number | null
  year?: number | string | null
  activity_name?: string | null
  broad_activity?: string | null
  intermediate_outcome?: string | null
  output_indicator?: string | null
  cumulative_target?: string | number | null
  has_budget?: number | boolean | null
  sub_activity_count?: number
  [key: string]: unknown
}

export interface WorkplanDivisionOption {
  division_id: number
  division_name: string
  division_short_name?: string | null
}

export interface WorkplanListResponse {
  data: WorkplanRow[]
  meta: {
    source?: string
    message?: string
    pra_configured?: boolean
    divisions: WorkplanDivisionOption[]
  }
}

export interface WorkplanShowResponse {
  data: {
    plan: Record<string, unknown>
    sub_activities: Array<Record<string, unknown>>
    weekly_tasks: Array<Record<string, unknown>>
  }
  meta?: {
    ingested_from?: string
  }
}

export async function fetchWorkplans(params: {
  q?: string
  division_id?: number | null
  year?: number | null
} = {}): Promise<WorkplanListResponse> {
  const { data } = await api.get<WorkplanListResponse>('/api/v1/workplans', {
    params: {
      q: params.q || undefined,
      division_id: params.division_id || undefined,
      year: params.year || undefined,
    },
  })
  return data
}

export async function fetchWorkplan(id: number): Promise<{
  plan: Record<string, unknown>
  sub_activities: Array<Record<string, unknown>>
  weekly_tasks: Array<Record<string, unknown>>
  ingested_from?: string
}> {
  const { data } = await api.get<WorkplanShowResponse>(`/api/v1/workplans/${id}`)
  return {
    ...data.data,
    weekly_tasks: data.data.weekly_tasks || [],
    ingested_from: data.meta?.ingested_from,
  }
}

export async function syncPraWorkplan(params: {
  year?: number | null
  division?: string | null
} = {}): Promise<{ message: string; data: Record<string, unknown> }> {
  const { data } = await api.post<{ message: string; data: Record<string, unknown> }>(
    '/api/v1/workplans/sync-pra',
    {
      year: params.year || undefined,
      division: params.division || undefined,
    },
  )
  return data
}
