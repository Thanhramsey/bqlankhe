<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import { api } from './api'
import { useAuthStore } from './stores/auth'

type ResourceConfig = {
  endpoint: string
  title: string
  icon: string
  columns: Array<[string, string]>
  fields: Array<[string, string, string, string?]>
}

const auth = useAuthStore()
const route = useRoute()
const theme = useTheme()
const drawer = ref(true)
const busy = ref(false)
const error = ref('')
const snackbar = ref(false)
const snackbarText = ref('')
const search = ref('')
const modal = ref(false)
const editing = ref<Record<string, any>>({})
const rows = ref<any[]>([])
const households = ref<any[]>([])
const userOptions = ref<{ roles: any[]; routes: any[] }>({ roles: [], routes: [] })
const avatarFile = ref<File | null>(null)
const avatarPreview = ref('')
const routeOptions = ref<Record<string, any[]>>({ provinces: [], wards: [], neighborhoods: [], users: [], routes: [] })
const importInput = ref<HTMLInputElement | null>(null)
const showDeleted = ref(false)
const dashboard = ref<any>({})
const credentials = reactive({ identifier: 'admin', password: 'Admin@123' })
const payment = reactive({
  household_id: null as number | null,
  from_month: new Date().toISOString().slice(0, 7),
  to_month: new Date().toISOString().slice(0, 7),
  payment_method: 'TIEN_MAT',
  note: '',
})

const resources: Record<string, ResourceConfig> = {
  '/provinces': { endpoint: 'provinces', title: 'Tỉnh / thành phố', icon: 'mdi-map-outline', columns: [['code','Mã tỉnh'],['name','Tên tỉnh']], fields: [['code','Mã tỉnh','text'],['name','Tên tỉnh','text']] },
  '/wards': { endpoint: 'wards', title: 'Phường / xã', icon: 'mdi-city-variant-outline', columns: [['code','Mã phường/xã'],['name','Tên phường/xã'],['province.name','Tỉnh']], fields: [['province_id','Tỉnh','select','provinces'],['code','Mã phường/xã','text'],['name','Tên phường/xã','text']] },
  '/neighborhoods': { endpoint: 'neighborhoods', title: 'Thôn / xóm / tổ', icon: 'mdi-home-group', columns: [['code','Mã thôn/xóm/tổ'],['name','Tên thôn/xóm/tổ'],['ward.name','Phường/xã']], fields: [['ward_id','Phường / xã','select','wards'],['code','Mã thôn/xóm/tổ','text'],['name','Tên thôn/xóm/tổ','text']] },
  '/households': {
    endpoint: 'households',
    title: 'Hộ dân',
    icon: 'mdi-home-city-outline',
    columns: [
      ['code', 'Mã hộ'],
      ['owner_name', 'Chủ hộ'],
      ['phone', 'Điện thoại'],
      ['address', 'Địa chỉ'],
    ],
    fields: [
      ['code', 'Mã hộ', 'text'],
      ['owner_name', 'Chủ hộ', 'text'],
      ['phone', 'Điện thoại', 'text'],
      ['address', 'Địa chỉ', 'text'],
      ['ward', 'Phường', 'text'],
    ],
  },
  '/services': {
    endpoint: 'services',
    title: 'Dịch vụ',
    icon: 'mdi-recycle-variant',
    columns: [
      ['code', 'Mã'],
      ['name', 'Tên dịch vụ'],
      ['monthly_price', 'Đơn giá/tháng'],
    ],
    fields: [
      ['code', 'Mã', 'text'],
      ['name', 'Tên dịch vụ', 'text'],
      ['monthly_price', 'Đơn giá', 'number'],
      ['description', 'Mô tả', 'text'],
    ],
  },
  '/routes': {
    endpoint: 'routes',
    title: 'Tuyến thu',
    icon: 'mdi-map-marker-path',
    columns: [
      ['neighborhood.name', 'Thôn / xóm / tổ'],
      ['users_display', 'Người phụ trách'],
      ['code', 'Mã'],
      ['name', 'Tên tuyến'],
      ['description', 'Mô tả'],
    ],
    fields: [
      ['neighborhood_id', 'Thôn / xóm / tổ', 'select', 'neighborhoods'],
      ['user_ids', 'Người phụ trách', 'multiselect', 'users'],
      ['code', 'Mã', 'text'],
      ['name', 'Tên tuyến', 'text'],
      ['description', 'Mô tả', 'text'],
    ],
  },
  '/users': {
    endpoint: 'users',
    title: 'Người dùng',
    icon: 'mdi-account-group-outline',
    columns: [
      ['username', 'Tài khoản'],
      ['name', 'Họ tên'],
      ['email', 'Email'],
      ['phone', 'Điện thoại'],
      ['roles_display', 'Vai trò'],
      ['route_display', 'Tuyến thu'],
      ['status_display', 'Trạng thái'],
    ],
    fields: [
      ['username', 'Tài khoản', 'text'],
      ['date_of_birth', 'Ngày sinh', 'date'],
      ['gender', 'Giới tính (NAM/NU/KHAC)', 'text'],
      ['identity_number', 'Số giấy tờ', 'text'],
      ['address', 'Địa chỉ', 'text'],
      ['name', 'Họ tên', 'text'],
      ['email', 'Email', 'email'],
      ['phone', 'Điện thoại', 'text'],
      ['password', 'Mật khẩu', 'password'],
    ],
  },
  '/settings': {
    endpoint: 'settings',
    title: 'Cấu hình',
    icon: 'mdi-cog-outline',
    columns: [
      ['key', 'Khóa'],
      ['value', 'Giá trị'],
      ['group', 'Nhóm'],
    ],
    fields: [
      ['key', 'Khóa', 'text'],
      ['value', 'Giá trị', 'text'],
      ['type', 'Loại', 'text'],
      ['group', 'Nhóm', 'text'],
    ],
  },
}

const page = computed(() => route.path)
const routeManagementPages = ['/provinces', '/wards', '/neighborhoods', '/routes']
const config = computed(() => resources[page.value])
const pageTitle = computed(() =>
  page.value === '/'
    ? 'Tổng quan'
    : page.value === '/payments'
      ? 'Thu phí'
      : config.value?.title || 'Quản lý',
)
const menuIcons: Record<string, string> = {
  '/': 'mdi-view-dashboard-outline',
  '/households': 'mdi-home-city-outline',
  '/services': 'mdi-recycle-variant',
  '/routes': 'mdi-map-marker-path',
  '/payments': 'mdi-wallet-outline',
  '/users': 'mdi-account-group-outline',
  '/settings': 'mdi-cog-outline',
}
const headers = computed(() => [
  ...(config.value?.columns || []).map(([key, title]) => ({ key, title })),
  { key: 'actions', title: 'Thao tác', sortable: false, align: 'end' as const },
])
const kpis = computed(() => [
  {
    label: 'Hộ đang quản lý',
    value: dashboard.value.households || 0,
    icon: 'mdi-home-city',
    color: 'primary',
  },
  {
    label: 'Thu tháng này',
    value: money(dashboard.value.revenue_month),
    icon: 'mdi-cash-multiple',
    color: 'success',
  },
  {
    label: 'Lượt thu tháng',
    value: dashboard.value.payments_month || 0,
    icon: 'mdi-receipt-text-check',
    color: 'info',
  },
  {
    label: 'Công nợ',
    value: money(dashboard.value.outstanding_debt),
    icon: 'mdi-alert-circle-outline',
    color: 'secondary',
  },
])

function money(value: any) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(
    Number(value || 0),
  )
}
function notify(message: string) {
  snackbarText.value = message
  snackbar.value = true
}
function toggleTheme() {
  const isDark = theme.global.current.value.dark
  theme.global.name.value = isDark ? 'ankheLight' : 'ankheDark'
  localStorage.setItem('theme', isDark ? 'light' : 'dark')
}
async function login() {
  error.value = ''
  try {
    await auth.login(credentials.identifier, credentials.password)
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function load() {
  if (!auth.user) return
  busy.value = true
  error.value = ''
  try {
    if (page.value === '/') dashboard.value = (await api<any>('/dashboard')).data
    else if (page.value === '/payments') {
      rows.value = (await api<any>('/payments?per_page=50')).data.data
      households.value = (await api<any>('/households?per_page=100')).data.data
    } else if (config.value) {
      rows.value = (
        await api<any>(`/${config.value.endpoint}?search=${encodeURIComponent(search.value)}&with_deleted=${showDeleted.value ? 1 : 0}`)
      ).data.data
      busy.value = false
      if (page.value === '/wards') routeOptions.value.provinces = (await api<any>('/provinces?per_page=100')).data.data
      if (page.value === '/neighborhoods') routeOptions.value.wards = (await api<any>('/wards?per_page=100')).data.data
      if (page.value === '/routes') {
        rows.value = rows.value.map((item) => ({ ...item, user_ids: item.users?.map((user:any) => user.id), users_display: item.users?.map((user:any) => user.name).join(', ') || '—' }))
        const [neighborhoods, users] = await Promise.all([api<any>('/neighborhoods?per_page=100'), api<any>('/users?per_page=100')])
        routeOptions.value.neighborhoods = neighborhoods.data.data; routeOptions.value.users = users.data.data
      }
      if (page.value === '/users') {
        rows.value = rows.value.map((user) => ({
          ...user,
          roles_display: user.roles?.map((role: any) => role.name).join(', '),
          route_display: user.collection_routes?.map((route:any) => route.name).join(', ') || '—',
          status_display: user.is_active ? 'Hoạt động' : 'Ngừng hoạt động',
        }))
        if (!userOptions.value.roles.length) userOptions.value = (await api<any>('/users/options')).data
      }
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
function openForm(row: any = null) {
  editing.value = row
    ? { ...row }
    : { is_active: true, ward: 'An Khê', type: 'string', group: 'general' }
  modal.value = true
  avatarFile.value = null
  avatarPreview.value = row?.avatar_url || ''
}
async function save() {
  try {
    const endpoint = `/${config.value!.endpoint}${editing.value.id ? `/${editing.value.id}` : ''}`
    if (page.value === '/users') {
      const form = new FormData()
      const ignored = ['id', 'roles', 'collection_route', 'roles_display', 'avatar_url', 'created_at']
      Object.entries(editing.value).forEach(([key, value]) => {
        if (ignored.includes(key) || value === null || value === '') return
        if (key === 'role_ids' && Array.isArray(value)) value.forEach((id) => form.append('role_ids[]', String(id)))
        else form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
      })
      form.set('is_active', editing.value.is_active ? '1' : '0')
      if (avatarFile.value) form.set('avatar', avatarFile.value)
      if (editing.value.id) form.set('_method', 'PUT')
      await api(endpoint, { method: 'POST', body: form })
    } else {
      await api(endpoint, { method: editing.value.id ? 'PUT' : 'POST', body: JSON.stringify(editing.value) })
    }
    modal.value = false
    notify('Đã lưu dữ liệu')
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function remove(row: any) {
  if (!confirm('Bạn chắc chắn muốn xóa dữ liệu này?')) return
  try {
    await api(`/${config.value!.endpoint}/${row.id}`, { method: 'DELETE' })
    notify('Đã xóa dữ liệu')
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function restore(row: any) {
  try { await api(`/${config.value!.endpoint}/${row.id}/restore`, { method: 'POST' }); notify('Đã khôi phục dữ liệu'); await load() } catch (e:any) { error.value = e.message }
}
async function collect() {
  try {
    await api('/payments', { method: 'POST', body: JSON.stringify(payment) })
    notify('Thu phí thành công')
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function importRoutes(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const form = new FormData(); form.append('file', file)
  try { const result = await api<any>('/routes-import', { method: 'POST', body: form }); notify(`Import thành công ${result.data.routes} tuyến thu`); await load() } catch (e:any) { error.value = e.message } finally { input.value = '' }
}
async function downloadRouteTemplate() {
  const token = localStorage.getItem('token')
  const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  const response = await fetch(`${base}/routes-import/template`, { headers: token ? { Authorization: `Bearer ${token}` } : {} })
  const blob = await response.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'mau-import-tuyen-thu.xlsx'; link.click(); URL.revokeObjectURL(url)
}

let searchTimer: ReturnType<typeof setTimeout>
watch(() => route.path, load)
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 350)
})
watch(showDeleted, load)
watch(avatarFile, (file) => {
  if (avatarPreview.value.startsWith('blob:')) URL.revokeObjectURL(avatarPreview.value)
  avatarPreview.value = file ? URL.createObjectURL(file) : editing.value.avatar_url || ''
})
onMounted(async () => {
  await auth.restore()
  await load()
})
</script>

<template>
  <v-app>
    <div v-if="!auth.user" class="login-shell">
      <v-card class="login-card pa-8 pa-sm-10" width="440">
        <div class="login-logo mx-auto mb-5"><v-icon icon="mdi-recycle" size="34" /></div>
        <v-card-title class="text-h4 font-weight-bold text-center">Quản lý phí rác</v-card-title>
        <v-card-subtitle class="text-center mb-7">Ban Quản lý phường An Khê</v-card-subtitle>
        <v-form @submit.prevent="login">
          <v-text-field
            v-model="credentials.identifier"
            label="Tài khoản hoặc số CCCD"
            prepend-inner-icon="mdi-account-key-outline"
            autocomplete="username"
            required
          />
          <v-text-field
            v-model="credentials.password"
            label="Mật khẩu"
            prepend-inner-icon="mdi-lock-outline"
            type="password"
            required
          />
          <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{
            error
          }}</v-alert>
          <v-btn
            class="login-button"
            color="primary"
            size="large"
            type="submit"
            block
            :loading="auth.loading"
            >Đăng nhập</v-btn
          >
          <div class="text-caption text-medium-emphasis text-center mt-4">
            Tài khoản mẫu đã được điền sẵn
          </div>
        </v-form>
      </v-card>
    </div>

    <template v-else>
      <v-navigation-drawer v-model="drawer" color="#123D2B" width="264">
        <div class="d-flex align-center ga-3 pa-5">
          <v-avatar color="primary" rounded="lg" size="46"><v-icon icon="mdi-recycle" /></v-avatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold text-white">PHÍ RÁC AN KHÊ</div>
            <div class="text-caption text-green-lighten-3">Hệ thống quản lý</div>
          </div>
        </div>
        <v-divider color="white" opacity="0.12" />
        <v-list nav class="px-3 mt-3">
          <v-list-item
            v-for="menu in auth.user.menus"
            :key="menu.id"
            :to="menu.path"
            :prepend-icon="menuIcons[menu.path] || 'mdi-circle-outline'"
            :title="menu.name"
            color="white"
            rounded="lg"
          />
        </v-list>
        <template #append>
          <div class="pa-4">
            <v-card color="rgba(255,255,255,.08)" class="pa-3"
              ><div class="d-flex align-center ga-3">
                <v-avatar color="secondary">{{ auth.user.name[0] }}</v-avatar>
                <div class="overflow-hidden">
                  <div class="text-body-2 font-weight-bold text-white text-truncate">
                    {{ auth.user.name }}
                  </div>
                  <div class="text-caption text-green-lighten-3 text-truncate">
                    {{ auth.user.email }}
                  </div>
                </div>
              </div></v-card
            >
          </div>
        </template>
      </v-navigation-drawer>

      <v-app-bar flat border color="surface" height="76">
        <v-app-bar-nav-icon @click="drawer = !drawer" />
        <v-app-bar-title
          ><div class="text-h6 font-weight-bold">{{ pageTitle }}</div>
          <div class="text-caption text-medium-emphasis">
            {{
              new Date().toLocaleDateString('vi-VN', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
              })
            }}
          </div></v-app-bar-title
        >
        <v-btn icon="mdi-theme-light-dark" variant="text" @click="toggleTheme" />
        <v-menu
          ><template #activator="{ props }"
            ><v-btn v-bind="props" icon="mdi-account-circle-outline" variant="text" /></template
          ><v-list
            ><v-list-item
              prepend-icon="mdi-logout"
              title="Đăng xuất"
              @click="auth.logout" /></v-list
        ></v-menu>
      </v-app-bar>

      <v-main>
        <v-container fluid class="pa-4 pa-md-7">
          <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            closable
            class="mb-5"
            @click:close="error = ''"
            >{{ error }}</v-alert
          >

          <template v-if="page === '/'">
            <v-row
              ><v-col v-for="item in kpis" :key="item.label" cols="12" sm="6" lg="3"
                ><v-card class="kpi-card pa-5" border
                  ><div class="d-flex justify-space-between">
                    <div>
                      <div class="text-body-2 text-medium-emphasis mb-2">{{ item.label }}</div>
                      <div class="text-h5 font-weight-bold">{{ item.value }}</div>
                    </div>
                    <v-avatar :color="item.color" variant="tonal" rounded="lg"
                      ><v-icon :icon="item.icon"
                    /></v-avatar></div></v-card></v-col
            ></v-row>
            <v-card class="mt-2 pa-5" border
              ><v-card-title class="px-0">Hoạt động thu phí 30 ngày gần nhất</v-card-title
              ><v-card-subtitle class="px-0">Doanh thu theo ngày</v-card-subtitle>
              <div v-if="!dashboard.revenue_chart?.length" class="empty-state">
                <v-icon icon="mdi-chart-bar" size="56" />
                <div class="mt-3">Chưa có giao dịch trong kỳ</div>
              </div>
              <div v-else class="chart-bars">
                <div
                  v-for="point in dashboard.revenue_chart"
                  :key="point.date"
                  class="chart-bar"
                  :style="{
                    height: Math.min(240, Math.max(16, Number(point.total) / 1000)) + 'px',
                  }"
                  :title="`${point.date}: ${money(point.total)}`"
                /></div
            ></v-card>
          </template>

          <template v-else-if="page === '/payments'">
            <v-row
              ><v-col cols="12" lg="4"
                ><v-card class="pa-5" border
                  ><v-card-title class="px-0 pb-5"
                    ><v-icon icon="mdi-cash-register" color="primary" class="mr-2" />Thu phí theo
                    kỳ</v-card-title
                  ><v-form @submit.prevent="collect"
                    ><v-autocomplete
                      v-model="payment.household_id"
                      :items="households"
                      item-title="owner_name"
                      item-value="id"
                      label="Hộ dân"
                      prepend-inner-icon="mdi-home-account"
                      required
                      ><template #item="{ props, item }"
                        ><v-list-item
                          v-bind="props"
                          :subtitle="`${item.raw.code} · ${item.raw.address}`" /></template></v-autocomplete
                    ><v-row dense
                      ><v-col cols="6"
                        ><v-text-field
                          v-model="payment.from_month"
                          label="Từ tháng"
                          type="month"
                          required /></v-col
                      ><v-col cols="6"
                        ><v-text-field
                          v-model="payment.to_month"
                          label="Đến tháng"
                          type="month"
                          required /></v-col></v-row
                    ><v-select
                      v-model="payment.payment_method"
                      :items="[
                        { title: 'Tiền mặt', value: 'TIEN_MAT' },
                        { title: 'Chuyển khoản', value: 'CHUYEN_KHOAN' },
                      ]"
                      label="Hình thức thanh toán"
                      prepend-inner-icon="mdi-credit-card-outline"
                    /><v-textarea v-model="payment.note" label="Ghi chú" rows="2" /><v-btn
                      color="primary"
                      size="large"
                      type="submit"
                      block
                      prepend-icon="mdi-check-circle-outline"
                      >Xác nhận thu phí</v-btn
                    ></v-form
                  ></v-card
                ></v-col
              ><v-col cols="12" lg="8"
                ><v-card border
                  ><v-card-title class="pa-5">Giao dịch gần đây</v-card-title
                  ><v-table hover
                    ><thead>
                      <tr>
                        <th>Mã phiếu</th>
                        <th>Hộ dân</th>
                        <th>Khoảng thu</th>
                        <th>Số tiền</th>
                        <th>Trạng thái</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in rows" :key="row.id">
                        <td class="font-weight-medium">{{ row.code }}</td>
                        <td>{{ row.household?.owner_name }}</td>
                        <td>{{ row.from_month?.slice(0, 7) }} → {{ row.to_month?.slice(0, 7) }}</td>
                        <td class="font-weight-bold">{{ money(row.amount) }}</td>
                        <td>
                          <v-chip color="success" size="small" variant="tonal">Đã thu</v-chip>
                        </td>
                      </tr>
                    </tbody></v-table
                  ></v-card
                ></v-col
              ></v-row
            >
          </template>

          <template v-else-if="config">
            <v-tabs v-if="routeManagementPages.includes(page)" color="primary" class="mb-5" show-arrows>
              <v-tab to="/provinces">Tỉnh</v-tab><v-tab to="/wards">Phường / xã</v-tab><v-tab to="/neighborhoods">Thôn / xóm / tổ</v-tab><v-tab to="/routes">Tuyến thu</v-tab>
            </v-tabs>
            <v-checkbox v-if="routeManagementPages.includes(page)" v-model="showDeleted" label="Hiển thị dữ liệu đã xóa" color="primary" hide-details class="mb-3" />
            <div v-if="page === '/routes'" class="d-flex ga-2 mb-4 flex-wrap"><input ref="importInput" type="file" accept=".xlsx,.xls" hidden @change="importRoutes" /><v-btn variant="outlined" prepend-icon="mdi-download" @click="downloadRouteTemplate">Tải file mẫu</v-btn><v-btn variant="outlined" prepend-icon="mdi-file-excel" @click="importInput?.click()">Import Excel</v-btn></div>
            <div class="d-flex flex-column flex-sm-row justify-space-between ga-3 mb-5">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                label="Tìm kiếm"
                hide-details
                max-width="360"
                clearable
              /><v-btn color="primary" size="large" prepend-icon="mdi-plus" @click="openForm()"
                >Thêm mới</v-btn
              >
            </div>
            <v-card border
              ><v-data-table :headers="headers" :items="rows" :loading="busy" hover
                ><template
                  v-for="[key] in config.columns.filter((c) => c[0].includes('price'))"
                  #[`item.${key}`]="{ value }"
                  >{{ money(value) }}</template
                ><template #item.actions="{ item }"
                  ><v-btn v-if="item.deleted_at" icon="mdi-restore" size="small" variant="text" color="success" title="Khôi phục" @click="restore(item)" /><template v-else><v-btn
                    icon="mdi-pencil-outline"
                    size="small"
                    variant="text"
                    color="primary"
                    @click="openForm(item)" /><v-btn
                    icon="mdi-delete-outline"
                    size="small"
                    variant="text"
                    color="error"
                    @click="remove(item)" /></template></template
                ><template #no-data
                  ><div class="empty-state">
                    <v-icon :icon="config.icon" size="48" />
                    <div class="mt-2">Chưa có dữ liệu</div>
                  </div></template
                ></v-data-table
              ></v-card
            >
          </template>
        </v-container>
      </v-main>

      <v-dialog v-if="page === '/users'" v-model="modal" max-width="960" scrollable>
        <v-card class="user-modal" rounded="xl">
          <div class="user-modal__header">
            <div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-account-edit-outline" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">{{ editing.id ? 'Cập nhật người dùng' : 'Thêm người dùng mới' }}</div><div class="text-body-2 opacity-80">Quản lý hồ sơ, tài khoản và quyền truy cập</div></div></div>
            <v-btn icon="mdi-close" variant="text" color="white" @click="modal = false" />
          </div>
          <v-card-text class="user-modal__body"><v-form id="user-form" @submit.prevent="save">
            <div class="avatar-panel mb-6"><v-avatar size="88" color="primary" variant="tonal" :image="avatarPreview || undefined"><span v-if="!avatarPreview" class="text-h4 font-weight-bold">{{ editing.name?.[0]?.toUpperCase() || '?' }}</span></v-avatar><div class="flex-grow-1"><div class="text-subtitle-1 font-weight-bold mb-1">Ảnh đại diện</div><div class="text-caption text-medium-emphasis mb-3">JPG, PNG hoặc WebP · Tối đa 2 MB</div><v-file-input v-model="avatarFile" accept="image/png,image/jpeg,image/webp" label="Chọn ảnh" density="compact" variant="outlined" hide-details prepend-icon="" prepend-inner-icon="mdi-camera-outline" /></div></div>
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-account-outline" /> Thông tin cá nhân</div><v-row dense>
              <v-col cols="12" md="6"><v-text-field v-model="editing.name" label="Họ và tên *" prepend-inner-icon="mdi-account" required /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.date_of_birth" label="Ngày sinh" type="date" prepend-inner-icon="mdi-calendar" /></v-col>
              <v-col cols="12" md="6"><v-select v-model="editing.gender" :items="[{title:'Nam',value:'NAM'},{title:'Nữ',value:'NU'},{title:'Khác',value:'KHAC'}]" label="Giới tính" prepend-inner-icon="mdi-gender-male-female" clearable /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.identity_number" label="Số giấy tờ" prepend-inner-icon="mdi-card-account-details-outline" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="editing.phone" label="Số điện thoại" prepend-inner-icon="mdi-phone-outline" /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.email" label="Email *" type="email" prepend-inner-icon="mdi-email-outline" required /></v-col><v-col cols="12"><v-text-field v-model="editing.address" label="Địa chỉ" prepend-inner-icon="mdi-map-marker-outline" /></v-col>
            </v-row></div>
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-shield-account-outline" /> Tài khoản và phân quyền</div><v-row dense>
              <v-col cols="12" md="6"><v-text-field v-model="editing.username" label="Tài khoản *" prepend-inner-icon="mdi-at" required /></v-col><v-col cols="12" md="6"><v-select v-model="editing.role_ids" :items="userOptions.roles" item-title="name" item-value="id" label="Vai trò *" prepend-inner-icon="mdi-shield-key-outline" multiple chips required /></v-col>
              <v-col cols="12"><v-select v-model="editing.route_ids" :items="userOptions.routes" item-title="name" item-value="id" label="Phân tuyến đường thu tiền" prepend-inner-icon="mdi-map-marker-path" multiple chips clearable /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.password" :label="editing.id ? 'Mật khẩu mới' : 'Mật khẩu *'" type="password" prepend-inner-icon="mdi-lock-outline" :hint="editing.id ? 'Để trống nếu không đổi mật khẩu' : 'Tối thiểu 8 ký tự'" persistent-hint :required="!editing.id" /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.password_confirmation" label="Xác nhận mật khẩu" type="password" prepend-inner-icon="mdi-lock-check-outline" :required="!editing.id || !!editing.password" /></v-col>
            </v-row></div>
            <div class="status-panel"><div><div class="font-weight-bold">Trạng thái tài khoản</div><div class="text-caption text-medium-emphasis">Cho phép người dùng đăng nhập và sử dụng hệ thống</div></div><v-switch v-model="editing.is_active" :label="editing.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'" color="success" hide-details inset /></div>
          </v-form></v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="modal = false">Hủy</v-btn><v-btn color="primary" size="large" type="submit" form="user-form" prepend-icon="mdi-content-save-outline">{{ editing.id ? 'Lưu thay đổi' : 'Thêm người dùng' }}</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-else v-model="modal" max-width="560"
        ><v-card
          ><v-card-title class="d-flex align-center justify-space-between pa-5"
            ><span>{{ editing.id ? 'Cập nhật' : 'Thêm' }} {{ config?.title.toLowerCase() }}</span
            ><v-btn icon="mdi-close" variant="text" @click="modal = false" /></v-card-title
          ><v-divider /><v-card-text class="pt-6"
            ><v-form id="resource-form" @submit.prevent="save"
              ><template v-for="field in config?.fields" :key="field[0]"><v-select
                v-if="field[2] === 'select' || field[2] === 'multiselect'"
                v-model="editing[field[0]]"
                :items="routeOptions[field[3] || '']"
                item-title="name"
                item-value="id"
                :label="field[1]"
                :multiple="field[2] === 'multiselect'"
                :chips="field[2] === 'multiselect'"
                clearable
              /><v-text-field v-else
                v-model="editing[field[0]]"
                :label="field[1]"
                :type="field[2]"
                :required="
                  !['phone', 'description', 'password', 'date_of_birth', 'gender', 'identity_number', 'address'].includes(field[0])
                " /></template>
              <template v-if="page === '/users'">
                <v-select v-model="editing.role_ids" :items="userOptions.roles" item-title="name" item-value="id" label="Vai trò *" multiple chips required />
                <v-select v-model="editing.collection_route_id" :items="userOptions.routes" item-title="name" item-value="id" label="Phân tuyến đường thu tiền" clearable />
                <v-text-field v-model="editing.password_confirmation" label="Xác nhận mật khẩu" type="password" :required="!editing.id || !!editing.password" />
                <v-file-input v-model="avatarFile" label="Avatar (tối đa 2 MB)" accept="image/png,image/jpeg,image/webp" />
                <v-switch v-model="editing.is_active" label="Đang hoạt động" color="primary" />
              </template>
              </v-form></v-card-text
          ><v-card-actions class="pa-5 pt-0"
            ><v-spacer /><v-btn variant="text" @click="modal = false">Hủy</v-btn
            ><v-btn
              color="primary"
              type="submit"
              form="resource-form"
              prepend-icon="mdi-content-save-outline"
              >Lưu dữ liệu</v-btn
            ></v-card-actions
          ></v-card
        ></v-dialog
      >
      <v-snackbar v-model="snackbar" color="success" location="bottom end"
        ><v-icon icon="mdi-check-circle" class="mr-2" />{{ snackbarText }}</v-snackbar
      >
    </template>
  </v-app>
</template>
