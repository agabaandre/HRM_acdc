import vuetify from '@/plugins/vuetify'
import { mixWithWhite, normalizeHex } from '@/lib/colorUtils'

type BrandingTheme = {
  primary_color: string
  secondary_color: string
  app_name?: string
  favicon_url?: string | null
  admin_logo_size_percent?: number
}

const ADMIN_LOGO_BASE_HEIGHT = 40

function setFavicon(url: string | null | undefined) {
  if (!url) {
    return
  }

  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }

  link.href = url
}

export function applyBrandingTheme(branding: BrandingTheme) {
  const primary = normalizeHex(branding.primary_color)
  const secondary = normalizeHex(branding.secondary_color)
  const darkPrimary = mixWithWhite(primary, 0.35)
  const logoPercent = branding.admin_logo_size_percent ?? 100
  const logoHeight = Math.round(ADMIN_LOGO_BASE_HEIGHT * (logoPercent / 100))

  vuetify.theme.themes.value.light.colors.primary = primary
  vuetify.theme.themes.value.light.colors.secondary = secondary
  vuetify.theme.themes.value.dark.colors.primary = darkPrimary
  vuetify.theme.themes.value.dark.colors.secondary = secondary

  document.documentElement.style.setProperty('--brand-primary', primary)
  document.documentElement.style.setProperty('--brand-secondary', secondary)
  document.documentElement.style.setProperty('--admin-logo-height', `${logoHeight}px`)

  if (branding.app_name) {
    document.title = branding.app_name
  }

  setFavicon(branding.favicon_url)
}
