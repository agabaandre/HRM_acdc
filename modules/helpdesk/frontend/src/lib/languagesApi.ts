import { api } from './api'

export interface PortalLanguageOption {
  code: string
  name: string
  flag: string
  google_code?: string
  is_rtl?: boolean
}

export interface PortalLocaleCatalog {
  locale: string
  direction: 'ltr' | 'rtl'
  is_rtl: boolean
  languages: PortalLanguageOption[]
  translations: Record<string, Record<string, string>>
}

export async function fetchLocaleCatalog(): Promise<PortalLocaleCatalog> {
  const { data } = await api.get<{ data: PortalLocaleCatalog }>('/api/v1/languages')
  return data.data
}

export async function applyLocale(locale: string): Promise<PortalLocaleCatalog> {
  const { data } = await api.post<{ data: PortalLocaleCatalog }>('/api/v1/locale', { locale })
  return data.data
}
