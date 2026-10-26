<script lang="ts">
// The last query typed, kept alive between openings
let lastSearch = ''
</script>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, computed } from 'vue'
import { Search, X } from '@lucide/vue'
import { Button } from '@/ui/button'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/ui/hover-card'
import { Input } from '@/ui/input'
import type { ComponentDef } from './types'

const props = withDefaults(defineProps<{
  // Where the picker's left edge sits: right after the panel (rail + card).
  left?: number
  components?: ComponentDef[]
  targetZone?: string | null
  // Where the component will land, named for the title: a slot's label when the + pressed was a slot's own
  targetLabel?: string
  // A slot's allowed_groups when the + pressed was that slot's own; null takes every group
  allowedGroups?: string[] | null
  // Registered names the target slot already holds as often as it allows (max_per_component): shown, but locked
  fullComponents?: string[]
}>(), { left: 364, components: () => [], targetZone: null, targetLabel: '', allowedGroups: null, fullComponents: () => [] })
const emit = defineEmits<{ select: [component: ComponentDef]; close: [] }>()

const search = ref(lastSearch)

// On the window rather than @keydown on the panel, which is how AIGenerateModal does it
function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  // Closing is the only way out of the picker, so this is where the query is kept.
  lastSearch = search.value
})

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  let list = props.components.filter(c => {
    const zones = c.zones ? Object.values(c.zones as Record<string, string>) : []
    // Backstage lists only components that name it (see PageSection)
    if (props.targetZone === 'backstage') return zones.includes('backstage')
    return !zones.length || !props.targetZone || zones.includes(props.targetZone)
  })
  if (props.allowedGroups) list = list.filter(c => props.allowedGroups!.includes(String(c.group)))
  if (q) list = list.filter(c => String(c.label).toLowerCase().includes(q) || String(c.module).toLowerCase().includes(q))
  return list.sort((a, b) => String(a.label).localeCompare(String(b.label)))
})

// checkout_step → "Checkout Step"
const groupLabel = (key: string) => key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')

// One heading per group, alphabetical with General last; the tiles keep their order within
const sections = computed(() => {
  const byGroup = new Map<string, ComponentDef[]>()
  for (const c of filtered.value) byGroup.set(String(c.group), [...(byGroup.get(String(c.group)) ?? []), c])
  return [...byGroup.entries()]
    .sort(([a], [b]) => Number(a === 'general') - Number(b === 'general') || groupLabel(a).localeCompare(groupLabel(b)))
    .map(([key, items]) => ({ key, label: groupLabel(key), items }))
})

const componentId = (c: ComponentDef) => String(c.key).replace('/', '::')

const isFull = (c: ComponentDef) => props.fullComponents.includes(String(c.name))

function pick(component: ComponentDef) {
  if (isFull(component)) return
  emit('select', component)
  emit('close')
}
</script>

<template>
  <!-- Backdrop -->
  <div class="fixed inset-0 z-30" @click="emit('close')"/>

  <!-- Picker panel -->
  <div class="fixed top-12 w-[340px] bottom-0 flex flex-col bg-background text-sm leading-snug z-30 shadow-lg select-none border-x" :style="{ left: left + 'px' }">

    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 border-b shrink-0">
      <!-- Where the component lands is what the + button carried and nothing on screen says, so the title says it -->
      <span class="text-sm font-semibold">
        Add component<span v-if="targetLabel" class="font-normal text-muted-foreground"> · {{ targetLabel }}</span>
      </span>
      <Button variant="ghost" size="icon-sm" title="Close" @click="emit('close')"><X /></Button>
    </div>

    <!-- Search -->
    <div class="px-3 pt-3 pb-2 shrink-0">
      <div class="relative">
        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
        <Input v-model="search" class="pl-8" placeholder="Search components…" autofocus />
      </div>
    </div>

    <!-- Component tiles by group: two a row, the icon large and the name under it -->
    <div class="flex-1 overflow-y-auto px-3 pb-3 pb-scroll">
      <div v-if="filtered.length === 0" class="text-center py-8 text-muted-foreground">
        No components found
      </div>
      <section v-for="section in sections" :key="section.key" class="pt-2 first:pt-0">
        <h3 class="pt-2 pb-2 px-0.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ section.label }}</h3>
        <div class="grid grid-cols-2 gap-2">
          <!-- Hovering a tile opens a card beside the panel: name, registered id, description -->
          <HoverCard v-for="c in section.items" :key="c.name" :open-delay="150" :close-delay="0">
          <HoverCardTrigger as-child>
          <button
            type="button"
            class="group flex flex-col items-center justify-center gap-2 px-2 py-4 min-h-22 bg-background border rounded-md shadow-xs transition-colors text-center outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
            :class="isFull(c) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-accent hover:border-primary cursor-pointer'"
            :disabled="isFull(c)"
            @click="pick(c)"
          >
            <i v-if="c.icon" class="ph text-[28px] leading-none text-muted-foreground group-hover:text-foreground transition-colors" :class="c.icon" aria-hidden="true"></i>
            <svg v-else class="size-7 fill-none stroke-current text-muted-foreground group-hover:text-foreground transition-colors" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/>
            </svg>
            <span class="text-sm font-medium leading-tight break-words">{{ c.label }}</span>
            <span v-if="isFull(c)" class="text-xs text-muted-foreground leading-tight">Already added</span>
          </button>
          </HoverCardTrigger>
          <HoverCardContent side="right" :side-offset="12" class="w-[300px]">
            <div class="text-sm font-semibold leading-snug">{{ c.label }}</div>
            <div class="mt-1 font-mono text-xs leading-snug text-muted-foreground break-all">{{ componentId(c) }}</div>
            <p v-if="c.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ c.description }}</p>
          </HoverCardContent>
          </HoverCard>
        </div>
      </section>
    </div>
  </div>
</template>
