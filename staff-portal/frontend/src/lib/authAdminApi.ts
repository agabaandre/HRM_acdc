import { api } from './api'

export interface AuthUserRow {
  user_id: number
  name?: string | null
  status?: number | string | null
  role?: number | null
  auth_staff_id?: number | null
  group_name?: string | null
  work_email?: string | null
  staff_name?: string | null
  [key: string]: unknown
}

export interface AuditLogRow {
  id?: number
  log_id?: number
  user_id?: number | null
  user_name?: string | null
  user_email?: string | null
  action?: string | null
  activity?: string | null
  http_method?: string | null
  event_type?: string | null
  request_uri?: string | null
  ip_address?: string | null
  user_agent?: string | null
  target_table?: string | null
  target_id?: number | string | null
  old_values?: unknown
  new_values?: unknown
  reverted_at?: string | null
  reverted_by_user_id?: number | string | null
  created_at?: string | null
  [key: string]: unknown
}

export interface PaginatedMeta {
  current_page: number
  last_page: number
  total: number
  per_page?: number
  extended?: boolean
  message?: string
}

export interface FetchAuditLogsParams {
  q?: string
  search?: string
  name?: string
  email?: string
  http_method?: string
  event_type?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export async function fetchAuthUsers(params: {
  q?: string
  page?: number
  per_page?: number
} = {}): Promise<{ data: AuthUserRow[]; meta: PaginatedMeta }> {
  const { data } = await api.get<{ data: AuthUserRow[]; meta: PaginatedMeta }>('/api/v1/auth/users', {
    params,
  })
  return data
}

export async function fetchAuditLogs(params: FetchAuditLogsParams = {}): Promise<{ data: AuditLogRow[]; meta: PaginatedMeta }> {
  const { data } = await api.get<{ data: AuditLogRow[]; meta: PaginatedMeta }>(
    '/api/v1/auth/audit-logs',
    { params },
  )
  return data
}

export async function revertAuditLog(id: number | string): Promise<{ ok: boolean; message: string; [key: string]: unknown }> {
  const { data } = await api.post<{ ok: boolean; message: string; [key: string]: unknown }>(
    `/api/v1/auth/audit-logs/${id}/revert`,
  )
  return data
}
