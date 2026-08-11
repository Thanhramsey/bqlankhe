<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(defineProps<{ modelValue: string; label: string; min?: string; hideDetails?: boolean }>(), {
  hideDetails: true,
})
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const menu = ref(false)
const year = ref(Number(props.modelValue?.slice(0, 4)) || new Date().getFullYear())
const months = Array.from({ length: 12 }, (_, index) => index + 1)
const displayValue = computed(() => props.modelValue ? `${props.modelValue.slice(5, 7)}/${props.modelValue.slice(0, 4)}` : '')

watch(() => props.modelValue, (value) => {
  if (value) year.value = Number(value.slice(0, 4))
})

function selectMonth(month: number) {
  const value = `${year.value}-${String(month).padStart(2, '0')}`
  if (props.min && value < props.min) return
  emit('update:modelValue', value)
  menu.value = false
}
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" location="bottom" max-width="360">
    <template #activator="{ props: activatorProps }">
      <v-text-field v-bind="activatorProps" :model-value="displayValue" :label="label" prepend-inner-icon="mdi-calendar-month-outline" append-inner-icon="mdi-chevron-down" :hide-details="hideDetails" readonly required />
    </template>
    <v-card class="month-picker pa-3" rounded="xl" elevation="12">
      <div class="d-flex align-center justify-space-between mb-3"><v-btn icon="mdi-chevron-left" variant="text" @click="year--" /><div class="text-h6 font-weight-bold">{{ year }}</div><v-btn icon="mdi-chevron-right" variant="text" @click="year++" /></div>
      <div class="month-picker__grid"><v-btn v-for="month in months" :key="month" :color="modelValue === `${year}-${String(month).padStart(2, '0')}` ? 'primary' : undefined" :variant="modelValue === `${year}-${String(month).padStart(2, '0')}` ? 'flat' : 'text'" :disabled="!!min && `${year}-${String(month).padStart(2, '0')}` < min" height="46" @click="selectMonth(month)">Tháng {{ month }}</v-btn></div>
    </v-card>
  </v-menu>
</template>

<style scoped>
.month-picker__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
</style>
