import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { applyLocale, fetchLocaleCatalog, type PortalLanguageOption } from '@/lib/languagesApi'

const STORAGE_KEY = 'staff_portal_locale'

const AU_LANGUAGES: PortalLanguageOption[] = [
  { code: 'en', name: 'English', flag: '🇬🇧', google_code: 'en' },
  { code: 'fr', name: 'Français', flag: '🇫🇷', google_code: 'fr' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦', google_code: 'ar', is_rtl: true },
  { code: 'es', name: 'Español', flag: '🇪🇸', google_code: 'es' },
  { code: 'pt', name: 'Português', flag: '🇵🇹', google_code: 'pt' },
  { code: 'sw', name: 'Kiswahili', flag: '🇰🇪', google_code: 'sw' },
]

function readStoredLocale(): string {
  try {
    return localStorage.getItem(STORAGE_KEY) || ''
  } catch {
    return ''
  }
}

function writeStoredLocale(locale: string) {
  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    /* ignore */
  }
}

function applyDocumentDirection(locale: string, isRtl: boolean) {
  if (typeof document === 'undefined') return
  document.documentElement.lang = locale
  document.documentElement.dir = isRtl ? 'rtl' : 'ltr'
}

function interpolate(text: string, vars?: Record<string, string | number>): string {
  if (!vars) return text
  let out = text
  for (const [key, value] of Object.entries(vars)) {
    out = out.replaceAll(`{${key}}`, String(value))
  }
  return out
}

export const useLocaleStore = defineStore('locale', () => {
  const locale = ref(readStoredLocale() || 'en')
  const direction = ref<'ltr' | 'rtl'>('ltr')
  const languages = ref<PortalLanguageOption[]>(AU_LANGUAGES)
  const translations = ref<Record<string, Record<string, string>>>({})
  const loaded = ref(false)

  const currentLanguage = computed(() => {
    return languages.value.find((row) => row.code === locale.value) || languages.value[0] || null
  })

  function t(key: string, fallback?: string, vars?: Record<string, string | number>): string {
    const dot = key.indexOf('.')
    if (dot < 1) return interpolate(fallback || key, vars)
    const group = key.slice(0, dot)
    const item = key.slice(dot + 1)
    const value = translations.value[group]?.[item]
    const out = typeof value === 'string' && value !== '' ? value : fallback || item
    return interpolate(out, vars)
  }

  function applyCatalog(payload: {
    locale: string
    direction?: string
    is_rtl?: boolean
    languages?: PortalLanguageOption[]
    translations?: Record<string, Record<string, string>>
  }) {
    locale.value = payload.locale || locale.value
    const rtl = Boolean(payload.is_rtl) || payload.direction === 'rtl' || locale.value === 'ar'
    direction.value = rtl ? 'rtl' : 'ltr'
    if (payload.languages?.length) languages.value = payload.languages
    if (payload.translations) translations.value = payload.translations
    writeStoredLocale(locale.value)
    applyDocumentDirection(locale.value, rtl)
    loaded.value = true
  }

  async function bootstrap() {
    try {
      applyCatalog(await fetchLocaleCatalog())
    } catch {
      loaded.value = true
    }
  }

  async function setLocale(code: string) {
    if (!code || code === locale.value) return
    const applied = await applyLocale(code)
    applyCatalog({
      locale: applied.locale,
      direction: applied.direction,
      is_rtl: applied.is_rtl,
      languages: languages.value,
      translations: applied.translations,
    })
  }

  return {
    locale,
    direction,
    languages,
    translations,
    loaded,
    currentLanguage,
    t,
    bootstrap,
    setLocale,
    applyCatalog,
  }
})
