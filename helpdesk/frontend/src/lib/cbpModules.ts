import { api } from './api'

export interface CbpNavHome {
  id: string
  label: string
  description: string
  href: string
  is_active?: boolean
}

export interface CbpNavModule {
  id: string
  label: string
  description: string
  href: string
  icon?: string
  opens_in_new_tab?: boolean
  is_active?: boolean
}

export interface CbpNavPayload {
  home: CbpNavHome
  modules: CbpNavModule[]
}

export async function fetchCbpModules(): Promise<CbpNavPayload> {
  const { data } = await api.get<{ data: CbpNavPayload }>('/api/v1/cbp-modules')
  return data.data
}
