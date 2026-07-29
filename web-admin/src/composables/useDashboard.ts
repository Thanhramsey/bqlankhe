import { onBeforeUnmount, reactive, ref } from 'vue'
import { fetchDashboard } from '@/services/dashboard.service'
import type { DashboardData, DashboardFilters } from '@/types/dashboard'

const today = new Date()
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const iso = (date: Date) => date.toISOString().slice(0, 10)

export function useDashboard() {
  const data = ref<DashboardData | null>(null)
  const loading = ref(false)
  const error = ref('')
  const filters = reactive<DashboardFilters>({
    from_date: iso(startOfMonth), to_date: iso(today), route_id: null, collector_id: null,
    service_id: null, payment_status: null, invoice_status: null, chart_range: '12',
    collector_period: 'month', collector_rank_by: 'revenue',
  })
  let controller: AbortController | null = null

  async function load() {
    controller?.abort()
    controller = new AbortController()
    loading.value = true; error.value = ''
    try { data.value = await fetchDashboard(filters, controller.signal) }
    catch (exception: unknown) { const cause = exception as { name?: string; message?: string }; if (cause.name !== 'AbortError') error.value = cause.message || 'Không thể tải dữ liệu dashboard.' }
    finally { loading.value = false }
  }
  function setQuickRange(range: 'today' | 'month' | 'quarter' | 'year') {
    const now = new Date(); let start = new Date(now)
    if (range === 'month') start = new Date(now.getFullYear(), now.getMonth(), 1)
    if (range === 'quarter') start = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1)
    if (range === 'year') start = new Date(now.getFullYear(), 0, 1)
    filters.from_date = iso(start); filters.to_date = iso(now)
  }
  function reset() {
    Object.assign(filters, { from_date: iso(startOfMonth), to_date: iso(today), route_id: null, collector_id: null, service_id: null, payment_status: null, invoice_status: null, chart_range: '12', collector_period: 'month', collector_rank_by: 'revenue' })
  }
  onBeforeUnmount(() => controller?.abort())
  return { data, filters, loading, error, load, reset, setQuickRange }
}
