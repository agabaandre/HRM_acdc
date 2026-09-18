import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { cachedGet, clearApiCache } from '@/lib/apiCache'

export type PortalBranding = {
  company_name: string
  system_logo: string
  logo_url: string
  footer_copyright: string
  footer_copyright_rendered: string
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

const FALLBACK: PortalBranding = {
  company_name: 'Africa CDC',
  system_logo: '/assets/images/AU_CDC_Logo-800.png',
  logo_url: '',
  footer_copyright: 'Copyright © Africa CDC {year}. All rights reserved.',
  footer_copyright_rendered: `Copyright © Africa CDC ${new Date().getFullYear()}. All rights reserved.`,
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

export const useBrandingStore = defineStore('branding', () => {
  const branding = ref<PortalBranding>({ ...FALLBACK })
  const loaded = ref(false)

  const logoUrl = computed(() => branding.value.logo_url || branding.value.system_logo)
  const companyName = computed(() => branding.value.company_name || 'Africa CDC')
  const copyright = computed(() => {
    const raw = branding.value.footer_copyright_rendered
      || branding.value.footer_copyright.replace('{year}', String(new Date().getFullYear()))
    return raw || FALLBACK.footer_copyright_rendered
  })

  async function bootstrap(force = false) {
    if (loaded.value && !force) return
    try {
      if (force) clearApiCache('branding:')
      const data = await cachedGet<{ data: PortalBranding }>(
        'branding:catalog',
        '/api/v1/branding',
        5 * 60_000,
      )
      branding.value = { ...FALLBACK, ...(data.data || {}) }
    } catch {
      branding.value = { ...FALLBACK }
    } finally {
      loaded.value = true
    }
  }

  async function refresh() {
    clearApiCache('branding:')
    loaded.value = false
    await bootstrap(true)
  }

  return {
    branding,
    loaded,
    logoUrl,
    companyName,
    copyright,
    bootstrap,
    refresh,
  }
})
