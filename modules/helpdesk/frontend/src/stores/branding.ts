import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../lib/api'

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

const FALLBACK: PortalBranding = {
  company_name: 'Africa CDC',
  logo_url: '',
  system_logo: '/assets/images/AU_CDC_Logo-800.png',
  footer_copyright_rendered: `Copyright © Africa CDC ${new Date().getFullYear()}. All rights reserved.`,
  footer_copyright: 'Copyright © Africa CDC {year}. All rights reserved.',
  print_footer: '',
  company_email: 'registry@africacdc.org',
  company_phone: '',
  company_website: 'https://africacdc.org',
  company_address: '',
  login_welcome_title: 'Welcome Back',
  login_welcome_text: '',
  login_background: '/assets/images/bg_login.jpg',
  login_background_url: '',
}

export const useBrandingStore = defineStore('branding', () => {
  const branding = ref<PortalBranding>({ ...FALLBACK })
  const loaded = ref(false)

  const logoUrl = computed(() => branding.value.logo_url || branding.value.system_logo || '')
  const companyName = computed(() => branding.value.company_name || 'Africa CDC')
  const copyright = computed(
    () =>
      branding.value.footer_copyright_rendered
      || branding.value.footer_copyright.replace('{year}', String(new Date().getFullYear()))
      || FALLBACK.footer_copyright_rendered,
  )

  async function bootstrap(force = false) {
    if (loaded.value && !force) return
    try {
      const { data } = await api.get<{ data: PortalBranding }>('/api/v1/branding')
      branding.value = { ...FALLBACK, ...(data.data || {}) }
    } catch {
      branding.value = { ...FALLBACK }
    } finally {
      loaded.value = true
    }
  }

  async function refresh() {
    loaded.value = false
    await bootstrap(true)
  }

  return { branding, loaded, logoUrl, companyName, copyright, bootstrap, refresh }
})
