import { api } from './api'

export interface CbpModuleLink {
  label: string
  href: string
  icon?: string
  is_active?: boolean
  opens_in_new_tab?: boolean
  module_key?: string | null
}

export interface CbpNavPayload {
  home: CbpModuleLink
  modules: CbpModuleLink[]
}

export async function fetchCbpModules(): Promise<CbpNavPayload> {
  const { data } = await api.get<CbpNavPayload>('/api/v1/cbp-modules')
  return data
}
