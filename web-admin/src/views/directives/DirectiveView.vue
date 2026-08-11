<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useDirectiveStore } from '@/stores/directives'
import DatePicker from '@/components/DatePicker.vue'
type Row = Record<string, any>
const route = useRoute(),
  notifications = useDirectiveStore(),
  sentMode = computed(() => route.path.endsWith('/sent'))
const rows = ref<Row[]>([]),
  users = ref<Row[]>([]),
  busy = ref(false),
  saving = ref(false),
  dialog = ref(false),
  detailDialog = ref(false),
  search = ref(''),
  error = ref(''),
  formError = ref(''),
  notice = ref(''),
  attachments = ref<File[]>([]),
  editor = ref<HTMLElement | null>(null),
  detail = ref<Row | null>(null)
const form = reactive<Row>({}),
  filters = reactive({ read_status: null as string | null, from_date: '', to_date: '' })
const headers = computed<
  Array<{ title: string; key: string; align?: 'start' | 'center' | 'end'; sortable?: boolean }>
>(() =>
  sentMode.value
    ? [
        { title: 'Thông tin điều hành', key: 'title' },
        { title: 'Ngày tạo', key: 'created_at' },
        { title: 'Người nhận', key: 'recipients_count', align: 'center' },
        { title: 'Đã xem', key: 'read_count', align: 'center' },
        { title: 'File', key: 'attachments', align: 'center' },
        { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
      ]
    : [
        { title: 'Thông tin điều hành', key: 'title' },
        { title: 'Người gửi', key: 'creator.name' },
        { title: 'Ngày nhận', key: 'created_at' },
        { title: 'Trạng thái', key: 'read_status' },
        { title: 'File', key: 'attachments', align: 'center' },
        { title: 'Thao tác', key: 'actions', align: 'end', sortable: false },
      ],
)
const isRead = (item: Row) => !!item.recipients?.[0]?.pivot?.read_at
const dateTime = (v: string) => (v ? new Date(v).toLocaleString('vi-VN') : '—')
const size = (v: number) =>
  v < 1048576 ? `${(v / 1024).toFixed(1)} KB` : `${(v / 1048576).toFixed(1)} MB`
async function loadUsers() {
  if (sentMode.value) users.value = (await api<any>('/directives/options')).data.users || []
}
async function load() {
  busy.value = true
  error.value = ''
  try {
    const p = new URLSearchParams()
    if (search.value) p.set('search', search.value)
    if (filters.read_status && !sentMode.value) p.set('read_status', filters.read_status)
    if (filters.from_date) p.set('from_date', filters.from_date)
    if (filters.to_date) p.set('to_date', filters.to_date)
    const data = (await api<any>(`/directives/${sentMode.value ? 'sent' : 'inbox'}?${p}`)).data
    rows.value = data.data || data
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
function emptyForm() {
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, {
    title: '',
    code: '',
    content: '',
    recipient_ids: [],
    is_active: true,
    remove_attachment_ids: [],
  })
  attachments.value = []
}
async function add() {
  emptyForm()
  formError.value = ''
  dialog.value = true
  await nextTick()
  if (editor.value) editor.value.innerHTML = ''
}
async function edit(item: Row) {
  emptyForm()
  formError.value = ''
  Object.assign(form, JSON.parse(JSON.stringify(item)), {
    recipient_ids: item.recipients?.map((x: Row) => x.id) || [],
    remove_attachment_ids: [],
  })
  dialog.value = true
  await nextTick()
  if (editor.value) editor.value.innerHTML = form.content || ''
}
function format(command: string, value?: string) {
  editor.value?.focus()
  document.execCommand(command, false, value)
}
function removeAttachment(item: Row) {
  form.remove_attachment_ids.push(item.id)
  form.attachments = form.attachments.filter((x: Row) => x.id !== item.id)
}
async function save() {
  formError.value = ''
  form.content = editor.value?.innerHTML || ''
  if (!form.content.replace(/<[^>]+>/g, '').trim()) {
    formError.value = 'Vui lòng nhập nội dung thông tin điều hành.'
    return
  }
  saving.value = true
  try {
    const fd = new FormData()
    for (const key of ['code', 'title', 'content', 'is_active'])
      if (form[key] !== null && form[key] !== undefined)
        fd.append(key, typeof form[key] === 'boolean' ? (form[key] ? '1' : '0') : String(form[key]))
    form.recipient_ids.forEach((id: number) => fd.append('recipient_ids[]', String(id)))
    form.remove_attachment_ids?.forEach((id: number) =>
      fd.append('remove_attachment_ids[]', String(id)),
    )
    attachments.value.forEach((f) => fd.append('attachments[]', f))
    await api(`/directives${form.id ? `/${form.id}` : ''}`, { method: 'POST', body: fd })
    dialog.value = false
    notice.value = form.id ? 'Đã cập nhật thông tin điều hành' : 'Đã gửi thông tin điều hành'
    await load()
  } catch (e: any) {
    if (dialog.value) formError.value = e.message
    else error.value = e.message
  } finally {
    saving.value = false
  }
}
async function view(item: Row) {
  try {
    detail.value = (await api<Row>(`/directives/${item.id}`)).data
    detailDialog.value = true
    await notifications.refresh()
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function remove(item: Row) {
  if (!confirm(`Xóa thông tin “${item.title}”?`)) return
  try {
    await api(`/directives/${item.id}`, { method: 'DELETE' })
    notice.value = 'Đã xóa thông tin điều hành'
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}
async function download(file: Row) {
  const base = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
    token = localStorage.getItem('token')
  const response = await fetch(`${base}/directives/attachments/${file.id}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!response.ok) {
    error.value = 'Không thể tải file đính kèm.'
    return
  }
  const url = URL.createObjectURL(await response.blob()),
    a = document.createElement('a')
  a.href = url
  a.download = file.original_name
  a.click()
  URL.revokeObjectURL(url)
}
let timer: number | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = window.setTimeout(load, 350)
})
watch(dialog, (isOpen) => {
  if (!isOpen) formError.value = ''
})
watch(
  () => route.path,
  async () => {
    await loadUsers()
    await load()
  },
)
onMounted(async () => {
  await loadUsers()
  await load()
})
</script>
<template>
  <div class="directive-page">
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
    <div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-3 mb-5">
      <div>
        <div class="text-h5 font-weight-bold">
          {{ sentMode ? 'Thông tin điều hành đã gửi' : 'Thông tin điều hành được nhận' }}
        </div>
        <div class="text-body-2 text-medium-emphasis">
          {{
            sentMode
              ? 'Tạo phiếu, phân công người nhận và theo dõi tình trạng đã xem'
              : 'Tra cứu và tiếp nhận thông tin chỉ đạo nội bộ'
          }}
        </div>
      </div>
      <v-btn
        v-if="sentMode"
        color="primary"
        size="large"
        prepend-icon="mdi-send-plus-outline"
        @click="add"
        >Tạo phiếu thông tin</v-btn
      >
    </div>
    <v-card class="pa-4 mb-5 directive-filter" border rounded="xl"
      ><v-row dense
        ><v-col cols="12" md="5"
          ><v-text-field
            v-model="search"
            label="Tìm mã, tiêu đề hoặc nội dung"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details /></v-col
        ><v-col v-if="!sentMode" cols="12" sm="4" md="3"
          ><v-select
            v-model="filters.read_status"
            :items="[
              { title: 'Chưa xem', value: 'unread' },
              { title: 'Đã xem', value: 'read' },
            ]"
            label="Trạng thái xem"
            clearable
            hide-details
            @update:model-value="load" /></v-col
        ><v-col cols="12" sm="6" md="2"
          ><DatePicker
            v-model="filters.from_date"
            label="Từ ngày"
            @change="load" /></v-col
        ><v-col cols="12" sm="6" md="2"
          ><DatePicker
            v-model="filters.to_date"
            label="Đến ngày"
            :min="filters.from_date"
            @change="load" /></v-col></v-row
    ></v-card>
    <v-card border rounded="xl" class="overflow-hidden"
      ><v-data-table :headers="headers" :items="rows" :loading="busy" hover items-per-page="20"
        ><template #item.title="{ item }"
          ><div class="py-2">
            <div class="d-flex align-center ga-2">
              <v-icon
                v-if="!sentMode && !isRead(item)"
                icon="mdi-circle"
                color="error"
                size="10"
              /><span :class="{ 'font-weight-bold': !sentMode && !isRead(item) }">{{
                item.title
              }}</span>
            </div>
            <div class="text-caption text-medium-emphasis">{{ item.code }}</div>
          </div></template
        ><template #item.created_at="{ value }"
          ><span class="text-no-wrap">{{ dateTime(value) }}</span></template
        ><template #item.read_count="{ item }"
          ><v-chip
            :color="item.read_count === item.recipients_count ? 'success' : 'warning'"
            size="small"
            variant="tonal"
            >{{ item.read_count }}/{{ item.recipients_count }}</v-chip
          ></template
        ><template #item.read_status="{ item }"
          ><v-chip
            :color="isRead(item) ? 'success' : 'error'"
            size="small"
            variant="tonal"
            :prepend-icon="isRead(item) ? 'mdi-check-circle-outline' : 'mdi-email-alert-outline'"
            >{{ isRead(item) ? 'Đã xem' : 'Chưa xem' }}</v-chip
          ></template
        ><template #item.attachments="{ value }"
          ><v-chip v-if="value?.length" size="small" prepend-icon="mdi-paperclip" variant="tonal">{{
            value.length
          }}</v-chip
          ><span v-else>—</span></template
        ><template #item.actions="{ item }"
          ><div class="d-flex justify-end">
            <v-btn
              icon="mdi-eye-outline"
              color="primary"
              variant="text"
              title="Xem chi tiết"
              @click="view(item)"
            /><template v-if="sentMode"
              ><v-btn
                icon="mdi-pencil-outline"
                variant="text"
                title="Sửa"
                @click="edit(item)" /><v-btn
                icon="mdi-delete-outline"
                color="error"
                variant="text"
                title="Xóa"
                @click="remove(item)"
            /></template></div></template
        ><template #no-data
          ><div class="empty-state">
            <v-icon icon="mdi-inbox-outline" size="52" />
            <div class="mt-2">Chưa có thông tin điều hành phù hợp</div>
          </div></template
        ></v-data-table
      ></v-card
    >
    <v-dialog v-model="dialog" max-width="1040" scrollable
      ><v-card class="user-modal" rounded="xl"
        ><div class="user-modal__header">
          <div class="d-flex align-center ga-3">
            <v-avatar color="white"
              ><v-icon color="primary" icon="mdi-bullhorn-outline"
            /></v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ form.id ? 'Cập nhật' : 'Tạo' }} phiếu thông tin điều hành
              </div>
              <div class="text-body-2 opacity-80">
                Nội dung gửi đến đúng những tài khoản được lựa chọn
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" color="white" variant="text" @click="dialog = false" />
        </div>
        <v-card-text class="user-modal__body"
          ><v-form id="directive-form" @submit.prevent="save"
            ><v-alert v-if="formError" type="error" variant="tonal" class="mb-4">{{
              formError
            }}</v-alert>
            ><div class="form-section">
              <div class="form-section__title">
                <v-icon icon="mdi-file-document-edit-outline" />Thông tin phiếu
              </div>
              <v-row dense
                ><v-col cols="12" md="4"
                  ><v-text-field v-model="form.code" label="Mã phiếu (tự sinh nếu trống)" /></v-col
                ><v-col cols="12" md="8"
                  ><v-text-field v-model="form.title" label="Tiêu đề *" required /></v-col
                ><v-col cols="12"
                  ><v-autocomplete
                    v-model="form.recipient_ids"
                    :items="users"
                    :item-title="(x: any) => `${x.name} — ${x.email}`"
                    item-value="id"
                    label="Người nhận *"
                    prepend-inner-icon="mdi-account-multiple-check-outline"
                    multiple
                    chips
                    closable-chips
                    required /></v-col
              ></v-row>
            </div>
            <div class="form-section">
              <div class="form-section__title">
                <v-icon icon="mdi-format-text" />Nội dung điều hành
              </div>
              <div class="editor-toolbar">
                <v-btn
                  icon="mdi-format-bold"
                  size="small"
                  variant="text"
                  @click="format('bold')"
                /><v-btn
                  icon="mdi-format-italic"
                  size="small"
                  variant="text"
                  @click="format('italic')"
                /><v-btn
                  icon="mdi-format-underline"
                  size="small"
                  variant="text"
                  @click="format('underline')"
                /><v-divider vertical /><v-btn
                  icon="mdi-format-list-bulleted"
                  size="small"
                  variant="text"
                  @click="format('insertUnorderedList')"
                /><v-btn
                  icon="mdi-format-list-numbered"
                  size="small"
                  variant="text"
                  @click="format('insertOrderedList')"
                /><v-btn
                  icon="mdi-format-quote-close"
                  size="small"
                  variant="text"
                  @click="format('formatBlock', 'blockquote')"
                /><v-btn
                  icon="mdi-format-header-2"
                  size="small"
                  variant="text"
                  @click="format('formatBlock', 'h2')"
                />
              </div>
              <div
                ref="editor"
                class="directive-editor"
                contenteditable="true"
                data-placeholder="Nhập nội dung thông tin điều hành…"
              />
            </div>
            <div class="form-section">
              <div class="form-section__title"><v-icon icon="mdi-paperclip" />File đính kèm</div>
              <div v-if="form.attachments?.length" class="mb-3">
                <v-chip
                  v-for="file in form.attachments"
                  :key="file.id"
                  class="mr-2 mb-2"
                  closable
                  prepend-icon="mdi-file-outline"
                  @click:close="removeAttachment(file)"
                  >{{ file.original_name }}</v-chip
                >
              </div>
              <v-file-input
                v-model="attachments"
                multiple
                chips
                label="Chọn file đính kèm"
                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                hint="Tối đa 10 file, 20 MB mỗi file"
                persistent-hint
              />
            </div>
            <div class="status-panel">
              <div>
                <div class="font-weight-bold">Trạng thái phiếu</div>
                <div class="text-caption text-medium-emphasis">
                  Phiếu ngừng hoạt động sẽ không hiển thị trong hộp thư người nhận
                </div>
              </div>
              <v-switch
                v-model="form.is_active"
                color="success"
                label="Đang hoạt động"
                hide-details
                inset
              /></div></v-form></v-card-text
        ><v-divider /><v-card-actions class="user-modal__actions"
          ><v-spacer /><v-btn variant="text" @click="dialog = false">Hủy</v-btn
          ><v-btn
            color="primary"
            size="large"
            type="submit"
            form="directive-form"
            prepend-icon="mdi-send"
            :loading="saving"
            >{{ form.id ? 'Lưu thay đổi' : 'Gửi thông tin' }}</v-btn
          ></v-card-actions
        ></v-card
      ></v-dialog
    >
    <v-dialog v-model="detailDialog" max-width="980" scrollable
      ><v-card v-if="detail" rounded="xl"
        ><div class="directive-detail__header">
          <div>
            <v-chip color="primary" size="small" variant="tonal" class="mb-2">{{
              detail.code
            }}</v-chip>
            <div class="text-h5 font-weight-bold">{{ detail.title }}</div>
            <div class="text-body-2 text-medium-emphasis mt-2">
              {{ detail.creator?.name }} · {{ dateTime(detail.created_at) }}
            </div>
          </div>
          <v-btn icon="mdi-close" variant="text" @click="detailDialog = false" />
        </div>
        <v-divider /><v-card-text class="pa-5 pa-md-7"
          ><div class="directive-content" v-html="detail.content" />
          <template v-if="detail.attachments?.length"
            ><v-divider class="my-6" />
            <div class="font-weight-bold mb-3">
              <v-icon icon="mdi-paperclip" class="mr-2" />File đính kèm
            </div>
            <v-list border rounded="lg"
              ><v-list-item
                v-for="file in detail.attachments"
                :key="file.id"
                :title="file.original_name"
                :subtitle="size(file.file_size)"
                prepend-icon="mdi-file-outline"
                ><template #append
                  ><v-btn
                    icon="mdi-download"
                    color="primary"
                    variant="text"
                    @click="download(file)" /></template></v-list-item></v-list></template
          ><template v-if="sentMode"
            ><v-divider class="my-6" />
            <div class="font-weight-bold mb-3">Tình trạng người nhận</div>
            <v-table
              ><thead>
                <tr>
                  <th>Người nhận</th>
                  <th>Email</th>
                  <th>Trạng thái</th>
                  <th>Thời gian xem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="person in detail.recipients" :key="person.id">
                  <td>{{ person.name }}</td>
                  <td>{{ person.email }}</td>
                  <td>
                    <v-chip
                      :color="person.pivot.read_at ? 'success' : 'warning'"
                      size="small"
                      variant="tonal"
                      >{{ person.pivot.read_at ? 'Đã xem' : 'Chưa xem' }}</v-chip
                    >
                  </td>
                  <td>{{ person.pivot.read_at ? dateTime(person.pivot.read_at) : '—' }}</td>
                </tr>
              </tbody></v-table
            ></template
          ></v-card-text
        ></v-card
      ></v-dialog
    >
  </div>
</template>
<style scoped>
.directive-filter {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.06),
    rgb(var(--v-theme-surface))
  );
}
.editor-toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 7px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 0;
  border-radius: 10px 10px 0 0;
  background: rgba(var(--v-theme-on-surface), 0.035);
}
.directive-editor {
  min-height: 260px;
  padding: 18px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0 0 10px 10px;
  outline: none;
  line-height: 1.65;
  background: rgb(var(--v-theme-surface));
}
.directive-editor:empty:before {
  content: attr(data-placeholder);
  color: rgba(var(--v-theme-on-surface), 0.45);
}
.directive-editor:focus {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}
.directive-detail__header {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 24px;
}
.directive-content {
  font-size: 15px;
  line-height: 1.75;
}
.directive-content :deep(h1),
.directive-content :deep(h2) {
  margin: 20px 0 10px;
}
.directive-content :deep(blockquote) {
  margin: 16px 0;
  padding: 12px 18px;
  border-left: 4px solid rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}
@media (max-width: 600px) {
  .editor-toolbar {
    overflow-x: auto;
  }
  .directive-detail__header {
    padding: 18px;
  }
  .directive-page :deep(.v-data-table__wrapper) {
    overflow-x: auto;
  }
}
</style>
