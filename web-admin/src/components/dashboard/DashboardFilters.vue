<script setup lang="ts">
import type { DashboardFilters, SelectOption } from '@/types/dashboard'
const model = defineModel<DashboardFilters>({ required: true })
defineProps<{ options: { routes: SelectOption[]; collectors: SelectOption[]; services: SelectOption[] }; loading: boolean }>()
const emit = defineEmits<{ apply: []; reset: []; quick: [value: 'today'|'month'|'quarter'|'year']; export: [] }>()
</script>
<template>
  <v-card class="dashboard-filter pa-4 pa-md-5" border rounded="xl">
    <div class="d-flex flex-column flex-lg-row align-lg-center justify-space-between ga-3 mb-4">
      <div><div class="text-subtitle-1 font-weight-bold">Bộ lọc tổng quan</div><div class="text-caption text-medium-emphasis">Mọi số liệu và biểu đồ cập nhật đồng bộ theo bộ lọc</div></div>
      <div class="d-flex flex-wrap ga-2"><v-btn v-for="item in [{v:'today',t:'Hôm nay'},{v:'month',t:'Tháng này'},{v:'quarter',t:'Quý này'},{v:'year',t:'Năm nay'}]" :key="item.v" size="small" variant="tonal" color="primary" @click="emit('quick', item.v as any)">{{ item.t }}</v-btn></div>
    </div>
    <v-row dense><v-col cols="6" md="2"><v-text-field v-model="model.from_date" type="date" label="Từ ngày" hide-details /></v-col><v-col cols="6" md="2"><v-text-field v-model="model.to_date" type="date" label="Đến ngày" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="model.route_id" :items="options.routes" item-title="name" item-value="id" label="Tuyến thu" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="model.collector_id" :items="options.collectors" item-title="name" item-value="id" label="Nhân viên" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="model.service_id" :items="options.services" item-title="name" item-value="id" label="Dịch vụ" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="model.invoice_status" :items="[{title:'Chờ phát hành',value:'CHO_PHAT_HANH'},{title:'Đã phát hành',value:'DA_PHAT_HANH'},{title:'Phát hành lỗi',value:'PHAT_HANH_LOI'}]" label="Trạng thái HĐ" clearable hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="model.payment_status" :items="[{title:'Đã thu',value:'DA_THU'},{title:'Đã phát hành HĐ',value:'DA_PHAT_HANH_HOA_DON'}]" label="Trạng thái thu" clearable hide-details /></v-col></v-row>
    <div class="d-flex justify-end flex-wrap ga-2 mt-4"><v-btn variant="text" prepend-icon="mdi-filter-remove-outline" @click="emit('reset')">Đặt lại</v-btn><v-btn variant="tonal" color="success" prepend-icon="mdi-file-chart-outline" @click="emit('export')">Xuất báo cáo</v-btn><v-btn color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="emit('apply')">Áp dụng</v-btn></div>
  </v-card>
</template>
