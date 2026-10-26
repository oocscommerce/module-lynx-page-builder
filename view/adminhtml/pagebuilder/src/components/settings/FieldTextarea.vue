<script setup lang="ts">
import { Textarea } from '@/ui/textarea'
import type { FieldDef } from './types'

defineProps<{ value?: string | number; def?: FieldDef }>()
defineEmits<{ update: [value: string] }>()
</script>

<template>
  <Textarea
    :model-value="value ?? ''"
    :rows="def?.rows ?? 3"
    :placeholder="def?.placeholder ?? ''"
    class="resize-y"
    :class="def?.mono ? 'font-mono whitespace-pre' : ''"
    @update:model-value="$emit('update', String($event))"
  />
</template>
