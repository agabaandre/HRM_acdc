import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import { withAppBase } from '@/lib/basePath'
import { applyBrandingTheme } from '@/lib/brandingTheme'

export type Branding = {
  app_name: string
  tagline: string | null
  logo_url: string | null
  logo_dark_url: string | null
  admin_logo_inverse: boolean
  admin_logo_size_percent: number
  favicon_url: string | null
  primary_color: string
  secondary_color: string
  support_email: string | null
}

const fallback: Branding = {
  app_name: 'Africa CDC DevTrack',
  tagline: 'Software delivery, assignment and reporting',
  logo_url: withAppBase('/ui/branding-logo.png'),
  logo_dark_url: withAppBase('/ui/branding-logo.png'),
  admin_logo_inverse: false,
  admin_logo_size_percent: 100,
  favicon_url: null,
  primary_color: '#0d7a3a',
  secondary_color: '#c9a227',
  support_email: null,
}

function publicAsset(url: string | null | undefined): string | null {
  if (!url) return null
  if (url.startsWith('http://') || url.startsWith('https://')) return url
  if (url.startsWith('/')) return withAppBase(url)
  return url
}

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    branding: { ...fallback } as Branding,
    loaded: false,
  }),
  actions: {
    applyPayload(payload: Partial<Branding>) {
      this.branding = {
        ...fallback,
        ...payload,
        logo_url: publicAsset(payload.logo_url) ?? fallback.logo_url,
        logo_dark_url: publicAsset(payload.logo_dark_url) ?? fallback.logo_dark_url,
        favicon_url: publicAsset(payload.favicon_url),
      }
      applyBrandingTheme(this.branding)
    },
    async fetch() {
      try {
        const { data } = await api.get<{ data: Branding }>('/branding')
        this.applyPayload(data.data)
      } catch {
        this.branding = { ...fallback }
        applyBrandingTheme(this.branding)
      } finally {
        this.loaded = true
      }
    },
    async update(form: FormData) {
      const { data } = await api.post('/admin/branding', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      this.applyPayload(data.data)
      return data
    },
  },
})
