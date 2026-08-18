import { api } from './api'
import { cachedGet, clearApiCache } from './apiCache'

export interface SettingsHubCard {
  to: string
  label: string
  icon: string
  special?: boolean
}

export type PortalModuleRow = {
  key: string
  label: string
  description: string
  enabled: boolean
  locked?: boolean
}

export async function fetchPortalModules() {
  const { data } = await api.get<{
    data: { modules: PortalModuleRow[]; enabled: Record<string, boolean> }
  }>('/api/v1/settings/portal-modules')
  return data.data
}

export async function savePortalModules(modules: Record<string, boolean>) {
  const { data } = await api.put<{
    data: { modules: PortalModuleRow[]; enabled: Record<string, boolean> }
  }>('/api/v1/settings/portal-modules', { modules })
  invalidateSettingsCaches()
  return data.data
}

export type EmailDriverField = {
  key: string
  label: string
  type: 'text' | 'number' | 'password' | 'select' | 'textarea'
  required?: boolean
  secret?: boolean
  default?: string
  placeholder?: string
  options?: string[]
}

export type EmailDriverDef = {
  key: string
  label: string
  category: string
  description: string
  fields: EmailDriverField[]
}

export type EmailProvider = {
  id: number
  uuid: string
  name: string
  slug: string
  driver: string
  config: Record<string, string>
  from_address: string
  from_name: string
  description?: string | null
  is_default: boolean
  is_active: boolean
}

export async function fetchEmailDrivers() {
  const { data } = await api.get<{ data: EmailDriverDef[] }>('/api/v1/settings/email-servers/drivers')
  return data.data
}

export async function fetchEmailProviders() {
  const { data } = await api.get<{ data: EmailProvider[] }>('/api/v1/settings/email-servers')
  return data.data
}

export async function createEmailProvider(payload: Partial<EmailProvider> & { name: string; driver: string }) {
  const { data } = await api.post<{ data: EmailProvider }>('/api/v1/settings/email-servers', payload)
  invalidateSettingsCaches()
  return data.data
}

export async function updateEmailProvider(uuid: string, payload: Partial<EmailProvider>) {
  const { data } = await api.put<{ data: EmailProvider }>(`/api/v1/settings/email-servers/${uuid}`, payload)
  invalidateSettingsCaches()
  return data.data
}

export async function deleteEmailProvider(uuid: string) {
  await api.delete(`/api/v1/settings/email-servers/${uuid}`)
  invalidateSettingsCaches()
}

export async function setDefaultEmailProvider(uuid: string) {
  const { data } = await api.post<{ data: EmailProvider }>(`/api/v1/settings/email-servers/${uuid}/default`)
  invalidateSettingsCaches()
  return data.data
}

export async function testEmailProvider(uuid: string, to: string) {
  const { data } = await api.post<{ message: string }>(`/api/v1/settings/email-servers/${uuid}/test`, { to })
  return data.message
}

export interface LookupColumnMeta {
  label: string
  required?: boolean
  type?: 'text' | 'number' | 'checkbox' | 'select' | 'textarea'
  options?: Record<string, string>
}

export async function fetchSettingsHub(): Promise<SettingsHubCard[]> {
  const data = await cachedGet<{ data: SettingsHubCard[] }>(
    'settings:hub-v3',
    '/api/v1/settings/hub',
    5 * 60_000,
  )
  return data.data
}

export function invalidateSettingsCaches(): void {
  clearApiCache('settings:')
  clearApiCache('staff:form-lookups')
  clearApiCache('cbp:modules')
}

export async function fetchOrgStaffOptions(q = '') {
  const { data } = await api.get<{
    data: Array<{ staff_id: number; name: string; email?: string | null }>
  }>('/api/v1/settings/staff-options', { params: q ? { q } : {} })
  return data.data
}

export interface DivisionRow {
  division_id: number
  division_name: string
  division_short_name?: string | null
  category?: string | null
  is_active: boolean
  directorate_id?: number | null
  directorate_name?: string | null
  division_head?: number | null
  division_head_name?: string | null
  focal_person?: number | null
  focal_person_name?: string | null
  finance_officer?: number | null
  finance_officer_name?: string | null
  admin_assistant?: number | null
  admin_assistant_name?: string | null
  director_id?: number | null
  director_name?: string | null
  head_oic_id?: number | null
  head_oic_name?: string | null
  head_oic_start_date?: string | null
  head_oic_end_date?: string | null
  director_oic_id?: number | null
  director_oic_name?: string | null
  director_oic_start_date?: string | null
  director_oic_end_date?: string | null
}

export interface DivisionFormPayload {
  division_name: string
  division_short_name?: string | null
  category: string
  division_head: number
  focal_person: number
  finance_officer: number
  admin_assistant: number
  director_id?: number | null
  head_oic_id?: number | null
  head_oic_start_date?: string | null
  head_oic_end_date?: string | null
  director_oic_id?: number | null
  director_oic_start_date?: string | null
  director_oic_end_date?: string | null
  directorate_id?: number | null
  is_active?: boolean
}

export async function fetchDivisionsSettings(params: { q?: string; page?: number; per_page?: number } = {}) {
  const { data } = await api.get<{
    data: DivisionRow[]
    meta: {
      current_page: number
      last_page: number
      per_page: number
      total: number
      directorates: Array<{ id: number; name: string; director_id?: number | null }>
      categories: string[]
    }
  }>('/api/v1/settings/divisions', { params })
  return data
}

export async function createDivision(payload: DivisionFormPayload) {
  const { data } = await api.post<{ message: string }>('/api/v1/settings/divisions', payload)
  invalidateSettingsCaches()
  return data.message
}

export async function updateDivision(id: number, payload: DivisionFormPayload) {
  const { data } = await api.put<{ message: string }>(`/api/v1/settings/divisions/${id}`, payload)
  invalidateSettingsCaches()
  return data.message
}

export async function deleteDivision(id: number) {
  const { data } = await api.delete<{ message: string }>(`/api/v1/settings/divisions/${id}`)
  invalidateSettingsCaches()
  return data.message
}

export interface DirectorateRow {
  id: number
  name: string
  is_active: boolean
  director_id?: number | null
  director_name?: string | null
  created_at?: string | null
}

export async function fetchDirectoratesSettings(params: { q?: string; page?: number; per_page?: number } = {}) {
  const { data } = await api.get<{
    data: DirectorateRow[]
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }>('/api/v1/settings/directorates', { params })
  return data
}

export async function createDirectorate(payload: {
  name: string
  is_active: boolean
  director_id?: number | null
}) {
  const { data } = await api.post<{ message: string }>('/api/v1/settings/directorates', payload)
  invalidateSettingsCaches()
  return data.message
}

export async function updateDirectorate(
  id: number,
  payload: { name: string; is_active: boolean; director_id?: number | null },
) {
  const { data } = await api.put<{ message: string }>(`/api/v1/settings/directorates/${id}`, payload)
  invalidateSettingsCaches()
  return data.message
}

export async function deleteDirectorate(id: number) {
  const { data } = await api.delete<{ message: string }>(`/api/v1/settings/directorates/${id}`)
  invalidateSettingsCaches()
  return data.message
}

export type CbpModuleAdminRow = {
  id: number
  module_key: string
  system_name: string
  description: string
  base_url: string
  base_url_development: string
  base_url_production: string
  icon_class: string
  permission_code: string
  uses_staff_portal_token: boolean
  is_production: boolean
  is_enabled: boolean
  show_in_apm_menu: boolean
  alternate_base_url: string
  alternate_for_role_id: number | null
  target_resolver: string
  sort_order: number
}

export type CbpModuleFormPayload = {
  module_key?: string
  system_name: string
  description?: string | null
  base_url?: string
  base_url_development?: string | null
  base_url_production?: string | null
  icon_class?: string
  permission_code?: string
  uses_staff_portal_token?: boolean
  is_production?: boolean
  is_enabled?: boolean
  show_in_apm_menu?: boolean
  alternate_base_url?: string | null
  alternate_for_role_id?: number | null
  target_resolver: string
  sort_order?: number
}

export async function fetchCbpModulesSettings() {
  const { data } = await api.get<{
    data: CbpModuleAdminRow[]
    meta: {
      table_exists: boolean
      next_sort_order: number
      next_permission_id_hint: number
      icon_options: Record<string, string>
      resolver_options: Record<string, string>
      auto_assign_group_id: number
    }
  }>('/api/v1/settings/cbp-modules')
  return data
}

export async function createCbpModule(payload: CbpModuleFormPayload) {
  const { data } = await api.post<{ message: string }>('/api/v1/settings/cbp-modules', payload)
  invalidateSettingsCaches()
  return data.message
}

export async function updateCbpModule(id: number, payload: CbpModuleFormPayload) {
  const { data } = await api.put<{ message: string }>(`/api/v1/settings/cbp-modules/${id}`, payload)
  invalidateSettingsCaches()
  return data.message
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
  invalidateSettingsCaches()
}

export async function updateLookupRow(table: string, id: string | number, payload: Record<string, unknown>) {
  await api.put(`/api/v1/settings/lookups/${table}/${id}`, payload)
  invalidateSettingsCaches()
}

export async function deleteLookupRow(table: string, id: string | number) {
  await api.delete(`/api/v1/settings/lookups/${table}/${id}`)
  invalidateSettingsCaches()
}

export async function fetchPerformanceSettings() {
  const { data } = await api.get('/api/v1/settings/performance')
  return data.data as {
    settings: Record<string, boolean | number | null>
    workflow_preview: Record<string, string[]>
    window_statuses: Record<
      string,
      { open: boolean; label: string; message: string; opens_on?: string | null; closes_on?: string | null }
    >
    month_options: Record<number, string>
    current_month_label: string
    current_year?: number
    help?: Record<string, string>
  }
}

export async function savePerformanceSettings(payload: Record<string, unknown>) {
  await api.put('/api/v1/settings/performance', payload)
}

export interface OrgStructurePerson {
  staff_id: number
  name: string
  work_email?: string | null
  photo_url?: string | null
  grade?: string | null
  match_status?: string | null
}

export interface OrgStructureNode {
  id: number
  parent_id?: number | null
  node_type: string
  title: string
  job_id?: number | null
  grade_code?: string | null
  grade_band?: string | null
  division_id?: number | null
  directorate_id?: number | null
  approved_slots: number
  filled_slots: number
  vacant_slots: number
  sort_order: number
  source?: string | null
  tier?: string | null
  notes?: string | null
  filled_by: OrgStructurePerson[]
  children: OrgStructureNode[]
}

export async function fetchOrgStructure(): Promise<{
  tree: OrgStructureNode[]
  meta: { ready: boolean; message?: string; totals: Record<string, number> }
}> {
  const { data } = await api.get<{
    data: {
      tree: OrgStructureNode[]
      meta: { ready: boolean; message?: string; totals: Record<string, number> }
    }
  }>('/api/v1/settings/org-structure')
  return data.data
}

export async function generateOrgStructure(replace = true): Promise<{
  message: string
  data: { created_nodes: number; created_assignments: number; message: string }
  tree: { tree: OrgStructureNode[]; meta: { ready: boolean; totals: Record<string, number> } }
}> {
  const { data } = await api.post('/api/v1/settings/org-structure/generate', { replace })
  clearApiCache('settings:hub')
  return data
}

export async function updateOrgStructureNode(
  id: number,
  payload: {
    title?: string
    parent_id?: number | null
    approved_slots?: number
    sort_order?: number
    notes?: string | null
  },
): Promise<{
  message: string
  data: Record<string, unknown>
  tree: { tree: OrgStructureNode[]; meta: { ready: boolean; totals: Record<string, number> } }
}> {
  const { data } = await api.put(`/api/v1/settings/org-structure/nodes/${id}`, payload)
  return data
}

export type SharedStorageModule = {
  key: string
  label: string
  legacy_path: string
  host_path: string
  legacy_files: number
  legacy_bytes: number
  host_files: number
  host_bytes: number
  legacy_is_symlink: boolean
  needs_migration: boolean
  can_purge_legacy: boolean
  env_var: string
  migrate_script: string
}

export type SharedStorageStatus = {
  using_host_storage: boolean
  site_id: string
  data_root: string
  repo_root: string
  recommended: Record<string, string>
  modules: SharedStorageModule[]
  scripts_dir: string
  docs: string
}

export async function fetchSharedStorage(): Promise<SharedStorageStatus> {
  const { data } = await api.get<{ data: SharedStorageStatus }>('/api/v1/settings/shared-storage')
  return data.data
}

export async function migrateSharedStorage(module: string) {
  const { data } = await api.post<{
    message: string
    data: { result: { status: string; message: string; output: string }; status: SharedStorageStatus }
  }>('/api/v1/settings/shared-storage/migrate', { module })
  return data
}

export async function enableHostSharedStorage() {
  const { data } = await api.post<{ message: string; data: SharedStorageStatus }>(
    '/api/v1/settings/shared-storage/enable-host',
  )
  return data
}

export async function purgeCiSharedStorage(dryRun = false) {
  const { data } = await api.post<{
    message: string
    data: { result: { status: string; message: string; output: string }; status: SharedStorageStatus }
  }>('/api/v1/settings/shared-storage/purge-ci', {
    confirm: 'DELETE_CI_UPLOADS',
    dry_run: dryRun,
  })
  return data
}
