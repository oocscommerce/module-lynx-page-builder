<script setup lang="ts">
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

/** Copy page from design: the design to copy from picked inside the dialog; PageBuilderPanel does the copy */
defineProps<{ designOptions: Record<string, any>[] }>()
defineEmits<{ copy: [] }>()
const open = defineModel<boolean>('open', { default: false })
const source = defineModel<Record<string, any> | null>('source', { default: null })
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Copy page from design</DialogTitle>
        <DialogDescription>Replaces the content of this page with what the chosen design has for it. Header and footer stay. Save to keep it.</DialogDescription>
      </DialogHeader>
      <Select
        :model-value="source?.url"
        @update:model-value="source = designOptions.find(opt => opt.url === $event) ?? null"
      >
        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem v-for="opt in designOptions" :key="opt.url" :value="opt.url">{{ opt.label }}</SelectItem>
        </SelectContent>
      </Select>
      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button @click="$emit('copy')">Copy</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
