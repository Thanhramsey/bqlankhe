<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    label: string
    min?: string
    max?: string
    clearable?: boolean
    required?: boolean
    hideDetails?: boolean
    hint?: string
  }>(),
  { modelValue: '', clearable: true, required: false, hideDetails: true },
)

const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const menu = ref(false)
const displayValue = computed(() => {
  if (!props.modelValue) return ''
  const [year, month, day] = props.modelValue.slice(0, 10).split('-')
  return day && month && year ? `${day}/${month}/${year}` : ''
})
const selectedDate = computed(() =>
  props.modelValue ? new Date(`${props.modelValue.slice(0, 10)}T00:00:00`) : null,
)
const minDate = computed(() =>
  props.min ? new Date(`${props.min.slice(0, 10)}T00:00:00`) : undefined,
)
const maxDate = computed(() =>
  props.max ? new Date(`${props.max.slice(0, 10)}T00:00:00`) : undefined,
)

function update(value: unknown) {
  const date = value instanceof Date ? value : new Date(String(value))
  if (Number.isNaN(date.getTime())) return
  const result = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  emit('update:modelValue', result)
  emit('change', result)
  menu.value = false
}
function clear() {
  emit('update:modelValue', '')
  emit('change', '')
}
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" location="bottom" max-width="370">
    <template #activator="{ props: activatorProps }">
      <v-text-field
        v-bind="activatorProps"
        :model-value="displayValue"
        :label="label"
        placeholder="dd/mm/yyyy"
        prepend-inner-icon="mdi-calendar-outline"
        append-inner-icon="mdi-calendar-chevron-down"
        readonly
        :clearable="clearable"
        :required="required"
        :hide-details="hideDetails"
        :hint="hint"
        :persistent-hint="!!hint"
        @click:clear.stop="clear"
      />
    </template>
    <v-card rounded="xl" elevation="14" class="overflow-hidden">
      <v-date-picker
        :model-value="selectedDate"
        :min="minDate"
        :max="maxDate"
        :title="label"
        color="primary"
        show-adjacent-months
        @update:model-value="update"
      />
      <v-divider />
      <div class="d-flex justify-end pa-2">
        <v-btn variant="text" @click="menu = false">Đóng</v-btn>
      </div>
    </v-card>
  </v-menu>
</template>
