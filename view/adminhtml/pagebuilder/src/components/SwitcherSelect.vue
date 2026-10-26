<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Check, ChevronDown, ChevronLeft, ChevronRight, Search } from '@lucide/vue'
import { ListboxFilter } from 'reka-ui'
import { Command, CommandGroup, CommandItem, CommandList } from '@/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/ui/popover'

type Option = { value?: string; label: string; disabled?: boolean; depth?: number; children?: Option[]; [key: string]: any }

/**
 * The dropdowns in the middle of the top bar: which scope is being edited, which page is on screen
 */
const props = withDefaults(defineProps<{
  options?: Option[]
  modelValue?: string
  title?: string
  // What the button chooses, shown in front of the choice: a newcomer reads "Page  Home", not a bare "Home"
  label?: string
  placeholder?: string
}>(), { options: () => [], modelValue: '', title: '', label: '', placeholder: 'Search…' })
const emit = defineEmits<{ select: [option: Option] }>()

const open = ref(false)
const query = ref('')
/** Levels drilled into, outermost first. Empty means the top level. */
const path = ref<Option[]>([])
const listRef = ref<InstanceType<typeof CommandList> | null>(null)

/** Every option that can be chosen, at any depth. */
function flatten(options: Option[]): Option[] {
  return options.flatMap(o => (o.children ? flatten(o.children) : [o]))
}

const currentLabel = computed(
  () => flatten(props.options).find(o => !o.disabled && o.value === props.modelValue)?.label ?? '—'
)

/** The level being shown: the children of the last row drilled into. */
const level = computed(
  () => path.value.length ? (path.value[path.value.length - 1]!.children ?? []) : props.options
)

// Searching looks through every level at once, which is why the labels inside a group keep repeating the group's own
const searching = computed(() => query.value.trim() !== '')
const visible = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? flatten(props.options).filter(o => !o.disabled && o.label.toLowerCase().includes(q)) : level.value
})

/** Inside a group the "Account · " in front of each label repeats the group, so it goes; search results keep it. */
function rowLabel(option: Option) {
  return path.value.length && !searching.value ? option.label.replace(/^[^·]+·\s*/, '') : option.label
}

// Opens where the visitor already is: a page inside a group opens that group, its row scrolled to the middle
function onOpen(isOpen: boolean) {
  open.value = isOpen
  if (!isOpen) return
  query.value = ''
  path.value = props.options.filter(o => o.children?.some(c => c.value === props.modelValue))
  nextTick(() => {
    const list = listRef.value?.$el as HTMLElement | undefined
    list?.querySelector('[data-current]')?.scrollIntoView({ block: 'center' })
  })
}

function pick(option: Option) {
  if (option.disabled) return
  if (option.children) {
    path.value = [...path.value, option]
    return
  }
  open.value = false
  emit('select', option)
}

function back() {
  path.value = path.value.slice(0, -1)
}

// Left goes back a level, as does Backspace once there is nothing to delete in the search field
function onKeydown(event: KeyboardEvent) {
  if (!path.value.length || searching.value) return
  if (event.key === 'ArrowLeft' || event.key === 'Backspace') {
    event.preventDefault()
    back()
  }
}
</script>

<template>
  <Popover :open="open" @update:open="onOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        class="h-8 w-[240px] min-w-[110px] flex items-center gap-1.5 px-2.5 rounded-md bg-background text-foreground hover:bg-accent text-sm cursor-pointer transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
        :title="title"
      >
        <span v-if="label" class="shrink-0 text-muted-foreground">{{ label }}</span>
        <span class="flex-1 min-w-0 truncate text-left font-medium">{{ currentLabel }}</span>
        <ChevronDown class="size-4 text-muted-foreground shrink-0" />
      </button>
    </PopoverTrigger>
    <PopoverContent class="w-80 p-0" align="center">
      <Command>
        <!-- The search is ours, not the command's: it looks through every level, which the command cannot see -->
        <div class="flex h-9 items-center gap-2 border-b px-3">
          <Search class="size-4 shrink-0 opacity-50" />
          <ListboxFilter
            v-model="query"
            auto-focus
            :placeholder="placeholder"
            class="placeholder:text-muted-foreground flex h-10 w-full bg-transparent py-3 text-sm outline-hidden"
            @keydown="onKeydown"
          />
        </div>
        <CommandList ref="listRef" class="max-h-[min(60vh,420px)]">
          <div v-if="!visible.some(o => !o.disabled)" class="py-6 text-center text-sm">No match</div>
          <CommandGroup>

          <!-- Which level this is, and the way out of it -->
          <CommandItem v-if="path.length && !searching" value="__back__" class="font-semibold" @select="back">
            <ChevronLeft />
            <span class="truncate">{{ path[path.length - 1]!.label }}</span>
          </CommandItem>

          <template v-for="(o, i) in visible" :key="`${o.value}-${i}`">
            <div
              v-if="o.disabled"
              class="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground"
              :style="{ paddingLeft: `${8 + (o.depth ?? 0) * 12}px` }"
            >{{ o.label }}</div>

            <CommandItem
              v-else
              :value="`${o.value ?? o.label}-${i}`"
              :data-current="o.value === modelValue || undefined"
              :class="o.value === modelValue ? 'font-semibold' : ''"
              :style="{ paddingLeft: `${8 + (o.depth ?? 0) * 12}px` }"
              @select="(event: Event) => { event.preventDefault(); pick(o) }"
            >
              <!-- Wraps rather than truncates: long names differ only at the end, e.g. "(sample)" -->
              <span class="flex-1 min-w-0 break-words">{{ rowLabel(o) }}</span>
              <!-- A level to open, rather than a page to go to -->
              <ChevronRight v-if="o.children" class="text-muted-foreground" />
              <Check v-else-if="o.value === modelValue" />
            </CommandItem>
          </template>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
