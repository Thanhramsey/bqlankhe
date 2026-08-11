<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '@/api'
import DatePicker from '@/components/DatePicker.vue'

type AnyRow = Record<string, any>
const tab = ref('materials'),
  busy = ref(false),
  saving = ref(false),
  search = ref(''),
  error = ref(''),
  message = ref('')
const rows = ref<AnyRow[]>([]),
  options = ref<AnyRow>({ categories: [], warehouses: [], materials: [] }),
  report = ref<AnyRow>({ summary: {}, stocks: [] })
const dialog = ref(false),
  modalError = ref(''),
  historyDialog = ref(false),
  selectedHistory = ref<AnyRow[]>([]),
  imageFile = ref<File | null>(null),
  documents = ref<File[]>([])
const form = reactive<AnyRow>({}),
  filters = reactive<AnyRow>({
    warehouse_id: null,
    category_id: null,
    type: null,
    from_date: '',
    to_date: '',
  })
const tabs = [
  ['materials', 'Vật tư', 'mdi-package-variant-closed'],
  ['categories', 'Loại vật tư', 'mdi-shape-outline'],
  ['warehouses', 'Kho', 'mdi-warehouse'],
  ['transactions', 'Nhập / xuất kho', 'mdi-swap-horizontal-bold'],
  ['stocks', 'Tồn kho', 'mdi-chart-box-outline'],
  ['report', 'Báo cáo', 'mdi-file-chart-outline'],
]
const title = computed(() => tabs.find((x) => x[0] === tab.value)?.[1] || 'Vật tư')
const money = (v: any) => Number(v || 0).toLocaleString('vi-VN') + ' ₫'
const quantity = (v: any) => Number(v || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 })
const summary = computed(() => report.value.summary || {})

async function loadOptions() {
  options.value = (await api<AnyRow>('/inventory/options')).data
}
async function load() {
  busy.value = true
  error.value = ''
  try {
    const p = new URLSearchParams()
    if (search.value) p.set('search', search.value)
    Object.entries(filters).forEach(([k, v]) => {
      if (v) p.set(k, String(v))
    })
    if (tab.value === 'report') {
      report.value = (await api<AnyRow>(`/inventory/report?${p}`)).data
      rows.value = report.value.stocks || []
    } else {
      const endpoint = tab.value === 'stocks' ? 'stocks' : tab.value
      const result = (await api<any>(`/inventory/${endpoint}?${p}`)).data
      rows.value = result.data || result
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
function clearForm() {
  Object.keys(form).forEach((k) => delete form[k])
  imageFile.value = null
  documents.value = []
  modalError.value = ''
}
function openCreate(kind = tab.value) {
  clearForm()
  form.kind = kind
  form.is_active = true
  if (kind === 'transactions') {
    form.type = 'IN'
    form.transaction_date = new Date().toISOString().slice(0, 10)
    form.items = [{ material_id: null, quantity: 1, unit_price: 0, note: '' }]
  }
  dialog.value = true
}
function openEdit(item: AnyRow) {
  clearForm()
  Object.assign(form, JSON.parse(JSON.stringify(item)), { kind: tab.value })
  dialog.value = true
}
function addItem() {
  form.items.push({ material_id: null, quantity: 1, unit_price: 0, note: '' })
}
function selectedMaterial(row: AnyRow) {
  return options.value.materials.find((x: AnyRow) => x.id === row.material_id)
}
function materialChanged(row: AnyRow) {
  const m = selectedMaterial(row)
  if (m) row.unit_price = Number(m.price || 0)
}
async function save() {
  saving.value = true
  modalError.value = ''
  try {
    let endpoint = `/inventory/${form.kind}`,
      method = form.id ? 'PUT' : 'POST',
      body: any
    if (form.kind === 'transactions') {
      endpoint = `/inventory/transactions${form.id ? `/${form.id}` : ''}`
      const fd = new FormData()
      fd.append('payload', JSON.stringify({ ...form, kind: undefined }))
      if (form.id) {
        fd.append('_method', 'PUT')
        method = 'POST'
      } else method = 'POST'
      documents.value.forEach((f) => fd.append('documents[]', f))
      body = fd
    } else if (form.kind === 'materials') {
      const fd = new FormData()
      Object.entries(form).forEach(([k, v]) => {
        if (
          ![
            'kind',
            'category',
            'creator',
            'stocks',
            'image_url',
            'total_stock',
            'created_at',
            'updated_at',
            'deleted_at',
            'image_path',
          ].includes(k) &&
          v !== null &&
          v !== undefined
        )
          fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v))
      })
      if (imageFile.value) fd.append('image', imageFile.value)
      body = fd
      method = 'POST'
      if (form.id) endpoint += `/${form.id}`
    } else {
      endpoint += form.id ? `/${form.id}` : ''
      body = JSON.stringify(form)
    }
    const result = await api<any>(endpoint, { method, body })
    message.value = result.message
    dialog.value = false
    await Promise.all([load(), loadOptions()])
  } catch (e: any) {
    if (dialog.value) modalError.value = e.message
    else error.value = e.message
  } finally {
    saving.value = false
  }
}
async function remove(item: AnyRow) {
  if (tab.value === 'transactions') {
    if (!confirm(`Xóa phiếu ${item.code}?`)) return
    try {
      await api(`/inventory/transactions/${item.id}`, { method: 'DELETE' })
      message.value = 'Đã xóa phiếu kho'
      await load()
    } catch (e: any) {
      error.value = e.message
    }
    return
  }

  if (!confirm(`Xóa ${item.name}?`)) return
  try {
    await api(`/inventory/${tab.value}/${item.id}`, { method: 'DELETE' })
    message.value = 'Đã xóa dữ liệu'
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function history(item: AnyRow) {
  selectedHistory.value = []
  historyDialog.value = true
  try {
    const r = (await api<any>(`/inventory/transactions?material_id=${item.id}`)).data
    selectedHistory.value = r.data || []
  } catch (e: any) {
    error.value = e.message
  }
}
async function exportExcel() {
  const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
    token = localStorage.getItem('token')
  const res = await fetch(`${base}/inventory/report/export`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) {
    error.value = 'Không thể xuất báo cáo'
    return
  }
  const url = URL.createObjectURL(await res.blob()),
    a = document.createElement('a')
  a.href = url
  a.download = 'bao-cao-ton-kho.xlsx'
  a.click()
  URL.revokeObjectURL(url)
}
let timer: number | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = window.setTimeout(load, 350)
})
watch(tab, () => {
  search.value = ''
  load()
})
watch(dialog, (value) => {
  if (!value) modalError.value = ''
})
onMounted(async () => {
  await loadOptions()
  await load()
})
</script>

<template>
  <div class="inventory-page">
    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="error = ''"
      >{{ error }}</v-alert
    >
    <v-snackbar
      :model-value="!!message"
      color="success"
      @update:model-value="
        (value) => {
          if (!value) message = ''
        }
      "
      >{{ message }}</v-snackbar
    >
    <v-card border rounded="xl" class="mb-5 overflow-hidden"
      ><v-tabs v-model="tab" color="primary" show-arrows
        ><v-tab v-for="t in tabs" :key="t[0]" :value="t[0]" :prepend-icon="t[2]">{{
          t[1]
        }}</v-tab></v-tabs
      ></v-card
    >
    <div class="d-flex flex-column flex-md-row justify-space-between align-md-center ga-3 mb-4">
      <div>
        <div class="text-h5 font-weight-bold">{{ title }}</div>
        <div class="text-body-2 text-medium-emphasis">
          Quản lý tập trung vật tư, chứng từ và số lượng thực tế theo từng kho
        </div>
      </div>
      <div class="d-flex ga-2 flex-wrap">
        <v-btn
          v-if="tab === 'report' || tab === 'stocks'"
          color="success"
          variant="tonal"
          prepend-icon="mdi-microsoft-excel"
          @click="exportExcel"
          >Xuất Excel</v-btn
        ><v-btn
          v-if="['materials', 'categories', 'warehouses'].includes(tab)"
          color="primary"
          prepend-icon="mdi-plus"
          @click="openCreate()"
          >Thêm {{ title.toLowerCase() }}</v-btn
        ><template v-if="tab === 'transactions'"
          ><v-btn
            color="success"
            prepend-icon="mdi-tray-arrow-down"
            @click="openCreate('transactions'); form.type = 'IN'"
            >Nhập kho</v-btn
          ><v-btn
            color="warning"
            prepend-icon="mdi-tray-arrow-up"
            @click="openCreate('transactions'); form.type = 'OUT'"
            >Xuất kho</v-btn
          ></template
        >
      </div>
    </div>
    <v-card border rounded="xl" class="pa-4 mb-5"
      ><v-row dense align="center"
        ><v-col v-if="!['report'].includes(tab)" cols="12" md="4"
          ><v-text-field
            v-model="search"
            label="Tìm theo mã, tên hoặc nhà cung cấp"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details /></v-col
        ><v-col v-if="['materials', 'stocks'].includes(tab)" cols="12" md="3"
          ><v-select
            v-model="filters.category_id"
            :items="options.categories"
            item-title="name"
            item-value="id"
            label="Loại vật tư"
            clearable
            hide-details
            @update:model-value="load" /></v-col
        ><v-col v-if="['transactions', 'stocks'].includes(tab)" cols="12" md="3"
          ><v-select
            v-model="filters.warehouse_id"
            :items="options.warehouses"
            item-title="name"
            item-value="id"
            label="Kho"
            clearable
            hide-details
            @update:model-value="load" /></v-col
        ><v-col v-if="tab === 'transactions'" cols="12" md="2"
          ><v-select
            v-model="filters.type"
            :items="[
              { title: 'Nhập kho', value: 'IN' },
              { title: 'Xuất kho', value: 'OUT' },
            ]"
            label="Loại phiếu"
            clearable
            hide-details
            @update:model-value="load" /></v-col></v-row
    ></v-card>

    <v-row v-if="tab === 'report'" class="mb-2"
      ><v-col
        v-for="k in [
          { l: 'Vật tư hoạt động', v: summary.materials, i: 'mdi-package-variant', c: 'primary' },
          { l: 'Kho hoạt động', v: summary.warehouses, i: 'mdi-warehouse', c: 'info' },
          {
            l: 'Giá trị tồn kho',
            v: money(summary.stock_value),
            i: 'mdi-cash-multiple',
            c: 'success',
          },
          {
            l: 'Giá trị đã xuất',
            v: money(summary.out_value),
            i: 'mdi-tray-arrow-up',
            c: 'warning',
          },
        ]"
        :key="k.l"
        cols="12"
        sm="6"
        lg="3"
        ><v-card border rounded="xl" class="pa-4 h-100"
          ><div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis">{{ k.l }}</div>
              <div class="text-h6 font-weight-bold mt-1">{{ k.v }}</div>
            </div>
            <v-avatar :color="k.c" variant="tonal" rounded="lg"
              ><v-icon :icon="k.i"
            /></v-avatar></div></v-card></v-col
    ></v-row>
    <v-card border rounded="xl" class="overflow-hidden">
      <v-data-table
        v-if="tab === 'categories'"
        :items="rows"
        :loading="busy"
        :headers="[
          { title: 'Mã danh mục', key: 'code' },
          { title: 'Tên danh mục', key: 'name' },
          { title: 'Mô tả', key: 'description' },
          { title: 'Ngày tạo', key: 'created_at' },
          { title: 'Trạng thái', key: 'is_active' },
          { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
        ]"
        ><template #item.created_at="{ value }">{{
          new Date(value).toLocaleDateString('vi-VN')
        }}</template
        ><template #item.is_active="{ value }"
          ><v-chip :color="value ? 'success' : 'default'" size="small" variant="tonal">{{
            value ? 'Hoạt động' : 'Ngừng'
          }}</v-chip></template
        ><template #item.actions="{ item }"
          ><v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" /><v-btn
            icon="mdi-delete"
            color="error"
            size="small"
            variant="text"
            @click="remove(item)" /></template
      ></v-data-table>
      <v-data-table
        v-else-if="tab === 'warehouses'"
        :items="rows"
        :loading="busy"
        :headers="[
          { title: 'Mã kho', key: 'code' },
          { title: 'Tên kho', key: 'name' },
          { title: 'Ghi chú', key: 'note' },
          { title: 'Trạng thái', key: 'is_active' },
          { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
        ]"
        ><template #item.is_active="{ value }"
          ><v-chip :color="value ? 'success' : 'default'" size="small" variant="tonal">{{
            value ? 'Hoạt động' : 'Ngừng'
          }}</v-chip></template
        ><template #item.actions="{ item }"
          ><v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" /><v-btn
            icon="mdi-delete"
            color="error"
            size="small"
            variant="text"
            @click="remove(item)" /></template
      ></v-data-table>
      <v-data-table
        v-else-if="tab === 'materials'"
        :items="rows"
        :loading="busy"
        :headers="[
          { title: 'Vật tư', key: 'name' },
          { title: 'Loại', key: 'category.name' },
          { title: 'ĐVT', key: 'unit' },
          { title: 'Nhà cung cấp', key: 'supplier' },
          { title: 'Giá', key: 'price', align: 'end' },
          { title: 'Tổng tồn', key: 'total_stock', align: 'end' },
          { title: 'Người tạo', key: 'creator.name' },
          { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
        ]"
        ><template #item.name="{ item }"
          ><div class="d-flex align-center ga-3 py-2">
            <v-avatar
              rounded="lg"
              color="primary"
              variant="tonal"
              :image="item.image_url || undefined"
              ><v-icon v-if="!item.image_url" icon="mdi-package-variant"
            /></v-avatar>
            <div>
              <div class="font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.code }}</div>
            </div>
          </div></template
        ><template #item.price="{ value }"
          ><strong>{{ money(value) }}</strong></template
        ><template #item.total_stock="{ item }"
          ><strong :class="Number(item.total_stock) <= 0 ? 'text-error' : 'text-success'"
            >{{ quantity(item.total_stock) }} {{ item.unit }}</strong
          ></template
        ><template #item.actions="{ item }"
          ><v-btn
            icon="mdi-history"
            color="secondary"
            size="small"
            variant="text"
            title="Lịch sử nhập xuất"
            @click="history(item)" /><v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="openEdit(item)" /><v-btn
            icon="mdi-delete"
            color="error"
            size="small"
            variant="text"
            @click="remove(item)" /></template
      ></v-data-table>
      <v-data-table
        v-else-if="tab === 'transactions'"
        :items="rows"
        :loading="busy"
        :headers="[
          { title: 'Mã phiếu', key: 'code' },
          { title: 'Ngày', key: 'transaction_date' },
          { title: 'Loại', key: 'type' },
          { title: 'Kho', key: 'warehouse.name' },
          { title: 'Đối tác', key: 'partner' },
          { title: 'Số chứng từ', key: 'reference_no' },
          { title: 'Số mặt hàng', key: 'items.length', align: 'center' },
          { title: 'Tổng tiền', key: 'total_amount', align: 'end' },
          { title: 'Người tạo', key: 'creator.name' },
          { title: 'Tệp', key: 'documents', align: 'center' },
          { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
        ]"
        ><template #item.transaction_date="{ value }">{{
          new Date(value).toLocaleDateString('vi-VN')
        }}</template
        ><template #item.type="{ value }"
          ><v-chip
            :color="value === 'IN' ? 'success' : 'warning'"
            size="small"
            variant="tonal"
            :prepend-icon="value === 'IN' ? 'mdi-tray-arrow-down' : 'mdi-tray-arrow-up'"
            >{{ value === 'IN' ? 'Nhập kho' : 'Xuất kho' }}</v-chip
          ></template
        ><template #item.total_amount="{ value }"
          ><strong>{{ money(value) }}</strong></template
        ><template #item.documents="{ value }"
          ><v-btn
            v-for="d in value"
            :key="d.id"
            :href="d.url"
            target="_blank"
            icon="mdi-paperclip"
            size="small"
            variant="text" /></template
        ><template #item.actions="{ item }"
          ><v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            title="Sửa phiếu"
            @click="openEdit(item)" /><v-btn
            icon="mdi-delete"
            color="error"
            size="small"
            variant="text"
            title="Xóa phiếu"
            @click="remove(item)" /></template
      ></v-data-table>
      <v-data-table
        v-else
        :items="rows"
        :loading="busy"
        :headers="[
          { title: 'Kho', key: 'warehouse.name' },
          { title: 'Mã vật tư', key: 'material.code' },
          { title: 'Tên vật tư', key: 'material.name' },
          { title: 'Loại', key: 'material.category.name' },
          { title: 'ĐVT', key: 'material.unit' },
          { title: 'Tồn hiện tại', key: 'quantity', align: 'end' },
          { title: 'Giá bình quân', key: 'average_price', align: 'end' },
          { title: 'Giá trị tồn', key: 'stock_value', align: 'end' },
        ]"
        ><template #item.quantity="{ item }"
          ><strong :class="Number(item.quantity) <= 0 ? 'text-error' : 'text-success'">{{
            quantity(item.quantity)
          }}</strong></template
        ><template #item.average_price="{ value }">{{ money(value) }}</template
        ><template #item.stock_value="{ item }"
          ><strong>{{
            money(Number(item.quantity) * Number(item.average_price))
          }}</strong></template
        ></v-data-table
      >
    </v-card>

    <v-dialog v-model="dialog" max-width="980" scrollable
      ><v-card rounded="xl"
        ><v-card-title class="modal-header"
          ><div>
            <div class="text-h6 font-weight-bold">
              {{ form.id ? 'Chỉnh sửa' : 'Thêm mới' }}
              {{
                form.kind === 'transactions'
                  ? form.type === 'IN'
                    ? 'phiếu nhập kho'
                    : 'phiếu xuất kho'
                  : title.toLowerCase()
              }}
            </div>
            <div class="text-caption text-medium-emphasis">Nhập đầy đủ thông tin bên dưới</div>
          </div>
          <v-btn icon="mdi-close" variant="text" @click="dialog = false" /></v-card-title
        ><v-divider /><v-card-text class="pa-5">
          <v-alert v-if="modalError" type="error" variant="tonal" class="mb-4">{{
            modalError
          }}</v-alert>
          <v-row v-if="form.kind === 'categories' || form.kind === 'warehouses'" dense
            ><v-col cols="12" sm="4"><v-text-field v-model="form.code" label="Mã *" /></v-col
            ><v-col cols="12" sm="8"><v-text-field v-model="form.name" label="Tên *" /></v-col
            ><v-col cols="12"
              ><v-textarea
                v-model="form[form.kind === 'categories' ? 'description' : 'note']"
                :label="form.kind === 'categories' ? 'Mô tả' : 'Ghi chú'"
                rows="3" /></v-col
            ><v-col cols="12"
              ><v-switch
                v-model="form.is_active"
                color="success"
                label="Đang hoạt động"
                inset /></v-col
          ></v-row>
          <v-row v-else-if="form.kind === 'materials'" dense
            ><v-col cols="12" md="4"><v-text-field v-model="form.code" label="Mã vật tư *" /></v-col
            ><v-col cols="12" md="8"
              ><v-text-field v-model="form.name" label="Tên vật tư *" /></v-col
            ><v-col cols="12" md="4"
              ><v-select
                v-model="form.material_category_id"
                :items="options.categories"
                item-title="name"
                item-value="id"
                label="Loại vật tư *" /></v-col
            ><v-col cols="12" sm="6" md="4"
              ><v-text-field v-model="form.unit" label="Đơn vị tính *" /></v-col
            ><v-col cols="12" sm="6" md="4"
              ><v-text-field
                v-model.number="form.price"
                type="number"
                min="0"
                label="Giá tham khảo *"
                suffix="₫" /></v-col
            ><v-col cols="12" md="6"
              ><v-text-field v-model="form.supplier" label="Nhà cung cấp" /></v-col
            ><v-col cols="12" md="6"
              ><v-file-input
                v-model="imageFile"
                accept="image/*"
                label="Hình ảnh vật tư"
                prepend-icon="mdi-camera" /></v-col
            ><v-col cols="12"
              ><v-textarea v-model="form.description" label="Mô tả" rows="3" /></v-col
            ><v-col cols="12"
              ><v-switch
                v-model="form.is_active"
                color="success"
                label="Đang hoạt động"
                inset /></v-col
          ></v-row>
          <template v-else
            ><v-row dense
              ><v-col cols="12" md="3"
                ><v-select
                  v-model="form.type"
                  :items="[
                    { title: 'Nhập kho', value: 'IN' },
                    { title: 'Xuất kho', value: 'OUT' },
                  ]"
                  label="Loại phiếu *" /></v-col
              ><v-col cols="12" md="3"
                ><v-select
                  v-model="form.warehouse_id"
                  :items="options.warehouses"
                  item-title="name"
                  item-value="id"
                  label="Kho *" /></v-col
              ><v-col cols="12" md="3"
                ><DatePicker
                  v-model="form.transaction_date"
                  label="Ngày nhập/xuất *"
                  required
                  :hide-details="false" /></v-col
              ><v-col cols="12" md="3"
                ><v-text-field v-model="form.reference_no" label="Số hóa đơn/chứng từ" /></v-col
              ><v-col cols="12" md="6"
                ><v-text-field
                  v-model="form.partner"
                  :label="form.type === 'IN' ? 'Nhà cung cấp' : 'Đơn vị/người nhận'" /></v-col
              ><v-col cols="12" md="6"
                ><v-file-input
                  v-model="documents"
                  multiple
                  chips
                  accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx"
                  label="Hóa đơn, chứng từ (tối đa 10MB/tệp)" /></v-col
            ></v-row>
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="font-weight-bold">Chi tiết vật tư</div>
              <v-btn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="mdi-plus"
                @click="addItem"
                >Thêm dòng</v-btn
              >
            </div>
            <div class="stock-items">
              <v-row v-for="(row, i) in form.items" :key="i" dense align="center"
                ><v-col cols="12" md="5"
                  ><v-autocomplete
                    v-model="row.material_id"
                    :items="options.materials"
                    :item-title="(x: any) => `${x.code} — ${x.name}`"
                    item-value="id"
                    label="Vật tư *"
                    @update:model-value="materialChanged(row)" /></v-col
                ><v-col cols="6" md="2"
                  ><v-text-field
                    v-model.number="row.quantity"
                    type="number"
                    min="0.001"
                    step="0.001"
                    label="Số lượng *" /></v-col
                ><v-col cols="6" md="3"
                  ><v-text-field
                    v-model.number="row.unit_price"
                    type="number"
                    min="0"
                    label="Đơn giá"
                    suffix="₫" /></v-col
                ><v-col cols="10" md="1" class="text-right font-weight-bold">{{
                  money(Number(row.quantity) * Number(row.unit_price))
                }}</v-col
                ><v-col cols="2" md="1"
                  ><v-btn
                    icon="mdi-close"
                    color="error"
                    variant="text"
                    :disabled="form.items.length === 1"
                    @click="form.items.splice(i, 1)" /></v-col
              ></v-row>
            </div>
            <v-textarea v-model="form.note" label="Ghi chú phiếu" rows="2" class="mt-3"
          /></template> </v-card-text
        ><v-divider /><v-card-actions class="pa-4 justify-end"
          ><v-btn variant="text" @click="dialog = false">Hủy</v-btn
          ><v-btn color="primary" prepend-icon="mdi-content-save" :loading="saving" @click="save">{{
            form.kind === 'transactions' ? 'Xác nhận và ghi sổ' : 'Lưu dữ liệu'
          }}</v-btn></v-card-actions
        ></v-card
      ></v-dialog
    >
    <v-dialog v-model="historyDialog" max-width="900"
      ><v-card rounded="xl"
        ><v-card-title class="d-flex justify-space-between align-center pa-5"
          ><span>Lịch sử nhập xuất vật tư</span
          ><v-btn icon="mdi-close" variant="text" @click="historyDialog = false" /></v-card-title
        ><v-divider /><v-data-table
          :items="selectedHistory"
          :headers="[
            { title: 'Ngày', key: 'transaction_date' },
            { title: 'Mã phiếu', key: 'code' },
            { title: 'Loại', key: 'type' },
            { title: 'Kho', key: 'warehouse.name' },
            { title: 'Đối tác', key: 'partner' },
            { title: 'Tổng tiền phiếu', key: 'total_amount', align: 'end' },
          ]"
          ><template #item.type="{ value }"
            ><v-chip :color="value === 'IN' ? 'success' : 'warning'" size="small">{{
              value === 'IN' ? 'Nhập' : 'Xuất'
            }}</v-chip></template
          ><template #item.total_amount="{ value }">{{ money(value) }}</template></v-data-table
        ></v-card
      ></v-dialog
    >
  </div>
</template>

<style scoped>
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
}
.stock-items {
  max-height: 340px;
  overflow: auto;
  padding: 4px;
}
.inventory-page :deep(th) {
  white-space: nowrap;
  background: rgba(var(--v-theme-primary), 0.055);
  font-weight: 700 !important;
}
.inventory-page :deep(td) {
  min-height: 58px;
}
@media (max-width: 600px) {
  .inventory-page :deep(.v-data-table__wrapper) {
    overflow-x: auto;
  }
  .modal-header {
    padding: 16px;
  }
}
</style>
