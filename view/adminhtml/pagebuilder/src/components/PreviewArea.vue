<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { X } from '@lucide/vue'
import { Badge } from '@/ui/badge'
import { Button } from '@/ui/button'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'

const ConfirmDialog = defineAsyncComponent(() => import('./ConfirmDialog.vue'))

type FrameOption = { text: string; url: string; live: boolean }

const props = withDefaults(defineProps<{
  panelVisible?: boolean
  // Whether the panel's card is unfolded beside the rail; folded leaves the rail only.
  cardOpen?: boolean
  // Width of the panel's card; the rail beside it is 64px.
  panelWidth?: number
}>(), { panelVisible: true, cardOpen: true, panelWidth: 300 })

const store = usePageBuilderStore()
const preview = usePreviewStore()
const { config } = storeToRefs(store)
const { device } = storeToRefs(preview)

// The route being previewed comes from the page switcher alone
const previewSrc = computed(() => {
  const { previewUrl, previewToken, previewRoute, previewStoreCode } = config.value
  if (!previewUrl) return ''
  const params = new URLSearchParams()
  if (previewToken) params.set('token', previewToken)
  if (previewStoreCode) params.set('___store', previewStoreCode)
  const query = params.toString()
  // A route the frame was on can carry a query of its own (a search).
  const separator = previewRoute.includes('?') ? '&' : '?'
  return previewUrl + previewRoute + (query ? separator + query : '')
})

const iframeRef    = ref<any>(null)
const containerRef = ref<any>(null)
const scale        = ref(1)
const cw           = ref(0)
const ch           = ref(0)
const ready        = ref(false)

// Everything the bridge in the frame is told goes by postMessage, to the preview's own origin
const previewOrigin = computed(() => {
  try { return new URL(previewSrc.value, window.location.href).origin } catch { return '' }
})

function send(message) {
  const win = iframeRef.value?.contentWindow
  if (!win || !previewOrigin.value) return
  win.postMessage(message, previewOrigin.value)
}

/**
 * The draft as the preview gets it: every node whose component declares slots also carries their labels
 */
function withSlotLabels(layout) {
  const walk = nodes => nodes.map((node) => {
    const declared = Object.entries(store.componentOf({ name: node.name, key: node.component })?.slots ?? {})
    return {
      ...node,
      ...(node.slots ? { slots: Object.fromEntries(Object.entries(node.slots).map(([name, children]) => [name, walk(children)])) } : {}),
      ...(declared.length ? { slot_labels: Object.fromEntries(declared.map(([name, def]: [string, any]) => [name, def.label ?? name])) } : {}),
    }
  })
  return Object.fromEntries(Object.entries(layout).map(([zone, nodes]) => [zone, walk(nodes ?? [])]))
}

function pushLayout() {
  send({
    type: 'lynx:draft',
    layout: JSON.stringify(withSlotLabels(store.serializedLayout)),
    theme: JSON.stringify(store.theme),
  })
}

/** Back onto the page being edited, where the draft is shown: the frame's own route again. */
function backToPage() {
  if (iframeRef.value && previewSrc.value) iframeRef.value.src = previewSrc.value
}

/**
 * The preview in a tab of its own: the preview route with `__lynx_open`
 */
function openInTab() {
  if (!previewSrc.value) return
  const url = new URL(previewSrc.value, window.location.href)
  url.searchParams.set('__lynx_open', '1')
  window.open(url.href, '_blank', 'noopener')
}

defineExpose({ backToPage, openInTab })
// Its own message, so flipping it costs no render: the bridge redraws the outlines alone.
function pushOutlines() {
  send({ type: 'lynx:outlines', on: preview.outlines, zones: store.framedZones.join(',') })
}

function pushHighlight() {
  preview.highlight(store.selectedNode?.id ?? null)
}

// Only from this frame, and only from the origin it was pointed at
function onMessage(e) {
  if (e.source !== iframeRef.value?.contentWindow) return
  if (e.origin !== previewOrigin.value) return
  if (e.data?.type === 'lynx:ready') {
    preview.frameAway = e.data.editing !== true
    frameOffer.value = e.data.editing === true ? null : offerFor(e.data.target, e.data.href)
    pushOutlines()
    pushHighlight()
    pushLayout()
    return
  }
  if (e.data?.type === 'lynx:select' && e.data.id) store.selectFromPreview(String(e.data.id))
  // A click on an empty slot's placeholder: open the picker for that slot.
  if (e.data?.type === 'lynx:add' && e.data.id && e.data.slot) store.addFromPreview({ id: String(e.data.id), slot: String(e.data.slot) })
  // A + beside the selected node: open the picker for a component before or after it.
  if (e.data?.type === 'lynx:insert' && e.data.id && ['before', 'after'].includes(e.data.position)) {
    store.insertFromPreview({ id: String(e.data.id), position: e.data.position })
  }
}
onMounted(() => {
  window.addEventListener('message', onMessage)
  preview.connect(send)
})
onUnmounted(() => {
  window.removeEventListener('message', onMessage)
  preview.connect(null)
})

// ── Edit this page ──────────────────────────────────────────────────────────────
const frameOffer = ref<{ label: string; options: FrameOption[] } | null>(null)

// The frame's URL as a route under the preview's base, without what the preview adds (the token, ___store)
function routeOf(href) {
  const base = config.value.previewUrl.replace(/lynx_pagebuilder\/$/, '')
  if (!href || !base || !href.startsWith(base)) return ''
  const url = new URL(href)
  url.searchParams.delete('token')
  url.searchParams.delete('___store')
  const route = url.href.slice(base.length).split('#')[0]
  return route.startsWith('lynx_pagebuilder/') ? '' : route
}

function editorUrl(params: Record<string, string>) {
  const url = new URL(config.value.frameEditorUrl, window.location.href)
  for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value)
  return url.href
}

// A layout of its own (CMS page, category), unless the editor is on it already
function ownOption(target) {
  if (!target.params || typeof target.params !== 'object') return null
  // Only plain names and scalar values: they become query params of an admin URL.
  const params: Record<string, string> = {}
  for (const [key, value] of Object.entries(target.params)) {
    if (/^[a-z_]+$/.test(key) && ['string', 'number'].includes(typeof value)) params[key] = String(value)
  }
  const current = config.value.currentTarget.params ?? {}
  if (!Object.keys(params).length || Object.keys(params).every(key => String(current[key] ?? '') === params[key])) return null
  return { label: String(target.label ?? ''), url: editorUrl(params) }
}

// The design page the frame's path reads; the one being edited too, reopened on this route so it gets its outlines
function pageOption(target, href) {
  if (typeof target.path !== 'string') return null
  const page = config.value.framePages.find(candidate => candidate.path === target.path)
  const route = routeOf(href)
  if (!page || (page.code === config.value.currentTarget.page && !route)) return null
  const params: Record<string, string> = { page: page.code }
  if (route) params.preview_route = route
  return { label: page.label, url: editorUrl(params) }
}

// A page with a layout of its own over a shared design page offers both; the one the storefront shows is marked live
function offerFor(target, href) {
  if (!target || typeof target !== 'object' || !config.value.frameEditorUrl) return null

  const own = ownOption(target)
  const page = pageOption(target, href)
  const both = Boolean(target.params && target.path)
  const options = [
    page && { text: both ? `Edit ${page.label}` : 'Edit this page', url: page.url, live: both && !target.uses_own },
    own && { text: both ? `Edit ${own.label} only` : 'Edit this page', url: own.url, live: both && target.uses_own === true },
  ].filter(Boolean) as FrameOption[]

  return options.length ? { label: own?.label || page?.label || '', options } : null
}

/** Always asks, as Exit does: the editor reloads on the new page and unsaved work is gone. */
// The option stays while the dialog closes: the confirm button closes it before it confirms
const confirmEdit = ref<FrameOption | null>(null)
const confirmOpen = ref(false)
function askEdit(option: FrameOption) {
  confirmEdit.value = option
  confirmOpen.value = true
}
function editFramePage() {
  if (confirmEdit.value) window.location.assign(confirmEdit.value.url)
}

watch(() => [preview.outlines, store.framedZones], pushOutlines, { deep: true })
watch(() => store.serializedLayout, pushLayout, { deep: true })
watch(() => store.theme, pushLayout, { deep: true })
watch(() => store.selectedNode?.id, pushHighlight)

const isDesktop   = computed(() => device.value === 'desktop')
const deviceWidth = computed(() => {
  if (device.value === 'tablet') return 768
  if (device.value === 'mobile') return 390
  return null
})

function recalcScale() {
  if (!containerRef.value) return
  cw.value = containerRef.value.clientWidth
  ch.value = containerRef.value.clientHeight
  if (!ready.value) ready.value = true
  if (isDesktop.value || !deviceWidth.value) { scale.value = 1; return }
  const available = cw.value - 16
  scale.value = available < deviceWidth.value ? available / deviceWidth.value : 1
}

watch(() => [device.value, props.panelVisible, props.cardOpen, props.panelWidth], () => {
  setTimeout(recalcScale, 320)
}, { immediate: true })

if (typeof ResizeObserver !== 'undefined') {
  const ro = new ResizeObserver(recalcScale)
  watch(containerRef, (el) => { if (el) ro.observe(el) }, { immediate: true })
}

const EASE = 'cubic-bezier(0.4,0,0.2,1)'
const T    = `width .45s ${EASE}, height .45s ${EASE}, border-radius .45s ${EASE}, border-width .35s ${EASE}, border-color .35s, box-shadow .45s ${EASE}, transform .45s ${EASE}`

const frameStyle = computed((): Record<string, string> => {
  if (!ready.value) return { visibility: 'hidden' }
  if (isDesktop.value) {
    return {
      // 12px shy of the padded box, which centring turns into 6px of air on every side
      width:        `${Math.max(0, cw.value - 12)}px`,
      height:       `${Math.max(0, ch.value - 12)}px`,
      borderRadius: '8px',
      // The frame reads by contrast, not by its line: the canvas around it is one step darker than the panel (zinc-200
      border:       'none',
      boxShadow:    '0 0 0 1px rgba(0,0,0,.05), 0 0 14px rgba(0,0,0,.12), 0 8px 24px rgba(0,0,0,.08)',
      transform:    'scale(1)',
      transformOrigin: 'center center',
      transition:   T,
    }
  }
  return {
    width:        `${deviceWidth.value}px`,
    // Tablet is sized like the desktop frame so switching device changes the width and nothing else
    height:       device.value === 'mobile' ? '812px' : `${Math.max(0, ch.value - 12)}px`,
    maxHeight:    'calc(100% - 16px)',
    borderRadius: device.value === 'mobile' ? '44px' : '16px',
    transform:    `scale(${scale.value})`,
    transformOrigin: 'center center',
    border:       device.value === 'mobile' ? '10px solid #09090b' : '8px solid #111827',
    boxShadow:    '0 0 0 2px #374151, 0 32px 64px rgba(0,0,0,0.4)',
    transition:   T,
  }
})
</script>

<template>
  <div
    ref="containerRef"
    class="fixed top-12 bottom-0 right-0 flex items-center justify-center overflow-hidden p-2 transition-[left] duration-300"
    :style="{ left: panelVisible ? (cardOpen ? (64 + panelWidth) + 'px' : '64px') : '0', backgroundColor: '#e4e4e7' }"
  >
    <div class="relative flex flex-col bg-white overflow-hidden" :style="frameStyle">

      <!-- Device: top notch -->
      <Transition name="pb-fade">
        <div
          v-if="!isDesktop"
          class="shrink-0 flex items-center justify-center bg-zinc-950"
          :style="{ height: device === 'mobile' ? '30px' : '18px' }"
        >
          <div v-if="device === 'mobile'" class="w-24 h-5 bg-black rounded-full"/>
          <div v-else class="w-8 h-1.5 bg-zinc-700 rounded-full"/>
        </div>
      </Transition>

      <!-- The frame followed a link to another page: offer to edit that one -->
      <div
        v-if="frameOffer"
        class="shrink-0 flex items-center gap-2 pl-4 pr-1.5 py-1.5 bg-zinc-950 text-white text-sm"
      >
        <span class="flex-1 min-w-0 truncate">Viewing <span class="font-semibold">{{ frameOffer.label || 'another page' }}</span></span>
        <Button
          v-for="option in frameOffer.options"
          :key="option.url"
          size="sm"
          class="h-7 bg-white text-zinc-950 hover:bg-zinc-200"
          :title="option.live ? 'Shown on the storefront' : undefined"
          @click="askEdit(option)"
        >{{ option.text }}<Badge v-if="option.live" variant="secondary" class="ml-0.5">live</Badge></Button>
        <Button variant="ghost" size="icon-sm" class="text-zinc-400 hover:text-white hover:bg-zinc-800" aria-label="Dismiss" @click="frameOffer = null"><X /></Button>
      </div>

      <ConfirmDialog
        v-if="confirmEdit"
        v-model:open="confirmOpen"
        title="Edit this page?"
        description="Unsaved changes will be lost."
        :confirm-label="confirmEdit.text"
        @confirm="editFramePage"
      />

      <!-- Single iframe — never destroyed on device switch -->
      <div class="relative flex-1 min-h-0">
        <iframe
          v-if="previewSrc"
          ref="iframeRef"
          :src="previewSrc"
          class="border-none w-full h-full block"
        />
        <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground text-sm">
          No preview URL configured
        </div>
      </div>

      <!-- Device: bottom bar -->
      <Transition name="pb-fade">
        <div
          v-if="!isDesktop"
          class="shrink-0 bg-zinc-950 flex items-center justify-center"
          :style="{ height: device === 'mobile' ? '30px' : '14px' }"
        >
          <div v-if="device === 'mobile'" class="w-28 h-1 bg-zinc-500 rounded-full"/>
        </div>
      </Transition>

    </div>
  </div>
</template>
