<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { useDashboard } from '@/composables/useDashboard'
import DashboardFilters from '@/components/dashboard/DashboardFilters.vue'
import KpiCard from '@/components/dashboard/KpiCard.vue'
import RevenueChart from '@/components/dashboard/RevenueChart.vue'
import RoutePerformance from '@/components/dashboard/RoutePerformance.vue'
import ServiceDistribution from '@/components/dashboard/ServiceDistribution.vue'
import DebtSummary from '@/components/dashboard/DebtSummary.vue'
import TopCollectors from '@/components/dashboard/TopCollectors.vue'
import InvoiceStatus from '@/components/dashboard/InvoiceStatus.vue'
import RecentPayments from '@/components/dashboard/RecentPayments.vue'
import ActionAlerts from '@/components/dashboard/ActionAlerts.vue'

const router = useRouter()
const { data, filters, loading, error, load, reset, setQuickRange } = useDashboard()
const publishing = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

async function quick(value: 'today'|'month'|'quarter'|'year') { setQuickRange(value); await load() }
async function resetFilters() { reset(); await load() }
async function changeChartRange(value: '6'|'12'|'year') { filters.chart_range = value; await load() }
async function changeCollectorPeriod(value: string) { filters.collector_period = value as 'today'|'month'|'quarter'; await load() }
async function changeCollectorRank(value: string) { filters.collector_rank_by = value as 'revenue'|'completion'; await load() }
async function retryInvoice(paymentId: number) {
  publishing.value = true
  try { const response = await api<unknown>('/invoices/publish',{method:'POST',body:JSON.stringify({payment_ids:[paymentId]})}); snackbarText.value=response.message;snackbar.value=true;await load() }
  finally { publishing.value=false }
}
async function printReceipt(paymentId:number) {
  const token=localStorage.getItem('token');const base=import.meta.env.VITE_API_URL||'http://localhost:8000/api/v1';const popup=window.open('','_blank')
  const response=await fetch(`${base}/payments/${paymentId}/receipt`,{headers:token?{Authorization:`Bearer ${token}`}:{}});const url=URL.createObjectURL(await response.blob());if(popup)popup.location.href=url;setTimeout(()=>URL.revokeObjectURL(url),60000)
}
function collectHousehold(householdId:number){router.push({path:'/payments',query:{household_id:String(householdId)}})}
function printDebt(){router.push('/debts')}
onMounted(load)
</script>

<template><section class="dashboard-view">
  <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3 mb-5"><div><h1 class="text-h5 text-md-h4 font-weight-bold mb-1">Tổng quan thu phí</h1><p class="text-body-2 text-medium-emphasis mb-0">Theo dõi tình hình thu phí, công nợ và hóa đơn</p><div v-if="data?.updated_at" class="text-caption text-medium-emphasis mt-1"><v-icon icon="mdi-clock-outline" size="15"/> Cập nhật {{ new Date(data.updated_at).toLocaleString('vi-VN') }}</div></div><v-btn color="primary" variant="tonal" prepend-icon="mdi-refresh" :loading="loading" @click="load">Làm mới dữ liệu</v-btn></div>
  <v-alert v-if="error" type="error" variant="tonal" closable class="mb-5"><div class="d-flex align-center justify-space-between ga-3"><span>{{ error }}</span><v-btn size="small" color="error" @click="load">Thử lại</v-btn></div></v-alert>
  <DashboardFilters v-model="filters" :options="data?.options||{routes:[],collectors:[],services:[]}" :loading="loading" @apply="load" @reset="resetFilters" @quick="quick" @export="router.push('/reports')"/>
  <v-row class="mt-2"><v-col v-for="item in data?.kpis||[]" :key="item.key" cols="6" md="4" xl="2"><KpiCard :item="item" :loading="loading"/></v-col><template v-if="loading&&!data"><v-col v-for="n in 6" :key="n" cols="6" md="4" xl="2"><v-skeleton-loader type="card"/></v-col></template></v-row>
  <v-row><v-col cols="12" lg="8"><RevenueChart :points="data?.revenue_chart.points||[]" :max="data?.revenue_chart.max||1" :range="filters.chart_range" :loading="loading" @range="changeChartRange"/></v-col><v-col cols="12" lg="4"><ActionAlerts :items="data?.alerts||[]" :loading="loading" @handle="router.push($event)"/></v-col></v-row>
  <v-row><v-col cols="12" lg="7"><RoutePerformance :items="data?.route_performance||[]" :loading="loading" @detail="router.push({path:'/households',query:{route_id:String($event)}})"/></v-col><v-col cols="12" lg="5"><ServiceDistribution :items="data?.service_distribution||[]" :loading="loading"/></v-col></v-row>
  <v-row><v-col cols="12" lg="6"><TopCollectors :items="data?.top_collectors||[]" :loading="loading" :period="filters.collector_period" :rank-by="filters.collector_rank_by" @period="changeCollectorPeriod" @rank="changeCollectorRank"/></v-col><v-col cols="12" lg="6"><InvoiceStatus :statuses="data?.invoice_status.statuses||[]" :failures="data?.invoice_status.failures||[]" :loading="loading" :publishing="publishing" @retry="retryInvoice"/></v-col></v-row>
  <DebtSummary class="mb-6" :buckets="data?.debt.buckets||[]" :items="data?.debt.high_debts||[]" :loading="loading" @detail="router.push('/debts')" @collect="collectHousehold" @print="printDebt"/>
  <RecentPayments :items="data?.recent_payments||[]" :loading="loading" @detail="router.push('/payments')" @print="printReceipt"/>
  <v-snackbar v-model="snackbar" color="success" location="bottom end">{{ snackbarText }}</v-snackbar>
</section></template>
