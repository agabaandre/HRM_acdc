/**
 * Staff Portal Vuetify — same Africa CDC theme as helpdesk-lib, with
 * outlined fields that keep labels floated (MFL-style persistent notch labels).
 */
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import { VColorInput } from 'vuetify/labs/VColorInput'
import { VDateInput } from 'vuetify/labs/VDateInput'

const africaCdcLight = {
  dark: false,
  colors: {
    background: '#eef5f9',
    surface: '#ffffff',
    primary: '#0d7a3a',
    'primary-darken-1': '#065f2c',
    secondary: '#c9a227',
    error: '#F8285A',
    info: '#2CABE3',
    success: '#2CD07E',
    // Darker amber so tonal chips/alerts stay readable (pale yellow-on-yellow was invisible).
    warning: '#B45309',
    'on-warning': '#FFFFFF',
    'on-surface': '#3A4752',
    'text-primary': '#3A4752',
    'text-secondary': '#768B9E',
    inputBorder: '#DFE5EF',
  },
  variables: {
    'border-color': '223, 229, 239',
    'border-opacity': 1,
  },
}

const africaCdcDark = {
  dark: true,
  colors: {
    background: '#0f172a',
    surface: '#1e293b',
    primary: '#4ade80',
    'primary-darken-1': '#22c55e',
    secondary: '#c9a227',
    error: '#f87171',
    info: '#60a5fa',
    success: '#34d399',
    warning: '#fbbf24',
    'on-warning': '#1c1917',
    'on-surface': '#e2e8f0',
    'on-background': '#e2e8f0',
    'text-primary': '#e2e8f0',
    'text-secondary': '#94a3b8',
    inputBorder: '#475569',
  },
  variables: {
    'border-color': '148, 163, 184',
    'border-opacity': 0.28,
    'high-emphasis-opacity': 1,
    'medium-emphasis-opacity': 0.82,
  },
}

/** Keeps the outline label on the top notch even when the field is empty.
 * Density compact ≈ MFL field height (~2.7rem). */
const outlinedField = {
  variant: 'outlined' as const,
  density: 'compact' as const,
  color: 'primary',
  hideDetails: 'auto' as const,
  persistentPlaceholder: true,
  placeholder: ' ',
}

export default createVuetify({
  locale: {
    locale: 'en',
    fallback: 'en',
    rtl: { ar: true },
  },
  components: {
    VColorInput,
    VDateInput,
  },
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi,
    },
  },
  defaults: {
    VBtn: {
      rounded: 'md',
      elevation: 0,
      height: 40,
    },
    VTextField: { ...outlinedField },
    VTextarea: { ...outlinedField },
    VSelect: { ...outlinedField },
    VAutocomplete: { ...outlinedField },
    VCombobox: { ...outlinedField },
    VColorInput: {
      ...outlinedField,
      pipLocation: 'prepend-inner',
    },
    VDateInput: { ...outlinedField },
    VCheckbox: {
      color: 'primary',
      hideDetails: 'auto',
    },
    VCard: {
      rounded: 'md',
      elevation: 0,
    },
    VDataTable: {
      density: 'comfortable',
      hover: true,
    },
    VDataTableServer: {
      density: 'comfortable',
      hover: true,
    },
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: africaCdcLight,
      dark: africaCdcDark,
    },
  },
})
