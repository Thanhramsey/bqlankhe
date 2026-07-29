import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: localStorage.getItem('theme') === 'dark' ? 'ankheDark' : 'ankheLight',
    themes: {
      ankheLight: {
        dark: false,
        colors: {
          primary: '#16794A',
          secondary: '#E8A936',
          background: '#F4F7F5',
          surface: '#FFFFFF',
          success: '#168451',
          error: '#C44438',
          info: '#2878B8',
        },
      },
      ankheDark: {
        dark: true,
        colors: {
          primary: '#67C99A',
          secondary: '#D8AE62',
          background: '#111315',
          surface: '#1B1F22',
          'surface-bright': '#282D31',
          'surface-light': '#24292D',
          'surface-variant': '#30363B',
          'on-surface': '#E6E9E7',
          'on-background': '#E6E9E7',
          success: '#63C694',
          info: '#70AEE2',
          warning: '#D8AE62',
          error: '#E27B73',
        },
      },
    },
  },
  defaults: {
    VCard: { elevation: 0, rounded: 'lg' },
    VBtn: { rounded: 'lg', textTransform: 'none' },
    VTextField: { variant: 'outlined', density: 'comfortable', color: 'primary' },
    VSelect: { variant: 'outlined', density: 'comfortable', color: 'primary' },
    VTextarea: { variant: 'outlined', density: 'comfortable', color: 'primary' },
  },
})
