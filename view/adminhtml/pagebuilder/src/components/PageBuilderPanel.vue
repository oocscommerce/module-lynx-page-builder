<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, defineAsyncComponent, type Component } from 'vue'
import { storeToRefs } from 'pinia'
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { TooltipProvider } from '@/ui/tooltip'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'
import TopBar from './TopBar.vue'
import PreviewArea from './PreviewArea.vue'
import PageSection from './PageSection.vue'

// Loaded the first time each is needed
const ComponentPicker = defineAsyncComponent(() => import('./ComponentPicker.vue'))
const SettingsPanel = defineAsyncComponent(() => import('./SettingsPanel.vue'))
const ThemeSettingsPanel = defineAsyncComponent(() => import('./ThemeSettingsPanel.vue'))
const ConfirmDialog = defineAsyncComponent(() => import('./ConfirmDialog.vue'))
const CopyDesignDialog = defineAsyncComponent(() => import('./CopyDesignDialog.vue'))
const ShareDialog = defineAsyncComponent(() => import('./ShareDialog.vue'))

type AnyRecord = Record<string, any>
type Extension = { topbar?: Component; panel?: Component; rail?: { id?: string; label: string; icon: string } }

// What main.ts loaded, one per extension bundle
const props = withDefaults(defineProps<{ extensions?: Extension[] }>(), { extensions: () => [] })

const store = usePageBuilderStore()
const preview = usePreviewStore()
const { config, selectedNode, settingsOpen, pickerTarget, notice, activeTab, cardOpen, theme, themeSaved } = storeToRefs(store)
const { frameAway } = storeToRefs(preview)

const ZONE_LABELS: Record<string, string> = { header: 'Header', content: 'Content', footer: 'Footer', backstage: 'Backstage' }

const outlineScrollRef = ref<any>(null)
// The theme warning is a standing condition, not an event, so it does not time out like the notice strip
const themeNoticeOpen = ref(true)
const panelVisible = ref(true)
// The preview, for what the bar and the outline card ask of it (open in a tab, back to the page being edited)
const previewRef = ref<any>(null)

// The card's width, dragged at its right edge; back to the default on every load.
const PANEL_MIN = 240, PANEL_MAX = 640, PANEL_DEFAULT = 300
const panelWidth = ref(PANEL_DEFAULT)
const resizing = ref(false)
function startResize(e) {
  e.preventDefault()
  resizing.value = true
  const startX = e.clientX, startWidth = panelWidth.value
  const onMove = ev => { panelWidth.value = Math.min(PANEL_MAX, Math.max(PANEL_MIN, startWidth + ev.clientX - startX)) }
  const onUp = () => {
    resizing.value = false
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }
  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}
function pick(tab) {
  if (cardOpen.value && activeTab.value === tab) { cardOpen.value = false; return }
  store.openTab(tab)
}

const fileInputRef = ref<any>(null)
const showImportConfirm = ref(false)
const importMounted = ref(false)
function openImportConfirm() {
  if (frameAway.value) return
  importMounted.value = true
  showImportConfirm.value = true
}
const showCopyDialog = ref(false)
const copyMounted = ref(false)
// The option picked in the copy dialog; the first design until the admin picks another.
const copySource = ref<any>(null)
function openCopyDialog() {
  if (frameAway.value) return
  copySource.value = config.value.designOptions[0] ?? null
  copyMounted.value = true
  showCopyDialog.value = true
}
function copyFromDesign() {
  showCopyDialog.value = false
  store.copyFromDesign(copySource.value)
}

const showShareDialog = ref(false)
const shareMounted = ref(false)
function openShareDialog() {
  shareMounted.value = true
  showShareDialog.value = true
}

watch([selectedNode, settingsOpen], ([node]) => {
  if (!node) return
  setTimeout(() => {
    const el = outlineScrollRef.value?.querySelector(`[data-tree-id="${node.id}"]`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  }, 320)
})

// Ctrl/Cmd+Z, Ctrl/Cmd+Shift+Z, Ctrl+Y
function onKeydown(e) {
  if (!(e.ctrlKey || e.metaKey)) return
  const key = e.key.toLowerCase()
  if (key !== 'z' && key !== 'y') return
  const t = e.target
  if (t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return
  e.preventDefault()
  if (key === 'y' || e.shiftKey) store.redo()
  else store.undo()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

function handleImportFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = ev => store.importLayout(String(ev.target?.result ?? ''))
  reader.readAsText(file)
  e.target.value = ''
}

const topbarItems = computed(() => props.extensions.map(e => e.topbar).filter(Boolean) as Component[])
// An extension with `rail` puts its panel in the left card under its own tab, named by `rail.id` for openTab()
const railExtensions = computed(() => props.extensions
  .map((e, i): AnyRecord => ({ ...e, tab: e.rail?.id || `ext:${i}` }))
  .filter(e => e.rail && e.panel))
const panelItems  = computed(() => props.extensions.filter(e => e.panel && !e.rail).map(e => e.panel!))
const activeExtension = computed(() => railExtensions.value.find(e => e.tab === activeTab.value) ?? null)

const ICONS = {
  outline: '<g transform="scale(0.8333)" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12"/><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17"/></g>',
  theme: '<g transform="scale(0.8333)" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></g>',
}
// Icon is a path (d) or ready markup; either way it goes inside a 20x20 svg.
const iconMarkup = icon => icon.trim().startsWith('<') ? icon : `<path d="${icon}"/>`
const railItems = computed(() => [
  { tab: 'outline', label: 'Outline', icon: ICONS.outline },
  ...(config.value.canEditTheme ? [{ tab: 'theme', label: 'Settings', icon: ICONS.theme }] : []),
  ...railExtensions.value.map(e => ({ tab: e.tab, label: e.rail.label, icon: e.rail.icon })),
])
</script>

<template>
  <TooltipProvider :delay-duration="300">
  <input
    ref="fileInputRef"
    type="file"
    accept=".json,application/json"
    class="hidden"
    @change="handleImportFile"
  />

  <!-- Notice strip: sits directly under the top bar (h-12) and centred -->
  <Alert
    v-if="notice"
    :variant="notice.type === 'error' ? 'destructive' : 'default'"
    class="fixed left-1/2 -translate-x-1/2 z-40 w-auto max-w-[560px] pr-10 shadow-lg"
    :class="config.themeNotice && themeNoticeOpen ? 'top-[104px]' : 'top-14'"
  >
    <CircleAlert v-if="notice.type === 'error'" />
    <CircleCheck v-else />
    <AlertDescription :class="notice.type === 'error' ? 'text-destructive' : 'text-foreground'">{{ notice.text }}</AlertDescription>
    <Button variant="ghost" size="icon-xs" class="absolute right-2 top-2.5" title="Dismiss" @click="notice = null"><X /></Button>
  </Alert>

  <!-- Store view being previewed does not run a Lynx theme -->
  <Alert
    v-if="config.themeNotice && themeNoticeOpen"
    class="fixed top-14 left-1/2 -translate-x-1/2 z-40 w-auto max-w-[560px] pr-10 shadow-lg"
  >
    <TriangleAlert />
    <AlertDescription>{{ config.themeNotice }}</AlertDescription>
    <Button variant="ghost" size="icon-xs" class="absolute right-2 top-2.5" title="Dismiss" @click="themeNoticeOpen = false"><X /></Button>
  </Alert>

  <div v-if="resizing" class="fixed inset-0 z-[45] cursor-col-resize"></div>

  <TopBar
    :panel-visible="panelVisible"
    :extension-items="topbarItems"
    @open-tab="previewRef?.openInTab()"
    @import="openImportConfirm"
    @copy-design="openCopyDialog"
    @share="openShareDialog"
    @toggle-panel="panelVisible = !panelVisible"
  />

  <PreviewArea
    ref="previewRef"
    :panel-visible="panelVisible"
    :card-open="cardOpen"
    :panel-width="panelWidth"
  />

  <!-- Rail: which card the left panel shows -->
  <nav
    class="fixed top-12 left-0 w-16 bottom-0 z-30 flex flex-col items-stretch bg-zinc-950 select-none transition-transform duration-300"
    :class="panelVisible ? 'translate-x-0' : '-translate-x-full'"
  >
    <button
      v-for="item in railItems"
      :key="item.tab"
      type="button"
      class="relative flex flex-col items-center justify-center gap-1 py-3 cursor-pointer transition-colors text-xs font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
      :class="cardOpen && activeTab === item.tab
        ? 'text-white bg-zinc-800/70'
        : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50'"
      :title="item.label"
      @click="pick(item.tab)"
    >
      <span v-if="cardOpen && activeTab === item.tab" class="absolute inset-y-2 left-0 w-0.5 rounded-r bg-white"></span>
      <svg class="size-5 fill-current" viewBox="0 0 20 20" v-html="iconMarkup(item.icon)"></svg>
      {{ item.label }}
    </button>
  </nav>

  <!-- Left panel: on the same surface as the area around the preview frame -->
  <div
    class="fixed top-12 left-16 bottom-0 flex flex-col text-sm leading-snug z-20 select-none bg-muted"
    :style="{ boxSizing: 'border-box', width: panelWidth + 'px', transition: resizing ? 'none' : 'transform .3s' }"
    :class="[
      // Off by its own width plus the rail's, since it starts 4rem in (left-16).
      panelVisible && cardOpen ? 'translate-x-0' : '-translate-x-[calc(100%+4rem)]',
      // The gap belongs between two cards, and there is only one until a node is selected
      'gap-0',
    ]"
  >
    <!-- Resize handle: drag the card's right edge; a full-screen shield keeps the preview iframe from swallowing -->
    <div
      class="absolute top-0 bottom-0 -right-0.5 w-1.5 cursor-col-resize z-10 hover:bg-primary/40"
      :class="resizing ? 'bg-primary/70' : ''"
      @mousedown="startResize"
    ></div>

    <!-- Theme card: the design's settings, in place of the outline while open -->
    <div v-if="activeTab === 'theme'" class="flex flex-col h-full bg-background overflow-hidden border-r">
      <ThemeSettingsPanel
        :fields="store.themeFields"
        :values="theme"
        :saved="themeSaved"
        :can-save="config.canSave"
      />
    </div>

    <!-- Extension card: the panel of the extension whose rail tab is active -->
    <div v-if="activeExtension" class="flex flex-col h-full bg-background overflow-hidden border-r">
      <KeepAlive>
        <component :is="activeExtension.panel" :key="activeExtension.tab" />
      </KeepAlive>
    </div>

    <!-- Outline card -->
    <div
      v-if="activeTab === 'outline'"
      class="flex flex-col bg-background overflow-hidden transition-[height] duration-300 ease-in-out border-r"
      :style="{ height: selectedNode && settingsOpen ? '0px' : '100%' }"
    >
      <!-- Same header as the Theme card (ThemeSettingsPanel.vue) -->
      <div class="flex items-center px-4 pt-3 pb-2 border-b shrink-0 min-h-11">
        <span class="text-sm font-semibold">Outline</span>
      </div>
      <Alert v-if="frameAway" class="mx-3 my-2 w-auto shrink-0">
        <Info />
        <AlertDescription>
          <p>The preview is on another page. This outline belongs to the page being edited, and is read-only until the preview is back on it.</p>
          <Button size="sm" class="mt-2" @click="previewRef?.backToPage()">Back to the page being edited</Button>
        </AlertDescription>
      </Alert>
      <div ref="outlineScrollRef" class="flex-1 overflow-y-auto px-2 py-2 pb-scroll min-h-0" :class="{ 'opacity-50': frameAway }" :inert="frameAway || undefined">
        <PageSection
          v-for="zone in store.outlineZones"
          :key="zone"
          :zone-key="zone"
          :label="ZONE_LABELS[zone]"
        />
      </div>
    </div>

    <!-- Settings card: the full column while open, nothing while closed. -->
    <div
      v-if="activeTab === 'outline'"
      class="overflow-hidden transition-[height] duration-300 ease-in-out"
      :style="{ height: selectedNode && settingsOpen ? '100%' : '0px' }"
    >
      <div class="bg-background border-t border-r overflow-hidden h-full flex flex-col">
        <SettingsPanel
          v-if="selectedNode && settingsOpen"
          :key="selectedNode.id"
          :node="selectedNode"
          @close="settingsOpen = false"
        />
      </div>
    </div>
  </div>

  <!-- Component picker -->
  <ComponentPicker
    v-if="panelVisible && cardOpen && pickerTarget !== null"
    :left="64 + panelWidth"
    :components="config.components"
    :target-zone="store.targetZone"
    :target-label="store.targetLabel"
    :allowed-groups="store.allowedGroups"
    :full-components="store.fullComponents"
    @select="store.addBlock"
    @close="store.closePicker"
  />

  <!-- Extension panels: whatever the loaded extensions place beside the preview -->
  <component
    v-for="(panel, i) in panelItems"
    :key="i"
    :is="panel"
  />

  <ConfirmDialog
    v-if="importMounted"
    v-model:open="showImportConfirm"
    title="Import layout"
    description="This will replace the entire current layout. All existing content will be lost. Are you sure?"
    confirm-label="Yes, import"
    @confirm="fileInputRef?.click()"
  />
  <CopyDesignDialog
    v-if="copyMounted"
    v-model:open="showCopyDialog"
    v-model:source="copySource"
    :design-options="config.designOptions"
    @copy="copyFromDesign"
  />
  <!-- Dialogs load on first use, then stay mounted for their closing animation -->
  <ShareDialog v-if="shareMounted" v-model:open="showShareDialog" :share="config.share" @notify="store.notify" />
  </TooltipProvider>
</template>
