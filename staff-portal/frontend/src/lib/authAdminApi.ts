import { api } from './api'

export interface AuthUserRow {
  user_id: number
  name?: string | null
  status?: number | string | null
  status_label?: string | null
  role?: number | null
  auth_staff_id?: number | null
  group_name?: string | null
  work_email?: string | null
  staff_name?: string | null
  allow_email_login?: number | null
  tel_1?: string | null
  tel_2?: string | null
  photo_url?: string | null
  sap_number?: string | null
  title?: string | null
  fname?: string | null
  lname?: string | null
  oname?: string | null
  created_at?: string | null
  [key: string]: unknown
}

export interface AuthUserGroup {
  id: number
  group_name: string
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
  can_revert?: boolean
  created_at?: string | null
  [key: string]: unknown
}

export interface OAuthClientRow {
  id: string
  name: string
  redirect_uris: string[]
  grant_types?: string[]
  public: boolean
  plain_secret?: string | null
  created_at?: string | null
  updated_at?: string | null
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

function normalizeRedirectUrisInput(value: string[] | string): string[] {
  if (Array.isArray(value)) {
    return value.map((item) => item.trim()).filter(Boolean)
  }

  return value
    .split(/[\r\n,]+/)
    .map((item) => item.trim())
    .filter(Boolean)
}

export async function fetchAuthUsers(params: {
  q?: string
  group_id?: number | ''
  status?: number | ''
  page?: number
  per_page?: number
} = {}): Promise<{ data: AuthUserRow[]; meta: PaginatedMeta }> {
  const { data } = await api.get<{ data: AuthUserRow[]; meta: PaginatedMeta }>('/api/v1/auth/users', {
    params,
  })
  return data
}

export async function fetchAuthUserGroups(): Promise<AuthUserGroup[]> {
  const { data } = await api.get<{ data: AuthUserGroup[] }>('/api/v1/auth/user-groups')
  return data.data
}

export async function updateAuthUser(
  id: number,
  payload: {
    name?: string
    role?: number
    status?: number
    allow_email_login?: number
  },
): Promise<AuthUserRow> {
  const { data } = await api.put<{ data: AuthUserRow }>(`/api/v1/auth/users/${id}`, payload)
  return data.data
}

export async function blockAuthUser(id: number): Promise<string> {
  const { data } = await api.post<{ message: string }>(`/api/v1/auth/users/${id}/block`)
  return data.message
}

export async function unblockAuthUser(id: number): Promise<string> {
  const { data } = await api.post<{ message: string }>(`/api/v1/auth/users/${id}/unblock`)
  return data.message
}

export async function resetAuthUserPassword(id: number): Promise<string> {
  const { data } = await api.post<{ message: string }>(`/api/v1/auth/users/${id}/reset-password`)
  return data.message
}

export async function setAuthUserAllowEmailLogin(id: number, allow: boolean): Promise<string> {
  const { data } = await api.post<{ message: string }>(`/api/v1/auth/users/${id}/allow-email-login`, {
    allow_email_login: allow ? 1 : 0,
  })
  return data.message
}

export async function bulkCreateAuthUsers(): Promise<{ created: number; message: string }> {
  const { data } = await api.post<{ created: number; message: string }>('/api/v1/auth/users/bulk-create')
  return data
}

export interface ImpersonationStatus {
  active: boolean
  user_name?: string | null
  user_id?: number | null
  started_at?: number | null
  expires_at?: number | null
  remaining_seconds?: number | null
  original_user_name?: string | null
}

export interface ImpersonationAuthPayload {
  message: string
  token: string
  user: {
    id: number
    name: string
    email: string
    avatar_url?: string | null
    profile?: Record<string, unknown> | null
    impersonation?: ImpersonationStatus
  }
  sso_token?: string
  impersonation?: ImpersonationStatus
}

export async function impersonateAuthUser(id: number): Promise<ImpersonationAuthPayload> {
  const { data } = await api.post<ImpersonationAuthPayload>(`/api/v1/auth/users/${id}/impersonate`)
  return data
}

export async function revertImpersonation(): Promise<ImpersonationAuthPayload> {
  const { data } = await api.post<ImpersonationAuthPayload>('/api/v1/auth/impersonation/revert')
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

export async function fetchOAuthClients(): Promise<{ data: OAuthClientRow[] }> {
  const { data } = await api.get<{ data: OAuthClientRow[] }>('/api/v1/auth/oauth-clients')
  return data
}

export async function createOAuthClient(payload: {
  name: string
  redirect_uris: string[] | string
  public: boolean
}): Promise<{ data: OAuthClientRow }> {
  const { data } = await api.post<{ data: OAuthClientRow }>('/api/v1/auth/oauth-clients', {
    ...payload,
    redirect_uris: normalizeRedirectUrisInput(payload.redirect_uris),
  })
  return data
}

export async function updateOAuthClient(
  id: string,
  payload: {
    name: string
    redirect_uris: string[] | string
  },
): Promise<{ message: string; data: OAuthClientRow }> {
  const { data } = await api.put<{ message: string; data: OAuthClientRow }>(
    `/api/v1/auth/oauth-clients/${id}`,
    {
      name: payload.name,
      redirect_uris: normalizeRedirectUrisInput(payload.redirect_uris),
    },
  )
  return data
}

export async function revokeOAuthClient(id: string): Promise<{ ok: boolean; message: string; [key: string]: unknown }> {
  const { data } = await api.delete<{ ok: boolean; message: string; [key: string]: unknown }>(
    `/api/v1/auth/oauth-clients/${id}`,
  )
  return data
}
