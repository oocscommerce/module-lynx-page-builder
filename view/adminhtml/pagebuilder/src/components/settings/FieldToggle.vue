<script setup lang="ts">
import { Switch } from '@/ui/switch'
import type { FieldDef } from './types'

// Layouts saved earlier may hold 1 or "1" for on
defineProps<{ value?: boolean | number | string; def?: FieldDef }>()
defineEmits<{ update: [value: boolean] }>()
</script>

<template>
  <Switch :model-value="!!value" @update:model-value="$emit('update', $event)" />
</template>
