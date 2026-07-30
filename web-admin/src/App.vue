<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import { api } from './api'
import { useAuthStore } from './stores/auth'
import MonthPicker from './components/MonthPicker.vue'
import DashboardView from './views/dashboard/DashboardView.vue'
import InventoryView from './views/inventory/InventoryView.vue'
import DocumentManagementView from './views/documents/DocumentManagementView.vue'
import DirectiveView from './views/directives/DirectiveView.vue'
import { useDirectiveStore } from './stores/directives'

type ResourceConfig = {
  endpoint: string
  title: string
  icon: string
  columns: Array<[string, string]>
  fields: Array<[string, string, string, string?]>
}

const auth = useAuthStore()
const directiveStore = useDirectiveStore()
const route = useRoute()
const theme = useTheme()
const drawer = ref(true)
const directiveAlert = ref(false)
const busy = ref(false)
const error = ref('')
const snackbar = ref(false)
const snackbarText = ref('')
const search = ref('')
const householdRouteFilter = ref<number | null>(null)
const householdServiceFilter = ref<number | null>(null)
const paymentRouteFilter = ref<number | null>(null)
const modal = ref(false)
const birthDateMenu = ref(false)
const priceFromDateMenu = ref(false)
const priceToDateMenu = ref(false)
const householdServiceDateMenu = ref(false)
const editing = ref<Record<string, any>>({})
const rows = ref<any[]>([])
const households = ref<any[]>([])
const userOptions = ref<{ roles: any[]; routes: any[]; menus: any[] }>({ roles: [], routes: [], menus: [] })
const avatarFile = ref<File | null>(null)
const avatarPreview = ref('')
const routeOptions = ref<Record<string, any[]>>({ provinces: [], wards: [], neighborhoods: [], users: [], routes: [] })
const importInput = ref<HTMLInputElement | null>(null)
const householdImportInput = ref<HTMLInputElement | null>(null)
const historyModal = ref(false)
const paymentHistory = ref<any[]>([])
const historyHousehold = ref<any>(null)
const historyLoading = ref(false)
const priceModal = ref(false)
const priceLoading = ref(false)
const priceSaving = ref(false)
const priceData = ref<any>({ service: null, periods: [], documents: [] })
const priceEditing = ref<any>(null)
const paymentPriceModal = ref(false)
const paymentPriceDetail = ref<any>(null)
const paymentSubmitting = ref(false)
const invoiceBusy = ref(false)
const invoiceSettings = ref<any[]>([])
const invoiceData = ref<any>({ items: { data: [] }, summary: {}, routes: [] })
const invoiceSearch = ref('')
const invoiceStatusFilter = ref<string | null>(null)
const invoiceRouteFilter = ref<number | null>(null)
const auditData = ref<any>({ logs: { data: [] }, users: [], actions: [] })
const auditSearch = ref('')
const auditFilters = reactive({ action: null as string | null, user_id: null as number | null, from_date: '', to_date: '' })
const auditDetail = ref<any>(null)
const auditModal = ref(false)
const reportData = ref<any>({ summary: {}, groups: [], details: [], options: { collectors: [], routes: [] } })
const reportFilters = reactive({
  basis: 'paid_at', dimension: 'period', period_unit: 'month', report_type: 'summary',
  from_date: `${new Date().getFullYear()}-01-01`, to_date: new Date().toISOString().slice(0, 10),
  collector_id: null as number | null, collection_route_id: null as number | null,
})
const debtData = ref<any>({ summary: {}, items: [], options: { routes: [], collectors: [] } })
const debtFilters = reactive({ collection_route_id: null as number | null, collector_id: null as number | null, from_month: '', to_month: new Date().toISOString().slice(0, 7), over_six_months: false })
const settingsSaving = ref(false)
const exportBusy = ref(false)
const profileModal = ref(false)
const profileSaving = ref(false)
const profileAvatarFile = ref<File | null>(null)
const profileAvatarPreview = ref('')
const profileForm = reactive<Record<string, any>>({})
const showDeleted = ref(false)
const credentials = reactive({ identifier: 'admin', password: 'Admin@123' })
const payment = reactive({
  household_ids: [] as number[],
  from_month: new Date().toISOString().slice(0, 7),
  month_count: 1,
  to_month: new Date().toISOString().slice(0, 7),
  payment_method: 'TIEN_MAT',
  note: '',
})
let paymentLoadSequence = 0

const resources: Record<string, ResourceConfig> = {
  '/provinces': { endpoint: 'provinces', title: 'Tỉnh / thành phố', icon: 'mdi-map-outline', columns: [['code','Mã tỉnh'],['name','Tên tỉnh']], fields: [['code','Mã tỉnh','text'],['name','Tên tỉnh','text']] },
  '/wards': { endpoint: 'wards', title: 'Phường / xã', icon: 'mdi-city-variant-outline', columns: [['code','Mã phường/xã'],['name','Tên phường/xã'],['province.name','Tỉnh']], fields: [['province_id','Tỉnh','select','provinces'],['code','Mã phường/xã','text'],['name','Tên phường/xã','text']] },
  '/neighborhoods': { endpoint: 'neighborhoods', title: 'Thôn / xóm / tổ', icon: 'mdi-home-group', columns: [['code','Mã thôn/xóm/tổ'],['name','Tên thôn/xóm/tổ'],['ward.name','Phường/xã']], fields: [['ward_id','Phường / xã','select','wards'],['code','Mã thôn/xóm/tổ','text'],['name','Tên thôn/xóm/tổ','text']] },
  '/households': {
    endpoint: 'households',
    title: 'Hộ dân',
    icon: 'mdi-home-city-outline',
    columns: [
      ['sequence_number', 'STT'],
      ['code', 'Mã hộ'],
      ['owner_name', 'Họ tên'],
      ['phone', 'Điện thoại'],
      ['address', 'Địa chỉ'],
      ['invoice_address', 'Địa chỉ HĐ'],
      ['route_display', 'Tuyến đường'],
      ['services_display', 'Dịch vụ'],
      ['status_display', 'Trạng thái'],
    ],
    fields: [
      ['code', 'Mã hộ', 'text'],
      ['owner_name', 'Chủ hộ', 'text'],
      ['phone', 'Điện thoại', 'text'],
      ['address', 'Địa chỉ', 'text'],
      ['invoice_address', 'Địa chỉ HĐ', 'text'],
      ['identity_number', 'CCCD', 'text'],
      ['email', 'Email', 'email'],
      ['sequence_number', 'Số thứ tự', 'number'],
      ['tax_code', 'Mã số thuế', 'text'],
      ['representative', 'Người đại diện', 'text'],
      ['collection_route_id', 'Tuyến đường', 'select', 'routes'],
      ['service_id', 'Loại dịch vụ', 'select', 'services'],
      ['note', 'Ghi chú', 'text'],
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
      ['tax_fee', 'Thuế, phí (%)'],
    ],
    fields: [
      ['code', 'Mã', 'text'],
      ['name', 'Tên dịch vụ', 'text'],
      ['monthly_price', 'Đơn giá', 'number'],
      ['tax_fee', 'Thuế, phí (%)', 'number'],
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
  page.value === '/inventory'
    ? 'Quản lý vật tư'
  : page.value === '/documents'
    ? 'Văn bản và tài liệu'
  : page.value === '/directives/sent'
    ? 'Gửi thông tin điều hành'
  : page.value === '/directives/inbox'
    ? 'Thông tin điều hành nhận'
  : page.value === '/'
    ? 'Tổng quan'
    : page.value === '/payments'
      ? 'Thu phí'
      : page.value === '/invoices'
        ? 'Quản lý hóa đơn điện tử'
      : page.value === '/audit-logs'
        ? 'Log hệ thống'
      : page.value === '/reports'
        ? 'Báo cáo doanh thu'
      : page.value === '/debts'
        ? 'Quản lý công nợ'
      : config.value?.title || 'Quản lý',
)
const menuIcons: Record<string, string> = {
  '/': 'mdi-view-dashboard-outline',
  '/households': 'mdi-home-city-outline',
  '/services': 'mdi-recycle-variant',
  '/inventory': 'mdi-package-variant-closed',
  '/documents': 'mdi-file-document-multiple-outline',
  '/directives/sent': 'mdi-send-outline',
  '/directives/inbox': 'mdi-inbox-arrow-down-outline',
  '/routes': 'mdi-map-marker-path',
  '/payments': 'mdi-wallet-outline',
  '/invoices': 'mdi-receipt-text-check-outline',
  '/audit-logs': 'mdi-history',
  '/reports': 'mdi-chart-box-outline',
  '/debts': 'mdi-alert-circle-outline',
  '/users': 'mdi-account-group-outline',
  '/settings': 'mdi-cog-outline',
}
const headers = computed(() => [
  ...(config.value?.columns || []).map(([key, title]) => ({ key, title })),
  { key: 'actions', title: 'Thao tác', sortable: false, align: 'end' as const },
])
const paymentHouseholdOptions = computed(() => households.value.map((household) => ({
  ...household,
  payment_label: `${household.code || 'Chưa có mã'} — ${household.owner_name || 'Chưa có tên'} · ${household.address || 'Chưa có địa chỉ'}`,
})))
const paymentMonthCount = computed(() => {
  if (!payment.from_month || !payment.to_month) return 0
  const [fromYear = 0, fromMonth = 0] = payment.from_month.split('-').map(Number)
  const [toYear = 0, toMonth = 0] = payment.to_month.split('-').map(Number)
  return Math.max(0, (toYear - fromYear) * 12 + toMonth - fromMonth + 1)
})

function addMonthsToPeriod(monthValue: string, numberOfMonths: number) {
  const [year = 0, month = 0] = monthValue.split('-').map(Number)
  if (!year || !month) return monthValue
  const date = new Date(year, month - 1 + Math.max(1, Math.trunc(numberOfMonths)) - 1, 1)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}
const debtKpis = computed(() => [
  { label: 'Hộ đang nợ', value: debtData.value.summary.households_in_debt || 0, icon: 'mdi-home-alert-outline', color: 'warning' },
  { label: 'Tổng tiền nợ', value: money(debtData.value.summary.total_debt), icon: 'mdi-cash-remove', color: 'error' },
  { label: 'Tổng tháng còn nợ', value: debtData.value.summary.total_debt_months || 0, icon: 'mdi-calendar-alert', color: 'info' },
  { label: 'Nợ trên 6 tháng', value: debtData.value.summary.over_six_months || 0, icon: 'mdi-alert-decagram-outline', color: 'error' },
])
const invoiceKpis = computed(() => [
  { label: 'Tổng hóa đơn', value: invoiceData.value.summary.total || 0, icon: 'mdi-receipt-text-outline', color: 'primary' },
  { label: 'Chờ phát hành', value: invoiceData.value.summary.pending || 0, icon: 'mdi-clock-outline', color: 'warning' },
  { label: 'Đã phát hành', value: invoiceData.value.summary.published || 0, icon: 'mdi-check-decagram-outline', color: 'success' },
  { label: 'Lỗi phát hành', value: invoiceData.value.summary.failed || 0, icon: 'mdi-alert-circle-outline', color: 'error' },
])
const userMenuGroups = computed(() => {
  const groups = new Map<string, any[]>()
  for (const menu of userOptions.value.menus || []) {
    const name = menu.parent?.name || 'Chức năng chung'
    groups.set(name, [...(groups.get(name) || []), menu])
  }
  return [...groups.entries()].map(([name, menus]) => ({ name, menus }))
})
const requiresAssignedMenus = computed(() => {
  const selected = new Set((editing.value.role_ids || []).map(Number))
  if (userOptions.value.roles.some((role) => selected.has(Number(role.id)) && role.code === 'ADMIN')) return false
  return userOptions.value.roles.some((role) => selected.has(Number(role.id)) && ['ACCOUNTANT', 'LEADER'].includes(role.code))
})
const isAdministratorRole = computed(() => {
  const selected = new Set((editing.value.role_ids || []).map(Number))
  return userOptions.value.roles.some((role) => selected.has(Number(role.id)) && role.code === 'ADMIN')
})
const reportKpis = computed(() => [
  { label: 'Tổng doanh thu', value: money(reportData.value.summary.total_revenue), icon: 'mdi-cash-multiple', color: 'success' },
  { label: 'Số giao dịch', value: reportData.value.summary.transactions || 0, icon: 'mdi-receipt-text-check-outline', color: 'primary' },
  { label: 'Số hộ dân', value: reportData.value.summary.households || 0, icon: 'mdi-home-group', color: 'info' },
  { label: 'Bình quân/giao dịch', value: money(reportData.value.summary.average), icon: 'mdi-chart-line', color: 'secondary' },
])

function money(value: any) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(
    Number(value || 0),
  )
}
function monthLabel(value: string) {
  return value ? `${value.slice(5, 7)}/${value.slice(0, 4)}` : '—'
}
function birthDateLabel(value?: string | null) {
  if (!value) return ''
  const [year, month, day] = value.slice(0, 10).split('-')
  return day && month && year ? `${day}/${month}/${year}` : ''
}
function setBirthDate(value: unknown) {
  const date = value instanceof Date ? value : new Date(String(value))
  if (Number.isNaN(date.getTime())) return
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  editing.value.date_of_birth = `${year}-${month}-${day}`
  birthDateMenu.value = false
}
function setHouseholdServiceDate(value: unknown) {
  const date = value instanceof Date ? value : new Date(String(value))
  if (Number.isNaN(date.getTime())) return
  editing.value.service_started_at = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  householdServiceDateMenu.value = false
}
function debtCollectors(item: any) {
  return item?.collectors?.map((collector: any) => collector.name).join(', ') || '—'
}
function debtPeriod(item: any) {
  return `${monthLabel(item?.oldest_debt_month)} – ${monthLabel(item?.latest_debt_month)}`
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
function openProfile() {
  Object.assign(profileForm, {
    name: auth.user?.name || '', email: auth.user?.email || '', phone: auth.user?.phone || '',
    date_of_birth: auth.user?.date_of_birth || '', gender: auth.user?.gender || null,
    identity_number: auth.user?.identity_number || '', address: auth.user?.address || '',
    current_password: '', password: '', password_confirmation: '', remove_avatar: false,
  })
  profileAvatarFile.value = null
  profileAvatarPreview.value = auth.user?.avatar_url || ''
  profileModal.value = true
}
function removeProfileAvatar() {
  profileAvatarFile.value = null
  profileAvatarPreview.value = ''
  profileForm.remove_avatar = true
}
async function saveProfile() {
  profileSaving.value = true
  error.value = ''
  try {
    const form = new FormData()
    Object.entries(profileForm).forEach(([key, value]) => {
      if (value !== null && value !== '') form.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
    })
    if (profileAvatarFile.value) form.set('avatar', profileAvatarFile.value)
    const response = await api<any>('/auth/profile', { method: 'POST', body: form })
    auth.user = response.data
    profileModal.value = false
    notify(response.message)
  } catch (e: any) { error.value = e.message }
  finally { profileSaving.value = false }
}
async function login() {
  error.value = ''
  try {
    await auth.login(credentials.identifier, credentials.password)
    await directiveStore.refresh()
    directiveAlert.value = directiveStore.unread > 0
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
    if (page.value === '/') return
    else if (page.value === '/payments') {
      const loadSequence = ++paymentLoadSequence
      const routeQuery = paymentRouteFilter.value ? `&collection_route_id=${paymentRouteFilter.value}` : ''
      const [paymentsResponse, optionsResponse] = await Promise.all([
        api<any>(`/payments?per_page=50${routeQuery}`),
        api<any>(`/payments/options?${routeQuery.slice(1)}`),
      ])
      if (loadSequence !== paymentLoadSequence) return
      rows.value = paymentsResponse.data.data
      households.value = optionsResponse.data.households
      routeOptions.value.routes = optionsResponse.data.routes || []
    } else if (page.value === '/invoices') {
      const params = new URLSearchParams({ per_page: '100' })
      if (invoiceSearch.value.trim()) params.set('search', invoiceSearch.value.trim())
      if (invoiceStatusFilter.value) params.set('status', invoiceStatusFilter.value)
      if (invoiceRouteFilter.value) params.set('collection_route_id', String(invoiceRouteFilter.value))
      invoiceData.value = (await api<any>(`/invoices?${params.toString()}`)).data
    } else if (page.value === '/audit-logs') {
      const params = new URLSearchParams({ per_page: '100' })
      if (auditSearch.value.trim()) params.set('search', auditSearch.value.trim())
      Object.entries(auditFilters).forEach(([key, value]) => { if (value !== null && value !== '') params.set(key, String(value)) })
      auditData.value = (await api<any>(`/audit-logs?${params.toString()}`)).data
    } else if (page.value === '/reports') {
      const params = new URLSearchParams()
      Object.entries(reportFilters).forEach(([key, value]) => { if (value !== null && value !== '') params.set(key, String(value)) })
      reportData.value = (await api<any>(`/reports/revenue?${params.toString()}`)).data
    } else if (page.value === '/debts') {
      const params = new URLSearchParams()
      Object.entries(debtFilters).forEach(([key, value]) => { if (value !== null && value !== '' && value !== false) params.set(key, String(value === true ? 1 : value)) })
      debtData.value = (await api<any>(`/debts?${params.toString()}`)).data
    } else if (page.value === '/settings') {
      invoiceSettings.value = (await api<any>('/invoice-settings')).data
    } else if (config.value) {
      const params = new URLSearchParams({ search: search.value, with_deleted: showDeleted.value ? '1' : '0' })
      if (page.value === '/households' && householdRouteFilter.value) params.set('collection_route_id', String(householdRouteFilter.value))
      if (page.value === '/households' && householdServiceFilter.value) params.set('service_id', String(householdServiceFilter.value))
      rows.value = (await api<any>(`/${config.value.endpoint}?${params.toString()}`)).data.data
      busy.value = false
      if (page.value === '/wards') routeOptions.value.provinces = (await api<any>('/provinces?per_page=100')).data.data
      if (page.value === '/neighborhoods') routeOptions.value.wards = (await api<any>('/wards?per_page=100')).data.data
      if (page.value === '/routes') {
        rows.value = rows.value.map((item) => ({ ...item, user_ids: item.users?.map((user:any) => user.id), users_display: item.users?.map((user:any) => user.name).join(', ') || '—' }))
        const [neighborhoods, users] = await Promise.all([api<any>('/neighborhoods?per_page=100'), api<any>('/users?per_page=100')])
        routeOptions.value.neighborhoods = neighborhoods.data.data; routeOptions.value.users = users.data.data
      }
      if (page.value === '/households') {
        rows.value = rows.value.map((item) => ({
          ...item,
          service_id: item.service_id || item.services?.[0]?.service_id,
          route_display: item.route?.name || '—',
          services_display: item.services?.map((subscription:any) => subscription.service?.name).join(', ') || '—',
          status_display: item.is_active ? 'Hoạt động' : 'Ngừng hoạt động',
        }))
        if (!routeOptions.value.routes?.length || !routeOptions.value.services?.length) await loadHouseholdOptions()
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
async function loadHouseholdOptions() {
  const options = (await api<any>('/households/options')).data
  routeOptions.value.routes = options.routes || []
  routeOptions.value.services = options.services || []
}
async function openForm(row: any = null) {
  if (page.value === '/households' && (!routeOptions.value.routes?.length || !routeOptions.value.services?.length)) {
    try { await loadHouseholdOptions() } catch (e:any) { error.value = e.message; return }
  }
  editing.value = row
    ? { ...row }
    : { is_active: true, menu_access_custom: false, menu_ids: [], role_ids: [], route_ids: [], tax_fee: page.value === '/services' ? 0 : undefined, ward: 'An Khê', type: 'string', group: 'general' }
  modal.value = true
  avatarFile.value = null
  avatarPreview.value = row?.avatar_url || ''
}
async function save() {
  try {
    const endpoint = `/${config.value!.endpoint}${editing.value.id ? `/${editing.value.id}` : ''}`
    if (page.value === '/users') {
      const form = new FormData()
      const ignored = ['id', 'roles', 'menus', 'collection_routes', 'collection_route', 'roles_display', 'route_display', 'status_display', 'avatar_url', 'avatar', 'created_at']
      Object.entries(editing.value).forEach(([key, value]) => {
        if (ignored.includes(key) || value === null || value === '') return
        if (['role_ids', 'route_ids', 'menu_ids'].includes(key) && Array.isArray(value)) value.forEach((id) => form.append(`${key}[]`, String(id)))
        else form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
      })
      form.set('is_active', editing.value.is_active ? '1' : '0')
      form.set('menu_access_custom', editing.value.menu_access_custom ? '1' : '0')
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
async function openPricePeriods(service: any) {
  priceModal.value = true
  priceLoading.value = true
  priceEditing.value = null
  try { priceData.value = (await api<any>(`/services/${service.id}/price-periods`)).data }
  catch (e:any) { error.value = e.message }
  finally { priceLoading.value = false }
}
function editPricePeriod(period: any = null) {
  priceEditing.value = period ? {
    ...period,
    effective_from: period.effective_from?.slice(0, 10),
    effective_to: period.effective_to?.slice(0, 10) || '',
    document_date: period.document_date?.slice(0, 10) || '',
  } : { document_id: null, document_number: '', document_name: '', document_date: '', effective_from: '', effective_to: '', monthly_price: 0, tax_fee: 0, note: '', is_active: true }
}
function selectPriceDocument(documentId: number | null) {
  const document = priceData.value.documents?.find((item:any) => item.id === documentId)
  if (!document || !priceEditing.value) return
  priceEditing.value.document_number = document.code || document.document_number || ''
  priceEditing.value.document_name = document.name || document.title || ''
  priceEditing.value.document_date = (document.document_date || document.created_at || '').slice(0, 10)
}
function setPriceDate(field: 'effective_from' | 'effective_to', value: unknown) {
  const date = value instanceof Date ? value : new Date(String(value))
  if (Number.isNaN(date.getTime()) || !priceEditing.value) return
  const local = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  priceEditing.value[field] = local
  if (field === 'effective_from') priceFromDateMenu.value = false
  else priceToDateMenu.value = false
}
function showPaymentPriceDetail(payment: any) {
  paymentPriceDetail.value = payment
  paymentPriceModal.value = true
}
function pricePeriodDocument(item: any) { return { number: item?.document_number || '—', name: item?.document_name || '—' } }
function pricePeriodEffective(item: any) { return `${birthDateLabel(item?.effective_from)} – ${item?.effective_to ? birthDateLabel(item.effective_to) : 'Không giới hạn'}` }
async function savePricePeriod() {
  if (!priceEditing.value || !priceData.value.service) return
  priceSaving.value = true
  try {
    const item = priceEditing.value
    const path = `/services/${priceData.value.service.id}/price-periods${item.id ? `/${item.id}` : ''}`
    await api(path, { method: item.id ? 'PUT' : 'POST', body: JSON.stringify(item) })
    notify(item.id ? 'Đã cập nhật giai đoạn giá' : 'Đã thêm giai đoạn giá')
    priceEditing.value = null
    priceData.value = (await api<any>(`/services/${priceData.value.service.id}/price-periods`)).data
    await load()
  } catch (e:any) { error.value = e.message }
  finally { priceSaving.value = false }
}
async function deletePricePeriod(period: any) {
  if (!confirm(`Xóa mức giá theo văn bản ${period.document_number}?`)) return
  try {
    await api(`/services/${priceData.value.service.id}/price-periods/${period.id}`, { method: 'DELETE' })
    notify('Đã xóa giai đoạn giá')
    priceData.value = (await api<any>(`/services/${priceData.value.service.id}/price-periods`)).data
    await load()
  } catch (e:any) { error.value = e.message }
}
async function collect(publishInvoice = false) {
  paymentSubmitting.value = true
  try {
    const selectedCount = payment.household_ids.length
    const result = await api<any>('/payments', { method: 'POST', body: JSON.stringify(payment) })
    let published = false
    if (publishInvoice) {
      try { await publishInvoices(result.data.map((item:any) => item.id), false); published = true } catch { /* Phiếu thu vẫn được giữ để phát hành lại. */ }
    }
    payment.household_ids = []
    if (!publishInvoice || published) notify(published ? `Đã thu và phát hành hóa đơn cho ${selectedCount} hộ dân` : `Đã thu phí thành công cho ${selectedCount} hộ dân`)
    await load()
  } catch (e: any) {
    error.value = e.message
  } finally { paymentSubmitting.value = false }
}
async function publishInvoices(paymentIds: number[], reload = true) {
  invoiceBusy.value = true
  try {
    const result = await api<any>('/invoices/publish', { method: 'POST', body: JSON.stringify({ payment_ids: paymentIds }) })
    const failures = result.data.filter((item:any) => !item.success)
    if (failures.length) throw new Error(failures.map((item:any) => item.message).join('\n'))
    notify(result.message)
    if (reload) await load()
  } catch (e:any) { error.value = e.message; if (reload) await load(); throw e }
  finally { invoiceBusy.value = false }
}
async function openPaymentPdf(paymentId: number, type: 'receipt' | 'invoice') {
  const popup = window.open('', '_blank')
  try {
    const token = localStorage.getItem('token'); const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
    const response = await fetch(`${base}/payments/${paymentId}/${type}`, { headers: token ? { Authorization: `Bearer ${token}` } : {} })
    if (!response.ok) throw new Error((await response.json()).message || 'Không thể tạo file in.')
    const url = URL.createObjectURL(await response.blob())
    if (popup) popup.location.href = url; else window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e:any) { popup?.close(); error.value = e.message }
}
function invoiceStatus(invoice: any) {
  return ({ CHO_PHAT_HANH: ['Chờ phát hành', 'warning'], DANG_PHAT_HANH: ['Đang phát hành', 'info'], DA_PHAT_HANH: ['Đã phát hành', 'success'], PHAT_HANH_LOI: ['Phát hành lỗi', 'error'] } as Record<string,string[]>)[invoice?.status] || ['Chưa có', 'default']
}
function canPublishInvoice(invoice: any) {
  return auth.user?.permissions.includes('payments.create') && ['CHO_PHAT_HANH', 'PHAT_HANH_LOI'].includes(invoice?.status)
}
function invoiceHousehold(invoice: any) { return invoice?.payment?.household }
function invoicePeriod(invoice: any) { return `${monthLabel(invoice?.payment?.from_month?.slice(0, 7))} – ${monthLabel(invoice?.payment?.to_month?.slice(0, 7))}` }
function invoicePaymentId(invoice: any) { return Number(invoice?.payment_id) }
function invoiceIsFailed(invoice: any) { return invoice?.status === 'PHAT_HANH_LOI' }
function invoiceIsPublished(invoice: any) { return invoice?.status === 'DA_PHAT_HANH' }
function canDeletePendingPayment(row: any) { return row?.invoice?.status === 'CHO_PHAT_HANH' && !row?.invoice?.invoice_no }
async function deletePendingPayment(row: any) {
  if (!confirm(`Xóa phiếu thu ${row.code}? Các tháng của phiếu sẽ được chuyển lại thành chưa thu.`)) return
  try {
    const result = await api(`/payments/${row.id}`, { method: 'DELETE' })
    notify(result.message)
    await load()
  } catch (e: any) { error.value = e.message }
}
const auditActionLabels: Record<string, [string, string, string]> = {
  LOGIN: ['Đăng nhập', 'success', 'mdi-login'], LOGIN_FAILED: ['Đăng nhập thất bại', 'error', 'mdi-login-variant'], LOGOUT: ['Đăng xuất', 'info', 'mdi-logout'],
  COLLECT_PAYMENT: ['Thu tiền', 'success', 'mdi-cash-check'], DELETE_PAYMENT: ['Xóa phiếu thu', 'error', 'mdi-cash-remove'], CREATE: ['Thêm mới', 'primary', 'mdi-plus-circle-outline'], UPDATE: ['Chỉnh sửa', 'warning', 'mdi-pencil-outline'], DELETE: ['Xóa', 'error', 'mdi-delete-outline'], RESTORE: ['Khôi phục', 'success', 'mdi-backup-restore'],
  CHANGE_PRICE: ['Đổi giá', 'warning', 'mdi-cash-edit'], PUBLISH_INVOICE: ['Xuất hóa đơn', 'success', 'mdi-receipt-text-check-outline'], PUBLISH_INVOICE_FAILED: ['Xuất hóa đơn lỗi', 'error', 'mdi-receipt-text-remove-outline'],
  IMPORT_HOUSEHOLDS: ['Import hộ dân', 'info', 'mdi-file-excel-outline'], IMPORT_ROUTES: ['Import tuyến thu', 'info', 'mdi-file-excel-outline'], CHANGE_PASSWORD: ['Đổi mật khẩu', 'warning', 'mdi-lock-reset'], UPDATE_PROFILE: ['Sửa hồ sơ', 'info', 'mdi-account-edit-outline'],
  EXPORT_HOUSEHOLDS: ['Xuất DS hộ dân', 'success', 'mdi-microsoft-excel'], EXPORT_DEBTS: ['Xuất DS chưa thu', 'success', 'mdi-microsoft-excel'], EXPORT_INVOICES: ['Xuất DS hóa đơn', 'success', 'mdi-microsoft-excel'],
  EXPORT_REPORT_EXCEL: ['Xuất báo cáo Excel', 'success', 'mdi-microsoft-excel'], EXPORT_REPORT_PDF: ['Xuất báo cáo PDF', 'error', 'mdi-file-pdf-box'],
  CREATE_DOCUMENT: ['Thêm tài liệu', 'primary', 'mdi-file-document-plus-outline'], UPDATE_DOCUMENT: ['Sửa tài liệu', 'warning', 'mdi-file-document-edit-outline'], DELETE_DOCUMENT: ['Xóa tài liệu', 'error', 'mdi-file-document-remove-outline'], RESTORE_DOCUMENT: ['Khôi phục tài liệu', 'success', 'mdi-file-restore-outline'],
  CREATE_DIRECTIVE: ['Gửi thông tin điều hành', 'primary', 'mdi-send-outline'], UPDATE_DIRECTIVE: ['Sửa thông tin điều hành', 'warning', 'mdi-file-document-edit-outline'], DELETE_DIRECTIVE: ['Xóa thông tin điều hành', 'error', 'mdi-delete-outline'],
}
function auditAction(action: string) { return auditActionLabels[action] || [action, 'default', 'mdi-history'] }
const auditActionOptions = computed(() => auditData.value.actions.map((action: string) => ({ title: auditAction(action)[0], value: action })))
function auditActionFor(log: any) { return auditAction(log?.action) }
function auditEntityFor(log: any) { return `${auditEntity(log?.entity_type)}${log?.entity_id ? ` #${log.entity_id}` : ''}` }
function auditEntity(type: string) {
  const entity = type?.split('\\').pop() || 'Hệ thống'
  return ({ Household: 'Hộ dân', Payment: 'Phiếu thu', Service: 'Dịch vụ', Invoice: 'Hóa đơn', User: 'Người dùng', CollectionRoute: 'Tuyến thu', Document: 'Tài liệu', DocumentCategory: 'Loại tài liệu', OperatingDirective: 'Thông tin điều hành' } as Record<string,string>)[entity] || entity
}
function auditUser(log: any) { return log?.user }
function showAuditDetail(log: any) { auditDetail.value = log; auditModal.value = true }
function prettyJson(value: any) { return value ? JSON.stringify(value, null, 2) : 'Không có dữ liệu' }
function reportShare(item: any) { return reportData.value.summary.total_revenue ? Number(item?.amount || 0) / reportData.value.summary.total_revenue * 100 : 0 }
function reportDetailPeriod(item: any) { return `${monthLabel(item?.from_month)} – ${monthLabel(item?.to_month)}` }
async function saveInvoiceSettings() {
  settingsSaving.value = true
  try {
    const settings = Object.fromEntries(invoiceSettings.value.map((item) => [item.key, item.value]))
    invoiceSettings.value = (await api<any>('/invoice-settings', { method: 'PUT', body: JSON.stringify({ settings }) })).data
    notify('Đã lưu cấu hình hóa đơn')
  } catch (e:any) { error.value = e.message }
  finally { settingsSaving.value = false }
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
async function importHouseholds(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const form = new FormData(); form.append('file', file)
  try {
    const result = await api<any>('/households-import', { method: 'POST', body: form })
    notify(`Đã import ${result.data.households} hộ dân`)
    await load()
  } catch (e:any) { error.value = e.message } finally { input.value = '' }
}
async function downloadHouseholdTemplate() {
  const token = localStorage.getItem('token')
  const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  const response = await fetch(`${base}/households-import/template`, { headers: token ? { Authorization: `Bearer ${token}` } : {} })
  if (!response.ok) { error.value = 'Không thể tải file mẫu hộ dân.'; return }
  const blob = await response.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'mau-import-ho-dan.xlsx'; link.click(); URL.revokeObjectURL(url)
}
async function downloadExport(path: string, fallbackName: string) {
  exportBusy.value = true
  error.value = ''
  try {
    const token = localStorage.getItem('token'); const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
    const response = await fetch(`${base}${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {} })
    if (!response.ok) throw new Error((await response.json()).message || 'Không thể xuất file Excel.')
    const disposition = response.headers.get('content-disposition') || ''
    const fileName = decodeURIComponent(disposition.match(/filename\*?=(?:UTF-8'')?["']?([^"';]+)/i)?.[1] || fallbackName)
    const url = URL.createObjectURL(await response.blob()); const link = document.createElement('a')
    link.href = url; link.download = fileName; link.click(); URL.revokeObjectURL(url)
    notify(fallbackName.endsWith('.pdf') ? 'Đã xuất báo cáo PDF' : 'Đã xuất file Excel')
  } catch (e:any) { error.value = e.message }
  finally { exportBusy.value = false }
}
function exportHouseholds() {
  const params = new URLSearchParams()
  if (search.value.trim()) params.set('search', search.value.trim())
  if (householdRouteFilter.value) params.set('collection_route_id', String(householdRouteFilter.value))
  if (householdServiceFilter.value) params.set('service_id', String(householdServiceFilter.value))
  return downloadExport(`/households-export?${params}`, 'danh-sach-ho-dan.xlsx')
}
function exportDebts() {
  const params = new URLSearchParams()
  Object.entries(debtFilters).forEach(([key, value]) => { if (value !== null && value !== '' && value !== false) params.set(key, String(value === true ? 1 : value)) })
  return downloadExport(`/debts-export?${params}`, 'danh-sach-chua-thu.xlsx')
}
function exportInvoices() {
  const params = new URLSearchParams()
  if (invoiceSearch.value.trim()) params.set('search', invoiceSearch.value.trim())
  if (invoiceStatusFilter.value) params.set('status', invoiceStatusFilter.value)
  if (invoiceRouteFilter.value) params.set('collection_route_id', String(invoiceRouteFilter.value))
  return downloadExport(`/invoices-export?${params}`, 'danh-sach-hoa-don-dien-tu.xlsx')
}
function reportQuery() {
  const params = new URLSearchParams()
  Object.entries(reportFilters).forEach(([key, value]) => { if (value !== null && value !== '') params.set(key, String(value)) })
  return params.toString()
}
function exportReport(format: 'excel' | 'pdf') {
  return downloadExport(`/reports/revenue/${format}?${reportQuery()}`, `bao-cao-doanh-thu.${format === 'excel' ? 'xlsx' : 'pdf'}`)
}
async function showPaymentHistory(household: any) {
  historyHousehold.value = household
  historyModal.value = true
  historyLoading.value = true
  try { paymentHistory.value = (await api<any>(`/households/${household.id}/payments`)).data.data }
  catch (e:any) { error.value = e.message }
  finally { historyLoading.value = false }
}

let searchTimer: ReturnType<typeof setTimeout>
let invoiceSearchTimer: ReturnType<typeof setTimeout>
let auditSearchTimer: ReturnType<typeof setTimeout>
watch(() => route.path, load)
watch(() => editing.value.role_ids, () => {
  if (isAdministratorRole.value) editing.value.menu_access_custom = false
  else if (requiresAssignedMenus.value) editing.value.menu_access_custom = true
}, { deep: true })
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 350)
})
watch(invoiceSearch, () => {
  if (page.value !== '/invoices') return
  clearTimeout(invoiceSearchTimer)
  invoiceSearchTimer = setTimeout(load, 350)
})
watch([invoiceStatusFilter, invoiceRouteFilter], () => { if (page.value === '/invoices') load() })
watch(auditSearch, () => {
  if (page.value !== '/audit-logs') return
  clearTimeout(auditSearchTimer)
  auditSearchTimer = setTimeout(load, 350)
})
watch(() => [auditFilters.action, auditFilters.user_id], () => { if (page.value === '/audit-logs') load() })
watch(showDeleted, load)
watch([householdRouteFilter, householdServiceFilter], load)
watch(paymentRouteFilter, () => {
  payment.household_ids = []
  load()
})
watch(() => payment.from_month, (value) => {
  payment.to_month = addMonthsToPeriod(value, payment.month_count)
})
watch(() => payment.month_count, (value) => {
  const normalized = Math.min(120, Math.max(1, Math.trunc(Number(value) || 1)))
  if (normalized !== value) payment.month_count = normalized
  payment.to_month = addMonthsToPeriod(payment.from_month, normalized)
})
watch(() => payment.to_month, () => {
  if (paymentMonthCount.value > 0 && payment.month_count !== paymentMonthCount.value) {
    payment.month_count = paymentMonthCount.value
  }
})
watch(() => payment.household_ids, async (householdIds) => {
  if (!householdIds.length) return
  try {
    const suggestions = await Promise.all(householdIds.map((householdId) => api<any>(`/households/${householdId}/payment-suggestion`)))
    const sortedSuggestions = suggestions.map((response) => response.data.next_month).sort()
    const suggestion = sortedSuggestions[sortedSuggestions.length - 1]
    payment.month_count = 1
    payment.from_month = suggestion
    payment.to_month = suggestion
  } catch (e:any) { error.value = e.message }
}, { deep: true })
watch(avatarFile, (file) => {
  if (avatarPreview.value.startsWith('blob:')) URL.revokeObjectURL(avatarPreview.value)
  avatarPreview.value = file ? URL.createObjectURL(file) : editing.value.avatar_url || ''
})
watch(profileAvatarFile, (file) => {
  if (profileAvatarPreview.value.startsWith('blob:')) URL.revokeObjectURL(profileAvatarPreview.value)
  profileAvatarPreview.value = file ? URL.createObjectURL(file) : (profileForm.remove_avatar ? '' : auth.user?.avatar_url || '')
  if (file) profileForm.remove_avatar = false
})
onMounted(async () => {
  await auth.restore()
  if (auth.user) await directiveStore.refresh()
  await load()
})
const directiveRefreshTimer = window.setInterval(() => { if (auth.user) directiveStore.refresh() }, 60000)
onBeforeUnmount(() => window.clearInterval(directiveRefreshTimer))
</script>

<template>
  <v-app>
    <v-snackbar v-model="directiveAlert" color="info" location="top right" timeout="7000"><div class="d-flex align-center ga-3"><v-icon icon="mdi-bell-ring-outline"/><div><div class="font-weight-bold">Có {{directiveStore.unread}} thông tin điều hành chưa xem</div><div class="text-caption">Mở hộp thư điều hành để xem nội dung mới.</div></div></div><template #actions><v-btn to="/directives/inbox" variant="text" @click="directiveAlert=false">Xem ngay</v-btn></template></v-snackbar>
    <div v-if="!auth.user" class="login-shell">
      <v-card class="login-card pa-8 pa-sm-10" width="440">
        <div class="login-logo mx-auto mb-5"><img src="/logo.png" alt="Logo Ban Quản lý phường An Khê" /></div>
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
      <v-navigation-drawer v-model="drawer" color="#216B47" width="292" class="app-sidebar">
        <div class="d-flex align-center ga-3 pa-4 sidebar-brand">
          <div class="sidebar-logo"><img src="/logo.png" alt="Logo Ban Quản lý phường An Khê" /></div>
          <div>
            <div class="text-subtitle-2 font-weight-bold text-white">BAN QUẢN LÝ PHƯỜNG</div>
            <div class="text-caption text-white opacity-80">An Khê · Gia Lai</div>
          </div>
        </div>
        <v-divider color="white" opacity="0.12" />
        <v-list nav class="sidebar-menu px-3 mt-3">
          <template v-for="menu in auth.user.menus" :key="menu.id">
            <v-list-group v-if="menu.children?.length" :value="menu.id">
              <template #activator="{ props }"><v-list-item v-bind="props" :prepend-icon="`mdi-${menu.icon || 'folder-outline'}`" :title="menu.name" color="white" rounded="lg" /></template>
              <v-list-item v-for="child in menu.children" :key="child.id" :to="child.path" :prepend-icon="menuIcons[child.path] || `mdi-${child.icon || 'circle-outline'}`" :title="child.name" color="white" rounded="lg" />
            </v-list-group>
            <v-list-item v-else :to="menu.path" :prepend-icon="menuIcons[menu.path] || `mdi-${menu.icon || 'circle-outline'}`" :title="menu.name" color="white" rounded="lg" />
          </template>
        </v-list>
        <template #append>
          <div class="pa-4">
            <v-card color="rgba(255,255,255,.08)" class="pa-3"
              ><div class="d-flex align-center ga-3">
                <v-avatar color="secondary" :image="auth.user.avatar_url || undefined"><span v-if="!auth.user.avatar_url">{{ auth.user.name[0] }}</span></v-avatar>
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
        <v-menu :close-on-content-click="false"><template #activator="{props}"><v-btn v-bind="props" icon variant="text"><v-badge :content="directiveStore.unread" :model-value="directiveStore.unread>0" color="error"><v-icon :icon="directiveStore.unread?'mdi-bell-ring-outline':'mdi-bell-outline'"/></v-badge></v-btn></template><v-card width="360" max-width="calc(100vw - 24px)" rounded="xl"><div class="d-flex align-center justify-space-between pa-4"><div><div class="font-weight-bold">Thông tin điều hành</div><div class="text-caption text-medium-emphasis">{{directiveStore.unread}} thông tin chưa xem</div></div><v-btn icon="mdi-refresh" size="small" variant="text" :loading="directiveStore.loading" @click="directiveStore.refresh"/></div><v-divider/><v-list v-if="directiveStore.recent.length" lines="two"><v-list-item v-for="item in directiveStore.recent" :key="item.id" to="/directives/inbox" prepend-icon="mdi-email-alert-outline" :title="item.title" :subtitle="`${item.creator?.name||'Hệ thống'} · ${new Date(item.created_at).toLocaleString('vi-VN')}`"/></v-list><div v-else class="pa-6 text-center text-medium-emphasis"><v-icon icon="mdi-check-circle-outline" color="success" size="36"/><div class="mt-2">Không có thông tin chưa xem</div></div><v-divider/><v-card-actions><v-btn to="/directives/inbox" color="primary" variant="text" block>Xem hộp thư điều hành</v-btn></v-card-actions></v-card></v-menu>
        <v-btn icon="mdi-theme-light-dark" variant="text" @click="toggleTheme" />
        <v-menu
          ><template #activator="{ props }"
            ><v-btn v-bind="props" icon variant="text"><v-avatar size="36" color="primary" variant="tonal" :image="auth.user.avatar_url || undefined"><span v-if="!auth.user.avatar_url" class="font-weight-bold">{{ auth.user.name?.[0]?.toUpperCase() }}</span></v-avatar></v-btn></template
          ><v-list min-width="230"
            ><v-list-item :title="auth.user.name" :subtitle="auth.user.email"><template #prepend><v-avatar size="38" color="primary" variant="tonal" :image="auth.user.avatar_url || undefined"><span v-if="!auth.user.avatar_url">{{ auth.user.name?.[0]?.toUpperCase() }}</span></v-avatar></template></v-list-item><v-divider class="my-2" /><v-list-item prepend-icon="mdi-account-edit-outline" title="Thông tin cá nhân" @click="openProfile" /><v-list-item
              prepend-icon="mdi-logout"
              title="Đăng xuất" base-color="error"
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

          <template v-if="page === '/'"><DashboardView /></template>

          <template v-else-if="page === '/inventory'"><InventoryView /></template>

          <template v-else-if="page === '/documents'"><DocumentManagementView /></template>

          <template v-else-if="page === '/directives/sent' || page === '/directives/inbox'"><DirectiveView /></template>

          <template v-else-if="page === '/reports'">
            <v-card class="payment-filter pa-4 pa-md-5 mb-5" border rounded="xl"><div class="d-flex flex-column flex-lg-row justify-space-between ga-4 mb-5"><div class="d-flex align-center ga-3"><v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-chart-box-outline" /></v-avatar><div><div class="font-weight-bold">Thiết lập báo cáo</div><div class="text-caption text-medium-emphasis">Doanh thu được tính theo căn cứ thời gian đã chọn</div></div></div><div class="d-flex flex-wrap ga-2"><v-btn-toggle v-model="reportFilters.report_type" color="primary" mandatory divided><v-btn value="summary" prepend-icon="mdi-chart-pie">Tổng hợp</v-btn><v-btn value="detail" prepend-icon="mdi-format-list-bulleted">Chi tiết</v-btn></v-btn-toggle><v-btn color="success" variant="tonal" prepend-icon="mdi-microsoft-excel" :loading="exportBusy" @click="exportReport('excel')">Excel</v-btn><v-btn color="error" variant="tonal" prepend-icon="mdi-file-pdf-box" :loading="exportBusy" @click="exportReport('pdf')">PDF</v-btn></div></div><v-row dense><v-col cols="12" md="4"><v-select v-model="reportFilters.basis" :items="[{title:'Theo ngày thu tiền',value:'paid_at'},{title:'Theo ngày xuất hóa đơn',value:'issued_at'}]" label="Căn cứ ghi nhận doanh thu" prepend-inner-icon="mdi-calendar-check-outline" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-text-field v-model="reportFilters.from_date" type="date" label="Từ ngày" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-text-field v-model="reportFilters.to_date" type="date" label="Đến ngày" hide-details /></v-col><v-col cols="12" md="4"><v-select v-model="reportFilters.dimension" :items="[{title:'Theo thời gian',value:'period'},{title:'Theo nhân viên thu',value:'collector'},{title:'Theo tuyến thu',value:'route'}]" label="Nhóm báo cáo" prepend-inner-icon="mdi-group" hide-details /></v-col><v-col v-if="reportFilters.dimension === 'period'" cols="12" md="4"><v-select v-model="reportFilters.period_unit" :items="[{title:'Theo tháng',value:'month'},{title:'Theo quý',value:'quarter'},{title:'Theo năm',value:'year'}]" label="Chu kỳ tổng hợp" prepend-inner-icon="mdi-calendar-range" hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="reportFilters.collector_id" :items="reportData.options.collectors" item-title="name" item-value="id" label="Tất cả nhân viên" prepend-inner-icon="mdi-account-tie-outline" clearable hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="reportFilters.collection_route_id" :items="reportData.options.routes" item-title="name" item-value="id" label="Tất cả tuyến thu" prepend-inner-icon="mdi-map-marker-path" clearable hide-details /></v-col><v-col cols="12" :md="reportFilters.dimension === 'period' ? 2 : 6"><v-btn color="primary" size="large" block prepend-icon="mdi-chart-bar" :loading="busy" @click="load">Xem báo cáo</v-btn></v-col></v-row><v-alert v-if="reportFilters.basis === 'issued_at'" class="mt-4" type="info" variant="tonal" density="compact">Chỉ tính các hóa đơn đã phát hành thành công, dựa trên ngày phát hành hóa đơn.</v-alert></v-card>
            <v-row class="mb-1"><v-col v-for="item in reportKpis" :key="item.label" cols="12" sm="6" lg="3"><v-card class="kpi-card pa-4 h-100" border rounded="xl"><div class="d-flex justify-space-between align-center ga-3"><div><div class="text-caption text-medium-emphasis mb-1">{{ item.label }}</div><div class="text-h6 font-weight-bold">{{ item.value }}</div></div><v-avatar :color="item.color" variant="tonal" rounded="lg"><v-icon :icon="item.icon" /></v-avatar></div></v-card></v-col></v-row>
            <v-card border rounded="xl"><div class="d-flex align-center justify-space-between pa-5"><div><div class="text-h6 font-weight-bold">{{ reportFilters.report_type === 'detail' ? 'Báo cáo doanh thu chi tiết' : 'Báo cáo doanh thu tổng hợp' }}</div><div class="text-caption text-medium-emphasis">{{ reportFilters.basis === 'issued_at' ? 'Căn cứ ngày xuất hóa đơn' : 'Căn cứ ngày thu tiền' }} · {{ new Date(reportFilters.from_date).toLocaleDateString('vi-VN') }} – {{ new Date(reportFilters.to_date).toLocaleDateString('vi-VN') }}</div></div><v-chip color="primary" variant="tonal">{{ reportData.summary.transactions || 0 }} giao dịch</v-chip></div><v-divider />
              <v-data-table v-if="reportFilters.report_type === 'summary'" :headers="[{title:'Nhóm báo cáo',key:'label'},{title:'Số giao dịch',key:'transactions',align:'center'},{title:'Số hộ dân',key:'households',align:'center'},{title:'Doanh thu',key:'amount',align:'end'},{title:'Tỷ trọng',key:'share',align:'end'}]" :items="reportData.groups" :loading="busy" hover items-per-page="15"><template #item.amount="{ value }"><strong class="text-success text-no-wrap">{{ money(value) }}</strong></template><template #item.share="{ item }"><span class="font-weight-medium">{{ reportShare(item).toLocaleString('vi-VN',{maximumFractionDigits:1}) }}%</span></template><template #no-data><div class="empty-state"><v-icon icon="mdi-chart-box-outline" size="52" /><div class="mt-2">Không có doanh thu trong kỳ báo cáo</div></div></template></v-data-table>
              <v-data-table v-else :headers="[{title:'Ngày ghi nhận',key:'date'},{title:'Mã phiếu',key:'code'},{title:'Số hóa đơn',key:'invoice_no'},{title:'Hộ dân',key:'household_name'},{title:'Tuyến thu',key:'route_name'},{title:'Nhân viên',key:'collector_name'},{title:'Kỳ thu',key:'period'},{title:'Số tiền',key:'amount',align:'end'}]" :items="reportData.details" :loading="busy" hover items-per-page="20"><template #item.date="{ value }"><span class="text-no-wrap">{{ value ? new Date(value).toLocaleString('vi-VN') : '—' }}</span></template><template #item.invoice_no="{ value }">{{ value || '—' }}</template><template #item.period="{ item }"><span class="text-no-wrap">{{ reportDetailPeriod(item) }}</span></template><template #item.amount="{ value }"><strong class="text-success text-no-wrap">{{ money(value) }}</strong></template><template #no-data><div class="empty-state"><v-icon icon="mdi-file-chart-outline" size="52" /><div class="mt-2">Không có giao dịch trong kỳ báo cáo</div></div></template></v-data-table>
            </v-card>
          </template>

          <template v-else-if="page === '/audit-logs'">
            <v-card class="payment-filter pa-4 pa-md-5 mb-5" border rounded="xl"><div class="d-flex align-center ga-3 mb-4"><v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-shield-search-outline" /></v-avatar><div><div class="font-weight-bold">Tra cứu nhật ký hoạt động</div><div class="text-caption text-medium-emphasis">Theo dõi người thực hiện, thao tác, thời gian và địa chỉ IP</div></div></div><v-row dense><v-col cols="12" md="4"><v-text-field v-model="auditSearch" label="Tìm người dùng, hành động, IP..." prepend-inner-icon="mdi-magnify" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="auditFilters.action" :items="auditActionOptions" label="Tất cả hành động" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="auditFilters.user_id" :items="auditData.users" item-title="name" item-value="id" label="Tất cả người dùng" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-text-field v-model="auditFilters.from_date" type="date" label="Từ ngày" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-text-field v-model="auditFilters.to_date" type="date" label="Đến ngày" hide-details /></v-col></v-row><div class="d-flex justify-end mt-4"><v-btn color="primary" prepend-icon="mdi-filter-check-outline" :loading="busy" @click="load">Áp dụng thời gian</v-btn></div></v-card>
            <v-card border rounded="xl"><div class="d-flex align-center justify-space-between pa-5"><div><div class="text-h6 font-weight-bold">Nhật ký hệ thống</div><div class="text-caption text-medium-emphasis">{{ auditData.logs.total || auditData.logs.data?.length || 0 }} hoạt động được ghi nhận</div></div><v-btn icon="mdi-refresh" color="primary" variant="tonal" title="Tải lại" :loading="busy" @click="load" /></div><v-divider /><v-data-table :headers="[{title:'Thời gian',key:'created_at'},{title:'Người thực hiện',key:'user.name'},{title:'Hành động',key:'action'},{title:'Đối tượng',key:'entity'},{title:'Địa chỉ IP',key:'ip_address'},{title:'Chi tiết',key:'actions',align:'end',sortable:false}]" :items="auditData.logs.data || []" :loading="busy" :sort-by="[{key:'created_at',order:'desc'}]" hover items-per-page="20"><template #item.created_at="{ value }"><span class="text-no-wrap">{{ new Date(value).toLocaleString('vi-VN') }}</span></template><template #[`item.user.name`]="{ item }"><div><div class="font-weight-medium">{{ auditUser(item)?.name || 'Không xác định' }}</div><div class="text-caption text-medium-emphasis">{{ auditUser(item)?.username || '—' }}</div></div></template><template #item.action="{ item }"><v-chip :color="auditActionFor(item)[1]" :prepend-icon="auditActionFor(item)[2]" size="small" variant="tonal" class="font-weight-medium">{{ auditActionFor(item)[0] }}</v-chip></template><template #item.entity="{ item }">{{ auditEntityFor(item) }}</template><template #item.ip_address="{ value }"><code>{{ value || '—' }}</code></template><template #item.actions="{ item }"><v-btn icon="mdi-eye-outline" size="small" color="primary" variant="text" title="Xem chi tiết" @click="showAuditDetail(item)" /></template><template #no-data><div class="empty-state"><v-icon icon="mdi-history" size="52" /><div class="mt-2">Chưa có nhật ký phù hợp</div></div></template></v-data-table></v-card>
          </template>

          <template v-else-if="page === '/invoices'">
            <v-card class="payment-filter pa-4 pa-md-5 mb-5" border rounded="xl"><div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-4"><div class="d-flex align-center ga-3"><v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-file-search-outline" /></v-avatar><div><div class="font-weight-bold">Tra cứu hóa đơn</div><div class="text-caption text-medium-emphasis">Tìm theo số hóa đơn, mã phiếu, mã hộ, tên hoặc số điện thoại</div></div></div><div class="d-flex flex-column flex-sm-row ga-3 invoice-filters"><v-text-field v-model="invoiceSearch" label="Nhập thông tin tra cứu" prepend-inner-icon="mdi-magnify" clearable hide-details /><v-select v-model="invoiceStatusFilter" :items="[{title:'Chờ phát hành',value:'CHO_PHAT_HANH'},{title:'Đã phát hành',value:'DA_PHAT_HANH'},{title:'Lỗi phát hành',value:'PHAT_HANH_LOI'}]" label="Tất cả trạng thái" clearable hide-details /><v-select v-model="invoiceRouteFilter" :items="invoiceData.routes" item-title="name" item-value="id" label="Tất cả tuyến thu" prepend-inner-icon="mdi-map-marker-path" clearable hide-details /></div></div></v-card>
            <v-row class="mb-1"><v-col v-for="item in invoiceKpis" :key="item.label" cols="12" sm="6" lg="3"><v-card class="kpi-card pa-4 h-100" border rounded="xl"><div class="d-flex justify-space-between align-center"><div><div class="text-caption text-medium-emphasis mb-1">{{ item.label }}</div><div class="text-h6 font-weight-bold">{{ item.value }}</div></div><v-avatar :color="item.color" variant="tonal" rounded="lg"><v-icon :icon="item.icon" /></v-avatar></div></v-card></v-col></v-row>
            <v-card border rounded="xl">
              <div class="d-flex align-center justify-space-between pa-5 ga-3"><div><div class="text-h6 font-weight-bold">Danh sách hóa đơn</div><div class="text-caption text-medium-emphasis">Hóa đơn được tạo tự động sau khi lập phiếu thu</div></div><div class="d-flex ga-2"><v-btn color="success" variant="tonal" prepend-icon="mdi-microsoft-excel" :loading="exportBusy" @click="exportInvoices">Export Excel</v-btn><v-btn icon="mdi-refresh" variant="tonal" color="primary" title="Tải lại" :loading="busy" @click="load" /></div></div>
              <v-divider />
              <v-data-table :headers="[{title:'Mã phiếu',key:'payment.code'},{title:'Số hóa đơn',key:'invoice_no'},{title:'Hộ dân',key:'household'},{title:'Tuyến thu',key:'payment.household.route.name'},{title:'Người thu',key:'payment.collector.name'},{title:'Người phát hành',key:'issuer.name'},{title:'Kỳ thu',key:'period'},{title:'Số tiền',key:'payment.amount',align:'end'},{title:'Ngày phát hành',key:'issued_at'},{title:'Trạng thái',key:'status'},{title:'Thao tác',key:'actions',align:'end',sortable:false}]" :items="invoiceData.items.data || []" :loading="busy" hover items-per-page="15">
                <template #[`item.payment.collector.name`]="{ item }">{{ (item as any).payment?.collector?.name || '—' }}</template>
                <template #[`item.issuer.name`]="{ item }">{{ (item as any).issuer?.name || '—' }}</template>
                <template #item.invoice_no="{ value }"><span v-if="value" class="font-weight-bold text-primary">{{ value }}</span><span v-else class="text-medium-emphasis">—</span></template>
                <template #item.household="{ item }"><div class="py-2"><div class="font-weight-medium">{{ invoiceHousehold(item)?.owner_name }}</div><div class="text-caption text-medium-emphasis">{{ invoiceHousehold(item)?.code }} · {{ invoiceHousehold(item)?.phone || 'Chưa có SĐT' }}</div></div></template>
                <template #item.period="{ item }"><span class="text-no-wrap">{{ invoicePeriod(item) }}</span></template>
                <template #[`item.payment.amount`]="{ value }"><strong class="text-primary text-no-wrap">{{ money(value) }}</strong></template>
                <template #item.issued_at="{ value }">{{ value ? new Date(value).toLocaleString('vi-VN') : '—' }}</template>
                <template #item.status="{ item }"><v-chip :color="invoiceStatus(item)[1]" size="small" variant="tonal" class="font-weight-medium">{{ invoiceStatus(item)[0] }}</v-chip></template>
                <template #item.actions="{ item }"><div class="d-flex justify-end ga-1"><v-btn v-if="canPublishInvoice(item)" :color="invoiceIsFailed(item) ? 'error' : 'primary'" variant="tonal" size="small" prepend-icon="mdi-send-outline" :loading="invoiceBusy" @click="publishInvoices([invoicePaymentId(item)])">{{ invoiceIsFailed(item) ? 'Phát hành lại' : 'Phát hành' }}</v-btn><v-btn v-if="invoiceIsPublished(item)" color="success" variant="tonal" size="small" prepend-icon="mdi-file-download-outline" @click="openPaymentPdf(invoicePaymentId(item), 'invoice')">Xem hóa đơn</v-btn></div></template>
                <template #no-data><div class="empty-state"><v-icon icon="mdi-receipt-text-remove-outline" size="52" /><div class="mt-2">Không tìm thấy hóa đơn phù hợp</div></div></template>
              </v-data-table>
            </v-card>
          </template>

          <template v-else-if="page === '/debts'">
            <v-card class="payment-filter pa-4 pa-md-5 mb-5" border rounded="xl"><div class="d-flex align-center ga-3 mb-4"><v-avatar color="warning" variant="tonal" rounded="lg"><v-icon icon="mdi-filter-variant" /></v-avatar><div><div class="font-weight-bold">Bộ lọc công nợ</div><div class="text-caption text-medium-emphasis">Khoảng thời gian tính theo các tháng chưa thanh toán</div></div></div><v-row dense align="center"><v-col cols="12" sm="6" lg="3"><v-select v-model="debtFilters.collection_route_id" :items="debtData.options.routes" item-title="name" item-value="id" label="Tất cả tuyến thu" prepend-inner-icon="mdi-map-marker-path" clearable hide-details /></v-col><v-col cols="12" sm="6" lg="3"><v-select v-model="debtFilters.collector_id" :items="debtData.options.collectors" item-title="name" item-value="id" label="Tất cả nhân viên" prepend-inner-icon="mdi-account-tie-outline" clearable hide-details /></v-col><v-col cols="12" sm="6" lg="2"><MonthPicker v-model="debtFilters.from_month" label="Từ tháng" /></v-col><v-col cols="12" sm="6" lg="2"><MonthPicker v-model="debtFilters.to_month" label="Đến tháng" :min="debtFilters.from_month" /></v-col><v-col cols="12" lg="2"><v-btn color="primary" size="large" block prepend-icon="mdi-magnify" :loading="busy" @click="load">Lọc dữ liệu</v-btn></v-col></v-row><div class="d-flex align-center flex-wrap ga-3 mt-4"><v-switch v-model="debtFilters.over_six_months" color="error" label="Chỉ hiện hộ nợ trên 6 tháng" hide-details inset @update:model-value="load" /><v-btn v-if="debtFilters.from_month" size="small" variant="text" prepend-icon="mdi-calendar-remove-outline" @click="debtFilters.from_month = ''; load()">Tính từ khi bắt đầu dịch vụ</v-btn></div></v-card>
            <v-row class="mb-1"><v-col v-for="item in debtKpis" :key="item.label" cols="12" sm="6" lg="3"><v-card class="kpi-card pa-4 h-100" border rounded="xl"><div class="d-flex justify-space-between align-center ga-3"><div><div class="text-caption text-medium-emphasis mb-1">{{ item.label }}</div><div class="text-h6 font-weight-bold">{{ item.value }}</div></div><v-avatar :color="item.color" variant="tonal" rounded="lg"><v-icon :icon="item.icon" /></v-avatar></div></v-card></v-col></v-row>
            <div class="d-flex justify-end mb-3"><v-btn color="success" variant="tonal" prepend-icon="mdi-microsoft-excel" :loading="exportBusy" @click="exportDebts">Export danh sách chưa thu</v-btn></div>
            <v-card border rounded="xl" overflow-x="auto"><div class="d-flex align-center justify-space-between pa-5"><div><div class="text-h6 font-weight-bold">Danh sách hộ còn công nợ</div><div class="text-caption text-medium-emphasis">Sắp xếp theo số tháng nợ và mức độ quá hạn</div></div><v-chip color="error" variant="tonal" prepend-icon="mdi-alert-outline">{{ debtData.summary.over_six_months || 0 }} hộ cảnh báo</v-chip></div><v-divider /><v-data-table :headers="[{title:'STT',key:'sequence_number'},{title:'Mã hộ',key:'code'},{title:'Hộ dân',key:'owner_name'},{title:'Tuyến thu',key:'route.name'},{title:'Nhân viên',key:'collectors'},{title:'Kỳ nợ',key:'debt_period'},{title:'Còn nợ',key:'debt_months',align:'center'},{title:'Quá hạn',key:'overdue_months',align:'center'},{title:'Tổng tiền nợ',key:'total_debt',align:'end'}]" :items="debtData.items" :loading="busy" :sort-by="[{key:'debt_months',order:'desc'}]" hover items-per-page="15"><template #item.collectors="{ item }">{{ debtCollectors(item) }}</template><template #item.debt_period="{ item }"><span class="text-no-wrap">{{ debtPeriod(item) }}</span></template><template #item.debt_months="{ value }"><v-chip :color="value > 6 ? 'error' : value >= 3 ? 'warning' : 'info'" size="small" variant="tonal" class="font-weight-bold">{{ value }} tháng</v-chip></template><template #item.overdue_months="{ value }"><span :class="value > 6 ? 'text-error font-weight-bold' : ''">{{ value }} tháng</span></template><template #item.total_debt="{ value }"><strong class="text-error text-no-wrap">{{ money(value) }}</strong></template><template #no-data><div class="empty-state"><v-icon icon="mdi-check-decagram-outline" color="success" size="52" /><div class="mt-2">Không có hộ dân còn nợ trong kỳ đã chọn</div></div></template></v-data-table></v-card>
          </template>

          <template v-else-if="page === '/payments'">
            <v-card class="payment-filter pa-4 mb-5" border rounded="xl"><div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-3"><div><div class="font-weight-bold"><v-icon icon="mdi-routes" color="primary" class="mr-2" />Chọn tuyến thu</div><div class="text-caption text-medium-emphasis mt-1">Lọc hộ dân và giao dịch theo tuyến phụ trách</div></div><v-select v-model="paymentRouteFilter" :items="routeOptions.routes || []" item-title="name" item-value="id" label="Tất cả tuyến thu" prepend-inner-icon="mdi-map-marker-path" hide-details clearable max-width="420" /></div></v-card>
            <v-row align="start"><v-col cols="12" lg="5"><v-card class="payment-form-card" border rounded="xl"><div class="payment-form-card__header"><v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-cash-register" /></v-avatar><div><div class="text-h6 font-weight-bold">Lập phiếu thu</div><div class="text-caption text-medium-emphasis">Thu phí theo khoảng tháng</div></div></div><v-divider /><v-card-text class="pa-4 pa-sm-5"><v-form @submit.prevent="collect()">
              <div class="payment-step"><div class="payment-step__label"><span>1</span> Chọn hộ dân</div><v-autocomplete v-model="payment.household_ids" :items="paymentHouseholdOptions" item-title="payment_label" item-value="id" label="Các hộ dân cần thu" prepend-inner-icon="mdi-home-account" no-data-text="Không có hộ dân trong tuyến này" multiple chips closable-chips required /><div v-if="payment.household_ids.length" class="text-caption text-medium-emphasis mt-n2">Đã chọn {{ payment.household_ids.length }} hộ dân · Mỗi hộ sẽ có một phiếu thu riêng</div></div>
              <div class="payment-step"><div class="payment-step__label"><span>2</span> Chọn kỳ thanh toán</div><v-row dense align="start"><v-col cols="12" sm="5"><MonthPicker v-model="payment.from_month" label="Từ tháng" /></v-col><v-col cols="12" sm="2"><v-text-field :model-value="String(payment.month_count)" class="month-count-field" type="text" inputmode="numeric" label="Số tháng" hide-details required @update:model-value="payment.month_count = Number($event) || 1" /></v-col><v-col cols="12" sm="5"><MonthPicker v-model="payment.to_month" label="Đến tháng" :min="payment.from_month" /></v-col></v-row><div class="period-summary"><v-icon icon="mdi-calendar-range" size="20" /><div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between flex-grow-1 ga-1"><span>Kỳ thu: <strong>{{ monthLabel(payment.from_month) }} – {{ monthLabel(payment.to_month) }}</strong></span><v-chip color="primary" size="small" variant="tonal" prepend-icon="mdi-calendar-multiselect">{{ paymentMonthCount }} tháng</v-chip></div></div></div>
              <div class="payment-step"><div class="payment-step__label"><span>3</span> Thanh toán</div><v-select v-model="payment.payment_method" :items="[{ title: 'Tiền mặt', value: 'TIEN_MAT' },{ title: 'Chuyển khoản', value: 'CHUYEN_KHOAN' }]" label="Hình thức thanh toán" prepend-inner-icon="mdi-credit-card-outline" /><v-textarea v-model="payment.note" label="Ghi chú (không bắt buộc)" prepend-inner-icon="mdi-note-text-outline" rows="2" auto-grow /></div>
              <v-row dense><v-col cols="12" sm="6"><v-btn color="primary" size="large" type="submit" block prepend-icon="mdi-check-circle-outline" :loading="paymentSubmitting">Xác nhận thu phí</v-btn></v-col><v-col cols="12" sm="6"><v-btn color="secondary" size="large" block prepend-icon="mdi-receipt-text-arrow-right-outline" :loading="paymentSubmitting || invoiceBusy" @click="collect(true)">Thu & phát hành HĐ</v-btn></v-col></v-row>
            </v-form></v-card-text></v-card></v-col>
              <v-col cols="12" lg="7"><v-card border rounded="xl"><div class="d-flex align-center justify-space-between pa-5"><div><div class="text-h6 font-weight-bold">Giao dịch gần đây</div><div class="text-caption text-medium-emphasis">{{ paymentRouteFilter ? 'Theo tuyến đã chọn' : 'Tất cả tuyến thu' }}</div></div><v-avatar color="success" variant="tonal" rounded="lg"><v-icon icon="mdi-receipt-text-check-outline" /></v-avatar></div><v-divider />
                  <div class="payment-table"><v-table hover><thead>
                      <tr>
                        <th>Mã phiếu</th>
                        <th>Số hóa đơn</th>
                        <th>Hộ dân</th>
                        <th>Người thu</th>
                        <th>Người phát hành</th>
                        <th>Khoảng thu</th>
                        <th>Số tiền</th>
                        <th>Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in rows" :key="row.id">
                        <td class="font-weight-medium">{{ row.code }}</td>
                        <td><span v-if="row.invoice?.invoice_no" class="font-weight-medium text-primary">{{ row.invoice.invoice_no }}</span><span v-else class="text-medium-emphasis">—</span></td>
                        <td>{{ row.household?.owner_name }}</td>
                        <td>{{ row.collector?.name || '—' }}</td>
                        <td>{{ row.invoice?.issuer?.name || '—' }}</td>
                        <td>{{ monthLabel(row.from_month?.slice(0, 7)) }} → {{ monthLabel(row.to_month?.slice(0, 7)) }}</td>
                        <td class="font-weight-bold">{{ money(row.amount) }}</td>
                        <td>
                          <v-chip :color="invoiceStatus(row.invoice)[1]" size="small" variant="tonal">{{ invoiceStatus(row.invoice)[0] }}</v-chip>
                        </td>
                        <td class="text-right"><v-menu><template #activator="{ props }"><v-btn v-bind="props" icon="mdi-dots-vertical" size="small" variant="text" /></template><v-list density="compact"><v-list-item prepend-icon="mdi-calculator-variant-outline" title="Chi tiết áp giá" @click="showPaymentPriceDetail(row)" /><v-list-item v-if="row.status === 'DA_THU' && row.invoice?.status !== 'DA_PHAT_HANH'" prepend-icon="mdi-receipt-text-arrow-right-outline" title="Phát hành hóa đơn" @click="publishInvoices([row.id])" /><v-list-item prepend-icon="mdi-printer-outline" title="In phiếu thu" @click="openPaymentPdf(row.id, 'receipt')" /><v-list-item v-if="row.invoice?.status === 'DA_PHAT_HANH'" prepend-icon="mdi-file-document-check-outline" title="In hóa đơn" @click="openPaymentPdf(row.id, 'invoice')" /><v-divider v-if="canDeletePendingPayment(row)" class="my-1"/><v-list-item v-if="canDeletePendingPayment(row)" prepend-icon="mdi-delete-outline" title="Xóa phiếu thu" base-color="error" @click="deletePendingPayment(row)" /></v-list></v-menu></td>
                      </tr>
                    </tbody></v-table></div>
                  <div class="payment-cards pa-3"><v-card v-for="row in rows" :key="row.id" class="pa-4 mb-3" variant="tonal" rounded="lg"><div class="d-flex justify-space-between ga-3"><div><div class="font-weight-bold">{{ row.household?.owner_name }}</div><div class="text-caption text-medium-emphasis">{{ row.code }} · {{ row.household?.route?.name || 'Chưa có tuyến' }}</div><div v-if="row.invoice?.invoice_no" class="text-caption text-primary font-weight-medium mt-1">HĐ số: {{ row.invoice.invoice_no }}</div></div><v-menu><template #activator="{ props }"><v-btn v-bind="props" icon="mdi-dots-vertical" size="small" variant="text" /></template><v-list density="compact"><v-list-item v-if="row.status === 'DA_THU' && row.invoice?.status !== 'DA_PHAT_HANH'" prepend-icon="mdi-receipt-text-arrow-right-outline" title="Phát hành hóa đơn" @click="publishInvoices([row.id])" /><v-list-item prepend-icon="mdi-printer-outline" title="In phiếu thu" @click="openPaymentPdf(row.id, 'receipt')" /><v-list-item v-if="row.invoice?.status === 'DA_PHAT_HANH'" prepend-icon="mdi-file-document-check-outline" title="In hóa đơn" @click="openPaymentPdf(row.id, 'invoice')" /><v-divider v-if="canDeletePendingPayment(row)" class="my-1"/><v-list-item v-if="canDeletePendingPayment(row)" prepend-icon="mdi-delete-outline" title="Xóa phiếu thu" base-color="error" @click="deletePendingPayment(row)" /></v-list></v-menu></div><v-divider class="my-3" /><div class="d-flex justify-space-between text-body-2 mb-3"><span>{{ monthLabel(row.from_month?.slice(0, 7)) }} – {{ monthLabel(row.to_month?.slice(0, 7)) }}</span><strong class="text-primary">{{ money(row.amount) }}</strong></div><v-chip :color="invoiceStatus(row.invoice)[1]" size="small" variant="tonal">{{ invoiceStatus(row.invoice)[0] }}</v-chip></v-card><div v-if="!rows.length" class="empty-state">Chưa có giao dịch</div></div>
                </v-card></v-col></v-row>
          </template>

          <template v-else-if="page === '/settings'">
            <v-form @submit.prevent="saveInvoiceSettings"><div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-3 mb-5"><div><div class="text-h6 font-weight-bold">Cấu hình hóa đơn điện tử</div><div class="text-body-2 text-medium-emphasis">Thông tin đơn vị, ngân hàng và kết nối VNPT Invoice</div></div><v-btn color="primary" size="large" type="submit" prepend-icon="mdi-content-save-outline" :loading="settingsSaving">Lưu cấu hình</v-btn></div>
              <v-row><v-col v-for="group in [{key:'organization',title:'Thông tin đơn vị',icon:'mdi-office-building-outline'},{key:'invoice',title:'Thông tin hóa đơn',icon:'mdi-receipt-text-outline'},{key:'bank',title:'Thông tin ngân hàng',icon:'mdi-bank-outline'},{key:'vnpt',title:'Kết nối VNPT',icon:'mdi-cloud-sync-outline'}]" :key="group.key" cols="12" :lg="group.key === 'vnpt' ? 12 : 6"><v-card class="pa-5 h-100" border rounded="xl"><div class="form-section__title"><v-icon :icon="group.icon" />{{ group.title }}</div><v-row dense><v-col v-for="setting in invoiceSettings.filter(item => item.group === group.key)" :key="setting.key" cols="12" :md="group.key === 'vnpt' ? 6 : 12"><v-text-field v-model="setting.value" :label="setting.key" :type="setting.is_secret ? 'password' : 'text'" :prepend-inner-icon="setting.is_secret ? 'mdi-lock-outline' : undefined" :hint="setting.is_secret && setting.has_value ? 'Đã cấu hình - để trống nếu không thay đổi' : setting.description" persistent-hint /></v-col></v-row></v-card></v-col></v-row>
            </v-form>
          </template>

          <template v-else-if="config">
            <v-tabs v-if="routeManagementPages.includes(page)" color="primary" class="mb-5" show-arrows>
              <v-tab to="/provinces">Tỉnh</v-tab><v-tab to="/wards">Phường / xã</v-tab><v-tab to="/neighborhoods">Thôn / xóm / tổ</v-tab><v-tab to="/routes">Tuyến thu</v-tab>
            </v-tabs>
            <v-checkbox v-if="routeManagementPages.includes(page) || page === '/households'" v-model="showDeleted" label="Hiển thị dữ liệu đã xóa" color="primary" hide-details class="mb-3" />
            <div v-if="page === '/routes'" class="d-flex ga-2 mb-4 flex-wrap"><input ref="importInput" type="file" accept=".xlsx,.xls" hidden @change="importRoutes" /><v-btn variant="outlined" prepend-icon="mdi-download" @click="downloadRouteTemplate">Tải file mẫu</v-btn><v-btn variant="outlined" prepend-icon="mdi-file-excel" @click="importInput?.click()">Import Excel</v-btn></div>
            <v-card v-if="page === '/households'" class="pa-4 mb-5 household-tools" border rounded="lg"><div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-4"><div><div class="font-weight-bold">Dữ liệu hộ dân</div><div class="text-caption text-medium-emphasis">Nhập dữ liệu theo file mẫu hoặc xuất danh sách đang lọc</div></div><div class="d-flex ga-2 flex-wrap"><input ref="householdImportInput" type="file" accept=".xlsx,.xls" hidden @change="importHouseholds" /><v-btn variant="outlined" prepend-icon="mdi-file-download-outline" @click="downloadHouseholdTemplate">Tải file mẫu</v-btn><v-btn color="success" variant="tonal" prepend-icon="mdi-file-upload-outline" @click="householdImportInput?.click()">Upload Excel</v-btn><v-btn color="success" prepend-icon="mdi-microsoft-excel" :loading="exportBusy" @click="exportHouseholds">Export Excel</v-btn></div></div></v-card>
            <v-card v-if="page === '/households'" class="pa-4 mb-5" border rounded="lg">
              <div class="text-subtitle-2 font-weight-bold mb-3"><v-icon icon="mdi-filter-variant" color="primary" class="mr-2" />Tìm kiếm và lọc hộ dân</div>
              <v-row dense align="center">
                <v-col cols="12" md="5"><v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Tên, SĐT, CCCD, MST hoặc địa chỉ" hide-details clearable /></v-col>
                <v-col cols="12" md="3"><v-select v-model="householdRouteFilter" :items="routeOptions.routes || []" item-title="name" item-value="id" prepend-inner-icon="mdi-routes" label="Tuyến thu" hide-details clearable /></v-col>
                <v-col cols="12" md="3"><v-select v-model="householdServiceFilter" :items="routeOptions.services || []" item-title="name" item-value="id" prepend-inner-icon="mdi-recycle-variant" label="Loại dịch vụ" hide-details clearable /></v-col>
                <v-col cols="12" md="1" class="text-md-end"><v-btn color="primary" icon="mdi-plus" size="large" title="Thêm hộ dân" @click="openForm()" /></v-col>
              </v-row>
            </v-card>
            <div v-else class="d-flex flex-column flex-sm-row justify-space-between ga-3 mb-5">
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
                ><template #item.status_display="{ item }"><v-chip :color="item.is_active ? 'success' : 'default'" size="small" variant="tonal">{{ item.status_display }}</v-chip></template><template #item.actions="{ item }"
                  ><v-btn v-if="item.deleted_at" icon="mdi-restore" size="small" variant="text" color="success" title="Khôi phục" @click="restore(item)" /><template v-else><v-btn
                    v-if="page === '/households'"
                    icon="mdi-history"
                    size="small"
                    variant="text"
                    color="secondary"
                    title="Lịch sử thanh toán"
                    @click="showPaymentHistory(item)" /><v-btn
                    v-if="page === '/services'"
                    icon="mdi-file-document-edit-outline"
                    size="small"
                    variant="text"
                    color="secondary"
                    title="Giai đoạn áp giá"
                    @click="openPricePeriods(item)" /><v-btn
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
              <v-col cols="12" md="6"><v-text-field v-model="editing.name" label="Họ và tên *" prepend-inner-icon="mdi-account" required /></v-col><v-col cols="12" md="6"><v-menu v-model="birthDateMenu" :close-on-content-click="false" location="bottom" max-width="360"><template #activator="{ props }"><v-text-field v-bind="props" :model-value="birthDateLabel(editing.date_of_birth)" label="Ngày sinh" placeholder="dd/mm/yyyy" prepend-inner-icon="mdi-calendar-heart" append-inner-icon="mdi-calendar-chevron-down" readonly clearable @click:clear.stop="editing.date_of_birth = null" /></template><v-date-picker :model-value="editing.date_of_birth ? new Date(`${editing.date_of_birth}T00:00:00`) : null" :max="new Date()" title="Chọn ngày sinh" color="primary" show-adjacent-months @update:model-value="setBirthDate" /></v-menu></v-col>
              <v-col cols="12" md="6"><v-select v-model="editing.gender" :items="[{title:'Nam',value:'NAM'},{title:'Nữ',value:'NU'},{title:'Khác',value:'KHAC'}]" label="Giới tính" prepend-inner-icon="mdi-gender-male-female" clearable /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.identity_number" label="Số giấy tờ" prepend-inner-icon="mdi-card-account-details-outline" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="editing.phone" label="Số điện thoại" prepend-inner-icon="mdi-phone-outline" /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.email" label="Email *" type="email" prepend-inner-icon="mdi-email-outline" required /></v-col><v-col cols="12"><v-text-field v-model="editing.address" label="Địa chỉ" prepend-inner-icon="mdi-map-marker-outline" /></v-col>
            </v-row></div>
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-shield-account-outline" /> Tài khoản và phân quyền</div><v-row dense>
              <v-col cols="12" md="6"><v-text-field v-model="editing.username" label="Tài khoản *" prepend-inner-icon="mdi-at" required /></v-col><v-col cols="12" md="6"><v-select v-model="editing.role_ids" :items="userOptions.roles" item-title="name" item-value="id" label="Vai trò *" prepend-inner-icon="mdi-shield-key-outline" multiple chips required /></v-col>
              <v-col cols="12"><v-select v-model="editing.route_ids" :items="userOptions.routes" item-title="name" item-value="id" label="Phân tuyến đường thu tiền" prepend-inner-icon="mdi-map-marker-path" multiple chips clearable /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.password" :label="editing.id ? 'Mật khẩu mới' : 'Mật khẩu *'" type="password" prepend-inner-icon="mdi-lock-outline" :hint="editing.id ? 'Để trống nếu không đổi mật khẩu' : 'Tối thiểu 8 ký tự'" persistent-hint :required="!editing.id" /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.password_confirmation" label="Xác nhận mật khẩu" type="password" prepend-inner-icon="mdi-lock-check-outline" :required="!editing.id || !!editing.password" /></v-col>
            </v-row></div>
            <div class="form-section"><div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-2 mb-3"><div class="form-section__title mb-0"><v-icon icon="mdi-menu-open" /> Phân quyền menu hiển thị</div><v-switch v-model="editing.menu_access_custom" label="Tùy chỉnh theo tài khoản" color="primary" :disabled="requiresAssignedMenus" hide-details inset /></div><v-alert v-if="requiresAssignedMenus" type="info" variant="tonal" density="compact" class="mb-3">Vai trò Lãnh đạo và Kế toán có đầy đủ quyền hệ thống nhưng chỉ hiển thị những menu được chọn bên dưới.</v-alert><v-alert v-else-if="!editing.menu_access_custom" type="info" variant="tonal" density="compact">Tài khoản sẽ nhìn thấy toàn bộ menu phù hợp với vai trò đã chọn.</v-alert><v-row v-if="editing.menu_access_custom" dense><v-col v-for="group in userMenuGroups" :key="group.name" cols="12" md="6"><v-card border rounded="lg" class="pa-3 h-100"><div class="text-subtitle-2 font-weight-bold mb-2"><v-icon icon="mdi-folder-outline" size="18" class="mr-1" />{{ group.name }}</div><v-checkbox v-for="menu in group.menus" :key="menu.id" v-model="editing.menu_ids" :value="menu.id" :label="menu.name" density="compact" color="primary" hide-details /></v-card></v-col></v-row><v-alert v-if="editing.menu_access_custom" type="warning" variant="tonal" density="compact" class="mt-3">Phải chọn ít nhất một menu. Tài khoản chỉ nhìn thấy các menu đã được phân công.</v-alert></div>
            <div class="status-panel"><div><div class="font-weight-bold">Trạng thái tài khoản</div><div class="text-caption text-medium-emphasis">Cho phép người dùng đăng nhập và sử dụng hệ thống</div></div><v-switch v-model="editing.is_active" :label="editing.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'" color="success" hide-details inset /></div>
          </v-form></v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="modal = false">Hủy</v-btn><v-btn color="primary" size="large" type="submit" form="user-form" prepend-icon="mdi-content-save-outline">{{ editing.id ? 'Lưu thay đổi' : 'Thêm người dùng' }}</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-else-if="page === '/households'" v-model="modal" max-width="980" scrollable>
        <v-card class="user-modal" rounded="xl">
          <div class="user-modal__header"><div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-home-account" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">{{ editing.id ? 'Cập nhật hộ dân' : 'Thêm hộ dân mới' }}</div><div class="text-body-2 opacity-80">Hồ sơ liên hệ, tuyến thu và dịch vụ đang sử dụng</div></div></div><v-btn icon="mdi-close" variant="text" color="white" @click="modal = false" /></div>
          <v-card-text class="user-modal__body"><v-form id="household-form" @submit.prevent="save">
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-account-details-outline" /> Thông tin hộ dân</div><v-row dense>
              <v-col cols="12" md="3"><v-text-field v-model="editing.sequence_number" label="Số thứ tự" type="number" min="1" prepend-inner-icon="mdi-numeric" /></v-col><v-col cols="12" md="3"><v-text-field v-model="editing.code" label="Mã hộ *" prepend-inner-icon="mdi-barcode" required /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.owner_name" label="Họ tên *" prepend-inner-icon="mdi-account-outline" required /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="editing.phone" label="Số điện thoại" prepend-inner-icon="mdi-phone-outline" /></v-col><v-col cols="12" md="4"><v-text-field v-model="editing.email" label="Email" type="email" prepend-inner-icon="mdi-email-outline" /></v-col><v-col cols="12" md="4"><v-text-field v-model="editing.identity_number" label="CCCD" prepend-inner-icon="mdi-card-account-details-outline" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="editing.tax_code" label="Mã số thuế" prepend-inner-icon="mdi-file-document-outline" /></v-col><v-col cols="12" md="6"><v-text-field v-model="editing.representative" label="Người đại diện" prepend-inner-icon="mdi-account-tie-outline" /></v-col>
            </v-row></div>
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-map-marker-path" /> Tuyến thu và dịch vụ</div><v-row dense>
              <v-col cols="12"><v-select v-model="editing.collection_route_id" :items="routeOptions.routes || []" item-title="name" item-value="id" label="Tuyến thu" prepend-inner-icon="mdi-routes" clearable /></v-col>
              <v-col cols="12"><v-text-field v-model="editing.address" label="Địa chỉ *" prepend-inner-icon="mdi-map-marker-outline" required /></v-col>
              <v-col cols="12"><v-text-field v-model="editing.invoice_address" label="Địa chỉ HĐ" prepend-inner-icon="mdi-receipt-text-outline" hint="Địa chỉ dùng khi xuất hóa đơn; để trống sẽ dùng địa chỉ hộ dân" persistent-hint /></v-col>
              <v-col cols="12"><v-select v-model="editing.service_id" :items="routeOptions.services || []" item-title="name" item-value="id" label="Loại dịch vụ *" prepend-inner-icon="mdi-recycle-variant" required /></v-col>
              <v-col cols="12" md="6"><v-menu v-model="householdServiceDateMenu" :close-on-content-click="false" location="bottom" max-width="360"><template #activator="{ props }"><v-text-field v-bind="props" :model-value="birthDateLabel(editing.service_started_at)" label="Ngày bắt đầu sử dụng dịch vụ" placeholder="dd/mm/yyyy" prepend-inner-icon="mdi-calendar-start" append-inner-icon="mdi-calendar-chevron-down" hint="Kỳ thu trước ngày này sẽ không được tính" persistent-hint readonly clearable @click:clear.stop="editing.service_started_at = null" /></template><v-date-picker :model-value="editing.service_started_at ? new Date(`${editing.service_started_at}T00:00:00`) : null" title="Chọn ngày bắt đầu dịch vụ" color="primary" show-adjacent-months @update:model-value="setHouseholdServiceDate" /></v-menu></v-col>
              <v-col cols="12"><v-textarea v-model="editing.note" label="Ghi chú" prepend-inner-icon="mdi-note-text-outline" rows="3" auto-grow /></v-col>
            </v-row></div>
            <div class="status-panel"><div><div class="font-weight-bold">Trạng thái hộ dân</div><div class="text-caption text-medium-emphasis">Hộ đang hoạt động mới được đưa vào nghiệp vụ thu phí</div></div><v-switch v-model="editing.is_active" :label="editing.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'" color="success" hide-details inset /></div>
          </v-form></v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="modal = false">Hủy</v-btn><v-btn color="primary" size="large" type="submit" form="household-form" prepend-icon="mdi-content-save-outline">{{ editing.id ? 'Lưu thay đổi' : 'Thêm hộ dân' }}</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-else v-model="modal" max-width="760" scrollable>
        <v-card class="user-modal" rounded="xl">
          <div class="user-modal__header">
            <div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" :icon="config?.icon || 'mdi-pencil-outline'" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">{{ editing.id ? 'Cập nhật' : 'Thêm' }} {{ config?.title.toLowerCase() }}</div><div class="text-body-2 opacity-80">Quản lý thông tin {{ config?.title.toLowerCase() }} trong hệ thống</div></div></div>
            <v-btn icon="mdi-close" variant="text" color="white" @click="modal = false" />
          </div>
          <v-card-text class="user-modal__body">
            <v-form id="resource-form" @submit.prevent="save">
              <v-alert v-if="page === '/services' && editing.id" type="info" variant="tonal" density="compact" class="mb-4">Đơn giá và thuế hiện hành được quản lý tại nút <strong>Giai đoạn áp giá</strong> ở danh sách dịch vụ.</v-alert>
              <div class="form-section"><div class="form-section__title"><v-icon :icon="config?.icon || 'mdi-form-select'" /> Thông tin chi tiết</div><v-row dense><v-col v-for="field in config?.fields" :key="field[0]" cols="12" :md="field[0] === 'description' || field[2] === 'multiselect' ? 12 : 6"><v-select
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
                :prefix="field[0] === 'monthly_price' ? '₫' : undefined"
                :suffix="field[0] === 'tax_fee' ? '%' : undefined"
                :min="field[2] === 'number' ? 0 : undefined"
                :disabled="page === '/services' && !!editing.id && ['monthly_price','tax_fee'].includes(field[0])"
                :required="
                  !['phone', 'description', 'password', 'date_of_birth', 'gender', 'identity_number', 'address'].includes(field[0])
                " /></v-col></v-row></div>
              <div v-if="page === '/services'" class="status-panel"><div><div class="font-weight-bold">Trạng thái dịch vụ</div><div class="text-caption text-medium-emphasis">Cho phép áp dụng dịch vụ cho hộ dân</div></div><v-switch v-model="editing.is_active" :label="editing.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'" color="success" hide-details inset /></div>
            </v-form>
          </v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="modal = false">Hủy</v-btn><v-btn color="primary" size="large" type="submit" form="resource-form" prepend-icon="mdi-content-save-outline">{{ editing.id ? 'Lưu thay đổi' : 'Thêm mới' }}</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
      <v-dialog v-model="auditModal" max-width="900" scrollable>
        <v-card class="user-modal" rounded="xl"><div class="user-modal__header"><div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-file-document-search-outline" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">Chi tiết nhật ký</div><div class="text-body-2 opacity-80">{{ auditDetail ? auditAction(auditDetail.action)[0] : '' }} · {{ auditDetail ? new Date(auditDetail.created_at).toLocaleString('vi-VN') : '' }}</div></div></div><v-btn icon="mdi-close" color="white" variant="text" @click="auditModal = false" /></div><v-card-text class="user-modal__body"><v-row><v-col cols="12" md="6"><v-card class="pa-4 h-100" border rounded="lg"><div class="text-caption text-medium-emphasis">Người thực hiện</div><div class="font-weight-bold mt-1">{{ auditDetail?.user?.name || 'Không xác định' }}</div><div class="text-caption">{{ auditDetail?.user?.username || '—' }}</div></v-card></v-col><v-col cols="12" md="6"><v-card class="pa-4 h-100" border rounded="lg"><div class="text-caption text-medium-emphasis">Đối tượng và IP</div><div class="font-weight-bold mt-1">{{ auditDetail ? auditEntityFor(auditDetail) : '—' }}</div><div class="text-caption">IP: {{ auditDetail?.ip_address || '—' }}</div></v-card></v-col><v-col cols="12" md="6"><div class="form-section mb-0 h-100"><div class="form-section__title"><v-icon icon="mdi-database-arrow-left-outline" />Dữ liệu trước thay đổi</div><pre class="audit-json">{{ prettyJson(auditDetail?.old_values) }}</pre></div></v-col><v-col cols="12" md="6"><div class="form-section mb-0 h-100"><div class="form-section__title"><v-icon icon="mdi-database-arrow-right-outline" />Dữ liệu sau thay đổi</div><pre class="audit-json">{{ prettyJson(auditDetail?.new_values) }}</pre></div></v-col></v-row></v-card-text><v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="auditModal = false">Đóng</v-btn></v-card-actions></v-card>
      </v-dialog>
      <v-dialog v-model="profileModal" max-width="760" scrollable>
        <v-card class="user-modal" rounded="xl">
          <div class="user-modal__header"><div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-account-edit-outline" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">Thông tin cá nhân</div><div class="text-body-2 opacity-80">Cập nhật hồ sơ và bảo mật tài khoản</div></div></div><v-btn icon="mdi-close" variant="text" color="white" @click="profileModal = false" /></div>
          <v-card-text class="user-modal__body"><v-form id="profile-form" @submit.prevent="saveProfile">
            <div class="avatar-panel mb-6"><v-avatar size="88" color="primary" variant="tonal" :image="profileAvatarPreview || undefined"><span v-if="!profileAvatarPreview" class="text-h4 font-weight-bold">{{ profileForm.name?.[0]?.toUpperCase() || '?' }}</span></v-avatar><div class="flex-grow-1"><div class="text-subtitle-1 font-weight-bold mb-1">Ảnh đại diện</div><div class="text-caption text-medium-emphasis mb-3">JPG, PNG hoặc WebP · Tối đa 2 MB</div><div class="d-flex align-center ga-2 flex-wrap"><v-file-input v-model="profileAvatarFile" accept="image/png,image/jpeg,image/webp" label="Chọn ảnh" density="compact" variant="outlined" hide-details prepend-icon="" prepend-inner-icon="mdi-camera-outline" class="flex-grow-1" /><v-btn v-if="profileAvatarPreview" icon="mdi-delete-outline" color="error" variant="tonal" title="Xóa ảnh" @click="removeProfileAvatar" /></div></div></div>
            <div class="form-section"><div class="form-section__title"><v-icon icon="mdi-card-account-details-outline" />Thông tin hồ sơ</div><v-row dense><v-col cols="12" md="6"><v-text-field v-model="profileForm.name" label="Họ và tên *" prepend-inner-icon="mdi-account-outline" required /></v-col><v-col cols="12" md="6"><v-text-field :model-value="auth.user?.username" label="Tên đăng nhập" prepend-inner-icon="mdi-account-key-outline" disabled /></v-col><v-col cols="12" md="6"><v-text-field v-model="profileForm.email" label="Email *" type="email" prepend-inner-icon="mdi-email-outline" required /></v-col><v-col cols="12" md="6"><v-text-field v-model="profileForm.phone" label="Số điện thoại" prepend-inner-icon="mdi-phone-outline" /></v-col><v-col cols="12" md="6"><v-text-field v-model="profileForm.identity_number" label="CCCD" prepend-inner-icon="mdi-card-account-details-outline" /></v-col><v-col cols="12" md="3"><v-text-field v-model="profileForm.date_of_birth" label="Ngày sinh" type="date" /></v-col><v-col cols="12" md="3"><v-select v-model="profileForm.gender" :items="[{title:'Nam',value:'NAM'},{title:'Nữ',value:'NU'},{title:'Khác',value:'KHAC'}]" label="Giới tính" clearable /></v-col><v-col cols="12"><v-text-field v-model="profileForm.address" label="Địa chỉ" prepend-inner-icon="mdi-map-marker-outline" /></v-col></v-row></div>
            <div class="form-section mb-0"><div class="form-section__title"><v-icon icon="mdi-shield-key-outline" />Đổi mật khẩu</div><div class="text-caption text-medium-emphasis mb-4">Để trống nếu bạn không muốn thay đổi mật khẩu.</div><v-row dense><v-col cols="12"><v-text-field v-model="profileForm.current_password" label="Mật khẩu hiện tại" type="password" prepend-inner-icon="mdi-lock-outline" autocomplete="current-password" /></v-col><v-col cols="12" md="6"><v-text-field v-model="profileForm.password" label="Mật khẩu mới" type="password" prepend-inner-icon="mdi-lock-reset" hint="Tối thiểu 8 ký tự" persistent-hint autocomplete="new-password" /></v-col><v-col cols="12" md="6"><v-text-field v-model="profileForm.password_confirmation" label="Xác nhận mật khẩu mới" type="password" prepend-inner-icon="mdi-lock-check-outline" autocomplete="new-password" /></v-col></v-row></div>
          </v-form></v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="profileModal = false">Hủy</v-btn><v-btn color="primary" size="large" type="submit" form="profile-form" prepend-icon="mdi-content-save-outline" :loading="profileSaving">Lưu thay đổi</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
      <v-dialog v-model="paymentPriceModal" max-width="760" scrollable>
        <v-card class="user-modal" rounded="xl"><div class="user-modal__header"><div><div class="text-h6 font-weight-bold">Chi tiết áp giá</div><div class="text-body-2 opacity-80">{{ paymentPriceDetail?.code }} · {{ paymentPriceDetail?.household?.owner_name }}</div></div><v-btn icon="mdi-close" variant="text" color="white" @click="paymentPriceModal = false" /></div>
          <v-card-text class="user-modal__body">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">Đây là đơn giá và thuế đã được chốt tại thời điểm thu; thay đổi giá về sau không làm thay đổi phiếu này.</v-alert>
            <div v-for="monthItem in paymentPriceDetail?.months || []" :key="monthItem.id" class="form-section mb-3">
              <div class="d-flex justify-space-between align-center mb-3"><strong>Tháng {{ monthLabel(monthItem.month?.slice(0,7)) }}</strong><strong class="text-primary">{{ money(monthItem.amount) }}</strong></div>
              <v-table density="compact"><thead><tr><th>Văn bản và thời gian áp dụng</th><th class="text-right">Số ngày</th><th class="text-right">Giá phân bổ</th><th class="text-right">Thuế</th><th class="text-right">Thành tiền</th></tr></thead><tbody>
                <tr v-for="(line, index) in monthItem.pricing_breakdown || [{document_number:monthItem.document_number,document_name:monthItem.price_period?.document_name,effective_from:monthItem.month,effective_to:monthItem.month,days:null,base_price:monthItem.base_price,tax_fee_rate:monthItem.tax_fee_rate,tax_fee_amount:monthItem.tax_fee_amount,amount:monthItem.amount}]" :key="index"><td><strong>{{ line.document_number || '—' }}</strong><div class="text-caption text-medium-emphasis">{{ line.document_name || '' }}</div><div class="text-caption">{{ birthDateLabel(line.effective_from) }} – {{ birthDateLabel(line.effective_to) }}</div></td><td class="text-right">{{ line.days || '—' }}</td><td class="text-right">{{ money(line.base_price) }}</td><td class="text-right">{{ line.tax_fee_rate || 0 }}% · {{ money(line.tax_fee_amount) }}</td><td class="text-right font-weight-bold">{{ money(line.amount) }}</td></tr>
              </tbody></v-table>
            </div>
          </v-card-text><v-divider/><v-card-actions><v-spacer/><v-btn variant="text" @click="paymentPriceModal = false">Đóng</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
      <v-dialog v-model="priceModal" max-width="1100" scrollable>
        <v-card class="user-modal" rounded="xl">
          <div class="user-modal__header"><div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-file-document-edit-outline" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">Giai đoạn áp giá</div><div class="text-body-2 opacity-80">{{ priceData.service?.code }} · {{ priceData.service?.name }}</div></div></div><v-btn icon="mdi-close" variant="text" color="white" @click="priceModal = false" /></div>
          <v-card-text class="user-modal__body">
            <div class="d-flex flex-column flex-md-row justify-space-between align-md-center ga-3 mb-4"><v-alert type="info" variant="tonal" density="compact" class="flex-grow-1 mb-0">Mỗi tháng chỉ thuộc một giai đoạn. Phiếu thu luôn lưu lại giá, thuế và văn bản đã áp dụng.</v-alert><v-btn color="primary" prepend-icon="mdi-plus" @click="editPricePeriod()">Thêm giai đoạn</v-btn></div>
            <v-progress-linear v-if="priceLoading" indeterminate color="primary" />
            <v-data-table v-else :headers="[{title:'Văn bản',key:'document'},{title:'Hiệu lực',key:'effective'},{title:'Đơn giá/tháng',key:'monthly_price'},{title:'Thuế, phí',key:'tax_fee'},{title:'Trạng thái',key:'is_active'},{title:'',key:'actions',align:'end',sortable:false}]" :items="priceData.periods || []" hover>
              <template #item.document="{ item }"><div class="font-weight-bold">{{ pricePeriodDocument(item).number }}</div><div class="text-caption text-medium-emphasis">{{ pricePeriodDocument(item).name }}</div></template>
              <template #item.effective="{ item }">{{ pricePeriodEffective(item) }}</template>
              <template #item.monthly_price="{ value }"><strong>{{ money(value) }}</strong></template><template #item.tax_fee="{ value }">{{ value }}%</template>
              <template #item.is_active="{ value }"><v-chip :color="value ? 'success' : 'default'" size="small" variant="tonal">{{ value ? 'Đang áp dụng' : 'Ngừng áp dụng' }}</v-chip></template>
              <template #item.actions="{ item }"><v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="editPricePeriod(item)"/><v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="deletePricePeriod(item)"/></template>
            </v-data-table>
            <v-expand-transition><v-card v-if="priceEditing" class="pa-4 mt-5" border rounded="lg"><div class="text-subtitle-1 font-weight-bold mb-4">{{ priceEditing.id ? 'Sửa giai đoạn giá' : 'Thêm giai đoạn giá' }}</div><v-form @submit.prevent="savePricePeriod"><v-row dense>
              <v-col cols="12" md="6"><v-autocomplete v-model="priceEditing.document_id" :items="priceData.documents || []" item-title="display_name" item-value="id" label="Chọn từ kho tài liệu, văn bản" prepend-inner-icon="mdi-file-document-search-outline" no-data-text="Không có tài liệu đang hoạt động" clearable @update:model-value="selectPriceDocument" /></v-col><v-col cols="12" md="3"><v-text-field v-model="priceEditing.document_number" label="Số văn bản *" required /></v-col><v-col cols="12" md="3"><v-text-field v-model="priceEditing.document_date" type="date" label="Ngày văn bản" /></v-col>
              <v-col cols="12"><v-text-field v-model="priceEditing.document_name" label="Tên / trích yếu văn bản" /></v-col>
              <v-col cols="12" md="3"><v-menu v-model="priceFromDateMenu" :close-on-content-click="false" location="bottom" max-width="360"><template #activator="{ props }"><v-text-field v-bind="props" :model-value="birthDateLabel(priceEditing.effective_from)" label="Áp dụng từ ngày *" placeholder="dd/mm/yyyy" prepend-inner-icon="mdi-calendar-start" readonly required /></template><v-date-picker :model-value="priceEditing.effective_from ? new Date(`${priceEditing.effective_from}T00:00:00`) : null" title="Chọn ngày bắt đầu" color="primary" show-adjacent-months @update:model-value="setPriceDate('effective_from', $event)" /></v-menu></v-col>
              <v-col cols="12" md="3"><v-menu v-model="priceToDateMenu" :close-on-content-click="false" location="bottom" max-width="360"><template #activator="{ props }"><v-text-field v-bind="props" :model-value="birthDateLabel(priceEditing.effective_to)" label="Áp dụng đến hết ngày" placeholder="dd/mm/yyyy" prepend-inner-icon="mdi-calendar-end" readonly clearable @click:clear.stop="priceEditing.effective_to = ''" /></template><v-date-picker :model-value="priceEditing.effective_to ? new Date(`${priceEditing.effective_to}T00:00:00`) : null" :min="priceEditing.effective_from ? new Date(`${priceEditing.effective_from}T00:00:00`) : undefined" title="Chọn ngày kết thúc" color="primary" show-adjacent-months @update:model-value="setPriceDate('effective_to', $event)" /></v-menu></v-col>
              <v-col cols="12" md="3"><v-text-field v-model.number="priceEditing.monthly_price" type="number" min="0" label="Đơn giá/tháng *" suffix="₫" required /></v-col><v-col cols="12" md="3"><v-text-field v-model.number="priceEditing.tax_fee" type="number" min="0" max="100" step="0.01" label="Thuế, phí *" suffix="%" required /></v-col>
              <v-col cols="12" md="9"><v-textarea v-model="priceEditing.note" label="Ghi chú" rows="2" auto-grow /></v-col><v-col cols="12" md="3"><v-switch v-model="priceEditing.is_active" color="success" label="Đang áp dụng" inset /></v-col>
            </v-row><div class="d-flex justify-end ga-2"><v-btn variant="text" @click="priceEditing = null">Hủy</v-btn><v-btn color="primary" type="submit" prepend-icon="mdi-content-save-outline" :loading="priceSaving">Lưu giai đoạn</v-btn></div></v-form></v-card></v-expand-transition>
          </v-card-text><v-divider/><v-card-actions><v-spacer/><v-btn variant="text" @click="priceModal = false">Đóng</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
      <v-dialog v-model="historyModal" max-width="1000" scrollable>
        <v-card class="user-modal" rounded="xl"><div class="user-modal__header"><div class="d-flex align-center ga-4"><v-avatar color="white" size="50"><v-icon color="primary" icon="mdi-history" size="28" /></v-avatar><div><div class="text-h6 font-weight-bold">Lịch sử thanh toán</div><div class="text-body-2 opacity-80">{{ historyHousehold?.code }} · {{ historyHousehold?.owner_name }}</div></div></div><v-btn icon="mdi-close" variant="text" color="white" @click="historyModal = false" /></div>
          <v-card-text class="user-modal__body"><v-data-table :headers="[{title:'Mã phiếu',key:'code'},{title:'Số hóa đơn',key:'invoice.invoice_no'},{title:'Kỳ thu',key:'period'},{title:'Số tiền',key:'amount'},{title:'Ngày thu',key:'paid_at'},{title:'Người thu',key:'collector.name'},{title:'Hóa đơn',key:'invoice.status'}]" :items="paymentHistory" :loading="historyLoading" hover><template #[`item.invoice.invoice_no`]="{ item }"><span v-if="item.invoice?.invoice_no" class="font-weight-medium text-primary">{{ item.invoice.invoice_no }}</span><span v-else class="text-medium-emphasis">—</span></template><template #item.period="{ item }">{{ new Date(item.from_month).toLocaleDateString('vi-VN',{month:'2-digit',year:'numeric'}) }} – {{ new Date(item.to_month).toLocaleDateString('vi-VN',{month:'2-digit',year:'numeric'}) }}</template><template #item.amount="{ value }"><span class="font-weight-bold text-primary">{{ money(value) }}</span></template><template #item.paid_at="{ value }">{{ value ? new Date(value).toLocaleString('vi-VN') : '—' }}</template><template #[`item.invoice.status`]="{ item }"><v-chip size="small" variant="tonal" color="info">{{ item.invoice?.status || 'Chưa có' }}</v-chip></template><template #no-data><div class="empty-state"><v-icon icon="mdi-receipt-text-outline" size="48" /><div class="mt-2">Hộ dân chưa có lịch sử thanh toán</div></div></template></v-data-table></v-card-text>
          <v-divider /><v-card-actions class="user-modal__actions"><v-spacer /><v-btn variant="text" @click="historyModal = false">Đóng</v-btn></v-card-actions></v-card>
      </v-dialog>
      <v-snackbar v-model="snackbar" color="success" location="bottom end"
        ><v-icon icon="mdi-check-circle" class="mr-2" />{{ snackbarText }}</v-snackbar
      >
    </template>
  </v-app>
</template>
