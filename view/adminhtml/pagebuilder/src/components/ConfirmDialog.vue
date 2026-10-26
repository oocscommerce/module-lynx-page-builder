<script setup lang="ts">
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle,
} from '@/ui/alert-dialog'

/** A question asked before something that cannot be taken back: import, exit, edit another page */
defineProps<{ title: string; description: string; confirmLabel: string; cancelLabel?: string }>()
defineEmits<{ confirm: [] }>()
const open = defineModel<boolean>('open', { default: false })
</script>

<template>
  <AlertDialog v-model:open="open">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ description }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>{{ cancelLabel ?? 'Cancel' }}</AlertDialogCancel>
        <AlertDialogAction @click="$emit('confirm')">{{ confirmLabel }}</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
