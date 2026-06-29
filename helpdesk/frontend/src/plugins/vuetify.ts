import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { VColorInput } from 'vuetify/labs/VColorInput'

const africaCdcLight = {
  dark: false,
  colors: {
    background: '#fafbf9',
    surface: '#ffffff',
    primary: '#0d7a3a',
    'primary-darken-1': '#065f2c',
    secondary: '#c9a227',
    error: '#b91c1c',
    info: '#1d4ed8',
    success: '#047857',
    warning: '#d97706',
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
  },
}

export default createVuetify({
  components: {
    ...components,
    VColorInput,
  },
  directives,
  defaults: {
    VBtn: {
      rounded: 'md',
      elevation: 0,
      height: 40,
    },
    VTextField: {
      variant: 'outlined',
      density: 'compact',
      color: 'primary',
      hideDetails: 'auto',
    },
    VTextarea: {
      variant: 'outlined',
      density: 'compact',
      color: 'primary',
      hideDetails: 'auto',
    },
    VSelect: {
      variant: 'outlined',
      density: 'compact',
      color: 'primary',
      hideDetails: 'auto',
    },
    VAutocomplete: {
      variant: 'outlined',
      density: 'compact',
      color: 'primary',
      hideDetails: 'auto',
    },
    VColorInput: {
      variant: 'outlined',
      density: 'compact',
      color: 'primary',
      hideDetails: 'auto',
      pipLocation: 'prepend-inner',
    },
    VCheckbox: {
      color: 'primary',
      hideDetails: 'auto',
    },
    VCard: {
      rounded: 'lg',
      elevation: 1,
    },
    VDataTable: {
      density: 'compact',
      hover: true,
    },
    VDataTableServer: {
      density: 'compact',
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
