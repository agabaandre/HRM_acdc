import { api } from './api'

export interface PortalLanguageOption {
  code: string
  name: string
  flag: string
  google_code?: string
  is_rtl?: boolean
}

export interface PortalLanguageAdminRow {
  id: number
  locale_code: string
  name: string
  google_translate_code?: string | null
  flag_emoji?: string | null
  sort_order: number
  is_active: boolean
}

export interface PortalLocaleCatalog {
  locale: string
  direction: 'ltr' | 'rtl'
  is_rtl: boolean
  languages: PortalLanguageOption[]
  groups: Record<string, string>
  translations: Record<string, Record<string, string>>
}

export interface PortalTranslationGrid {
  locales: string[]
  locale_labels: Record<string, { name: string; flag: string }>
  groups: Record<string, string>
  locale: string
  group: string
  english: Record<string, string>
  lines: Record<string, string>
}

export async function fetchLocaleCatalog(): Promise<PortalLocaleCatalog> {
  const { data } = await api.get<{ data: PortalLocaleCatalog }>('/api/v1/languages')
  return data.data
}

export async function applyLocale(locale: string): Promise<PortalLocaleCatalog | { locale: string; translations: Record<string, Record<string, string>>; direction: string; is_rtl: boolean }> {
  const { data } = await api.post<{ data: PortalLocaleCatalog }>('/api/v1/locale', { locale })
  return data.data
}

export async function fetchAdminLanguages(): Promise<{
  languages: PortalLanguageAdminRow[]
  groups: Record<string, string>
}> {
  const { data } = await api.get<{
    data: { languages: PortalLanguageAdminRow[]; groups: Record<string, string> }
  }>('/api/v1/settings/languages')
  return data.data
}

export async function createAdminLanguage(payload: {
  locale_code: string
  name: string
  google_translate_code?: string
  flag_emoji?: string
  sort_order?: number
  is_active?: boolean
}): Promise<PortalLanguageAdminRow> {
  const { data } = await api.post<{ data: PortalLanguageAdminRow }>('/api/v1/settings/languages', payload)
  return data.data
}

export async function updateAdminLanguage(
  id: number,
  payload: {
    name: string
    google_translate_code?: string
    flag_emoji?: string
    sort_order?: number
    is_active?: boolean
  },
): Promise<PortalLanguageAdminRow> {
  const { data } = await api.put<{ data: PortalLanguageAdminRow }>(`/api/v1/settings/languages/${id}`, payload)
  return data.data
}

export async function deleteAdminLanguage(id: number): Promise<void> {
  await api.delete(`/api/v1/settings/languages/${id}`)
}

export async function fetchTranslationGrid(locale: string, group: string): Promise<PortalTranslationGrid> {
  const { data } = await api.get<{ data: PortalTranslationGrid }>('/api/v1/settings/languages/translations', {
    params: { locale, group },
  })
  return data.data
}

export async function saveTranslationGrid(
  locale: string,
  group: string,
  translations: Record<string, string>,
): Promise<PortalTranslationGrid> {
  const { data } = await api.put<{ data: PortalTranslationGrid }>('/api/v1/settings/languages/translations', {
    locale,
    group,
    translations,
  })
  return data.data
}

export async function fillTranslationsWithAi(
  locale: string,
  group: string,
): Promise<{ locale: string; group: string; lines: Record<string, string> }> {
  const { data } = await api.post<{ data: { locale: string; group: string; lines: Record<string, string> } }>(
    '/api/v1/settings/languages/translations/ai',
    { locale, group },
  )
  return data.data
}
