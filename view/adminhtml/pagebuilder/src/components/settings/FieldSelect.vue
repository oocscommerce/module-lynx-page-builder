<script setup lang="ts">
import { computed } from 'vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import type { FieldDef } from './types'

const props = defineProps<{ value?: string | number; def?: FieldDef }>()
const emit = defineEmits<{ update: [value: string] }>()

// The select refuses an empty value, which an option ("None") may store: it travels under this name instead
const EMPTY = '__empty__'
const options = computed(() => Object.values(props.def?.options ?? {}))
const selected = computed(() => {
  const value = props.value === '' || props.value == null ? EMPTY : String(props.value)
  const values = options.value.map(opt => (opt.value === '' ? EMPTY : opt.value))
  // A value no option has shows the first one, as a native select did; nothing is written until a pick
  return values.includes(value) ? value : values[0]
})

function pick(value: unknown) {
  emit('update', value === EMPTY ? '' : String(value))
}
</script>

<template>
  <Select :model-value="selected" @update:model-value="pick">
    <SelectTrigger class="w-full">
      <SelectValue />
    </SelectTrigger>
    <SelectContent>
      <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value === '' ? EMPTY : opt.value">
        {{ opt.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
