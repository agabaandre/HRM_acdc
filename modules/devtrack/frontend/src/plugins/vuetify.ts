import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { mixWithWhite } from '@/lib/colorUtils'

const defaultPrimary = '#0d7a3a'
const defaultSecondary = '#c9a227'

const cspNonceMeta = document.querySelector('meta[name="csp-nonce"]')?.getAttribute('content')
const cspNonce =
  cspNonceMeta && cspNonceMeta !== '__CSP_NONCE__' && cspNonceMeta !== ''
    ? cspNonceMeta
    : undefined

const appLightTheme = {
  dark: false,
  colors: {
    background: '#eef5f9',
    surface: '#ffffff',
    primary: defaultPrimary,
    secondary: defaultSecondary,
    error: '#F8285A',
    info: '#2CABE3',
    success: '#2CD07E',
    warning: '#F6C000',
    'on-surface': '#3A4752',
    'text-primary': '#3A4752',
    'text-secondary': '#768B9E',
    inputBorder: '#DFE5EF',
  },
}

const appDarkTheme = {
  dark: true,
  colors: {
    background: '#111c2d',
    surface: '#1a2533',
    primary: mixWithWhite(defaultPrimary, 0.35),
    secondary: defaultSecondary,
    error: '#F8285A',
    info: '#2CABE3',
    success: '#2CD07E',
    warning: '#F6C000',
    'on-surface': '#e8eef4',
    'text-primary': '#e8eef4',
    'text-secondary': '#9fb0c3',
    inputBorder: '#2d3a4a',
  },
}

export default createVuetify({
  components,
  directives,
  defaults: {
    VBtn: { rounded: 'pill', elevation: 0, height: 40 },
    VTextField: { variant: 'outlined', density: 'comfortable', color: 'primary', hideDetails: 'auto' },
    VTextarea: { variant: 'outlined', density: 'comfortable', color: 'primary', hideDetails: 'auto' },
    VSelect: { variant: 'outlined', density: 'comfortable', color: 'primary', hideDetails: 'auto' },
    VCard: { rounded: 'md', elevation: 0 },
    VDataTable: { density: 'comfortable', hover: true },
  },
  theme: {
    cspNonce,
    defaultTheme: 'light',
    themes: { light: appLightTheme, dark: appDarkTheme },
  },
})
