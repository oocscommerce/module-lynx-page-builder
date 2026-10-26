<script setup lang="ts">
import { ref, computed } from 'vue'
import Draggable from 'vuedraggable'
import { ChevronDown, Code, PanelBottom, PanelTop, Plus, Square } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/ui/collapsible'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import TreeNode from './TreeNode.vue'

const props = defineProps<{ zoneKey: string; label: string }>()

const store = usePageBuilderStore()
const { openPicker, componentOf } = store
const expanded = ref(true)

const group = computed(() => ({
  name: 'builder',
  put(to: unknown, from: unknown, dragEl: HTMLElement) {
    const { componentKey: key, componentName: name } = dragEl.dataset
    if (!key) return true
    const comp = componentOf({ name, key })
    const zones = comp?.zones ? Object.values(comp.zones as Record<string, string>) : []
    // Backstage takes only components that name it: a component with no zones goes anywhere else
    if (props.zoneKey === 'backstage') return zones.includes('backstage')
    return !zones.length || zones.includes(props.zoneKey)
  },
}))

// Backstage holds what renders nothing: scripts, tracking
const icons: Record<string, unknown> = { header: PanelTop, content: Square, footer: PanelBottom, backstage: Code }
</script>

<template>
  <Collapsible v-model:open="expanded" class="mb-2">
    <!-- The zone header reads like the Settings card's: a firm title on a rule. -->
    <CollapsibleTrigger
      class="w-full flex items-center gap-2 px-2 h-10 hover:bg-accent transition-colors cursor-pointer border-b text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
    >
      <component :is="icons[zoneKey]" class="size-4 shrink-0" />
      <span class="flex-1 text-sm font-semibold">{{ label }}</span>
      <ChevronDown class="size-4 text-muted-foreground transition-transform duration-150 shrink-0" :class="{ 'rotate-180': expanded }" />
    </CollapsibleTrigger>

    <CollapsibleContent class="pl-2 pr-0.5 pt-1.5 pb-1.5">
      <Draggable
        :list="store.layout[zoneKey]"
        item-key="id"
        :group="group"
        handle=".pb-drag"
        :animation="150"
        ghost-class="pb-ghost"
        class="min-h-4"
      >
        <template #item="{ element }">
          <TreeNode :node="element" />
        </template>
      </Draggable>

      <Button variant="ghost" size="sm" class="w-full justify-start mt-0.5 px-2 text-muted-foreground font-normal" @click="openPicker(zoneKey)">
        <Plus />
        Add component
      </Button>
    </CollapsibleContent>
  </Collapsible>
</template>
