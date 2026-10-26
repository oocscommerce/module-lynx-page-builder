<script setup lang="ts">
import { CircleHelp } from '@lucide/vue'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/ui/tooltip'

defineProps<{ text?: string }>()
</script>

<template>
  <Tooltip v-if="text">
    <TooltipTrigger as-child>
      <span class="inline-flex items-center shrink-0 text-muted-foreground cursor-help" tabindex="0">
        <CircleHelp class="size-3.5" />
      </span>
    </TooltipTrigger>
    <TooltipContent side="right" class="max-w-56">{{ text }}</TooltipContent>
  </Tooltip>
</template>
