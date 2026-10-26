<script setup lang="ts">
import { ref, computed, inject, type Component } from 'vue'
import { ChevronDown, GripVertical, Plus, Trash2 } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Label } from '@/ui/label'
import type { FieldDef } from './types'

type Item = Record<string, unknown>

// A missing list comes down as '' from the panel, which fills the gaps that way
const props = withDefaults(defineProps<{ value?: Item[] | string | null; def?: FieldDef }>(), { value: () => [], def: () => ({}) })
const emit = defineEmits<{ update: [value: Item[]] }>()

const fieldComponents = inject<Record<string, Component>>('fieldComponents', {})

const items = computed<Item[]>(() => Array.isArray(props.value) ? props.value : [])

const expanded = ref(new Set<number>())
const draggingIdx = ref<number | null>(null)
const overIdx = ref<number | null>(null)
const confirmIdx = ref<number | null>(null)

function getDefault() {
  const obj: Item = {}
  for (const [k, d] of Object.entries(props.def.fields ?? {})) {
    if (d.type === 'toggle') obj[k] = false
    else if (d.type === 'array') obj[k] = []
    else if (d.type === 'number') obj[k] = d.min ?? 0
    else obj[k] = d.default ?? ''
  }
  return obj
}

function addItem() {
  const next = [...items.value, getDefault()]
  expanded.value.add(next.length - 1)
  emit('update', next)
}

function removeItem(index: number) {
  const next = items.value.filter((_, i) => i !== index)
  const newExp = new Set<number>()
  for (const i of expanded.value) {
    if (i < index) newExp.add(i)
    else if (i > index) newExp.add(i - 1)
  }
  expanded.value = newExp
  confirmIdx.value = null
  emit('update', next)
}

function updateItem(index: number, key: string, value: unknown) {
  emit('update', items.value.map((item, i) => i === index ? { ...item, [key]: value } : item))
}

function toggleExpand(index: number) {
  expanded.value.has(index) ? expanded.value.delete(index) : expanded.value.add(index)
}

function dragStart(e: DragEvent, i: number) {
  draggingIdx.value = i
  e.dataTransfer!.effectAllowed = 'move'
  e.dataTransfer!.setData('text/plain', '')
}

function dragOver(e: DragEvent, i: number) {
  e.preventDefault()
  overIdx.value = i
}

function dragLeave(e: DragEvent, i: number) {
  if (!(e.currentTarget as Node).contains(e.relatedTarget as Node | null) && overIdx.value === i) {
    overIdx.value = null
  }
}

function drop(e: DragEvent, i: number) {
  e.preventDefault()
  const from = draggingIdx.value
  if (from === null || from === i) { reset(); return }

  const next = [...items.value]
  const [moved] = next.splice(from, 1)
  next.splice(i, 0, moved)

  const newExp = new Set<number>()
  for (const idx of expanded.value) {
    if (idx === from) newExp.add(i)
    else if (from < i && idx > from && idx <= i) newExp.add(idx - 1)
    else if (from > i && idx >= i && idx < from) newExp.add(idx + 1)
    else newExp.add(idx)
  }
  expanded.value = newExp

  reset()
  emit('update', next)
}

function reset() {
  draggingIdx.value = null
  overIdx.value = null
}

function getItemLabel(index: number) {
  const lf = props.def.labelField
  const value = lf ? items.value[index]?.[lf] : undefined
  // A localized field (text_i18n) holds { default, <locale>… }; the label is its default.
  const text = typeof value === 'object' && value !== null ? (value as Record<string, unknown>).default : value
  if (text) return String(text)
  return `Item ${index + 1}`
}
</script>

<template>
  <div class="space-y-1.5">

    <div
      v-for="(item, index) in items"
      :key="index"
      class="border rounded-md overflow-hidden bg-background shadow-xs"
      :class="[
        draggingIdx === index ? 'opacity-40' : '',
        overIdx === index && draggingIdx !== index ? 'border-primary' : '',
      ]"
      @dragover="dragOver($event, index)"
      @dragleave="dragLeave($event, index)"
      @drop="drop($event, index)"
    >
      <!-- Row header -->
      <div class="flex items-center gap-1 px-2 min-h-10">

        <!-- Confirm delete state -->
        <template v-if="confirmIdx === index">
          <span class="flex-1 text-sm text-muted-foreground select-none truncate">Delete this item?</span>
          <Button variant="destructive" size="xs" @click="removeItem(index)">Yes</Button>
          <Button variant="ghost" size="xs" @click="confirmIdx = null">No</Button>
        </template>

        <!-- Normal state -->
        <template v-else>
          <span
            class="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground shrink-0 transition-colors"
            draggable="true"
            @dragstart="dragStart($event, index)"
            @dragend="reset"
          >
            <GripVertical class="size-4" />
          </span>

          <span
            class="flex-1 text-sm font-medium truncate select-none cursor-pointer"
            @click="toggleExpand(index)"
          >{{ getItemLabel(index) }}</span>

          <Button variant="ghost" size="icon-sm" title="Expand" @click="toggleExpand(index)">
            <ChevronDown class="transition-transform duration-150" :class="expanded.has(index) ? 'rotate-180' : ''" />
          </Button>

          <Button variant="ghost" size="icon-sm" class="text-muted-foreground hover:text-destructive" title="Delete" @click="confirmIdx = index">
            <Trash2 />
          </Button>
        </template>
      </div>

      <!-- Expanded sub-fields -->
      <div v-if="expanded.has(index)" class="px-3 pt-3 pb-3 space-y-3 border-t bg-muted/50">
        <div v-for="(fieldDef, fieldKey) in (def.fields ?? {})" :key="fieldKey" class="space-y-1.5">
          <Label class="text-muted-foreground">{{ fieldDef.label }}</Label>
          <component
            :is="fieldComponents[fieldDef.type ?? ''] ?? fieldComponents.text"
            :value="item[fieldKey] ?? (fieldDef.type === 'toggle' ? false : '')"
            :def="fieldDef"
            @update="updateItem(index, String(fieldKey), $event)"
          />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="items.length === 0"
      class="flex items-center justify-center py-4 text-sm text-muted-foreground border border-dashed rounded-md"
    >
      No items yet
    </div>

    <Button variant="ghost" size="sm" class="w-full justify-start text-muted-foreground" @click="addItem">
      <Plus />
      Add item
    </Button>

  </div>
</template>
