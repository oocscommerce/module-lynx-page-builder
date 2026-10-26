<script setup lang="ts">
import { Input } from '@/ui/input'
import type { FieldDef } from './types'

defineProps<{ value?: string | number; def?: FieldDef }>()
defineEmits<{ update: [value: string] }>()
</script>

<template>
  <Input
    :model-value="value ?? ''"
    type="text"
    :placeholder="def?.placeholder ?? ''"
    @update:model-value="$emit('update', String($event))"
  />
</template>
