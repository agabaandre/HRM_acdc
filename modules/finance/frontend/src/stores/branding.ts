import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'

export type PortalBranding = {
  company_name: string
  logo_url: string
  system_logo: string
  footer_copyright_rendered: string
  footer_copyright: string
  print_footer: string
  company_email: string
  company_phone: string
  company_website: string
  company_address: string
  login_welcome_title: string
  login_welcome_text: string
  login_background: string
  login_background_url: string
}

const STORAGE_KEY = 'rr.portal_branding.v1'
const STORAGE_TTL_MS = 60 * 60_000

function staffRoot(): string {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff`
  }
  return '/staff'
}

const FALLBACK: PortalBranding = {
  company_name: 'Africa CDC',
  logo_url: `${typeof window !== 'undefined' ? staffRoot() : '/staff'}/assets/images/AU_CDC_Logo-800.png`,
  system_logo: '/assets/images/AU_CDC_Logo-800.png',
  footer_copyright_rendered: `Copyright © Africa CDC ${new Date().getFullYear()}. All rights reserved.`,
  footer_copyright: 'Copyright © Africa CDC {year}. All rights reserved.',
  print_footer: '',
  company_email: 'registry@africacdc.org',
  company_phone: '',
  company_website: 'https://africacdc.org',
  company_address: '',
  login_welcome_title: 'Welcome Back',
  login_welcome_text:
    'Access your Africa CDC Central Business Platform account to manage staff operations and track activities efficiently.',
  login_background: '/assets/images/bg_login.jpg',
  login_background_url: '',
}

function readCached(): PortalBranding | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { at?: number; data?: PortalBranding }
    if (!parsed?.data || !parsed.at || Date.now() - parsed.at > STORAGE_TTL_MS) return null
    return { ...FALLBACK, ...parsed.data }
  } catch {
    return null
  }
}

function writeCached(data: PortalBranding) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    // ignore quota / private mode
  }
}

function normalizeLogo(url: string): string {
  const trimmed = (url || '').trim()
  if (!trimmed) return `${staffRoot()}/assets/images/AU_CDC_Logo-800.png`
  if (/^https?:\/\//i.test(trimmed)) {
    // Rewrite broken module-scoped asset URLs to staff root.
    return trimmed.replace(
      /^(https?:\/\/[^/]+)\/staff\/(?:risk-register|helpdesk|apm)(?=\/assets\/)/i,
      '$1/staff',
    )
  }
  if (trimmed.startsWith('/')) return `${staffRoot()}${trimmed}`
  return `${staffRoot()}/assets/images/AU_CDC_Logo-800.png`
}

export const useBrandingStore = defineStore('branding', () => {
  const cached = typeof window !== 'undefined' ? readCached() : null
  const branding = ref<PortalBranding>({ ...(cached || FALLBACK) })
  const loaded = ref(false)
  let inflight: Promise<void> | null = null

  const logoUrl = computed(() =>
    normalizeLogo(branding.value.logo_url || branding.value.system_logo || ''),
  )
  const companyName = computed(() => branding.value.company_name || 'Africa CDC')
  const copyright = computed(
    () =>
      branding.value.footer_copyright_rendered
      || branding.value.footer_copyright.replace('{year}', String(new Date().getFullYear()))
      || FALLBACK.footer_copyright_rendered,
  )

  async function bootstrap(force = false) {
    if (loaded.value && !force) return
    if (inflight && !force) return inflight

    inflight = (async () => {
      try {
        const { data } = await api.get<{ data: PortalBranding }>('/api/v1/branding', {
          timeout: 8_000,
        })
        const next = { ...FALLBACK, ...(data.data || {}) }
        next.logo_url = normalizeLogo(next.logo_url || next.system_logo || '')
        branding.value = next
        writeCached(next)
      } catch {
        if (!cached) {
          branding.value = { ...FALLBACK }
        }
      } finally {
        loaded.value = true
        inflight = null
      }
    })()

    return inflight
  }

  async function refresh() {
    loaded.value = false
    await bootstrap(true)
  }

  return { branding, loaded, logoUrl, companyName, copyright, bootstrap, refresh }
})
