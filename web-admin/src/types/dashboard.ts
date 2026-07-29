export type DashboardFilters = {
  from_date: string
  to_date: string
  route_id: number | null
  collector_id: number | null
  service_id: number | null
  payment_status: string | null
  invoice_status: string | null
  chart_range: '6' | '12' | 'year'
  collector_period: 'today' | 'month' | 'quarter'
  collector_rank_by: 'revenue' | 'completion'
}

export type DashboardKpi = { key: string; label: string; value: number; previous: number; change_percent: number; trend: 'up' | 'down' | 'flat'; format: 'count' | 'currency' }
export type RevenuePoint = { month: string; label: string; current: number; previous: number }
export type RoutePerformanceItem = { route_id: number; route_name: string; total_households: number; collected_households: number; uncollected_households: number; completion_rate: number; revenue: number }
export type ServiceDistributionItem = { service_id: number; name: string; households: number; percentage: number; revenue: number }
export type DebtBucket = { key: string; label: string; households: number; amount: number }
export type HighDebtItem = { household_id: number; code: string; owner_name: string; route_name: string; collector_name: string; last_paid_month: string | null; debt_months: number; total_debt: number; oldest_month: string }
export type CollectorRank = { id: number | null; name: string; avatar_url: string | null; households: number; revenue: number; transactions: number; completion_rate: number; rank: number }
export type InvoiceStatusItem = { status: string; label: string; count: number; amount: number }
export type InvoiceFailure = { invoice_id: number; payment_id: number; household_name: string; payment_code: string; amount: number; failed_at: string | null; error: string; retry_count: number }
export type RecentPayment = { id: number; paid_at: string; code: string; household_name: string; route_name: string; collector_name: string; period: string; service_name: string; amount: number; invoice_status: string }
export type DashboardAlert = { key: string; title: string; count: number; severity: 'info' | 'warning' | 'critical'; path: string }
export type SelectOption = { id: number; code?: string; name: string }

export type DashboardData = {
  filters: DashboardFilters
  updated_at: string
  kpis: DashboardKpi[]
  revenue_chart: { range: string; points: RevenuePoint[]; max: number }
  route_performance: RoutePerformanceItem[]
  service_distribution: ServiceDistributionItem[]
  debt: { buckets: DebtBucket[]; high_debts: HighDebtItem[]; total: number }
  top_collectors: CollectorRank[]
  invoice_status: { statuses: InvoiceStatusItem[]; failures: InvoiceFailure[] }
  recent_payments: RecentPayment[]
  alerts: DashboardAlert[]
  options: { routes: SelectOption[]; collectors: SelectOption[]; services: SelectOption[] }
}
