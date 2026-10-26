<script setup lang="ts">
import { computed, defineAsyncComponent, ref, type Component } from 'vue'
import { storeToRefs } from 'pinia'
import {
  ChevronLeft, Copy, Download, Ellipsis, ExternalLink, Monitor, PanelLeft, Redo2, Share2, Smartphone, SquareDashed,
  Tablet, Undo2, Upload,
} from '@lucide/vue'
import { Button } from '@/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { Separator } from '@/ui/separator'
import { ToggleGroup, ToggleGroupItem } from '@/ui/toggle-group'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'
import SwitcherSelect from './SwitcherSelect.vue'

const ConfirmDialog = defineAsyncComponent(() => import('./ConfirmDialog.vue'))

type Option = { value?: string; code?: string; label: string; url?: string; children?: Option[]; [key: string]: any }

withDefaults(defineProps<{
  panelVisible?: boolean
  // Components the loaded extensions place on the bar
  extensionItems?: Component[]
}>(), { panelVisible: true, extensionItems: () => [] })
const emit = defineEmits<{ import: []; 'copy-design': []; share: []; 'open-tab': []; 'toggle-panel': [] }>()

const store = usePageBuilderStore()
const { config, saving, saveStatus, canUndo, canRedo } = storeToRefs(store)
const { device, outlines } = storeToRefs(usePreviewStore())
// Whether this admin may make share links (ACL OOCS_LynxPageBuilder::share)
const canShare = computed(() => !!config.value.share.allowed && !!config.value.share.createUrl)

// Switching either one is a page load: which layout the editor holds
function switchTo(option: Option) {
  if (option?.url) window.location.assign(option.url)
}

// SwitcherSelect asks every option for a `value`; the page list is keyed by `code`
const withValue = (o: Option): Option => ({
  ...o,
  value: o.code,
  ...(o.children ? { children: o.children.map(withValue) } : {}),
})

const pages = computed(() => config.value.pageOptions.map(withValue))

/** Exit always asks: the link sits where a stray click lands, and unsaved work is gone with it. */
const confirmExit = ref(false)
const exitMounted = ref(false)
function onExit(e: MouseEvent) {
  e.preventDefault()
  if (!config.value.exitUrl) return
  exitMounted.value = true
  confirmExit.value = true
}
function leave() {
  window.location.assign(config.value.exitUrl)
}

/** The ⋯ menu holds what acts on the whole layout or on the editor itself. */
const moreItems = computed(() => [
  { label: 'Export layout', run: store.exportLayout, icon: Download },
  { label: 'Import layout', run: () => emit('import'), icon: Upload },
  // The content of this page, taken from another design.
  ...(config.value.designOptions.length ? [{ label: 'Copy page from design', run: () => emit('copy-design'), icon: Copy }] : []),
])

const devices = [
  { key: 'desktop', title: 'Desktop', icon: Monitor },
  { key: 'tablet', title: 'Tablet', icon: Tablet },
  { key: 'mobile', title: 'Mobile', icon: Smartphone },
]

// Icon buttons on the dark bar: muted until hovered, lit while on
const BAR_BUTTON = 'text-zinc-400 hover:bg-zinc-800 hover:text-white'
const BAR_BUTTON_ON = 'bg-white text-zinc-950 hover:bg-zinc-200 hover:text-zinc-950'
</script>

<template>
  <!-- Three columns rather than one flex row: the switchers stay centred whatever the sides hold -->
  <div class="fixed top-0 left-0 right-0 h-12 bg-zinc-950 text-zinc-100 grid grid-cols-[1fr_auto_1fr] items-center px-4 gap-3 z-40 select-none overflow-hidden">

    <div class="flex items-center gap-3 min-w-0">
      <a
        :href="config.exitUrl || '#'"
        class="flex items-center gap-1 text-sm font-medium text-zinc-400 hover:text-white transition-colors shrink-0"
        :title="config.exitUrl ? 'Exit' : ''"
        @click="onExit"
      >
        <ChevronLeft class="size-4" />
        Exit
      </a>

      <Separator orientation="vertical" class="bg-zinc-800 data-[orientation=vertical]:h-5" />

      <!-- One icon for both states: the button is lit while the panel is open -->
      <Button
        variant="ghost"
        size="icon-sm"
        :class="panelVisible ? BAR_BUTTON_ON : BAR_BUTTON"
        :title="panelVisible ? 'Hide panel' : 'Show panel'"
        @click="emit('toggle-panel')"
      ><PanelLeft /></Button>

      <Separator orientation="vertical" class="bg-zinc-800 data-[orientation=vertical]:h-5" />

      <div class="flex items-center gap-0.5 bg-zinc-800 rounded-md p-0.5 shrink-0">
        <Button variant="ghost" size="icon-xs" class="w-8 text-white hover:bg-zinc-700 hover:text-white disabled:text-zinc-600" :disabled="!canUndo" title="Undo (Ctrl+Z)" @click="store.undo"><Undo2 /></Button>
        <Button variant="ghost" size="icon-xs" class="w-8 text-white hover:bg-zinc-700 hover:text-white disabled:text-zinc-600" :disabled="!canRedo" title="Redo (Ctrl+Shift+Z)" @click="store.redo"><Redo2 /></Button>
      </div>

      <Separator orientation="vertical" class="bg-zinc-800 data-[orientation=vertical]:h-5" />

      <span class="hidden xl:block text-sm font-semibold text-white truncate min-w-0">{{ config.pageTitle }}</span>
    </div>

    <!-- Centre: which scope and which page is being built -->
    <div class="flex items-center justify-center gap-2 min-w-0">
      <SwitcherSelect
        v-if="config.scopeOptions.length"
        :options="config.scopeOptions"
        :model-value="config.currentScope"
        label="Store view"
        title="Store view being edited and previewed"
        placeholder="Search store views…"
        @select="switchTo"
      />

      <SwitcherSelect
        v-if="pages.length"
        :options="pages"
        :model-value="config.currentPage"
        label="Page"
        title="Page being edited"
        placeholder="Search pages…"
        @select="switchTo"
      />
    </div>

    <!-- No min-w-0 here on purpose -->
    <div class="flex items-center justify-end gap-3">
      <ToggleGroup
        type="single"
        :model-value="device"
        class="bg-zinc-800 rounded-md p-0.5 gap-0.5 shrink-0"
        @update:model-value="$event && (device = String($event))"
      >
        <ToggleGroupItem
          v-for="d in devices"
          :key="d.key"
          :value="d.key"
          :title="d.title"
          size="sm"
          class="h-7 min-w-8 rounded-md! text-zinc-400 hover:bg-transparent hover:text-white data-[state=on]:bg-white data-[state=on]:text-zinc-950 data-[state=on]:shadow-sm"
        ><component :is="d.icon" /></ToggleGroupItem>
      </ToggleGroup>

      <!-- Show all outlines: lit while every component is framed in the preview -->
      <Button
        variant="ghost"
        size="icon-sm"
        :class="outlines ? BAR_BUTTON_ON : BAR_BUTTON"
        :title="outlines ? 'Hide outlines' : 'Show all outlines'"
        @click="outlines = !outlines"
      ><SquareDashed /></Button>

      <!-- The storefront itself in a tab of its own, in the design being previewed -->
      <Button variant="ghost" size="icon-sm" :class="BAR_BUTTON" title="Open preview in new tab (saved design)" @click="emit('open-tab')"><ExternalLink /></Button>

      <!-- Share preview gets its own button on the bar, not the ⋯ menu -->
      <Button v-if="canShare" variant="ghost" size="icon-sm" :class="BAR_BUTTON" title="Share preview" @click="emit('share')"><Share2 /></Button>

      <!-- Extension buttons — on the bar at every width -->
      <component
        v-for="(item, i) in extensionItems"
        :key="i"
        :is="item"
      />

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" size="icon-sm" :class="[BAR_BUTTON, 'data-[state=open]:bg-white data-[state=open]:text-zinc-950']" title="More tools"><Ellipsis /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
          <DropdownMenuItem v-for="item in moreItems" :key="item.label" @select="item.run()">
            <component :is="item.icon" />
            {{ item.label }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Button
        v-if="config.saveUrl"
        size="sm"
        class="bg-white text-zinc-950 hover:bg-zinc-200 font-semibold"
        :disabled="saving || !config.canSave"
        :title="config.canSave ? '' : 'This design is read-only. Duplicate it to make changes.'"
        @click="store.save"
      >
        {{ saveStatus === 'ok' ? '✓ Saved' : saveStatus === 'err' ? '✗ Error' : saving ? 'Saving…' : 'Save' }}
      </Button>
    </div>
  </div>

  <ConfirmDialog
    v-if="exitMounted"
    v-model:open="confirmExit"
    title="Leave the editor?"
    description="Unsaved changes will be lost."
    confirm-label="Leave"
    cancel-label="Stay"
    @confirm="leave"
  />
</template>
