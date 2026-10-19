import vuetify from '@/plugins/vuetify'

const THEME_KEY = 'email_server.theme'

export type ThemeName = 'light' | 'dark'

export function initThemePreference(): void {
  const saved = localStorage.getItem(THEME_KEY)
  if (saved === 'light' || saved === 'dark') {
    vuetify.theme.global.name.value = saved
  }
}

export function saveThemePreference(theme: ThemeName): void {
  localStorage.setItem(THEME_KEY, theme)
}

export function currentThemePreference(): ThemeName {
  return vuetify.theme.global.current.value.dark ? 'dark' : 'light'
}

export function setThemePreference(theme: ThemeName): void {
  vuetify.theme.global.name.value = theme
  saveThemePreference(theme)
}

export function toggleThemePreference(): ThemeName {
  const next = vuetify.theme.global.current.value.dark ? 'light' : 'dark'
  setThemePreference(next)

  return next
}
