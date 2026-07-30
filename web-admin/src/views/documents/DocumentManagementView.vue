<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { api } from '@/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'

type Row = Record<string, any>
const auth = useAuthStore()
const tab = ref('documents'),
  rows = ref<Row[]>([]),
  categories = ref<Row[]>([]),
  busy = ref(false),
  saving = ref(false),
  dialog = ref(false),
  previewDialog = ref(false)
const search = ref(''),
  showDeleted = ref(false),
  error = ref(''),
  notice = ref(''),
  file = ref<File | null>(null),
  form = reactive<Row>({})
const filters = reactive({
  category_id: null as number | null,
  is_active: null as boolean | null,
  from_date: '',
  to_date: '',
})
const previewUrl = ref(''),
  previewName = ref(''),
  previewLoading = ref(false)
const categoryHeaders = [
  { title: 'Mã danh mục', key: 'code' },
  { title: 'Tên loại tài liệu', key: 'name' },
  { title: 'Ngày tạo', key: 'created_at' },
  { title: 'Trạng thái', key: 'is_active' },
  { title: 'Thao tác', key: 'actions', align: 'end' as const, sortable: false },
]
const documentHeaders = [
  { title: 'Tài liệu', key: 'name' },
  { title: 'Loại tài liệu', key: 'category.name' },
  { title: 'Ngày tài liệu', key: 'document_date' },
  { title: 'Định dạng', key: 'extension' },
  { title: 'Dung lượng', key: 'file_size', align: 'end' as const },
  { title: 'Người tạo', key: 'creator.name' },
  { title: 'Trạng thái', key: 'is_active' },
  { title: 'Thao tác', key: 'actions', align: 'end' as const, sortable: false },
]
const activeCount = computed(() => rows.value.filter((x) => x.is_active && !x.deleted_at).length)
const canManage = computed(() => auth.user?.permissions.includes('documents.manage') ?? false)
const formatDate = (v: string) => (v ? new Date(v).toLocaleDateString('vi-VN') : '—')
const formatSize = (v: number) => {
  if (!v) return '0 KB'
  if (v < 1048576) return `${(v / 1024).toFixed(1)} KB`
  return `${(v / 1048576).toFixed(1)} MB`
}
const fileIcon = (ext: string) =>
  ({
    pdf: 'mdi-file-pdf-box',
    doc: 'mdi-file-word-outline',
    docx: 'mdi-file-word-outline',
    xls: 'mdi-file-excel-outline',
    xlsx: 'mdi-file-excel-outline',
    jpg: 'mdi-file-image-outline',
    jpeg: 'mdi-file-image-outline',
    png: 'mdi-file-image-outline',
    webp: 'mdi-file-image-outline',
  })[ext] || 'mdi-file-outline'
const fileColor = (ext: string) =>
  ext === 'pdf'
    ? 'error'
    : ['doc', 'docx'].includes(ext)
      ? 'info'
      : ['xls', 'xlsx'].includes(ext)
        ? 'success'
        : 'secondary'
async function loadOptions() {
  categories.value = (await api<Row>('/documents/options')).data.categories || []
}
async function load() {
  busy.value = true
  error.value = ''
  try {
    const p = new URLSearchParams()
    if (search.value) p.set('search', search.value)
    if (showDeleted.value) p.set('with_deleted', '1')
    if (tab.value === 'documents') {
      if (filters.category_id) p.set('category_id', String(filters.category_id))
      if (filters.is_active !== null) p.set('is_active', filters.is_active ? '1' : '0')
      if (filters.from_date) p.set('from_date', filters.from_date)
      if (filters.to_date) p.set('to_date', filters.to_date)
    }
    const endpoint = tab.value === 'categories' ? '/documents/categories' : '/documents'
    const data = (await api<any>(`${endpoint}?${p}`)).data
    rows.value = data.data || data
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
function resetForm() {
  Object.keys(form).forEach((k) => delete form[k])
  file.value = null
}
function add() {
  resetForm()
  Object.assign(
    form,
    tab.value === 'categories'
      ? { kind: 'categories', is_active: true }
      : {
          kind: 'documents',
          is_active: true,
          document_date: new Date().toISOString().slice(0, 10),
        },
  )
  dialog.value = true
}
function edit(row: Row) {
  resetForm()
  Object.assign(form, JSON.parse(JSON.stringify(row)), { kind: tab.value })
  dialog.value = true
}
async function save() {
  saving.value = true
  error.value = ''
  try {
    let endpoint = form.kind === 'categories' ? '/documents/categories' : '/documents'
    if (form.id) endpoint += `/${form.id}`
    if (form.kind === 'categories') {
      await api(endpoint, { method: form.id ? 'PUT' : 'POST', body: JSON.stringify(form) })
    } else {
      const fd = new FormData()
      for (const key of [
        'document_category_id',
        'code',
        'name',
        'document_date',
        'description',
        'is_active',
      ])
        if (form[key] !== null && form[key] !== undefined)
          fd.append(
            key,
            typeof form[key] === 'boolean' ? (form[key] ? '1' : '0') : String(form[key]),
          )
      if (file.value) fd.append('file', file.value)
      await api(endpoint, { method: 'POST', body: fd })
    }
    dialog.value = false
    notice.value = form.id ? 'Đã cập nhật tài liệu' : 'Đã thêm dữ liệu'
    await Promise.all([load(), loadOptions()])
  } catch (e: any) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function remove(row: Row) {
  if (!confirm(`Xóa ${row.name}? Dữ liệu có thể khôi phục lại.`)) return
  try {
    await api(`/documents${tab.value === 'categories' ? '/categories' : ''}/${row.id}`, {
      method: 'DELETE',
    })
    notice.value = 'Đã chuyển dữ liệu vào danh sách đã xóa'
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function restore(row: Row) {
  try {
    await api(`/documents${tab.value === 'categories' ? '/categories' : ''}/${row.id}/restore`, {
      method: 'POST',
    })
    notice.value = 'Đã khôi phục dữ liệu'
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function authFile(row: Row, mode: 'preview' | 'download') {
  const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
    token = localStorage.getItem('token')
  return fetch(`${base}/documents/${row.id}/${mode}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
}
async function preview(row: Row) {
  previewDialog.value = true
  previewLoading.value = true
  previewName.value = row.name
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  try {
    const response = await authFile(row, 'preview')
    if (!response.ok) {
      const result = await response.json()
      throw new Error(result.message || 'Không thể xem tài liệu.')
    }
    previewUrl.value = URL.createObjectURL(await response.blob())
  } catch (e: any) {
    error.value = e.message
    previewDialog.value = false
  } finally {
    previewLoading.value = false
  }
}
async function download(row: Row) {
  try {
    const response = await authFile(row, 'download')
    if (!response.ok) throw new Error((await response.json()).message || 'Không thể tải tài liệu.')
    const url = URL.createObjectURL(await response.blob()),
      a = document.createElement('a')
    a.href = url
    a.download = row.original_name || row.name
    a.click()
    URL.revokeObjectURL(url)
  } catch (e: any) {
    error.value = e.message
  }
}
let timer: number | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = window.setTimeout(load, 350)
})
watch([tab, showDeleted], load)
onMounted(async () => {
  await loadOptions()
  await load()
})
onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>

<template>
  <div class="document-page">
    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="error = ''"
      >{{ error }}</v-alert
    ><v-snackbar
      :model-value="!!notice"
      color="success"
      @update:model-value="
        (v) => {
          if (!v) notice = ''
        }
      "
      >{{ notice }}</v-snackbar
    >
    <v-card v-if="canManage" border rounded="xl" class="mb-5 overflow-hidden"
      ><v-tabs v-model="tab" color="primary"
        ><v-tab value="documents" prepend-icon="mdi-file-document-multiple-outline"
          >Tài liệu, văn bản</v-tab
        ><v-tab value="categories" prepend-icon="mdi-folder-multiple-outline"
          >Loại tài liệu</v-tab
        ></v-tabs
      ></v-card
    >
    <div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-3 mb-5">
      <div>
        <div class="text-h5 font-weight-bold">
          {{
            canManage
              ? tab === 'documents'
                ? 'Quản lý tài liệu, văn bản'
                : 'Danh mục loại tài liệu'
              : 'Tra cứu văn bản, tài liệu'
          }}
        </div>
        <div class="text-body-2 text-medium-emphasis">
          Lưu trữ, tra cứu và xem tài liệu nội bộ an toàn
        </div>
      </div>
      <v-btn v-if="canManage" color="primary" size="large" prepend-icon="mdi-plus" @click="add">{{
        tab === 'documents' ? 'Thêm tài liệu' : 'Thêm loại tài liệu'
      }}</v-btn>
    </div>
    <v-card class="pa-4 mb-5 document-filter" border rounded="xl"
      ><v-row dense align="center"
        ><v-col cols="12" :md="tab === 'documents' ? 4 : 8"
          ><v-text-field
            v-model="search"
            label="Tìm mã, tên, tên file hoặc nội dung mô tả"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details /></v-col
        ><v-col v-if="tab === 'documents'" cols="12" sm="6" md="3"
          ><v-select
            v-model="filters.category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Tất cả loại tài liệu"
            clearable
            hide-details
            @update:model-value="load" /></v-col
        ><v-col v-if="tab === 'documents'" cols="12" sm="6" md="2"
          ><v-select
            v-model="filters.is_active"
            :items="[
              { title: 'Hoạt động', value: true },
              { title: 'Ngừng hoạt động', value: false },
            ]"
            label="Trạng thái"
            clearable
            hide-details
            @update:model-value="load" /></v-col
        ><v-col v-if="canManage" cols="12" :md="tab === 'documents' ? 3 : 4"
          ><v-checkbox
            v-model="showDeleted"
            label="Hiện dữ liệu đã xóa"
            color="primary"
            hide-details /></v-col></v-row
    ></v-card>
    <v-row v-if="tab === 'documents'" class="mb-2"
      ><v-col cols="12" sm="6" md="4"
        ><v-card border rounded="xl" class="pa-4"
          ><div class="d-flex align-center ga-3">
            <v-avatar color="primary" variant="tonal" rounded="lg"
              ><v-icon icon="mdi-file-document-multiple"
            /></v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Tổng tài liệu đang hiển thị</div>
              <div class="text-h6 font-weight-bold">{{ rows.length }}</div>
            </div>
          </div></v-card
        ></v-col
      ><v-col cols="12" sm="6" md="4"
        ><v-card border rounded="xl" class="pa-4"
          ><div class="d-flex align-center ga-3">
            <v-avatar color="success" variant="tonal" rounded="lg"
              ><v-icon icon="mdi-file-check-outline"
            /></v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Đang hoạt động</div>
              <div class="text-h6 font-weight-bold">{{ activeCount }}</div>
            </div>
          </div></v-card
        ></v-col
      ><v-col cols="12" md="4"
        ><v-card border rounded="xl" class="pa-4"
          ><div class="d-flex align-center ga-3">
            <v-avatar color="info" variant="tonal" rounded="lg"
              ><v-icon icon="mdi-folder-multiple-outline"
            /></v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Loại tài liệu</div>
              <div class="text-h6 font-weight-bold">{{ categories.length }}</div>
            </div>
          </div></v-card
        ></v-col
      ></v-row
    >
    <v-card border rounded="xl" class="overflow-hidden"
      ><v-data-table
        v-if="tab === 'documents'"
        :headers="documentHeaders"
        :items="rows"
        :loading="busy"
        hover
        items-per-page="20"
        ><template #item.name="{ item }"
          ><div class="d-flex align-center ga-3 py-2">
            <v-avatar :color="fileColor(item.extension)" variant="tonal" rounded="lg"
              ><v-icon :icon="fileIcon(item.extension)"
            /></v-avatar>
            <div class="min-width-0">
              <div class="font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.code }} · {{ item.original_name }}
              </div>
            </div>
          </div></template
        ><template #item.document_date="{ value }">{{ formatDate(value) }}</template
        ><template #item.extension="{ value }"
          ><v-chip size="small" variant="tonal" class="text-uppercase font-weight-bold">{{
            value
          }}</v-chip></template
        ><template #item.file_size="{ value }">{{ formatSize(value) }}</template
        ><template #item.is_active="{ item }"
          ><v-chip v-if="item.deleted_at" color="error" size="small" variant="tonal">Đã xóa</v-chip
          ><v-chip
            v-else
            :color="item.is_active ? 'success' : 'default'"
            size="small"
            variant="tonal"
            >{{ item.is_active ? 'Hoạt động' : 'Ngừng' }}</v-chip
          ></template
        ><template #item.actions="{ item }"
          ><div class="d-flex justify-end ga-1">
            <template v-if="item.deleted_at"
              ><v-btn
                v-if="canManage"
                icon="mdi-restore"
                color="success"
                variant="text"
                title="Khôi phục"
                @click="restore(item)" /></template
            ><template v-else
              ><v-btn
                icon="mdi-eye-outline"
                color="primary"
                variant="text"
                title="Xem trực tiếp"
                @click="preview(item)" /><v-btn
                icon="mdi-download-outline"
                color="info"
                variant="text"
                title="Tải xuống"
                @click="download(item)" /><v-btn
                v-if="canManage"
                icon="mdi-pencil-outline"
                variant="text"
                title="Chỉnh sửa"
                @click="edit(item)" /><v-btn
                v-if="canManage"
                icon="mdi-delete-outline"
                color="error"
                variant="text"
                title="Xóa"
                @click="remove(item)"
            /></template></div></template
        ><template #no-data
          ><div class="empty-state">
            <v-icon icon="mdi-file-search-outline" size="52" />
            <div class="mt-2">Không tìm thấy tài liệu phù hợp</div>
          </div></template
        ></v-data-table
      >
      <v-data-table v-else :headers="categoryHeaders" :items="rows" :loading="busy" hover
        ><template #item.created_at="{ value }">{{ formatDate(value) }}</template
        ><template #item.is_active="{ item }"
          ><v-chip v-if="item.deleted_at" color="error" size="small" variant="tonal">Đã xóa</v-chip
          ><v-chip
            v-else
            :color="item.is_active ? 'success' : 'default'"
            size="small"
            variant="tonal"
            >{{ item.is_active ? 'Hoạt động' : 'Ngừng' }}</v-chip
          ></template
        ><template #item.actions="{ item }"
          ><v-btn
            v-if="item.deleted_at"
            icon="mdi-restore"
            color="success"
            variant="text"
            @click="restore(item)" /><template v-else
            ><v-btn icon="mdi-pencil-outline" variant="text" @click="edit(item)" /><v-btn
              icon="mdi-delete-outline"
              color="error"
              variant="text"
              @click="remove(item)" /></template></template></v-data-table
    ></v-card>
    <v-dialog v-model="dialog" max-width="860" scrollable
      ><v-card class="user-modal" rounded="xl"
        ><div class="user-modal__header">
          <div class="d-flex align-center ga-3">
            <v-avatar color="white"
              ><v-icon
                color="primary"
                :icon="
                  form.kind === 'documents'
                    ? 'mdi-file-document-edit-outline'
                    : 'mdi-folder-edit-outline'
                "
            /></v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ form.id ? 'Cập nhật' : 'Thêm' }}
                {{ form.kind === 'documents' ? 'tài liệu, văn bản' : 'loại tài liệu' }}
              </div>
              <div class="text-body-2 opacity-80">Thông tin được lưu trong kho tài liệu nội bộ</div>
            </div>
          </div>
          <v-btn icon="mdi-close" variant="text" color="white" @click="dialog = false" />
        </div>
        <v-card-text class="user-modal__body"
          ><v-form id="document-form" @submit.prevent="save"
            ><div class="form-section">
              <div class="form-section__title">
                <v-icon icon="mdi-text-box-edit-outline" />Thông tin tài liệu
              </div>
              <v-row dense
                ><v-col cols="12" md="4"
                  ><v-text-field v-model="form.code" label="Mã *" required /></v-col
                ><v-col cols="12" md="8"
                  ><v-text-field v-model="form.name" label="Tên *" required /></v-col
                ><template v-if="form.kind === 'documents'"
                  ><v-col cols="12" md="6"
                    ><v-select
                      v-model="form.document_category_id"
                      :items="categories"
                      item-title="name"
                      item-value="id"
                      label="Loại tài liệu *"
                      required /></v-col
                  ><v-col cols="12" md="6"
                    ><DatePicker
                      v-model="form.document_date"
                      label="Ngày tạo tài liệu *"
                      :hide-details="false"
                      required /></v-col
                  ><v-col cols="12"
                    ><v-file-input
                      v-model="file"
                      :label="form.id ? 'Thay file tài liệu (không bắt buộc)' : 'File tài liệu *'"
                      accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                      prepend-icon="mdi-paperclip"
                      :hint="
                        form.id
                          ? `File hiện tại: ${form.original_name}`
                          : 'PDF, ảnh, Word hoặc Excel · tối đa 20 MB'
                      "
                      persistent-hint
                      :required="!form.id" /></v-col
                  ><v-col cols="12"
                    ><v-textarea
                      v-model="form.description"
                      label="Mô tả, ghi chú"
                      rows="3" /></v-col></template
                ><v-col cols="12"
                  ><div class="status-panel">
                    <div>
                      <div class="font-weight-bold">Trạng thái sử dụng</div>
                      <div class="text-caption text-medium-emphasis">
                        Tài liệu ngừng hoạt động vẫn được lưu trữ và tra cứu
                      </div>
                    </div>
                    <v-switch
                      v-model="form.is_active"
                      color="success"
                      :label="form.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'"
                      hide-details
                      inset
                    /></div></v-col
              ></v-row></div></v-form></v-card-text
        ><v-divider /><v-card-actions class="user-modal__actions"
          ><v-spacer /><v-btn variant="text" @click="dialog = false">Hủy</v-btn
          ><v-btn
            color="primary"
            size="large"
            type="submit"
            form="document-form"
            prepend-icon="mdi-content-save"
            :loading="saving"
            >Lưu dữ liệu</v-btn
          ></v-card-actions
        ></v-card
      ></v-dialog
    >
    <v-dialog v-model="previewDialog" fullscreen transition="dialog-bottom-transition"
      ><v-card
        ><v-toolbar color="surface" border
          ><v-btn icon="mdi-close" @click="previewDialog = false" /><v-toolbar-title
            class="font-weight-bold"
            >{{ previewName }}</v-toolbar-title
          ><v-spacer /><v-chip
            color="primary"
            variant="tonal"
            prepend-icon="mdi-shield-lock-outline"
            >Xem nội bộ</v-chip
          ></v-toolbar
        >
        <div class="document-preview">
          <div v-if="previewLoading" class="document-preview__loading">
            <v-progress-circular indeterminate color="primary" size="48" />
            <div>Đang tải tài liệu…</div>
          </div>
          <iframe v-else-if="previewUrl" :src="previewUrl" title="Xem tài liệu" /></div></v-card
    ></v-dialog>
  </div>
</template>

<style scoped>
.document-filter {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.06),
    rgb(var(--v-theme-surface))
  );
}
.document-page :deep(th) {
  white-space: nowrap;
}
.document-preview {
  height: calc(100vh - 64px);
  background: #cfd4d1;
}
.document-preview iframe {
  width: 100%;
  height: 100%;
  border: 0;
  background: white;
}
.document-preview__loading {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  color: rgb(var(--v-theme-on-surface));
  background: rgb(var(--v-theme-background));
}
@media (max-width: 600px) {
  .document-page :deep(.v-data-table__wrapper) {
    overflow-x: auto;
  }
  .document-preview {
    height: calc(100vh - 56px);
  }
}
</style>
