<script setup lang="ts">
import { ref, watch } from 'vue'
import 'vanilla-colorful/rgba-string-color-picker.js'
import { Input } from '@/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/ui/popover'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import type { FieldDef } from './types'

/**
 * A color setting: a swatch beside a text field that takes the value typed
 */
const props = defineProps<{ value?: string; def?: FieldDef }>()
const emit = defineEmits<{ update: [value: string] }>()

// The design's colors (Theme tab) so a component picks from the palette the page renders
const store = usePageBuilderStore()
const SWATCHES = [
  'rgb(41 84 255)', 'rgb(31 57 255)', 'rgb(61 132 255)', 'rgb(194 200 255)',
  'rgb(23 32 38)', 'rgb(84 93 99)', 'rgb(149 154 157)', 'rgb(223 225 226)', 'rgb(244 245 245)', 'rgb(255 255 255)',
  'rgb(215 55 63)', 'rgb(38 142 108)',
]
const swatches = () => (store.themeColors.length ? store.themeColors : SWATCHES)

const open = ref(false)
const pickerHost = ref<HTMLElement | null>(null)
let picker: (HTMLElement & { color: string }) | null = null

// Any CSS color the field may hold (a name, #rgb, rgb() with spaces) as the rgba() string the picker reads
const ctx = document.createElement('canvas').getContext('2d')!
function toRgba(value?: string) {
  ctx.fillStyle = '#000000'
  ctx.fillStyle = value || '#000000'
  const normal = String(ctx.fillStyle)
  if (normal.startsWith('#')) {
    const n = parseInt(normal.slice(1), 16)
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, 1)`
  }
  return normal
}

// What the picker gives back, as the value is stored: #rrggbb when solid.
function toValue(rgba: string) {
  const [r = 0, g = 0, b = 0, a = 1] = (rgba.match(/[\d.]+/g) ?? []).map(Number)
  if (a >= 1) return '#' + [r, g, b].map(c => Math.round(c).toString(16).padStart(2, '0')).join('')
  return `rgba(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)}, ${Math.round(a * 100) / 100})`
}

function onPicked(event: Event) {
  emit('update', toValue((event as CustomEvent<{ value: string }>).detail.value))
}

// A swatch is a choice made, so the popover closes; dragging in the picker keeps it open
function pick(color: string) {
  emit('update', toValue(toRgba(color)))
  open.value = false
}

// The popover mounts its content on open: the picker is made by hand each time, so Vue needs no custom-element setting for it
watch(pickerHost, (host) => {
  picker?.removeEventListener('color-changed', onPicked)
  picker = null
  if (!host) return
  picker = document.createElement('rgba-string-color-picker') as HTMLElement & { color: string }
  picker.className = 'lynx-color-picker' // sized and rounded in styles.css
  picker.color = toRgba(props.value)
  picker.addEventListener('color-changed', onPicked)
  host.appendChild(picker)
})

// A value typed in the field or a swatch moves the picker along
watch(() => props.value, v => { if (picker) picker.color = toRgba(v) })
</script>

<template>
  <div class="flex items-center gap-2">
    <Popover v-model:open="open">
      <PopoverTrigger as-child>
        <button
          type="button"
          class="size-9 shrink-0 rounded-md cursor-pointer border shadow-xs p-0.5 bg-[linear-gradient(45deg,#ccc_25%,transparent_25%),linear-gradient(-45deg,#ccc_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#ccc_75%),linear-gradient(-45deg,transparent_75%,#ccc_75%)] bg-[length:8px_8px] bg-clip-content outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
          :title="value || 'No color'"
        >
          <span class="block w-full h-full rounded-[4px]" :style="value ? { backgroundColor: value } : undefined"></span>
        </button>
      </PopoverTrigger>
      <PopoverContent align="start" class="w-64 p-3">
        <div ref="pickerHost"></div>
        <div class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(20px,1fr))] gap-1.5">
          <button
            v-for="color in swatches()"
            :key="color"
            type="button"
            class="aspect-square rounded-sm border cursor-pointer p-0 hover:scale-110 transition-transform"
            :style="{ backgroundColor: color }"
            :title="color"
            @click="pick(color)"
          ></button>
        </div>
      </PopoverContent>
    </Popover>
    <Input
      :model-value="value ?? ''"
      type="text"
      placeholder="#000000 or rgba()"
      class="flex-1 font-mono"
      @update:model-value="$emit('update', String($event).trim())"
    />
  </div>
</template>
