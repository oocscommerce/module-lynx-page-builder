<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/ui/input'
import { Slider } from '@/ui/slider'
import type { FieldDef } from './types'

const props = defineProps<{ value?: number | string | null; def?: FieldDef }>()
const emit = defineEmits<{ update: [value: number] }>()

function onNumberBlur(e: FocusEvent) {
  const v = parseFloat((e.target as HTMLInputElement).value)
  if (isNaN(v)) { emit('update', min.value); return }
  const clamped = Math.min(max.value, Math.max(min.value, v))
  emit('update', clamped)
}

// di.xml numbers arrive as strings ("1", "30"); the slider does arithmetic with them
const min  = computed(() => Number(props.def?.min  ?? 0))
const max  = computed(() => Number(props.def?.max  ?? 500))
const step = computed(() => Number(props.def?.step ?? 1))

const effectiveValue = computed(() =>
  props.value !== '' && props.value !== null && props.value !== undefined
    ? parseFloat(String(props.value))
    : min.value
)
</script>

<template>
  <div class="flex items-center gap-3">
    <Slider
      :model-value="[effectiveValue]"
      :min="min"
      :max="max"
      :step="step"
      class="flex-1 min-w-0"
      @update:model-value="emit('update', ($event ?? [min])[0])"
    />
    <!-- No spin buttons: they ate the room a four-digit value needs -->
    <Input
      :model-value="effectiveValue"
      type="number"
      :min="min"
      :max="max"
      :step="step"
      class="w-20 shrink-0 px-2 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
      @update:model-value="emit('update', Number($event))"
      @blur="onNumberBlur"
    />
  </div>
</template>
