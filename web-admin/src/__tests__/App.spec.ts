import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import App from '../App.vue'
import router from '../router'
import vuetify from '../plugins/vuetify'

describe('App', () => {
  beforeEach(() => localStorage.clear())

  it('hiển thị màn hình đăng nhập khi chưa có phiên làm việc', async () => {
    await router.push('/')
    await router.isReady()
    const wrapper = mount(App, { global: { plugins: [createPinia(), router, vuetify] } })
    expect(wrapper.text()).toContain('Quản lý phí rác')
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('.login-button').text()).toContain('Đăng nhập')
  })
})
