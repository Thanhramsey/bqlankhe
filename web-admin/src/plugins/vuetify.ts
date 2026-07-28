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
          primary: '#58C78F',
          secondary: '#F2BA54',
          background: '#101813',
          surface: '#18241E',
          success: '#58C78F',
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
