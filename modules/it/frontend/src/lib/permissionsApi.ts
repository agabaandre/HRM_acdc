import { api } from './api'
import { cachedGet, clearApiCache } from './apiCache'

export type PermItem = { id: number; name: string; definition: string }

export type PermissionsCatalog = {
  permissions: Array<{ id: number; name: string; definition: string; module?: string }>
  categories: Record<string, PermItem[]>
}

export type PermissionGroup = { id: number; group_name: string; user_count: number }

export type PermissionsBootstrap = {
  catalog: PermissionsCatalog
  groups: PermissionGroup[]
  selected_group_id: number | null
  permission_ids: number[]
}

export async function fetchPermissionsBootstrap(groupId?: number | null): Promise<PermissionsBootstrap> {
  const { data } = await api.get<{ data: PermissionsBootstrap }>('/api/v1/permissions/bootstrap', {
    params: groupId ? { group_id: groupId } : undefined,
  })
  return data.data
}

export async function fetchPermissionsCatalog(): Promise<PermissionsCatalog> {
  const data = await cachedGet<{ data: PermissionsCatalog }>(
    'permissions:catalog',
    '/api/v1/permissions/catalog',
    5 * 60_000,
  )
  return data.data
}

export async function fetchPermissionGroups(): Promise<PermissionGroup[]> {
  const { data } = await api.get<{ data: PermissionGroup[] }>('/api/v1/permissions/groups')
  return data.data
}

export async function fetchGroupAssignments(id: number): Promise<number[]> {
  const { data } = await api.get(`/api/v1/permissions/groups/${id}/assignments`)
  return data.data.permission_ids as number[]
}

export async function saveGroupAssignments(id: number, permission_ids: number[]) {
  await api.put(`/api/v1/permissions/groups/${id}/assignments`, { permission_ids })
}

export async function createPermissionGroup(group_name: string) {
  await api.post('/api/v1/permissions/groups', { group_name })
}

export async function fetchPermissionUsers(params: {
  q?: string
  group_id?: number | null
  page?: number
  per_page?: number
}) {
  const { data } = await api.get('/api/v1/permissions/users', { params })
  return data as {
    data: Array<{
      user_id: number
      name: string
      role: number
      group_name?: string
      custom_permission_count: number
    }>
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }
}

export async function fetchUserAssignments(id: number) {
  const { data } = await api.get(`/api/v1/permissions/users/${id}/assignments`)
  return data.data as {
    user: { user_id: number; name: string; role: number; group_name?: string }
    permission_ids: number[]
    group_permission_count: number
  }
}

export async function saveUserAssignments(id: number, permission_ids: number[]) {
  await api.put(`/api/v1/permissions/users/${id}/assignments`, { permission_ids })
}

export async function copyGroupPermissionsToUser(id: number) {
  const { data } = await api.post(`/api/v1/permissions/users/${id}/copy-group`)
  return data.data.permission_ids as number[]
}

export async function createPermissionDefinition(name: string, definition: string) {
  await api.post('/api/v1/permissions/definitions', { name, definition })
  clearApiCache('permissions:catalog')
}
