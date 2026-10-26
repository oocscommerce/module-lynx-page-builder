<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Draggable from 'vuedraggable'
import { ChevronDown, Eye, EyeOff, GripVertical, Plus, Trash2 } from '@lucide/vue'
import { Badge } from '@/ui/badge'
import { Button } from '@/ui/button'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import type { LayoutNode } from './types'

const props = defineProps<{ node: LayoutNode }>()

const confirmDelete = ref(false)
const store = usePageBuilderStore()
const { removeNode, selectNode, openSettings, openPicker, componentOf, isRegistered } = store
// Its module was disabled or removed: the storefront skips it, so the admin should remove it or bring the module back
const unavailable = computed(() => !!props.node.name && !isRegistered(props.node))

const isSelected = computed(() => store.selectedNode?.id === props.node.id)
const componentSlots = computed(() => componentOf(props.node)?.slots ?? {})
const hasSlots = computed(() => Object.keys(componentSlots.value).length > 0)

// Closed by default, so a page of many components reads as a list rather than a wall
const expanded = ref(false)

function holds(node: LayoutNode, id: string): boolean {
  for (const children of Object.values(node.slots ?? {})) {
    for (const child of children ?? []) {
      if (child.id === id || holds(child, id)) return true
    }
  }
  return false
}
const holdsSelected = computed(() => !!store.selectedNode && holds(props.node, store.selectedNode.id))
watch(holdsSelected, inside => { if (inside) expanded.value = true }, { immediate: true })

// A slot takes a dragged component only from its allowed_groups and below its max_per_component, as PageSection does for zones
function slotGroup(slotDef: Record<string, any>, slotName: string) {
  const allowed = slotDef.allowed_groups ? Object.values(slotDef.allowed_groups) : null
  const max = Number(slotDef.max_per_component) || 0
  return {
    name: 'builder',
    put(to: unknown, from: unknown, dragEl: HTMLElement) {
      const { componentKey: key, componentName: name } = dragEl.dataset
      if (!key) return true
      if (allowed && !allowed.includes(componentOf({ name, key })?.group as string)) return false
      // Reordering within the slot never changes the count; counted by registered name, as the picker does
      return !max || to === from || slotNodes(slotName).filter(child => child.name === name).length < max
    },
  }
}

function slotNodes(slotName: string) {
  if (!props.node.slots) props.node.slots = {}
  if (!props.node.slots[slotName]) props.node.slots[slotName] = []
  return props.node.slots[slotName]
}

function askDelete() { confirmDelete.value = true }
function cancelDelete() { confirmDelete.value = false }
function doDelete() { confirmDelete.value = false; removeNode(props.node.id) }
</script>

<template>
  <div
    :data-tree-id="node.id"
    :data-component-key="node.key"
    :data-component-name="node.name"
    class="my-px"
  >
    <!-- The selection paints this row alone, not the wrapper -->
    <div
      class="flex items-center gap-0.5 px-1.5 h-9 cursor-pointer rounded-md transition-colors"
      :class="[isSelected ? 'bg-primary text-primary-foreground' : 'hover:bg-accent', { 'opacity-40': !node.visible }]"
      @click.stop="selectNode(node)"
      @dblclick.stop="openSettings(node)"
    >
      <span
        class="pb-drag cursor-grab active:cursor-grabbing px-0.5 select-none shrink-0 transition-colors"
        :class="isSelected ? 'text-primary-foreground/60 hover:text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click.stop
      ><GripVertical class="size-4" /></span>

      <Button
        variant="ghost"
        size="icon-xs"
        :class="isSelected ? 'text-primary-foreground/60 hover:text-primary-foreground hover:bg-primary-foreground/10' : 'text-muted-foreground'"
        :title="node.visible ? 'Hide' : 'Show'"
        @click.stop="node.visible = !node.visible"
      >
        <Eye v-if="node.visible" />
        <EyeOff v-else />
      </Button>

      <span class="flex-1 text-sm font-medium truncate min-w-0" :title="node.key">
        {{ node.custom_name || node.label }}
      </span>
      <Badge v-if="unavailable" variant="destructive" class="shrink-0" :title="node.key">Component not available</Badge>

      <template v-if="confirmDelete">
        <Button variant="destructive" size="xs" @click.stop="doDelete">Yes</Button>
        <Button variant="secondary" size="xs" @click.stop="cancelDelete">No</Button>
      </template>
      <template v-else>
        <Button
          v-if="hasSlots"
          variant="ghost"
          size="icon-xs"
          :class="isSelected ? 'text-primary-foreground/80 hover:text-primary-foreground hover:bg-primary-foreground/10' : ''"
          :title="expanded ? 'Collapse' : 'Expand'"
          @click.stop="expanded = !expanded"
        >
          <ChevronDown class="size-4 transition-transform duration-150" :class="{ 'rotate-180': expanded }" />
        </Button>

        <Button
          variant="ghost"
          size="icon-xs"
          :class="isSelected ? 'text-primary-foreground/60 hover:text-destructive hover:bg-primary-foreground/10' : 'text-muted-foreground hover:text-destructive'"
          title="Remove"
          @click.stop="askDelete"
        >
          <Trash2 />
        </Button>
      </template>
    </div>

    <!-- The children of the selected node sit on a tint of their own, under a dark edge -->
    <div
      v-if="hasSlots && expanded"
      class="ml-4 pl-2 pb-1 border-l transition-colors"
      :class="isSelected ? 'border-primary bg-accent rounded-b-md' : ''"
    >
      <div
        v-for="(slotDef, slotName) in componentSlots"
        :key="slotName"
        class="pt-2"
      >
        <div class="text-xs font-medium text-muted-foreground mb-0.5 px-1.5">
          {{ slotDef.label ?? slotName }}
        </div>
        <Draggable
          :list="slotNodes(String(slotName))"
          item-key="id"
          :group="slotGroup(slotDef, String(slotName))"
          handle=".pb-drag"
          :animation="150"
          ghost-class="pb-ghost"
          class="min-h-4"
        >
          <template #item="{ element }">
            <TreeNode :node="element" />
          </template>
        </Draggable>

        <Button
          variant="ghost"
          size="sm"
          class="w-full justify-start mt-0.5 px-1.5 text-muted-foreground font-normal"
          @click.stop="openPicker(node.id, String(slotName))"
        >
          <Plus />
          Add to {{ slotDef.label ?? slotName }}
        </Button>
      </div>
    </div>
  </div>
</template>
